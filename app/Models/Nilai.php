<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Nilai extends Model {
 protected $table = 'nilai';
 protected $guarded = ['id'];
 protected function casts(): array { return ['mahasiswa_id'=>'integer', 'mata_kuliah_id'=>'integer', 'semester_akademik_id'=>'integer', 'kelas_id'=>'integer', 'published'=>'boolean', 'nilai'=>'float', 'bobot'=>'float']; }
 public function mahasiswa() { return $this->belongsTo(Mahasiswa::class); }
 public function mataKuliah() { return $this->belongsTo(MataKuliah::class,'mata_kuliah_id'); }
 public function term() { return $this->belongsTo(SemesterAkademik::class,'semester_akademik_id'); }
}
