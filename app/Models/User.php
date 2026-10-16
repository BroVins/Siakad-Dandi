<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
 use Notifiable, HasApiTokens;
 protected $fillable = ['name','email','password','role','prodi_id'];
 protected $hidden = ['password','remember_token'];
 protected function casts(): array { return ['prodi_id'=>'integer', 'password'=>'hashed', 'email_verified_at'=>'datetime']; }
 public function mahasiswa() { return $this->hasOne(Mahasiswa::class); }
 public function dosen() { return $this->hasOne(Dosen::class); }
 public function prodi() { return $this->belongsTo(ProgramStudi::class,'prodi_id'); }
}
