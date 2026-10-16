<?php
namespace App\Http\Controllers;
use App\Models\{Krs,Kelas,Kurikulum,Roadmap};
use App\Services\{AcademicService,KrsService,Access};
use Illuminate\Http\Request;
class KrsController extends Controller {
 public function index(Request $r,AcademicService $a,\App\Services\PlannerService $planner){
  $m=$r->user()->mahasiswa;abort_unless($m,403,'Profil mahasiswa belum tersedia.');$term=$a->activeTerm();
  $krs=Krs::where('mahasiswa_id',$m->id)->where('semester_akademik_id',$term->id)->with('details')->first();
  $courses=Kurikulum::where('prodi_id',$m->prodi_id)->pluck('mata_kuliah_id');
  $classes=Kelas::where('semester_akademik_id',$term->id)->whereIn('mata_kuliah_id',$courses)->with('mataKuliah.prasyarat','dosen')->withCount(['enrollments as occupied_count'=>fn($q)=>$q->whereHas('krs',fn($q)=>$q->whereIn('status',['submitted','approved'])->when($krs,fn($q)=>$q->where('id','!=',$krs->id)))])->orderBy('mata_kuliah_id')->get();
  $roadmap=Roadmap::where('mahasiswa_id',$m->id)->where('semester_akademik_id',$term->id)->latest('id')->first();
  $recommended=$roadmap?->details()->where('semester_target',$m->semester)->pluck('mata_kuliah_id')->all()??[];
  return view('krs.index',compact('m','term','krs','classes','roadmap','recommended')+['fresh'=>$roadmap?$planner->isFresh($roadmap,$m,$term):true,'passed'=>$a->passedIds($m),'priorPassed'=>$a->passedIds($m,$term),'limit'=>$a->limit($m,$term),'history'=>Krs::where('mahasiswa_id',$m->id)->with('term')->latest('id')->get()]);
 }
 public function store(Request $r,KrsService $service){$d=$r->validate(['kelas_ids'=>'required|array|min:1','kelas_ids.*'=>'required|integer|distinct','action'=>'required|in:draft,submit']);$m=$r->user()->mahasiswa;abort_unless($m,403);$service->save($m,array_map('intval',$d['kelas_ids']),$d['action']==='submit');return back()->with('success',$d['action']==='submit'?'KRS diajukan ke Dosen PA.':'Draft KRS disimpan.');}
 public function withdraw(Request $r,Krs $krs,KrsService $service){$service->withdraw($r->user(),$krs);return back()->with('success','Pengajuan KRS ditarik. Kuota kelas dilepas dan draft dapat diedit.');}
 public function show(Request $r,Krs $krs){Access::student($r->user(),$krs->mahasiswa);$krs->load('details.kelas.dosen','details.mataKuliah','mahasiswa.prodi','term','approver');return view('krs.show',compact('krs'));}
 public function review(Request $r,Krs $krs,KrsService $service){$d=$r->validate(['status'=>'required|in:approved,rejected','catatan'=>'nullable|string|max:2000|required_if:status,rejected']);$service->review($r->user(),$krs,$d['status'],$d['catatan']??null);return back()->with('success','Review KRS disimpan.');}
}
