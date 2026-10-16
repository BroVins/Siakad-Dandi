<?php

namespace Tests\Feature;

use App\Models\{Kelas, Krs, Mahasiswa, ProgramStudi, SemesterAkademik, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AcademicFixtures;
use Tests\TestCase;

class SecurityAndMasterTest extends TestCase
{
    use RefreshDatabase, AcademicFixtures;

    public function test_login_accepts_case_insensitive_email(): void
    {
        $this->post('/login', ['email'=>'MAHASISWA@SIAKAD.TEST','password'=>config('academic.demo_password')])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->account());
    }

    public function test_password_change_requires_current_password_and_confirmation(): void
    {
        $this->actingAs($this->account())->patch('/biodata/password', ['current_password'=>'incorrect','password'=>'NewAccountPass2026!','password_confirmation'=>'NewAccountPass2026!'])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check(config('academic.demo_password'), $this->account()->password));
    }

    public function test_own_password_change_revokes_tokens_and_allows_new_login(): void
    {
        $u=$this->account(); $u->createToken('old-token');
        $this->actingAs($u)->patch('/biodata/password', ['current_password'=>config('academic.demo_password'),'password'=>'NewAccountPass2026!','password_confirmation'=>'NewAccountPass2026!'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewAccountPass2026!', $u->fresh()->password));
        $this->assertSame(0, $u->tokens()->count());
        $this->get('/biodata')->assertOk();
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login',['email'=>$u->email,'password'=>'NewAccountPass2026!'])->assertRedirect('/dashboard');
    }

    public function test_bcrypt_multibyte_password_over_72_bytes_is_rejected_cleanly(): void
    {
        $password=str_repeat('界', 25);
        $this->actingAs($this->account('admin'))->post('/master/users', ['name'=>'Too long password','email'=>'long@test.test','role'=>'mahasiswa','password'=>$password])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users',['email'=>'long@test.test']);
    }

    public function test_reset_rejects_non_string_password_and_oversized_token(): void
    {
        $this->post('/reset-password', ['email'=>$this->account()->email,'token'=>str_repeat('x',1000),'password'=>['secret'],'password_confirmation'=>['secret']])->assertSessionHasErrors(['token','password']);
    }

    public function test_private_pages_disable_shared_caching_and_framing(): void
    {
        $this->actingAs($this->account())->get('/dashboard')->assertOk()->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('X-Frame-Options','DENY');
        $this->assertStringContainsString('no-store', $this->get('/biodata')->headers->get('Cache-Control'));
    }

    public function test_boolean_false_is_selected_when_editing_inactive_term(): void
    {
        $t=SemesterAkademik::where('aktif',false)->firstOrFail();
        $html=$this->actingAs($this->account('admin'))->get('/master/semester/'.$t->id.'/edit')->assertOk()->getContent();
        preg_match('/<select[^>]*name="aktif"[^>]*>(.*?)<\/select>/s', $html, $m);
        $this->assertMatchesRegularExpression('/<option[^>]*value="0"[^>]*selected/', $m[1] ?? '');
    }

    public function test_academic_year_must_be_consecutive(): void
    {
        $this->actingAs($this->account('admin'))->post('/master/semester', ['nama'=>'Tahun tidak valid','tahun_akademik'=>'2026/2030','periode'=>'ganjil','krs_mulai'=>'2026-08-01','krs_selesai'=>'2026-09-01','aktif'=>0])->assertSessionHasErrors('data');
        $this->assertDatabaseMissing('semester_akademik',['nama'=>'Tahun tidak valid']);
    }

    public function test_program_limit_cannot_be_reduced_below_existing_student_semester(): void
    {
        $p=ProgramStudi::firstOrFail();
        $data=$p->only('nama_prodi','fakultas_id','jenjang','sks_lulus','batas_semester'); $data['batas_semester']=4;
        $this->actingAs($this->account('admin'))->put('/master/prodi/'.$p->id, $data)->assertSessionHasErrors('data');
        $this->assertSame(14, $p->fresh()->batas_semester);
    }

    public function test_student_semester_is_frozen_while_current_krs_is_submitted(): void
    {
        $this->submitKrs(); $m=$this->student();
        $data=$m->only('user_id','nim','nama','prodi_id','angkatan','semester','dosen_pa_id','status'); $data['semester']=6;
        $this->actingAs($this->account('admin'))->put('/master/mahasiswa/'.$m->id, $data)->assertSessionHasErrors('data');
        $this->assertSame(5, $m->fresh()->semester);
    }

    public function test_capacity_can_increase_without_changing_an_enrolled_class(): void
    {
        $c=Kelas::whereHas('peserta')->firstOrFail();
        $data=$c->only('semester_akademik_id','mata_kuliah_id','dosen_id','nama','hari','mulai','selesai','ruang','kapasitas');
        $data['kapasitas']=45; $data['mulai']=substr($data['mulai'],0,5); $data['selesai']=substr($data['selesai'],0,5);
        $this->actingAs($this->account('admin'))->put('/master/kelas/'.$c->id,$data)->assertSessionHasNoErrors();
        $this->assertSame(45,$c->fresh()->kapasitas);
    }

    public function test_enrolled_class_schedule_cannot_be_changed(): void
    {
        $c=Kelas::whereHas('peserta')->firstOrFail();
        $data=$c->only('semester_akademik_id','mata_kuliah_id','dosen_id','nama','hari','mulai','selesai','ruang','kapasitas');
        $data['mulai']='17:00';$data['selesai']='19:00';
        $this->actingAs($this->account('admin'))->put('/master/kelas/'.$c->id,$data)->assertSessionHasErrors('data');
    }

    public function test_empty_array_search_is_rejected_not_server_error(): void
    {
        $this->actingAs($this->account('admin'))->get('/master/users?q[]=x')->assertSessionHasErrors('q');
    }

    public function test_all_master_indexes_create_edit_and_detail_pages_render(): void
    {
        foreach (\App\Support\MasterRegistry::all() as $key=>$spec) {
            $this->actingAs($this->account('admin'))->get('/master/'.$key)->assertOk();
            $this->get('/master/'.$key.'/create')->assertOk();
            if ($record=$spec['model']::first()) {
                $this->get('/master/'.$key.'/'.$record->id.'/edit')->assertOk();
                $this->get('/master/'.$key.'/'.$record->id)->assertOk();
            }
        }
    }

    public function test_api_requires_authentication_and_never_exposes_password(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->actingAs($this->account())->getJson('/api/me')->assertOk()->assertJsonMissingPath('password')->assertJsonPath('role','mahasiswa');
        $this->getJson('/api/academic-summary')->assertOk()->assertJsonPath('sks',72);
        $this->actingAs($this->account('keuangan'))->getJson('/api/academic-summary')->assertForbidden();
    }
    public function test_admin_password_reset_revokes_existing_api_tokens(): void
    {
        $user=$this->account();$user->createToken('old-device');
        $data=$user->only('name','email','role','prodi_id');$data['password']='AdminChangedPass2026!';
        $this->actingAs($this->account('admin'))->put('/master/users/'.$user->id,$data)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('AdminChangedPass2026!',$user->fresh()->password));
        $this->assertSame(0,$user->tokens()->count());
    }
}
