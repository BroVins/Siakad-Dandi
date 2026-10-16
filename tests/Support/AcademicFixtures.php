<?php

namespace Tests\Support;

use App\Models\{User, Mahasiswa, Kelas, Krs, Roadmap, SemesterAkademik};
use App\Services\{AcademicService, PlannerService};
use Database\Seeders\DemoSeeder;

trait AcademicFixtures
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    protected function account(string $role = 'mahasiswa'): User
    {
        return User::where('email', $role.'@siakad.test')->firstOrFail();
    }

    protected function student(): Mahasiswa { return $this->account()->mahasiswa; }
    protected function term(): SemesterAkademik { return app(AcademicService::class)->activeTerm(); }
    protected function currentClasses(): array
    {
        return Kelas::where('semester_akademik_id', $this->term()->id)
            ->whereHas('mataKuliah', fn ($q) => $q->where('semester', 5))->pluck('id')->all();
    }

    protected function approveRoadmap(): Roadmap
    {
        $roadmap = app(PlannerService::class)->generate($this->student(), 8);
        $this->actingAs($this->account())->post('/planner/'.$roadmap->id.'/submit')->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->account('dosen'))->post('/roadmap/'.$roadmap->id.'/review', ['status'=>'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $roadmap->fresh()->status);
        return $roadmap->fresh();
    }

    protected function submitKrs(): Krs
    {
        $this->approveRoadmap();
        $this->actingAs($this->account())->post('/krs', ['kelas_ids'=>$this->currentClasses(), 'action'=>'submit'])->assertRedirect()->assertSessionHasNoErrors();
        return Krs::where('mahasiswa_id', $this->student()->id)->where('semester_akademik_id', $this->term()->id)->firstOrFail();
    }
}
