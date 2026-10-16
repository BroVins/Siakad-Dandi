<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KrsDetail extends Model {
 protected $table = 'krs_detail';
 protected $guarded = ['id'];
 protected function casts(): array { return ['krs_id'=>'integer', 'mata_kuliah_id'=>'integer', 'kelas_id'=>'integer']; }
 public $timestamps = false;
 public function krs() { return $this->belongsTo(Krs::class,'krs_id'); }
 public function mataKuliah() { return $this->belongsTo(MataKuliah::class,'mata_kuliah_id'); }
 public function kelas() { return $this->belongsTo(Kelas::class,'kelas_id'); }
}
