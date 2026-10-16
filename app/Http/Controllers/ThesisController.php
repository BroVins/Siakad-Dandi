<?php

namespace App\Http\Controllers;

use App\Models\{Bimbingan, Dosen, Mahasiswa, Skripsi};
use App\Services\Access;
use App\Support\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ThesisController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Skripsi::with('mahasiswa.prodi', 'dosen', 'bimbingan.user');
        if ($user->role === 'mahasiswa') {
            $query->where('mahasiswa_id', $user->mahasiswa?->id ?? 0);
        } elseif ($user->role === 'dosen') {
            $query->where('dosen_id', $user->dosen?->id ?? 0);
        } elseif ($user->role === 'kaprodi') {
            $query->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $user->prodi_id ?? 0));
        }
        return view('thesis.index', [
            'rows' => $query->latest()->paginate(10),
            'students' => $user->role === 'admin' ? Mahasiswa::whereDoesntHave('skripsi')->orderBy('nama')->get() : collect(),
            'lecturers' => Dosen::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'mahasiswa_id' => 'required|integer|exists:mahasiswa,id|unique:skripsi,mahasiswa_id',
            'dosen_id' => 'required|integer|exists:dosen,id', 'judul' => 'required|string|max:255',
        ]);
        try {
            DB::transaction(function () use ($data) {
                Mahasiswa::lockForUpdate()->findOrFail($data['mahasiswa_id']);
                if (Skripsi::where('mahasiswa_id', $data['mahasiswa_id'])->exists()) {
                    throw ValidationException::withMessages(['mahasiswa_id' => 'Mahasiswa sudah memiliki penugasan skripsi.']);
                }
                $thesis = Skripsi::create($data);
                Audit::record('thesis.created', $thesis);
            }, 3);
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505', '19']) && Skripsi::where('mahasiswa_id', $data['mahasiswa_id'])->exists()) {
                throw ValidationException::withMessages(['mahasiswa_id' => 'Penugasan sudah tersimpan. Muat ulang halaman.']);
            }
            throw $e;
        }
        return back()->with('success', 'Topik dan dosen pembimbing ditetapkan.');
    }

    public function update(Request $request, Skripsi $skripsi)
    {
        Access::thesis($request->user(), $skripsi);
        abort_unless(in_array($request->user()->role, ['admin', 'dosen']), 403);
        $data = $request->validate([
            'judul' => 'required|string|max:255', 'status' => 'required|in:pengajuan,proposal,penelitian,sidang,selesai',
            'progres' => 'required|integer|min:0|max:100',
            'dosen_id' => $request->user()->role === 'admin' ? 'sometimes|required|integer|exists:dosen,id' : 'prohibited',
        ]);
        if (($data['status'] === 'selesai') !== ((int) $data['progres'] === 100)) {
            throw ValidationException::withMessages(['progres' => 'Tahap selesai wajib memiliki progres 100%. Tahap lainnya harus di bawah 100%.']);
        }
        DB::transaction(function () use ($request, $skripsi, $data) {
            $thesis = Skripsi::lockForUpdate()->findOrFail($skripsi->id);
            Access::thesis($request->user(), $thesis);
            $before = $thesis->only('judul', 'dosen_id', 'status', 'progres');
            $thesis->update($data);
            Audit::record('thesis.updated', $thesis, ['before' => $before, 'after' => $thesis->only(array_keys($before))]);
        }, 3);
        return back()->with('success', 'Progres dan penugasan skripsi diperbarui.');
    }

    public function note(Request $request, Skripsi $skripsi)
    {
        Access::thesis($request->user(), $skripsi);
        abort_unless(in_array($request->user()->role, ['mahasiswa', 'dosen', 'admin']), 403);
        $data = $request->validate(['tanggal' => 'required|date_format:Y-m-d|before_or_equal:today', 'catatan' => 'required|string|max:5000']);
        DB::transaction(function () use ($request, $skripsi, $data) {
            $thesis = Skripsi::lockForUpdate()->findOrFail($skripsi->id);
            Access::thesis($request->user(), $thesis);
            $note = $thesis->bimbingan()->create($data + ['user_id' => $request->user()->id]);
            Audit::record('thesis.note.created', $note, ['skripsi_id' => $thesis->id]);
        }, 3);
        return back()->with('success', 'Catatan bimbingan ditambahkan.');
    }

    public function feedback(Request $request, Skripsi $skripsi, Bimbingan $bimbingan)
    {
        Access::thesis($request->user(), $skripsi);
        abort_unless(in_array($request->user()->role, ['admin', 'dosen']) && $bimbingan->skripsi_id === $skripsi->id, 403);
        $data = $request->validate(['umpan_balik' => 'required|string|max:5000']);
        DB::transaction(function () use ($request, $skripsi, $bimbingan, $data) {
            $thesis = Skripsi::lockForUpdate()->findOrFail($skripsi->id);
            Access::thesis($request->user(), $thesis);
            $note = Bimbingan::where('skripsi_id', $thesis->id)->lockForUpdate()->findOrFail($bimbingan->id);
            $note->update($data);
            Audit::record('thesis.feedback.saved', $note, ['skripsi_id' => $thesis->id]);
        }, 3);
        return back()->with('success', 'Umpan balik disimpan.');
    }
}
