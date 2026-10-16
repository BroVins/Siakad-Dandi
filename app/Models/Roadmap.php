<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Roadmap extends Model {
 protected $table = 'roadmap';
 protected $guarded = ['id'];
 protected function casts(): array { return ['mahasiswa_id'=>'integer', 'semester_akademik_id'=>'integer', 'semester_mulai'=>'integer', 'target_lulus'=>'integer', 'warnings'=>'array']; }
 public function mahasiswa() { return $this->belongsTo(Mahasiswa::class); }
 public function details() { return $this->hasMany(RoadmapDetail::class,'roadmap_id'); }
 public function approvals() { return $this->hasMany(ApprovalPa::class,'roadmap_id')->latest(); }
}
