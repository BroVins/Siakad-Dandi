<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Dosen extends Model {
 protected $table = 'dosen';
 protected $guarded = ['id'];
 protected function casts(): array { return ['user_id'=>'integer']; }
 
 public function user() { return $this->belongsTo(User::class); }
 public function mahasiswa() { return $this->hasMany(Mahasiswa::class,'dosen_pa_id'); }
}
