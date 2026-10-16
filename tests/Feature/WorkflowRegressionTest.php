<?php

namespace Tests\Feature;

use App\Models\{Dosen, Kelas, Krs, Kurikulum, Mahasiswa, Nilai, Roadmap, SemesterAkademik, User};
use App\Services\{AcademicService, PlannerService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AcademicFixtures;
use Tests\TestCase;

class WorkflowRegressionTest extends TestCase
{
    use RefreshDatabase, AcademicFixtures;

    public function test_schedule_renders_for_all_authorized_roles(): void
    {
        foreach (['mahasiswa','admin','dosen','kaprodi'] as $role) {
            $this->actingAs($this->account($role))->get('/jadwal')->assertOk()->assertSee('Jadwal Kuliah');
        }
    }

    public function test_no_active_term_shows_actionable_empty_state_instead_of_404(): void
    {
        SemesterAkademik::query()->update(['aktif'=>false]);
        foreach (['/planner','/krs','/jadwal'] as $url) {
            $this->actingAs($this->account())->get($url)->assertOk()->assertSee('Semester akademik belum siap');
        }
    }

    public function test_old_pending_roadmap_does_not_block_new_active_term(): void
    {
        $old = app(PlannerService::class)->generate($this->student(), 8);
        $old->update(['status'=>'submitted','semester_akademik_id'=>SemesterAkademik::where('aktif',false)->first()->id]);
        $this->actingAs($this->account())->post('/planner', ['target_lulus'=>8])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('roadmap', 2);
    }

    public function test_inactive_student_cannot_submit_roadmap(): void
    {
        $r = app(PlannerService::class)->generate($this->student(), 8);
        $this->student()->update(['status'=>'cuti']);
        $this->actingAs($this->account())->post('/planner/'.$r->id.'/submit')->assertSessionHasErrors('roadmap');
        $this->assertSame('draft', $r->fresh()->status);
    }

    public function test_student_semester_change_invalidates_roadmap_snapshot(): void
    {
        $r = app(PlannerService::class)->generate($this->student(), 8);
        $this->student()->update(['semester'=>6]);
        $this->actingAs($this->account())->post('/planner/'.$r->id.'/submit')->assertSessionHasErrors('roadmap');
        $this->assertSame('draft', $r->fresh()->status);
    }

    public function test_curriculum_change_prevents_stale_roadmap_approval(): void
    {
        $r = app(PlannerService::class)->generate($this->student(), 8);
        $this->actingAs($this->account())->post('/planner/'.$r->id.'/submit')->assertSessionHasNoErrors();
        Kurikulum::first()->update(['semester'=>2]);
        $this->actingAs($this->account('dosen'))->post('/roadmap/'.$r->id.'/review', ['status'=>'approved'])->assertSessionHasErrors('roadmap');
        $this->assertSame('submitted', $r->fresh()->status);
    }

    public function test_student_can_withdraw_pending_roadmap_and_replan(): void
    {
        $r = app(PlannerService::class)->generate($this->student(), 8);
        $this->actingAs($this->account())->post('/planner/'.$r->id.'/submit')->assertSessionHasNoErrors();
        $this->post('/planner/'.$r->id.'/withdraw')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft', $r->fresh()->status);
        $this->post('/planner', ['target_lulus'=>8])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('roadmap', 2);
    }

    public function test_student_cannot_withdraw_other_students_roadmap(): void
    {
        $r = app(PlannerService::class)->generate($this->account('nadia')->mahasiswa, 14);
        $r->update(['status'=>'submitted']);
        $this->actingAs($this->account())->post('/planner/'.$r->id.'/withdraw')->assertForbidden();
        $this->assertSame('submitted', $r->fresh()->status);
    }

    public function test_submitted_krs_can_be_withdrawn_and_edited(): void
    {
        $krs = $this->submitKrs();
        $this->post('/krs/'.$krs->id.'/withdraw')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft', $krs->fresh()->status);
        $this->post('/krs', ['kelas_ids'=>[$this->currentClasses()[0]],'action'=>'draft'])->assertSessionHasNoErrors();
        $this->assertSame(1, $krs->details()->count());
    }

    public function test_final_krs_cannot_be_withdrawn(): void
    {
        $krs = $this->submitKrs();
        $this->actingAs($this->account('dosen'))->post('/krs/'.$krs->id.'/review', ['status'=>'approved'])->assertSessionHasNoErrors();
        $this->actingAs($this->account())->post('/krs/'.$krs->id.'/withdraw')->assertSessionHasErrors('krs');
        $this->assertSame('approved', $krs->fresh()->status);
    }

    public function test_adviser_can_review_submitted_krs_after_student_entry_window_closes(): void
    {
        $krs = $this->submitKrs();
        $this->term()->update(['krs_selesai'=>today()->subDay()]);
        $this->actingAs($this->account('dosen'))->post('/krs/'.$krs->id.'/review', ['status'=>'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $krs->fresh()->status);
    }

    public function test_krs_keeps_exact_approved_roadmap_reference(): void
    {
        $krs = $this->submitKrs();
        $this->assertNotNull($krs->roadmap_id);
        $this->assertSame(Roadmap::latest('id')->first()->id, $krs->roadmap_id);
    }

    public function test_same_term_grade_cannot_satisfy_a_krs_prerequisite(): void
    {
        $m = $this->account('nadia')->mahasiswa;
        $c = Kelas::findOrFail($this->currentClasses()[0]);
        foreach ($c->mataKuliah->prasyarat as $p) {
            Nilai::create(['mahasiswa_id'=>$m->id,'mata_kuliah_id'=>$p->id,'semester_akademik_id'=>$this->term()->id,'kelas_id'=>$c->id,'nilai'=>90,'grade'=>'A','bobot'=>4,'published'=>true]);
        }
        $this->actingAs($this->account('nadia'))->post('/krs', ['kelas_ids'=>[$c->id],'action'=>'draft'])->assertSessionHasErrors('krs');
        $this->assertDatabaseMissing('krs',['mahasiswa_id'=>$m->id]);
    }

    public function test_grade_save_without_publication_change_preserves_published_grade(): void
    {
        $grade = Nilai::where('published',true)->firstOrFail();
        $this->actingAs($this->account('dosen'))->post('/mengajar/'.$grade->kelas_id.'/nilai', ['nilai'=>[$grade->mahasiswa_id=>84]])->assertSessionHasNoErrors();
        $this->assertTrue($grade->fresh()->published);
        $this->assertSame('A-', $grade->fresh()->grade);
    }

    public function test_explicit_grade_publication_actions_work(): void
    {
        $grade = Nilai::where('published',true)->firstOrFail();
        $this->actingAs($this->account('dosen'))->post('/mengajar/'.$grade->kelas_id.'/nilai', ['nilai'=>[$grade->mahasiswa_id=>82],'publication'=>'draft'])->assertSessionHasNoErrors();
        $this->assertFalse($grade->fresh()->published);
        $this->post('/mengajar/'.$grade->kelas_id.'/nilai', ['nilai'=>[$grade->mahasiswa_id=>82],'publication'=>'publish'])->assertSessionHasNoErrors();
        $this->assertTrue($grade->fresh()->published);
    }

    public function test_grade_precision_is_validated_before_grade_mapping(): void
    {
        $grade = Nilai::firstOrFail(); $old = $grade->nilai;
        $this->actingAs($this->account('dosen'))->post('/mengajar/'.$grade->kelas_id.'/nilai', ['nilai'=>[$grade->mahasiswa_id=>'84.999']])->assertSessionHasErrors('nilai.'.$grade->mahasiswa_id);
        $this->assertSame($old, $grade->fresh()->nilai);
    }

    public function test_forged_grade_array_key_is_rejected_before_database_write(): void
    {
        $grade = Nilai::firstOrFail();
        $this->actingAs($this->account('dosen'))->post('/mengajar/'.$grade->kelas_id.'/nilai', ['nilai'=>[$grade->mahasiswa_id.'oops'=>90]])->assertForbidden();
    }

    public function test_attendance_form_loads_existing_meeting(): void
    {
        $grade = Nilai::firstOrFail();
        $this->actingAs($this->account('dosen'))->post('/mengajar/'.$grade->kelas_id.'/presensi', ['pertemuan'=>3,'tanggal'=>today()->subDay()->toDateString(),'status'=>[$grade->mahasiswa_id=>'izin']])->assertSessionHasNoErrors();
        $this->get('/mengajar/'.$grade->kelas_id.'?pertemuan=3')->assertOk()->assertViewHas('meeting',3)->assertViewHas('selectedAttendance', fn ($rows)=>$rows[$grade->mahasiswa_id]->status==='izin');
    }

    public function test_unauthorized_teacher_cannot_write_grades_or_attendance(): void
    {
        $u=User::create(['name'=>'Other lecturer','email'=>'other-teacher@test.test','role'=>'dosen','password'=>'LongPassword123!']);
        Dosen::create(['user_id'=>$u->id,'nidn'=>'OTHER','nama'=>'Other lecturer']);
        $grade=Nilai::firstOrFail();
        $this->actingAs($u)->post('/mengajar/'.$grade->kelas_id.'/nilai', ['nilai'=>[$grade->mahasiswa_id=>90]])->assertForbidden();
        $this->post('/mengajar/'.$grade->kelas_id.'/presensi',['pertemuan'=>1,'tanggal'=>today()->toDateString(),'status'=>[$grade->mahasiswa_id=>'hadir']])->assertForbidden();
    }
}
