<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProgramStudi extends Model {
 protected $table = 'program_studi';
 protected $guarded = ['id'];
 protected function casts(): array { return ['fakultas_id'=>'integer', 'sks_lulus'=>'integer', 'batas_semester'=>'integer']; }
 
 public function fakultas() { return $this->belongsTo(Fakultas::class); }
 public function kurikulum() { return $this->hasMany(Kurikulum::class,'prodi_id'); }
}
