<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Pengumuman extends Model {
 protected $table = 'pengumuman';
 protected $guarded = ['id'];
 protected function casts(): array { return ['mulai'=>'date', 'selesai'=>'date']; }
 

}
