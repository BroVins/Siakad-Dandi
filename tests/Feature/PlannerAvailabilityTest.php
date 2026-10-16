<?php

namespace Tests\Feature;

use App\Models\{Kelas,Krs,KrsDetail,Roadmap};
use App\Services\PlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AcademicFixtures;
use Tests\TestCase;

class PlannerAvailabilityTest extends TestCase
{
    use RefreshDatabase, AcademicFixtures;

    public function test_roadmap_does_not_recommend_a_class_reserved_by_another_student(): void
    {
        $class=Kelas::findOrFail($this->currentClasses()[0]);$class->update(['kapasitas'=>1]);
        $krs=Krs::create(['mahasiswa_id'=>$this->account('nadia')->mahasiswa->id,'semester_akademik_id'=>$this->term()->id,'semester'=>1,'tahun_akademik'=>$this->term()->tahun_akademik,'status'=>'submitted']);
        $krs->details()->create(['kelas_id'=>$class->id,'mata_kuliah_id'=>$class->mata_kuliah_id]);
        $r=app(PlannerService::class)->generate($this->student(),8);
        $this->assertFalse($r->details()->where('semester_target',5)->where('mata_kuliah_id',$class->mata_kuliah_id)->exists());
    }

    public function test_roadmap_persists_recommended_class_and_shows_it_to_student(): void
    {
        $r=app(PlannerService::class)->generate($this->student(),8);
        foreach($r->details()->where('semester_target',5)->get() as $d){
            $this->assertNotNull($d->kelas_id);
            $this->assertSame($d->mata_kuliah_id,$d->kelas->mata_kuliah_id);
        }
        $this->actingAs($this->account())->get('/planner')->assertOk()->assertSee('Kelas rekomendasi');
    }

    public function test_class_becoming_full_before_roadmap_submission_requires_new_plan(): void
    {
        $r=app(PlannerService::class)->generate($this->student(),8);
        $class=Kelas::findOrFail($this->currentClasses()[0]);$class->update(['kapasitas'=>1]);
        $k=Krs::create(['mahasiswa_id'=>$this->account('nadia')->mahasiswa->id,'semester_akademik_id'=>$this->term()->id,'semester'=>1,'tahun_akademik'=>$this->term()->tahun_akademik,'status'=>'submitted']);
        $k->details()->create(['kelas_id'=>$class->id,'mata_kuliah_id'=>$class->mata_kuliah_id]);
        $this->actingAs($this->account())->post('/planner/'.$r->id.'/submit')->assertSessionHasErrors('roadmap');
        $this->assertSame('draft',$r->fresh()->status);
    }
}
