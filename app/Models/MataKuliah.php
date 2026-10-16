<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MataKuliah extends Model {
 protected $table = 'mata_kuliah';
 protected $guarded = ['id'];
 protected function casts(): array { return ['sks'=>'integer', 'semester'=>'integer']; }
 
 public function prasyarat() { return $this->belongsToMany(self::class,'prasyarat','mata_kuliah_id','prasyarat_id'); }
}
