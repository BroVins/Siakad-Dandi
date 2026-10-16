<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RoadmapDetail extends Model {
 protected $table = 'roadmap_detail';
 protected $guarded = ['id'];
 protected function casts(): array { return ['roadmap_id'=>'integer', 'mata_kuliah_id'=>'integer', 'kelas_id'=>'integer', 'semester_target'=>'integer', 'prioritas'=>'integer']; }
 public $timestamps = false;
 public function kelas() { return $this->belongsTo(Kelas::class); }
 public function mataKuliah() { return $this->belongsTo(MataKuliah::class,'mata_kuliah_id'); }
}
