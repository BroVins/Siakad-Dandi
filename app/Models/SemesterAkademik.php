<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SemesterAkademik extends Model {
 protected $table = 'semester_akademik';
 protected $guarded = ['id'];
 protected function casts(): array { return ['aktif'=>'boolean','krs_mulai'=>'date','krs_selesai'=>'date']; }
 public function isOpen(): bool { return $this->aktif && now()->startOfDay()->betweenIncluded($this->krs_mulai,$this->krs_selesai); }

}
