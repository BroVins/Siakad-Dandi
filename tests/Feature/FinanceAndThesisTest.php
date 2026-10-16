<?php

namespace Tests\Feature;

use App\Models\{Bimbingan, Dosen, Pembayaran, Skripsi, Tagihan, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AcademicFixtures;
use Tests\TestCase;

class FinanceAndThesisTest extends TestCase
{
    use RefreshDatabase, AcademicFixtures;

    private function billData(): array
    {
        return ['mahasiswa_id'=>$this->student()->id, 'semester_akademik_id'=>$this->term()->id, 'keterangan'=>'Tagihan uji', 'jumlah'=>'100.50', 'jatuh_tempo'=>today()->addDay()->toDateString()];
    }

    public function test_money_rejects_excess_precision_and_exponential_notation(): void
    {
        $t=Tagihan::firstOrFail();
        foreach (['100.999','1e3'] as $amount) {
            $this->actingAs($this->account('keuangan'))->post('/tagihan/'.$t->id.'/bayar', ['jumlah'=>$amount,'tanggal'=>today()->toDateString(),'referensi'=>'BAD-'.md5($amount)])->assertSessionHasErrors('jumlah');
        }
        $this->assertDatabaseCount('pembayaran',0);
    }

    public function test_fractional_remaining_balance_can_be_paid_exactly(): void
    {
        $t=Tagihan::create($this->billData());
        $this->actingAs($this->account('keuangan'))->post('/tagihan/'.$t->id.'/bayar', ['jumlah'=>'100.00','tanggal'=>today()->toDateString(),'referensi'=>'PART-1'])->assertSessionHasNoErrors();
        $this->post('/tagihan/'.$t->id.'/bayar', ['jumlah'=>'0.50','tanggal'=>today()->toDateString(),'referensi'=>'PART-2'])->assertSessionHasNoErrors();
        $this->assertSame('100.50', number_format((float)$t->pembayaran()->sum('jumlah'),2,'.',''));
        $this->get('/pembayaran')->assertOk()->assertSee('100,50');
    }

    public function test_bill_form_token_prevents_accidental_double_creation(): void
    {
        $data=$this->billData()+['request_key'=>(string)Str::uuid()];
        $this->actingAs($this->account('keuangan'))->post('/tagihan',$data)->assertSessionHasNoErrors();
        $this->post('/tagihan',$data)->assertSessionHasNoErrors();
        $this->assertSame(1,Tagihan::where('keterangan','Tagihan uji')->count());
    }

    public function test_reused_bill_token_with_different_data_is_rejected(): void
    {
        $data=$this->billData()+['request_key'=>(string)Str::uuid()];
        $this->actingAs($this->account('keuangan'))->post('/tagihan',$data)->assertSessionHasNoErrors();
        $data['jumlah']='999.00';
        $this->post('/tagihan',$data)->assertSessionHasErrors('request_key');
        $this->assertSame(1,Tagihan::where('keterangan','Tagihan uji')->count());
    }

    public function test_duplicate_payment_reference_does_not_create_second_payment(): void
    {
        $t=Tagihan::firstOrFail(); $data=['jumlah'=>'25.25','tanggal'=>today()->toDateString(),'referensi'=>'ONCE-ONLY'];
        $this->actingAs($this->account('keuangan'))->post('/tagihan/'.$t->id.'/bayar',$data)->assertSessionHasNoErrors();
        $this->post('/tagihan/'.$t->id.'/bayar',$data)->assertSessionHasErrors('referensi');
        $this->assertDatabaseCount('pembayaran',1);
    }

    public function test_student_cannot_record_payment_or_read_other_students_receipt(): void
    {
        $t=Tagihan::firstOrFail();
        $this->actingAs($this->account('keuangan'))->post('/tagihan/'.$t->id.'/bayar',['jumlah'=>25,'tanggal'=>today()->toDateString(),'referensi'=>'PRIVATE'])->assertSessionHasNoErrors();
        $p=Pembayaran::firstOrFail();
        $this->actingAs($this->account('nadia'))->get('/pembayaran/'.$p->id.'/kwitansi')->assertForbidden();
        $this->post('/tagihan/'.$t->id.'/bayar',['jumlah'=>25,'tanggal'=>today()->toDateString(),'referensi'=>'ILLEGAL'])->assertForbidden();
        $this->assertDatabaseCount('pembayaran',1);
    }

    public function test_thesis_completion_requires_full_progress(): void
    {
        $s=Skripsi::firstOrFail();
        $this->actingAs($this->account('dosen'))->patch('/skripsi/'.$s->id,['judul'=>$s->judul,'status'=>'selesai','progres'=>10])->assertSessionHasErrors('progres');
        $this->assertSame('pengajuan',$s->fresh()->status);
    }

    public function test_thesis_full_progress_requires_completed_status(): void
    {
        $s=Skripsi::firstOrFail();
        $this->actingAs($this->account('dosen'))->patch('/skripsi/'.$s->id,['judul'=>$s->judul,'status'=>'penelitian','progres'=>100])->assertSessionHasErrors('progres');
    }

    public function test_admin_can_reassign_thesis_supervisor(): void
    {
        $u=User::create(['name'=>'New lecturer','email'=>'new-lecturer@test.test','role'=>'dosen','password'=>'SafePassword2026!']);
        $d=Dosen::create(['user_id'=>$u->id,'nidn'=>'NIDN-NEW','nama'=>$u->name]);
        $s=Skripsi::firstOrFail();
        $this->actingAs($this->account('admin'))->patch('/skripsi/'.$s->id,['judul'=>$s->judul,'status'=>'proposal','progres'=>20,'dosen_id'=>$d->id])->assertSessionHasNoErrors();
        $this->assertSame($d->id,$s->fresh()->dosen_id);
        $this->actingAs($this->account('dosen'))->patch('/skripsi/'.$s->id,['judul'=>$s->judul,'status'=>'sidang','progres'=>90])->assertForbidden();
        $this->actingAs($u)->get('/skripsi')->assertOk()->assertSee($s->judul);
    }

    public function test_lecturer_cannot_reassign_supervisor_through_extra_field(): void
    {
        $s=Skripsi::firstOrFail();
        $this->actingAs($this->account('dosen'))->patch('/skripsi/'.$s->id,['judul'=>$s->judul,'status'=>'proposal','progres'=>20,'dosen_id'=>$s->dosen_id])->assertSessionHasErrors('dosen_id');
    }

    public function test_guidance_note_and_feedback_are_audited(): void
    {
        $s=Skripsi::firstOrFail();
        $this->actingAs($this->account())->post('/skripsi/'.$s->id.'/bimbingan',['tanggal'=>today()->toDateString(),'catatan'=>'Perbaikan metode penelitian.'])->assertSessionHasNoErrors();
        $b=Bimbingan::firstOrFail();
        $this->assertDatabaseHas('audit_logs',['action'=>'thesis.note.created','subject_id'=>$b->id]);
        $this->actingAs($this->account('dosen'))->patch('/skripsi/'.$s->id.'/bimbingan/'.$b->id,['umpan_balik'=>'Metode sudah sesuai.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs',['action'=>'thesis.feedback.saved','subject_id'=>$b->id]);
    }

    public function test_guidance_rejects_cross_thesis_feedback_and_unrelated_students(): void
    {
        $s=Skripsi::firstOrFail();
        $other=Skripsi::create(['mahasiswa_id'=>$this->account('nadia')->mahasiswa->id,'dosen_id'=>$s->dosen_id,'judul'=>'Topik mahasiswa lain']);
        $b=$other->bimbingan()->create(['user_id'=>$this->account('nadia')->id,'tanggal'=>today()->toDateString(),'catatan'=>'Rahasia']);
        $this->actingAs($this->account('dosen'))->patch('/skripsi/'.$s->id.'/bimbingan/'.$b->id,['umpan_balik'=>'Tidak berhak'])->assertForbidden();
        $this->actingAs($this->account())->post('/skripsi/'.$other->id.'/bimbingan',['tanggal'=>today()->toDateString(),'catatan'=>'Tidak berhak'])->assertForbidden();
        $this->assertNull($b->fresh()->umpan_balik);
    }
}
