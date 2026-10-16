<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Presensi extends Model {
 protected $table = 'presensi';
 protected $guarded = ['id'];
 protected function casts(): array { return ['kelas_id'=>'integer', 'mahasiswa_id'=>'integer', 'pertemuan'=>'integer']; }
 
 public function kelas() { return $this->belongsTo(Kelas::class); }
 public function mahasiswa() { return $this->belongsTo(Mahasiswa::class); }
}
