<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Fakultas extends Model {
 protected $table = 'fakultas';
 protected $guarded = ['id'];
 
 public function prodi() { return $this->hasMany(ProgramStudi::class,'fakultas_id'); }
}
