<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApprovalPa extends Model {
 protected $table = 'approval_pa';
 protected $guarded = ['id'];
 protected function casts(): array { return ['roadmap_id'=>'integer', 'dosen_pa_id'=>'integer']; }
 
 public function dosen() { return $this->belongsTo(Dosen::class,'dosen_pa_id'); }
}
