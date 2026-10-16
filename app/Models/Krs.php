<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Krs extends Model {
 protected $table = 'krs';
 protected $guarded = ['id'];
 protected function casts(): array { return ['mahasiswa_id'=>'integer', 'semester_akademik_id'=>'integer', 'roadmap_id'=>'integer', 'semester'=>'integer', 'approved_by'=>'integer', 'approved_at'=>'datetime']; }
 
 public function roadmap() { return $this->belongsTo(Roadmap::class); }
 public function mahasiswa() { return $this->belongsTo(Mahasiswa::class); }
 public function term() { return $this->belongsTo(SemesterAkademik::class,'semester_akademik_id'); }
 public function details() { return $this->hasMany(KrsDetail::class,'krs_id'); }
 public function approver() { return $this->belongsTo(User::class,'approved_by'); }
}
