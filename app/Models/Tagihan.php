<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Tagihan extends Model {
 protected $table = 'tagihan';
 protected $guarded = ['id'];
 protected function casts(): array { return ['mahasiswa_id'=>'integer', 'semester_akademik_id'=>'integer', 'jumlah'=>'decimal:2']; }
 
 public function paidCents(): int {
  return $this->pembayaran->sum(fn($payment)=>\App\Support\Money::cents($payment->jumlah));
 }
 public function remainingCents(): int {
  return max(0,\App\Support\Money::cents($this->jumlah)-$this->paidCents());
 }
 public function mahasiswa() { return $this->belongsTo(Mahasiswa::class); }
 public function term() { return $this->belongsTo(SemesterAkademik::class,'semester_akademik_id'); }
 public function pembayaran() { return $this->hasMany(Pembayaran::class,'tagihan_id'); }
}
