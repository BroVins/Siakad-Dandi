<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Pembayaran extends Model {
 protected $table = 'pembayaran';
 protected $guarded = ['id'];
 protected function casts(): array { return ['tagihan_id'=>'integer', 'dicatat_oleh'=>'integer', 'jumlah'=>'decimal:2']; }
 
 public function tagihan() { return $this->belongsTo(Tagihan::class,'tagihan_id'); }
}
