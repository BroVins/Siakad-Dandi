<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\User;
class ProductionSeeder extends Seeder {
 public function run(): void {
  $email=strtolower(trim($this->command->ask('Email admin pertama')??''));
  $name=$this->command->ask('Nama admin pertama');
  $password=$this->command->secret('Kata sandi admin (minimal 12 karakter)');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL) || (mb_strlen($password??'')<12 || strlen($password??'')>72) || !$name)throw new \RuntimeException('Data admin tidak valid.');
  if(User::where('role','admin')->exists())throw new \RuntimeException('Admin sudah tersedia. Kelola pengguna melalui aplikasi.');
  User::create(['name'=>$name,'email'=>$email,'password'=>$password,'role'=>'admin']);
  $this->command->info('Admin pertama dibuat. Lengkapi master data melalui portal.');
 }
}
