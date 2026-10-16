<?php
namespace App\Http\Controllers;
use App\Models\{Mahasiswa,Dosen,Krs,Kelas,Nilai,Presensi,Roadmap,Tagihan,Pengumuman,SemesterAkademik};
use App\Services\{AcademicService,Access};
use Illuminate\Http\Request;
class AcademicController extends Controller {
 public function dashboard(Request $r,AcademicService $a){
  $user=$r->user();$term=SemesterAkademik::where('aktif',true)->first();
  $announcements=Pengumuman::whereDate('mulai','<=',today())->whereDate('selesai','>=',today())->latest()->limit(4)->get();
  if($user->role==='mahasiswa'){
   $m=$user->mahasiswa;abort_unless($m,403,'Profil mahasiswa belum dibuat. Hubungi admin.');$m->load('prodi.fakultas','dosenPA.user');
   $trend=SemesterAkademik::whereIn('id',$m->nilai()->where('published',true)->select('semester_akademik_id'))->orderBy('krs_mulai')->get()->map(fn($t)=>['label'=>$t->nama,'ips'=>$a->ips($m,$t->id)]);
   $summary=$a->summary($m);$roadmap=Roadmap::where('mahasiswa_id',$m->id)->with('details.mataKuliah')->latest('id')->first();
   $krs=$term?Krs::where('mahasiswa_id',$m->id)->where('semester_akademik_id',$term->id)->with('details.kelas.mataKuliah')->first():null;
   $previous=$term?SemesterAkademik::where('krs_mulai','<',$term->krs_mulai)->latest('krs_mulai')->first():null;
   $ips=$previous?$a->ips($m,$previous->id):null;
   $todayClasses=$krs?->status==='approved'?$krs->details->filter(fn($d)=>$d->kelas->hari===now()->translatedFormat('l')):collect();
   return view('dashboard.mahasiswa',compact('m','summary','roadmap','krs','ips','todayClasses','announcements','term','trend'));
  }
  $students=Mahasiswa::query();
  if($user->role==='kaprodi')$students->where('prodi_id',$user->prodi_id??0);
  if($user->role==='dosen')$students->where('dosen_pa_id',$user->dosen?->id??0);
  $studentIds=$students->pluck('id');
  $roadmaps=Roadmap::whereIn('mahasiswa_id',$studentIds)->where('status','submitted')->with('mahasiswa')->latest()->get();
  $pending=Krs::whereIn('mahasiswa_id',$studentIds)->where('status','submitted')->with('mahasiswa','term')->latest()->get();
  $stats=['Mahasiswa'=>$students->count(),'Dosen'=>Dosen::count(),'Roadmap menunggu'=>$roadmaps->count(),'KRS menunggu'=>$pending->count()];
  if($user->role==='keuangan')$stats=['Jumlah tagihan'=>Tagihan::count(),'Total tagihan'=>Tagihan::sum('jumlah'),'Total pembayaran'=>\App\Models\Pembayaran::sum('jumlah')];
  return view('dashboard.staff',compact('stats','roadmaps','pending','announcements','term'));
 }
 public function profile(Request $r){return view('profile.index',['user'=>$r->user(),'m'=>$r->user()->mahasiswa]);}
 public function updateProfile(Request $r){$d=$r->validate(['telepon'=>'nullable|string|max:30','alamat'=>'nullable|string|max:1000']);abort_unless($r->user()->mahasiswa,403);$r->user()->mahasiswa->update($d);return back()->with('success','Biodata kontak disimpan.');}
 public function grades(Request $r,AcademicService $a){
  $m=$r->user()->mahasiswa;abort_unless($m,403);$termId=$r->integer('semester_akademik_id');
  $query=$m->nilai()->where('published',true)->with('mataKuliah','term');if($termId)$query->where('semester_akademik_id',$termId);
  return view('reports.grades',['m'=>$m,'rows'=>$query->get(),'summary'=>$a->summary($m),'transcript'=>$a->transcript($m),'terms'=>SemesterAkademik::orderByDesc('krs_mulai')->get(),'ips'=>$termId?$a->ips($m,$termId):null,'presensi'=>Presensi::where('mahasiswa_id',$m->id)->with('kelas.mataKuliah')->when($termId,fn($q)=>$q->whereHas('kelas',fn($q)=>$q->where('semester_akademik_id',$termId)))->orderByDesc('tanggal')->get()]);
 }
 public function schedule(Request $r,AcademicService $a){
  $u=$r->user();$term=$a->activeTerm();$q=Kelas::where('semester_akademik_id',$term->id)->with('mataKuliah','dosen');
  if($u->role==='mahasiswa'){
   abort_unless($u->mahasiswa,403);$krs=Krs::where('mahasiswa_id',$u->mahasiswa->id)->where('semester_akademik_id',$term->id)->where('status','approved')->first();$q->whereIn('id',$krs?->details()->pluck('kelas_id')??[]);
  }elseif($u->role==='dosen')$q->where('dosen_id',$u->dosen?->id??0);
  elseif($u->role==='kaprodi')$q->whereIn('mata_kuliah_id',\App\Models\Kurikulum::where('prodi_id',$u->prodi_id??0)->pluck('mata_kuliah_id'));
  return view('reports.schedule',['classes'=>$q->orderBy('mulai')->get(),'term'=>$term]);
 }
 public function reports(Request $r,AcademicService $a){
  $r->validate(['q'=>'nullable|string|max:100','angkatan'=>'nullable|integer']);$q=Mahasiswa::with('prodi','dosenPA');
  if($r->user()->role==='kaprodi')$q->where('prodi_id',$r->user()->prodi_id??0);
  if($r->user()->role==='dosen')$q->where('dosen_pa_id',$r->user()->dosen?->id??0);
  if($r->filled('q'))$q->where(fn($q)=>$q->where('nama','like','%'.$r->q.'%')->orWhere('nim','like','%'.$r->q.'%'));
  if($r->filled('angkatan'))$q->where('angkatan',$r->integer('angkatan'));
  if($r->query('export')==='csv')return response()->streamDownload(function()use($q,$a){$f=fopen('php://output','w');fputcsv($f,['NIM','Nama','Prodi','Angkatan','Semester','IPK','SKS lulus','Wajib tersisa','Kelayakan akademik']);foreach($q->cursor() as $m){$s=$a->summary($m);$safe=fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v)?"'".$v:$v;fputcsv($f,array_map($safe,[$m->nim,$m->nama,$m->prodi->nama_prodi,$m->angkatan,$m->semester,$s['ipk'],$s['sks'],$s['required_remaining'],$s['eligible']?'Memenuhi SKS & wajib':'Belum memenuhi']));}fclose($f);},'laporan-akademik.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
  $students=$q->orderBy('nim')->paginate(20)->withQueryString();$summaries=[];foreach($students as $m)$summaries[$m->id]=$a->summary($m);
  return view('reports.index',compact('students','summaries'));
 }
}
