<?php

namespace App\Services;

use App\Models\{Mahasiswa, Kelas, Kurikulum, Krs, KrsDetail, Roadmap, SemesterAkademik, User};
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KrsService
{
    public function __construct(private AcademicService $academic, private PlannerService $planner) {}

    private function fail(string $message): never { throw ValidationException::withMessages(['krs' => $message]); }

    public function validateSelection(Mahasiswa $m, SemesterAkademik $term, array $ids, ?int $exclude = null, bool $requireOpen = true): Collection
    {
        if (!$term->aktif || ($requireOpen && !$term->isOpen())) $this->fail('Periode pengisian KRS belum dibuka atau sudah ditutup.');
        if ($m->status !== 'aktif') $this->fail('Mahasiswa tidak berstatus aktif.');
        if (!$ids || count($ids) !== count(array_unique($ids))) $this->fail('Pilih kelas tanpa duplikasi.');
        $classes = Kelas::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->with('mataKuliah.prasyarat')->get();
        if ($classes->count() !== count($ids)) $this->fail('Kelas tidak ditemukan.');
        if ($classes->pluck('mata_kuliah_id')->unique()->count() !== count($ids)) $this->fail('Satu mata kuliah hanya boleh dipilih satu kali.');
        $passed = $this->academic->passedIds($m);
        $priorPassed = $this->academic->passedIds($m, $term);
        $curriculum = Kurikulum::where('prodi_id', $m->prodi_id)->pluck('mata_kuliah_id')->all();
        if ($classes->sum(fn ($c) => $c->mataKuliah->sks) > $this->academic->limit($m, $term)) $this->fail('Jumlah SKS melebihi batas berdasarkan IPS semester sebelumnya.');
        foreach ($classes as $c) {
            if ($c->semester_akademik_id !== $term->id || !in_array($c->mata_kuliah_id, $curriculum, true)) $this->fail('Kelas tidak sesuai semester aktif atau kurikulum.');
            if (in_array($c->mata_kuliah_id, $passed, true)) $this->fail($c->mataKuliah->nama.' sudah lulus.');
            if (array_diff($c->mataKuliah->prasyarat->pluck('id')->all(), $priorPassed)) $this->fail('Prasyarat '.$c->mataKuliah->nama.' harus lulus pada semester sebelumnya.');
            $occupied = KrsDetail::where('kelas_id', $c->id)->whereHas('krs', fn ($q) => $q->whereIn('status', ['submitted', 'approved'])->when($exclude, fn ($q) => $q->where('id', '!=', $exclude)))->count();
            if ($occupied >= $c->kapasitas) $this->fail('Kelas '.$c->mataKuliah->nama.' sudah penuh.');
        }
        foreach ($classes as $i => $a) {
            foreach ($classes as $j => $b) {
                if ($j > $i && $a->hari === $b->hari && $a->mulai < $b->selesai && $a->selesai > $b->mulai) {
                    $this->fail('Jadwal '.$a->mataKuliah->nama.' bentrok dengan '.$b->mataKuliah->nama.'.');
                }
            }
        }
        return $classes;
    }

    private function validateRoadmap(?Roadmap $roadmap, Mahasiswa $m, SemesterAkademik $term, Collection $classes): void
    {
        if (!$roadmap || $roadmap->status !== 'approved') $this->fail('Roadmap terbaru harus disetujui Dosen PA sebelum pengajuan KRS.');
        if (!$this->planner->isFresh($roadmap, $m, $term)) $this->fail('Roadmap tidak lagi sesuai data akademik. Buat dan ajukan rencana baru.');
        $recommended = $roadmap->details()->where('semester_target', $m->semester)->pluck('mata_kuliah_id')->all();
        if (array_diff($classes->pluck('mata_kuliah_id')->all(), $recommended)) $this->fail('Pilihan KRS harus sesuai roadmap yang disetujui. Buat roadmap baru bila rencana berubah.');
    }

    public function save(Mahasiswa $student, array $ids, bool $submit): Krs
    {
        return DB::transaction(function () use ($student, $ids, $submit) {
            $m = Mahasiswa::lockForUpdate()->findOrFail($student->id);
            $term = $this->academic->activeTerm();
            $krs = Krs::where('mahasiswa_id', $m->id)->where('semester_akademik_id', $term->id)->lockForUpdate()->first();
            if ($krs && !in_array($krs->status, ['draft', 'rejected'], true)) $this->fail('KRS sudah diajukan atau disetujui. Tarik pengajuan yang masih menunggu review sebelum mengedit.');
            $classes = $this->validateSelection($m, $term, $ids, $krs?->id);
            $roadmap = null;
            if ($submit) {
                if (!$m->dosen_pa_id) $this->fail('Dosen PA belum ditetapkan. Hubungi admin akademik.');
                $roadmap = Roadmap::where('mahasiswa_id', $m->id)->where('semester_akademik_id', $term->id)->latest('id')->first();
                $this->validateRoadmap($roadmap, $m, $term, $classes);
            }
            $krs ??= new Krs(['mahasiswa_id' => $m->id, 'semester_akademik_id' => $term->id]);
            $krs->fill([
                'semester' => $m->semester, 'tahun_akademik' => $term->tahun_akademik,
                'roadmap_id' => $roadmap?->id, 'status' => $submit ? 'submitted' : 'draft',
                'catatan' => null, 'approved_by' => null, 'approved_at' => null,
            ])->save();
            $krs->details()->delete();
            $krs->details()->createMany($classes->map(fn ($c) => ['kelas_id' => $c->id, 'mata_kuliah_id' => $c->mata_kuliah_id])->all());
            Audit::record($submit ? 'krs.submitted' : 'krs.saved', $krs, ['roadmap_id' => $roadmap?->id]);
            return $krs;
        }, 3);
    }

    public function review(User $user, Krs $record, string $status, ?string $note): void
    {
        DB::transaction(function () use ($user, $record, $status, $note) {
            $m = Mahasiswa::lockForUpdate()->findOrFail($record->mahasiswa_id);
            Access::advise($user, $m);
            $krs = Krs::lockForUpdate()->findOrFail($record->id);
            if (!in_array($status, ['approved', 'rejected'], true)) $this->fail('Keputusan review tidak valid.');
            if ($status === 'rejected' && !trim($note ?? '')) $this->fail('Catatan wajib untuk mengembalikan KRS.');
            if ($krs->status !== 'submitted') $this->fail('KRS tidak sedang menunggu persetujuan.');
            if ($status === 'approved') {
                if ((int) $krs->semester !== (int) $m->semester) $this->fail('Semester mahasiswa telah berubah. Kembalikan KRS untuk revisi.');
                $classes = $this->validateSelection($m, $krs->term, $krs->details()->pluck('kelas_id')->all(), $krs->id, false);
                $this->validateRoadmap($krs->roadmap, $m, $krs->term, $classes);
            }
            $krs->update(['status' => $status, 'catatan' => $note, 'approved_by' => $user->id, 'approved_at' => now()]);
            Audit::record('krs.'.$status, $krs);
        }, 3);
    }

    public function withdraw(User $user, Krs $record): void
    {
        DB::transaction(function () use ($user, $record) {
            $m = Mahasiswa::lockForUpdate()->findOrFail($record->mahasiswa_id);
            abort_unless($user->role === 'mahasiswa' && $m->user_id === $user->id, 403);
            $krs = Krs::lockForUpdate()->findOrFail($record->id);
            if ($krs->status !== 'submitted') $this->fail('Hanya KRS yang masih menunggu persetujuan yang dapat ditarik. KRS final tidak dapat diubah.');
            $krs->update(['status' => 'draft', 'roadmap_id' => null, 'catatan' => null, 'approved_by' => null, 'approved_at' => null]);
            Audit::record('krs.withdrawn', $krs);
        }, 3);
    }
}
