<?php
namespace App\Services;
use App\Models\{User,Mahasiswa,Kelas,Skripsi};
class Access {
 public static function student(User $user, Mahasiswa $m): void {
  $allowed = $user->role==='admin' || ($user->role==='mahasiswa' && $m->user_id===$user->id)
    || ($user->role==='dosen' && $user->dosen && $m->dosen_pa_id===$user->dosen->id)
    || ($user->role==='kaprodi' && $user->prodi_id===$m->prodi_id);
  abort_unless($allowed,403);
 }
 public static function advise(User $user, Mahasiswa $m): void {
  abort_unless($user->role==='dosen' && $user->dosen && $m->dosen_pa_id===$user->dosen->id,403,'Hanya Dosen PA mahasiswa ini yang dapat menyetujui.');
 }
 public static function teach(User $user,Kelas $kelas): void {
  abort_unless($user->role==='dosen' && $user->dosen && $kelas->dosen_id===$user->dosen->id,403);
 }
 public static function thesis(User $user,Skripsi $skripsi): void {
  abort_unless($user->role==='admin' || ($user->role==='mahasiswa' && $skripsi->mahasiswa->user_id===$user->id) || ($user->role==='dosen' && $user->dosen && $skripsi->dosen_id===$user->dosen->id) || ($user->role==='kaprodi' && $skripsi->mahasiswa->prodi_id===$user->prodi_id),403);
 }
}
