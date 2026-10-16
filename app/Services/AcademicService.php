<?php
namespace App\Services;
use App\Models\{Mahasiswa,Nilai,SemesterAkademik,Kurikulum};
use Illuminate\Support\Collection;
class AcademicService {
 public function transcript(Mahasiswa $m, ?SemesterAkademik $beforeTerm = null): Collection {
  return $m->nilai()->where('published',true)
   ->when($beforeTerm, fn($q) => $q->whereHas('term', fn($t) => $t->where('krs_mulai', '<', $beforeTerm->krs_mulai->toDateString())))
   ->with('mataKuliah')->get()->sortByDesc('bobot')->unique('mata_kuliah_id')->values();
 }
 public function summary(Mahasiswa $m): array {
  $rows=$this->transcript($m);
  $attempted=$rows->sum(fn($n)=>$n->mataKuliah->sks);
  $passed=$rows->filter(fn($n)=>$n->bobot>=config('academic.passing_points'));
  $curriculum=Kurikulum::where('prodi_id',$m->prodi_id)->pluck('mata_kuliah_id');
  $credits=$passed->whereIn('mata_kuliah_id',$curriculum)->sum(fn($n)=>$n->mataKuliah->sks);
  $required=Kurikulum::where('prodi_id',$m->prodi_id)->where('status_wajib',true)->pluck('mata_kuliah_id')->diff($passed->pluck('mata_kuliah_id'))->count();
  return ['ipk'=>$attempted ? round($rows->sum(fn($n)=>$n->bobot*$n->mataKuliah->sks)/$attempted,2):0,
   'sks'=>$credits,'required_remaining'=>$required,'target'=>$m->prodi->sks_lulus,'remaining'=>max(0,$m->prodi->sks_lulus-$credits),
   'percent'=>min(100,round($credits/max(1,$m->prodi->sks_lulus)*100)),
   'a_percent'=>$rows->count()?round($rows->where('grade','A')->count()/$rows->count()*100):0,
   'eligible'=>$credits>=$m->prodi->sks_lulus && $required===0];
 }
 public function passedIds(Mahasiswa $m, ?SemesterAkademik $beforeTerm = null): array {
  return $this->transcript($m, $beforeTerm)->filter(fn($n)=>$n->bobot>=config('academic.passing_points'))->pluck('mata_kuliah_id')->all();
 }
 public function ips(Mahasiswa $m, int $termId): ?float {
  $rows=$m->nilai()->where('semester_akademik_id',$termId)->where('published',true)->with('mataKuliah')->get();
  $sks=$rows->sum(fn($n)=>$n->mataKuliah->sks);
  return $sks ? round($rows->sum(fn($n)=>$n->bobot*$n->mataKuliah->sks)/$sks,2):null;
 }
 public function limit(Mahasiswa $m, SemesterAkademik $term): int {
  $previous=SemesterAkademik::where('krs_mulai','<',$term->krs_mulai)->latest('krs_mulai')->first();
  $ips=$previous ? $this->ips($m,$previous->id):null;
  if($ips===null) return config('academic.first_semester_limit');
  foreach(config('academic.limits') as $rule) if($ips>=$rule['min']) return $rule['sks'];
  return 12;
 }
 public function grade(float $score): array {
  foreach(config('academic.grades') as $g) if($score>=$g['min']) return ['grade'=>$g['grade'],'bobot'=>$g['points']];
  return ['grade'=>'E','bobot'=>0];
 }
 public function activeTerm(): SemesterAkademik {
   $terms = SemesterAkademik::where('aktif', true)->limit(2)->get();
  if ($terms->count() !== 1) {
   throw \Illuminate\Validation\ValidationException::withMessages(['semester' => 'Semester akademik belum siap. Admin perlu menetapkan tepat satu semester aktif.']);
  }
  return $terms->first();
 }
}
