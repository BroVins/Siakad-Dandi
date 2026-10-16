<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder {
 public function run(): void {
  // Safe default: no public demo credentials are created in production.
  if(app()->environment('local','testing'))$this->call(DemoSeeder::class);
 }
}
