<?php

namespace App\Http\Controllers;

use App\Models\Roadmap;
use App\Services\{AcademicService, PlannerService, Access};
use Illuminate\Http\Request;

class PlannerController extends Controller
{
    public function index(Request $request, AcademicService $academic, PlannerService $planner)
    {
        $m = $request->user()->mahasiswa;
        abort_unless($m, 403, 'Profil mahasiswa belum dibuat admin.');
        $term = $academic->activeTerm();
        $history = Roadmap::where('mahasiswa_id', $m->id)->with('details.mataKuliah', 'details.kelas', 'approvals.dosen')->latest('id')->get();
        $roadmap = $history->firstWhere('semester_akademik_id', $term->id);
        return view('planner.index', compact('m', 'term', 'history', 'roadmap') + [
            'fresh' => $roadmap ? $planner->isFresh($roadmap, $m, $term) : true,
            'summary' => $academic->summary($m), 'limit' => $academic->limit($m, $term),
        ]);
    }

    public function generate(Request $request, PlannerService $planner)
    {
        $data = $request->validate(['target_lulus' => 'required|integer|min:1|max:20']);
        $m = $request->user()->mahasiswa;
        abort_unless($m, 403);
        $planner->generate($m, (int) $data['target_lulus']);
        return back()->with('success', 'Roadmap dibuat. Periksa hasil sebelum mengajukan ke Dosen PA.');
    }

    public function submit(Request $request, Roadmap $roadmap, PlannerService $planner)
    {
        $planner->submit($request->user(), $roadmap);
        return back()->with('success', 'Roadmap dikirim ke Dosen PA.');
    }

    public function withdraw(Request $request, Roadmap $roadmap, PlannerService $planner)
    {
        $planner->withdraw($request->user(), $roadmap);
        return back()->with('success', 'Pengajuan roadmap ditarik. Anda dapat membuat rencana baru.');
    }

    public function show(Request $request, Roadmap $roadmap)
    {
        Access::student($request->user(), $roadmap->mahasiswa);
        $roadmap->load('details.mataKuliah', 'details.kelas', 'approvals.dosen');
        return view('planner.review', compact('roadmap'));
    }

    public function review(Request $request, Roadmap $roadmap, PlannerService $planner)
    {
        Access::advise($request->user(), $roadmap->mahasiswa);
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'catatan' => 'nullable|string|max:2000|required_if:status,rejected']);
        $planner->review($request->user(), $roadmap, $data['status'], $data['catatan'] ?? null);
        return back()->with('success', 'Review roadmap disimpan.');
    }
}
