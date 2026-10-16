<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Mahasiswa extends Model {
 protected $table = 'mahasiswa';
 protected $guarded = ['id'];
 protected function casts(): array { return ['user_id'=>'integer', 'prodi_id'=>'integer', 'dosen_pa_id'=>'integer', 'angkatan'=>'integer', 'semester'=>'integer']; }
 
 public function skripsi() { return $this->hasOne(Skripsi::class, 'mahasiswa_id'); }
 public function user() { return $this->belongsTo(User::class); }
 public function prodi() { return $this->belongsTo(ProgramStudi::class,'prodi_id'); }
 public function dosenPA() { return $this->belongsTo(Dosen::class,'dosen_pa_id'); }
 public function nilai() { return $this->hasMany(Nilai::class,'mahasiswa_id'); }
 public function krs() { return $this->hasMany(Krs::class,'mahasiswa_id'); }
}
