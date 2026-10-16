<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Kelas extends Model {
 protected $table = 'kelas';
 protected $guarded = ['id'];
 protected function casts(): array { return ['mata_kuliah_id'=>'integer', 'dosen_id'=>'integer', 'semester_akademik_id'=>'integer', 'kapasitas'=>'integer']; }
 
 public function mataKuliah() { return $this->belongsTo(MataKuliah::class,'mata_kuliah_id'); }
 public function dosen() { return $this->belongsTo(Dosen::class); }
 public function term() { return $this->belongsTo(SemesterAkademik::class,'semester_akademik_id'); }
 public function enrollments() { return $this->hasMany(KrsDetail::class,'kelas_id'); }
 public function peserta() { return $this->hasMany(KrsDetail::class,'kelas_id')->whereHas('krs',fn($q)=>$q->where('status','approved')); }
}
