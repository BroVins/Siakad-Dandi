<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Bimbingan extends Model {
 protected $table = 'bimbingan';
 protected $guarded = ['id'];
 protected function casts(): array { return ['skripsi_id'=>'integer', 'user_id'=>'integer']; }
 
 public function user() { return $this->belongsTo(User::class); }
}
