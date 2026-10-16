<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Skripsi extends Model {
 protected $table = 'skripsi';
 protected $guarded = ['id'];
 protected function casts(): array { return ['mahasiswa_id'=>'integer', 'dosen_id'=>'integer', 'progres'=>'integer']; }
 
 public function mahasiswa() { return $this->belongsTo(Mahasiswa::class); }
 public function dosen() { return $this->belongsTo(Dosen::class); }
 public function bimbingan() { return $this->hasMany(Bimbingan::class,'skripsi_id')->latest(); }
}
