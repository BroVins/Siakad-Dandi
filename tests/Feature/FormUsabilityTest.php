<?php

namespace Tests\Feature;

use App\Models\{Skripsi,Tagihan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AcademicFixtures;
use Tests\TestCase;

class FormUsabilityTest extends TestCase
{
    use RefreshDatabase, AcademicFixtures;

    public function test_invalid_email_array_returns_a_renderable_login_form(): void
    {
        $this->from('/login')->post('/login',['email'=>['invalid'],'password'=>'example'])->assertSessionHasErrors('email');
        $this->get('/login')->assertOk()->assertSee('email');
    }
    public function test_invalid_krs_scalar_selection_returns_a_renderable_form(): void
    {
        $this->actingAs($this->account())->from('/krs')->post('/krs',['kelas_ids'=>'not-an-array','action'=>'draft'])->assertSessionHasErrors('kelas_ids');
        $this->get('/krs')->assertOk()->assertSee('Data belum dapat disimpan.');
    }
    public function test_invalid_master_scalar_array_does_not_break_error_redisplay(): void
    {
        $this->actingAs($this->account('admin'))->from('/master/fakultas/create')->post('/master/fakultas',['nama'=>['not-a-name']])->assertSessionHasErrors('nama');
        $this->get('/master/fakultas/create')->assertOk()->assertSee('Data belum dapat disimpan.');
    }
    public function test_invalid_payment_reference_array_does_not_break_error_redisplay(): void
    {
        $t=Tagihan::firstOrFail();
        $this->actingAs($this->account('keuangan'))->from('/pembayaran')->post('/tagihan/'.$t->id.'/bayar',['form_scope'=>'pay-'.$t->id,'jumlah'=>'20.00','tanggal'=>today()->toDateString(),'referensi'=>['invalid']])->assertSessionHasErrors('referensi');
        $this->get('/pembayaran')->assertOk()->assertSee('Data belum dapat disimpan.');
    }
    public function test_thesis_validation_preserves_the_correct_forms_user_input(): void
    {
        $s=Skripsi::firstOrFail();
        $this->actingAs($this->account('dosen'))->from('/skripsi')->patch('/skripsi/'.$s->id,['form_scope'=>'thesis-progress-'.$s->id,'judul'=>'Judul revisi belum tersimpan','status'=>'selesai','progres'=>40])->assertSessionHasErrors('progres');
        $this->get('/skripsi')->assertOk()->assertSee('value="Judul revisi belum tersimpan"',false);
        $this->assertNotSame('Judul revisi belum tersimpan',$s->fresh()->judul);
    }
    public function test_stale_approved_roadmap_is_explained_before_student_submits_krs(): void
    {
        $r=$this->approveRoadmap();$r->update(['context_hash'=>str_repeat('0',64)]);
        $this->actingAs($this->account())->get('/planner')->assertOk()->assertSee('Data rencana berubah');
        $this->get('/krs')->assertOk()->assertSee('Data rencana berubah');
    }
}
