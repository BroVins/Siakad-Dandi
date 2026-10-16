<?php

namespace App\Services;

use App\Models\{Kelas, Krs, Kurikulum, Mahasiswa, Roadmap, SemesterAkademik, User};
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlannerService
{
    public function __construct(private AcademicService $academic, private PlannerEngine $engine) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['roadmap' => $message]);
    }

    /** Fingerprint academic inputs, not mutable UI labels or the KRS closing date. */
    public function fingerprint(Mahasiswa $student, SemesterAkademik $term): string
    {
        $student->load('prodi');
        $courses = Kurikulum::where('prodi_id', $student->prodi_id)->with('mataKuliah.prasyarat')->orderBy('mata_kuliah_id')->get()
            ->map(fn ($k) => [
                (int) $k->mata_kuliah_id, (int) $k->semester, (bool) $k->status_wajib,
                (int) $k->mataKuliah->sks, $k->mataKuliah->prasyarat->pluck('id')->sort()->values()->all(),
            ])->all();
        $passed = $this->academic->passedIds($student);
        $prior = $this->academic->passedIds($student, $term);
        sort($passed); sort($prior);

        return hash('sha256', json_encode([
            'student' => [(int) $student->id, (int) $student->prodi_id, (int) $student->semester],
            'program' => [(int) $student->prodi->sks_lulus, (int) $student->prodi->batas_semester],
            'term' => [$term->id, $term->tahun_akademik, $term->periode, $term->krs_mulai->toDateString()],
            'curriculum' => $courses, 'passed' => $passed, 'prior_passed' => $prior,
            'limit' => $this->academic->limit($student, $term),
            'future_limit' => config('academic.future_semester_limit'),
            'passing_points' => config('academic.passing_points'),
        ], JSON_THROW_ON_ERROR));
    }

    public function isFresh(Roadmap $roadmap, Mahasiswa $student, SemesterAkademik $term): bool
    {
        return $roadmap->semester_akademik_id === $term->id
            && (int) $roadmap->semester_mulai === (int) $student->semester
            && is_string($roadmap->context_hash)
            && hash_equals($roadmap->context_hash, $this->fingerprint($student, $term));
    }

    /** Reservations include submitted and final KRS; the student's own place remains usable. */
    public function classOptions(Mahasiswa $student, SemesterAkademik $term, ?Krs $registered = null): array
    {
        $fixed = $registered?->details()->pluck('kelas_id', 'mata_kuliah_id')->all() ?? [];
        $classes = Kelas::where('semester_akademik_id', $term->id)
            ->withCount(['enrollments as reserved_count' => fn ($query) => $query->whereHas('krs', fn ($q) => $q->whereIn('status', ['submitted', 'approved'])->where('mahasiswa_id', '!=', $student->id))])
            ->orderBy('id')->get();
        $options = [];
        foreach ($classes as $class) {
            if ((int) $class->reserved_count >= $class->kapasitas) continue;
            if (isset($fixed[$class->mata_kuliah_id]) && $fixed[$class->mata_kuliah_id] !== $class->id) continue;
            $options[$class->mata_kuliah_id][] = $class->only('id', 'hari', 'mulai', 'selesai');
        }
        return $options;
    }

    private function validateCurrentOfferings(Roadmap $roadmap, Mahasiswa $student, SemesterAkademik $term): void
    {
        $registered = Krs::where('mahasiswa_id', $student->id)->where('semester_akademik_id', $term->id)->whereIn('status', ['submitted', 'approved'])->first();
        $courses = $roadmap->details()->where('semester_target', $student->semester)->pluck('mata_kuliah_id')->all();
        if (Timetable::choose($courses, $this->classOptions($student, $term, $registered)) === null) {
            $this->fail('Ketersediaan atau jadwal kelas berubah. Buat roadmap baru sebelum pengajuan atau persetujuan.');
        }
    }

    public function generate(Mahasiswa $student, int $target): Roadmap
    {
        return DB::transaction(function () use ($student, $target) {
            $m = Mahasiswa::lockForUpdate()->findOrFail($student->id);
            $term = $this->academic->activeTerm();
            if ($m->status !== 'aktif') $this->fail('Hanya mahasiswa aktif yang dapat membuat roadmap.');
            if (Roadmap::where('mahasiswa_id', $m->id)->where('semester_akademik_id', $term->id)->where('status', 'submitted')->exists()) {
                $this->fail('Roadmap semester aktif masih menunggu review. Tarik pengajuan sebelum mengubah rencana.');
            }
            if ($target < $m->semester || $target > $m->prodi->batas_semester) {
                throw ValidationException::withMessages(['target_lulus' => 'Target harus antara semester aktif dan batas masa studi program studi.']);
            }
            $courses = Kurikulum::where('prodi_id', $m->prodi_id)->with('mataKuliah.prasyarat')->get()->map(fn ($k) => [
                'id' => $k->mata_kuliah_id, 'kode' => $k->mataKuliah->kode, 'sks' => $k->mataKuliah->sks,
                'semester' => $k->semester, 'required' => (bool) $k->status_wajib,
                'prerequisites' => $k->mataKuliah->prasyarat->pluck('id')->all(),
            ])->all();
            $registered = Krs::where('mahasiswa_id', $m->id)->where('semester_akademik_id', $term->id)->whereIn('status', ['submitted', 'approved'])->first();
            $fixedCurrent = $registered?->details()->pluck('mata_kuliah_id')->all();
            $classOptions = $this->classOptions($m, $term, $registered);
            $result = $this->engine->plan(
                $courses, $this->academic->passedIds($m), $this->academic->summary($m)['sks'],
                $m->prodi->sks_lulus, $m->semester, $target, $this->academic->limit($m, $term),
                config('academic.future_semester_limit'), $term->periode,
                array_keys($classOptions), $fixedCurrent, $classOptions, $this->academic->passedIds($m, $term),
            );
            $roadmap = Roadmap::create([
                'mahasiswa_id' => $m->id, 'semester_akademik_id' => $term->id,
                'semester_mulai' => $m->semester, 'context_hash' => $this->fingerprint($m, $term),
                'target_lulus' => $target, 'status' => 'draft', 'warnings' => $result['warnings'],
            ]);
            $roadmap->details()->createMany($result['details']);
            Audit::record('roadmap.generated', $roadmap);
            return $roadmap;
        }, 3);
    }

    public function submit(User $user, Roadmap $record): void
    {
        DB::transaction(function () use ($user, $record) {
            $m = Mahasiswa::lockForUpdate()->findOrFail($record->mahasiswa_id);
            abort_unless($user->role === 'mahasiswa' && $m->user_id === $user->id, 403);
            $r = Roadmap::lockForUpdate()->findOrFail($record->id);
            $term = $this->academic->activeTerm();
            if ($m->status !== 'aktif') $this->fail('Hanya mahasiswa aktif yang dapat mengajukan roadmap.');
            $latest = Roadmap::where('mahasiswa_id', $m->id)->where('semester_akademik_id', $term->id)->max('id');
            if ((int) $latest !== $r->id || !in_array($r->status, ['draft', 'rejected'], true) || !$r->details()->exists()) {
                $this->fail('Hanya roadmap terbaru yang berisi mata kuliah dan belum diajukan yang dapat dikirim.');
            }
            if (!$m->dosen_pa_id) $this->fail('Dosen PA belum ditetapkan. Hubungi admin akademik.');
            if (!$this->isFresh($r, $m, $term)) $this->fail('Data akademik atau semester telah berubah. Buat roadmap baru sebelum mengajukan.');
            $this->validateCurrentOfferings($r, $m, $term);
            $r->update(['status' => 'submitted']);
            Audit::record('roadmap.submitted', $r);
        }, 3);
    }

    public function review(User $user, Roadmap $record, string $status, ?string $note): void
    {
        DB::transaction(function () use ($user, $record, $status, $note) {
            $m = Mahasiswa::lockForUpdate()->findOrFail($record->mahasiswa_id);
            Access::advise($user, $m);
            $r = Roadmap::lockForUpdate()->findOrFail($record->id);
            if (!in_array($status, ['approved', 'rejected'], true)) $this->fail('Keputusan review tidak valid.');
            if ($status === 'rejected' && !trim($note ?? '')) $this->fail('Catatan wajib untuk mengembalikan roadmap.');
            if ($r->status !== 'submitted') $this->fail('Roadmap tidak sedang menunggu persetujuan.');
            if ($status === 'approved') {
                $term = $this->academic->activeTerm();
                if ($m->status !== 'aktif' || !$this->isFresh($r, $m, $term)) {
                    $this->fail('Roadmap sudah tidak sesuai data akademik saat ini. Kembalikan untuk revisi agar mahasiswa membuat rencana baru.');
                }
            }
            $r->update(['status' => $status]);
            $r->approvals()->create(['dosen_pa_id' => $user->dosen->id, 'status' => $status, 'catatan' => $note]);
            Audit::record('roadmap.'.$status, $r);
        }, 3);
    }

    public function withdraw(User $user, Roadmap $record): void
    {
        DB::transaction(function () use ($user, $record) {
            $m = Mahasiswa::lockForUpdate()->findOrFail($record->mahasiswa_id);
            abort_unless($user->role === 'mahasiswa' && $m->user_id === $user->id, 403);
            $r = Roadmap::lockForUpdate()->findOrFail($record->id);
            if ($r->status !== 'submitted') $this->fail('Hanya pengajuan yang masih menunggu review yang dapat ditarik.');
            $r->update(['status' => 'draft']);
            Audit::record('roadmap.withdrawn', $r);
        }, 3);
    }
}
