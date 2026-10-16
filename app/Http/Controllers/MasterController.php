<?php
namespace App\Http\Controllers;
use App\Support\{MasterRegistry,Audit};
use App\Models\{User,Mahasiswa,Dosen,MataKuliah,SemesterAkademik,Kelas,KrsDetail};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};
use Illuminate\Database\QueryException;
class MasterController extends Controller {
 public function index(Request $r,string $entity) {
  $r->validate(['q'=>'nullable|string|max:100']);
  $spec=MasterRegistry::get($entity);$q=$spec['model']::query();
  if($r->filled('q') && $spec['search']) { $term=mb_substr($r->string('q'),0,100);$q->where(function($q)use($spec,$term){foreach($spec['search'] as $f)$q->orWhere($f,'like','%'.$term.'%');}); }
  if($r->filled('q') && $entity==='kurikulum'){
   $term=mb_substr($r->string('q'),0,100);
   $q->where(fn($q)=>$q->whereHas('mataKuliah',fn($q)=>$q->where('nama','like','%'.$term.'%')->orWhere('kode','like','%'.$term.'%'))->orWhereHas('prodi',fn($q)=>$q->where('nama_prodi','like','%'.$term.'%')));
  }
  return view('master.index',['entity'=>$entity,'spec'=>$spec,'rows'=>$q->latest('id')->paginate(15)->withQueryString(),'options'=>MasterRegistry::options($spec)]);
 }
 public function create(string $entity) { $spec=MasterRegistry::get($entity);return view('master.form',['entity'=>$entity,'spec'=>$spec,'record'=>new $spec['model'],'options'=>MasterRegistry::options($spec),'readonly'=>false]); }
 public function show(string $entity,int $id) { return $this->form($entity,$id,true); }
 public function edit(string $entity,int $id) { return $this->form($entity,$id,false); }
 private function form($entity,$id,$readonly){$spec=MasterRegistry::get($entity);return view('master.form',['entity'=>$entity,'spec'=>$spec,'record'=>$spec['model']::findOrFail($id),'options'=>MasterRegistry::options($spec),'readonly'=>$readonly]);}
 public function store(Request $r,string $entity) { return $this->save($r,$entity); }
 public function update(Request $r,string $entity,int $id) { return $this->save($r,$entity,$id); }
 private function error($msg): never { throw ValidationException::withMessages(['data'=>$msg]); }
 private function save(Request $r,string $entity,?int $id=null){
  $spec=MasterRegistry::get($entity);$record=$id?$spec['model']::findOrFail($id):new $spec['model'];
  $rules=[];foreach($spec['fields'] as $key=>$f)$rules[$key]=$f[2];
  $unique=['fakultas'=>['nama'],'users'=>['email'],'dosen'=>['user_id','nidn'],'mahasiswa'=>['user_id','nim'],'mata-kuliah'=>['kode'],'semester'=>['nama']];
  foreach($unique[$entity]??[] as $field)$rules[$field]=[...explode('|',$rules[$field]),Rule::unique($record->getTable(),$field)->ignore($id)];
  if($entity==='users'){
   $rules['password']=['bail',$id?'nullable':'required','string','min:12',new \App\Rules\PasswordBytes];
   if(is_string($r->input('email')))$r->merge(['email'=>strtolower(trim($r->input('email')))]);
  }
  if($entity==='mata-kuliah')$rules['prasyarat_ids.*']='integer|distinct|exists:mata_kuliah,id';
  $data=$r->validate($rules);
  try { DB::transaction(function()use($data,$record,$entity,$id){
   if($id)$record->setRawAttributes($record->newQuery()->lockForUpdate()->findOrFail($id)->getAttributes(),true);
   if($entity==='users'){
    if(User::whereRaw('LOWER(email) = ?',[$data['email']])->where('id','!=',$id??0)->exists())throw ValidationException::withMessages(['email'=>'Email sudah digunakan.']);
    if(empty($data['password']))unset($data['password']);
    if($data['role']==='kaprodi' && empty($data['prodi_id']))$this->error('Kaprodi wajib memiliki program studi.');
    if($id && ($record->mahasiswa || $record->dosen) && $record->role!==$data['role'])$this->error('Peran akun dengan profil akademik tidak dapat diganti.');
    if($record->id===auth()->id() && $data['role']!=='admin')$this->error('Anda tidak dapat menghapus hak admin akun sendiri.');
   }
   if(in_array($entity,['mahasiswa','dosen'])){
    $expected=$entity==='mahasiswa'?'mahasiswa':'dosen';if(User::findOrFail($data['user_id'])->role!==$expected)$this->error('Peran akun tidak sesuai profil.');
    if($id && $record->user_id!==(int)$data['user_id'])$this->error('Akun profil yang sudah dibuat tidak dapat dipindahkan.');
   }
   if($entity==='prodi' && $id && Mahasiswa::where('prodi_id',$id)->where('semester','>',$data['batas_semester'])->exists())$this->error('Batas semester tidak boleh lebih kecil dari semester mahasiswa yang sudah tercatat.');
   if($entity==='semester'){
    [$start,$end]=array_map('intval',explode('/',$data['tahun_akademik']));
    if($end!==$start+1)$this->error('Tahun akademik harus berurutan, misalnya 2026/2027.');
   }
   if($entity==='mahasiswa'){
    if($id && (int)$data['semester']!==$record->semester && $record->krs()->whereIn('status',['submitted','approved'])->whereHas('term',fn($q)=>$q->where('aktif',true))->exists())$this->error('Semester mahasiswa terkunci selama KRS semester aktif diajukan atau final. Selesaikan proses semester terlebih dahulu.');
    $prodi=\App\Models\ProgramStudi::findOrFail($data['prodi_id']);if($data['semester']>$prodi->batas_semester)$this->error('Semester melebihi batas program studi.');
    if($id && $record->prodi_id!==(int)$data['prodi_id'] && ($record->nilai()->exists() || $record->krs()->exists()))$this->error('Perpindahan prodi dengan riwayat akademik memerlukan migrasi kurikulum.');
   }
   if($entity==='semester' && !empty($data['aktif'])){
    SemesterAkademik::query()->lockForUpdate()->get();SemesterAkademik::where('id','!=',$id??0)->update(['aktif'=>false]);
   }
   if($entity==='kelas'){
    $clash=Kelas::where('semester_akademik_id',$data['semester_akademik_id'])->where('hari',$data['hari'])->where('id','!=',$id??0)->where('mulai','<',$data['selesai'])->where('selesai','>',$data['mulai'])->where(fn($q)=>$q->where('dosen_id',$data['dosen_id'])->orWhere('ruang',$data['ruang']))->exists();
    if($clash)$this->error('Jadwal bertabrakan dengan dosen atau ruang yang sama.');
    if($id && KrsDetail::where('kelas_id',$id)->exists()){
     foreach($data as $field=>$value){
      if($field==='kapasitas')continue;
      $original=$record->$field;
      if(in_array($field,['mulai','selesai']))$original=substr($original,0,5);
      if((string)$value!==(string)$original)$this->error('Kelas sudah memiliki KRS. Hanya kapasitas yang dapat diubah tanpa mengubah jadwal/data akademik.');
     }
     $reserved=KrsDetail::where('kelas_id',$id)->whereHas('krs',fn($q)=>$q->whereIn('status',['submitted','approved']))->count();
     if((int)$data['kapasitas']<$reserved)$this->error('Kapasitas tidak boleh di bawah jumlah peserta terdaftar dan menunggu persetujuan.');
    }
   }
   if($id && $entity==='mata-kuliah' && (\App\Models\Nilai::where('mata_kuliah_id',$id)->exists() || \App\Models\KrsDetail::where('mata_kuliah_id',$id)->exists())){
    if((int)$data['sks']!==(int)$record->sks)$this->error('SKS mata kuliah yang memiliki riwayat tidak dapat diubah. Buat mata kuliah baru untuk revisi kurikulum.');
   }
   if($id && $entity==='semester' && \App\Models\Krs::where('semester_akademik_id',$id)->exists()){
    if($data['tahun_akademik']!==$record->tahun_akademik || $data['periode']!==$record->periode)$this->error('Tahun/periode semester dengan riwayat KRS tidak dapat diubah.');
   }
   $prereqs=$data['prasyarat_ids']??[];unset($data['prasyarat_ids']);
   $record->fill($data);
   $securityChanged=$entity==='users' && $id && ($record->isDirty('password') || $record->isDirty('role'));
   if($securityChanged)$record->remember_token=\Illuminate\Support\Str::random(60);
   $record->save();
   if($securityChanged){
    $record->tokens()->delete();
    if($record->id===auth()->id())auth()->guard('web')->setUser($record);
   }
   if($entity==='mata-kuliah'){
    if(in_array($record->id,array_map('intval',$prereqs),true))$this->error('Mata kuliah tidak dapat menjadi prasyarat dirinya sendiri.');
    $record->prasyarat()->sync($prereqs);
    $walk=function($courseId,$path=[])use(&$walk){if(in_array($courseId,$path,true))$this->error('Prasyarat membentuk siklus.');foreach(MataKuliah::findOrFail($courseId)->prasyarat as $p)$walk($p->id,[...$path,$courseId]);};$walk($record->id);
   }
   Audit::record($id?'master.updated':'master.created',$record);
  },3); }catch(QueryException $e){if(in_array((string)$e->getCode(),['23000','23505','19']))$this->error('Data duplikat atau relasi masih digunakan. Periksa kembali isian.');throw $e;}
  return redirect()->route('master.index',$entity)->with('success','Data berhasil disimpan.');
 }
 public function destroy(string $entity,int $id){
  $spec=MasterRegistry::get($entity);$record=$spec['model']::findOrFail($id);
  if($entity==='users' && $id===auth()->id())$this->error('Akun sendiri tidak dapat dihapus.');
  if($entity==='semester' && $record->aktif)$this->error('Semester aktif tidak dapat dihapus.');
  try{DB::transaction(function()use($record){Audit::record('master.deleted',$record);$record->delete();});}catch(QueryException $e){if(in_array((string)$e->getCode(),['23000','23503','19']))$this->error('Data masih memiliki relasi dan tidak dapat dihapus.');throw $e;}
  return back()->with('success','Data berhasil dihapus.');
 }
}
