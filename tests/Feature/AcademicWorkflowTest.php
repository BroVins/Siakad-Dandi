<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Notification,Password};
use Illuminate\Auth\Notifications\ResetPassword;
use App\Models\{User,Mahasiswa,Roadmap,Krs,Kelas,Tagihan,Nilai};
use App\Services\{PlannerService,AcademicService};
use Database\Seeders\DemoSeeder;
class AcademicWorkflowTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();$this->seed(DemoSeeder::class);}
 private function user(string $role): User {return User::where('email',$role.'@siakad.test')->firstOrFail();}
 private function student(): Mahasiswa {return $this->user('mahasiswa')->mahasiswa;}
 private function classes(): array {return Kelas::whereHas('term',fn($q)=>$q->where('aktif',true))->whereHas('mataKuliah',fn($q)=>$q->where('semester',5))->pluck('id')->all();}
 public function test_login_logout_and_wrong_password(): void {
  $this->get('/dashboard')->assertRedirect('/login');
  $this->post('/login',['email'=>'mahasiswa@siakad.test','password'=>'wrong'])->assertSessionHasErrors('email');
  $this->post('/login',['email'=>'mahasiswa@siakad.test','password'=>config('academic.demo_password')])->assertRedirect('/dashboard');
  $this->assertAuthenticatedAs($this->user('mahasiswa'));$this->post('/logout')->assertRedirect('/login');$this->assertGuest();
 }
 public function test_student_cannot_access_admin_or_other_student_records(): void {
  $this->actingAs($this->user('mahasiswa'))->get('/master/users')->assertForbidden();
  $other=Mahasiswa::where('nim','2024010002')->first();$roadmap=app(PlannerService::class)->generate($other,14);
  $this->get('/roadmap/'.$roadmap->id)->assertForbidden();
 }
 public function test_admin_creates_edits_views_and_deletes_faculty(): void {
  $this->actingAs($this->user('admin'))->post('/master/fakultas',['nama'=>'Fakultas Pengujian'])->assertSessionHasNoErrors();
  $id=\App\Models\Fakultas::where('nama','Fakultas Pengujian')->value('id');
  $this->get('/master/fakultas/'.$id)->assertOk();
  $this->put('/master/fakultas/'.$id,['nama'=>'Fakultas Revisi'])->assertSessionHasNoErrors();
  $this->delete('/master/fakultas/'.$id)->assertSessionHasNoErrors();$this->assertDatabaseMissing('fakultas',['id'=>$id]);
 }
 public function test_planner_pa_review_and_final_krs_workflow(): void {
  $this->actingAs($this->user('mahasiswa'))->post('/planner',['target_lulus'=>8])->assertSessionHasNoErrors();
  $roadmap=Roadmap::latest('id')->first();$this->assertCount(0,$roadmap->warnings);$this->assertSame(4,$roadmap->details->groupBy('semester_target')->count());
  $this->post('/planner/'.$roadmap->id.'/submit')->assertSessionHasNoErrors();
  $this->actingAs($this->user('dosen'))->post('/roadmap/'.$roadmap->id.'/review',['status'=>'approved','catatan'=>'Rencana sesuai kurikulum.'])->assertSessionHasNoErrors();
  $this->actingAs($this->user('mahasiswa'))->post('/krs',['kelas_ids'=>$this->classes(),'action'=>'submit'])->assertSessionHasNoErrors();
  $krs=Krs::where('mahasiswa_id',$this->student()->id)->latest('id')->first();$this->assertSame('submitted',$krs->status);
  $this->actingAs($this->user('dosen'))->post('/krs/'.$krs->id.'/review',['status'=>'approved'])->assertSessionHasNoErrors();$this->assertSame('approved',$krs->fresh()->status);
  $this->actingAs($this->user('mahasiswa'))->post('/krs',['kelas_ids'=>$this->classes(),'action'=>'draft'])->assertSessionHasErrors('krs');
 }
 public function test_krs_requires_approved_roadmap_and_does_not_persist_failed_submit(): void {
  $this->actingAs($this->user('mahasiswa'))->post('/krs',['kelas_ids'=>$this->classes(),'action'=>'submit'])->assertSessionHasErrors('krs');
  $this->assertDatabaseMissing('krs',['mahasiswa_id'=>$this->student()->id,'semester_akademik_id'=>app(AcademicService::class)->activeTerm()->id]);
 }
 public function test_unmet_prerequisite_rejects_krs(): void {
  $u=User::where('email','nadia@siakad.test')->first();$this->actingAs($u)->post('/krs',['kelas_ids'=>[$this->classes()[0]],'action'=>'draft'])->assertSessionHasErrors('krs');
 }
 public function test_duplicate_course_and_over_limit_rejected(): void {
  $c=Kelas::find($this->classes()[0]);$copy=$c->replicate();$copy->nama='B';$copy->mulai='16:00';$copy->selesai='18:00';$copy->save();
  $this->actingAs($this->user('mahasiswa'))->post('/krs',['kelas_ids'=>[$c->id,$copy->id],'action'=>'draft'])->assertSessionHasErrors('krs');
  $ids=Kelas::where('semester_akademik_id',$c->semester_akademik_id)->where('id','!=',$copy->id)->pluck('id')->all();$this->post('/krs',['kelas_ids'=>$ids,'action'=>'draft'])->assertSessionHasErrors('krs');
 }
 public function test_schedule_conflict_rejected(): void {
  $ids=$this->classes();$first=Kelas::find($ids[0]);Kelas::whereKey($ids[1])->update(['hari'=>$first->hari,'mulai'=>$first->mulai,'selesai'=>$first->selesai]);
  $this->actingAs($this->user('mahasiswa'))->post('/krs',['kelas_ids'=>array_slice($ids,0,2),'action'=>'draft'])->assertSessionHasErrors('krs');
 }
 public function test_closed_period_and_full_class_rejected(): void {
  $c=Kelas::find($this->classes()[0]);$c->kapasitas=0;$c->save();$this->actingAs($this->user('mahasiswa'))->post('/krs',['kelas_ids'=>[$c->id],'action'=>'draft'])->assertSessionHasErrors('krs');
  $c->kapasitas=40;$c->save();$term=app(AcademicService::class)->activeTerm();$term->update(['krs_selesai'=>today()->subDay()]);$this->post('/krs',['kelas_ids'=>[$c->id],'action'=>'draft'])->assertSessionHasErrors('krs');
 }
 public function test_wrong_lecturer_cannot_approve(): void {
  $r=app(PlannerService::class)->generate($this->student(),8);$r->update(['status'=>'submitted']);
  $u=User::create(['name'=>'Dosen lain','email'=>'lain@example.test','password'=>'LongPassword123!','role'=>'dosen']);\App\Models\Dosen::create(['user_id'=>$u->id,'nidn'=>'999','nama'=>'Dosen lain']);
  $this->actingAs($u)->post('/roadmap/'.$r->id.'/review',['status'=>'approved'])->assertForbidden();
 }
 public function test_grade_publication_and_attendance_require_enrollment(): void {
  $kelas=Kelas::whereHas('peserta')->first();$student=$this->student();$this->actingAs($this->user('dosen'));
  $this->post('/mengajar/'.$kelas->id.'/nilai',['nilai'=>[$student->id=>88],'published'=>1])->assertSessionHasNoErrors();
  $this->assertDatabaseHas('nilai',['mahasiswa_id'=>$student->id,'kelas_id'=>$kelas->id,'grade'=>'A','published'=>1]);
  $this->post('/mengajar/'.$kelas->id.'/presensi',['pertemuan'=>1,'tanggal'=>today()->toDateString(),'status'=>[$student->id=>'hadir']])->assertSessionHasNoErrors();
  $other=Mahasiswa::where('id','!=',$student->id)->first();$this->post('/mengajar/'.$kelas->id.'/nilai',['nilai'=>[$other->id=>90]])->assertForbidden();
 }
 public function test_overpayment_and_duplicate_reference_rejected(): void {
  $t=Tagihan::first();$this->actingAs($this->user('keuangan'))->post('/tagihan/'.$t->id.'/bayar',['jumlah'=>8000000,'tanggal'=>today()->toDateString(),'referensi'=>'TEST-1'])->assertSessionHasErrors('jumlah');
  $this->post('/tagihan/'.$t->id.'/bayar',['jumlah'=>7500000,'tanggal'=>today()->toDateString(),'referensi'=>'TEST-1'])->assertSessionHasNoErrors();
  $this->post('/tagihan/'.$t->id.'/bayar',['jumlah'=>1,'tanggal'=>today()->toDateString(),'referensi'=>'TEST-1'])->assertSessionHasErrors('referensi');
 }
 public function test_password_reset_notification_and_token(): void {
  Notification::fake();$u=$this->user('mahasiswa');$this->post('/forgot-password',['email'=>$u->email])->assertSessionHasNoErrors();Notification::assertSentTo($u,ResetPassword::class);
  $token=Password::createToken($u);$this->post('/reset-password',['email'=>$u->email,'token'=>$token,'password'=>'NewPassword2026!','password_confirmation'=>'NewPassword2026!'])->assertRedirect('/login');
  $this->post('/reset-password',['email'=>$u->email,'token'=>$token,'password'=>'NextPassword2026!','password_confirmation'=>'NextPassword2026!'])->assertSessionHasErrors('email');
 }
 public function test_kaprodi_scope_and_finance_role_boundaries(): void {
  $this->actingAs($this->user('kaprodi'))->get('/laporan')->assertOk()->assertSee('Aditya Nugraha');
  $this->get('/master/users')->assertForbidden();$this->get('/pembayaran')->assertForbidden();
  $this->actingAs($this->user('keuangan'))->get('/laporan')->assertForbidden();
 }
 public function test_all_primary_role_pages_render(): void {
  foreach(['mahasiswa'=>['/dashboard','/biodata','/planner','/krs','/nilai','/jadwal','/pembayaran','/skripsi'],'dosen'=>['/dashboard','/mengajar','/laporan','/skripsi'],'kaprodi'=>['/dashboard','/laporan'],'keuangan'=>['/dashboard','/pembayaran'],'admin'=>['/dashboard','/master/users','/master/mahasiswa/create','/master/mata-kuliah','/master/kurikulum','/skripsi']] as $role=>$paths){foreach($paths as $path)$this->actingAs($this->user($role))->get($path)->assertOk();}
 }
}
