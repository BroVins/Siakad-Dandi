<?php

namespace App\Http\Controllers;

use App\Models\{Kelas, Nilai, Presensi};
use App\Services\{Access, AcademicService};
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeachingController extends Controller
{
    public function index(Request $request)
    {
        return view('teaching.index', ['classes' => Kelas::where('dosen_id', $request->user()->dosen?->id ?? 0)
            ->with('mataKuliah', 'term')->orderByDesc('semester_akademik_id')->get()]);
    }

    public function show(Request $request, Kelas $kelas)
    {
        Access::teach($request->user(), $kelas);
        $request->validate(['pertemuan' => 'sometimes|integer|min:1|max:24']);
        $meeting = $request->integer('pertemuan', 1);
        $selectedAttendance = Presensi::where('kelas_id', $kelas->id)->where('pertemuan', $meeting)->get()->keyBy('mahasiswa_id');
        return view('teaching.show', [
            'kelas' => $kelas->load('mataKuliah', 'term'),
            'students' => $kelas->peserta()->with('krs.mahasiswa')->get()->map(fn ($d) => $d->krs->mahasiswa)->unique('id')->sortBy('nim'),
            'grades' => Nilai::where('kelas_id', $kelas->id)->get()->keyBy('mahasiswa_id'),
            'attendance' => Presensi::where('kelas_id', $kelas->id)->orderByDesc('pertemuan')->with('mahasiswa')->get(),
            'meeting' => $meeting, 'selectedAttendance' => $selectedAttendance,
            'meetingDate' => $selectedAttendance->first()?->tanggal ?? today()->toDateString(),
        ]);
    }

    /** Validate keys as well as values. Casting an arbitrary key to int is unsafe. */
    private function authorizeRoster(Kelas $kelas, array $input): array
    {
        $ids = $kelas->peserta()->with('krs')->get()->pluck('krs.mahasiswa_id')->all();
        foreach (array_keys($input) as $id) {
            abort_unless(preg_match('/^[1-9][0-9]*$/D', (string) $id) && in_array((int) $id, $ids, true), 403,
                'Mahasiswa tidak terdaftar dalam KRS final kelas ini.');
        }
        return $ids;
    }

    public function grades(Request $request, Kelas $kelas, AcademicService $academic)
    {
        Access::teach($request->user(), $kelas);
        $data = $request->validate([
            'nilai' => 'required|array|min:1', 'nilai.*' => 'nullable|numeric|decimal:0,2|between:0,100',
            'publication' => 'nullable|in:keep,draft,publish', 'published' => 'nullable|boolean',
        ]);
        $mode = $data['publication'] ?? ($request->exists('published') ? ($request->boolean('published') ? 'publish' : 'draft') : 'keep');
        if (!count(array_filter($data['nilai'], fn ($score) => $score !== null))) {
            throw ValidationException::withMessages(['nilai' => 'Isi minimal satu nilai. Kolom kosong tidak menghapus nilai sebelumnya.']);
        }
        DB::transaction(function () use ($data, $kelas, $academic, $request, $mode) {
            $locked = Kelas::lockForUpdate()->findOrFail($kelas->id);
            Access::teach($request->user(), $locked);
            $this->authorizeRoster($locked, $data['nilai']);
            foreach ($data['nilai'] as $id => $score) {
                if ($score === null) continue;
                $row = Nilai::firstOrNew(['mahasiswa_id' => $id, 'mata_kuliah_id' => $locked->mata_kuliah_id, 'semester_akademik_id' => $locked->semester_akademik_id]);
                if ($row->exists && $row->kelas_id !== $locked->id) {
                    throw ValidationException::withMessages(['nilai' => 'Nilai mata kuliah ini berasal dari kelas lain. Hubungi admin untuk memeriksa riwayat.']);
                }
                $before = $row->exists ? $row->only('nilai', 'grade', 'published') : null;
                $published = match ($mode) { 'publish' => true, 'draft' => false, default => $row->exists && $row->published };
                $row->fill(['kelas_id' => $locked->id, 'nilai' => $score, 'published' => $published, ...$academic->grade((float) $score)])->save();
                Audit::record('grade.saved', $row, ['before' => $before, 'after' => $row->only('nilai', 'grade', 'published')]);
            }
        }, 3);
        return back()->with('success', match ($mode) {
            'publish' => 'Nilai disimpan dan dipublikasikan ke KHS.',
            'draft' => 'Nilai disimpan sebagai draft dan tidak ditampilkan di KHS.',
            default => 'Perubahan nilai disimpan. Status publikasi sebelumnya dipertahankan; nilai baru tetap draft.',
        });
    }

    public function attendance(Request $request, Kelas $kelas)
    {
        Access::teach($request->user(), $kelas);
        $data = $request->validate([
            'pertemuan' => 'required|integer|min:1|max:24', 'tanggal' => 'required|date_format:Y-m-d|before_or_equal:today',
            'status' => 'required|array|min:1', 'status.*' => 'required|in:hadir,izin,sakit,alpa',
        ]);
        DB::transaction(function () use ($data, $kelas, $request) {
            $locked = Kelas::lockForUpdate()->findOrFail($kelas->id);
            Access::teach($request->user(), $locked);
            $ids = $this->authorizeRoster($locked, $data['status']);
            $differentDate = Presensi::where('kelas_id', $locked->id)->where('pertemuan', $data['pertemuan'])->where('tanggal', '!=', $data['tanggal'])->exists();
            if ($differentDate && array_diff($ids, array_map('intval', array_keys($data['status'])))) {
                throw ValidationException::withMessages(['status' => 'Untuk mengubah tanggal pertemuan, isi status seluruh peserta agar tanggal tetap konsisten.']);
            }
            foreach ($data['status'] as $id => $status) {
                Presensi::updateOrCreate(['kelas_id' => $locked->id, 'mahasiswa_id' => $id, 'pertemuan' => $data['pertemuan']], ['tanggal' => $data['tanggal'], 'status' => $status]);
            }
            Audit::record('attendance.saved', $locked, ['pertemuan' => (int) $data['pertemuan'], 'tanggal' => $data['tanggal'], 'peserta' => count($data['status'])]);
        }, 3);
        return redirect()->route('teaching.show', ['kelas' => $kelas->id, 'pertemuan' => $data['pertemuan']])->with('success', 'Presensi disimpan. Data pertemuan yang dipilih ditampilkan kembali.');
    }
}
