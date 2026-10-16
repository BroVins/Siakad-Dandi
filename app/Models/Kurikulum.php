<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Kurikulum extends Model {
 protected $table = 'kurikulum';
 protected $guarded = ['id'];
 protected function casts(): array { return ['prodi_id'=>'integer', 'mata_kuliah_id'=>'integer', 'semester'=>'integer', 'status_wajib'=>'boolean']; }
 
 public function prodi() { return $this->belongsTo(ProgramStudi::class,'prodi_id'); }
 public function mataKuliah() { return $this->belongsTo(MataKuliah::class,'mata_kuliah_id'); }
}
