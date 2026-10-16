<?php

namespace Tests\Unit;

use App\Services\PlannerEngine;
use App\Services\Timetable;
use PHPUnit\Framework\TestCase;

class TimetablePlannerTest extends TestCase
{
    private function course(int $id, bool $required=true, array $prerequisites=[]): array
    {
        return ['id'=>$id,'kode'=>'MK'.$id,'sks'=>3,'semester'=>1,'required'=>$required,'prerequisites'=>$prerequisites];
    }
    private function slot(int $id, string $start, string $end): array
    {
        return ['id'=>$id,'hari'=>'Senin','mulai'=>$start,'selesai'=>$end];
    }
    public function test_conflicting_courses_are_not_recommended_in_the_same_current_term(): void
    {
        $options=[1=>[$this->slot(11,'08:00','10:00')],2=>[$this->slot(21,'09:00','11:00')]];
        $r=(new PlannerEngine)->plan([$this->course(1),$this->course(2)],[],0,6,1,3,24,20,'ganjil',[1,2],null,$options,[]);
        $this->assertSame([1,3],array_column($r['details'],'semester_target'));
        $this->assertSame(11,$r['details'][0]['kelas_id']);
    }
    public function test_solver_reconsiders_earlier_class_to_find_a_valid_combination(): void
    {
        $options=[1=>[$this->slot(11,'08:00','10:00'),$this->slot(12,'12:00','14:00')],2=>[$this->slot(21,'09:00','11:00')]];
        $r=(new PlannerEngine)->plan([$this->course(1),$this->course(2)],[],0,6,1,1,24,20,'ganjil',[1,2],null,$options,[]);
        $this->assertTrue($r['feasible']);
        $this->assertSame([12,21],array_column($r['details'],'kelas_id'));
    }
    public function test_adjacent_class_times_do_not_overlap(): void
    {
        $this->assertSame([1=>11,2=>21],Timetable::choose([1,2],[1=>[$this->slot(11,'08:00','10:00')],2=>[$this->slot(21,'10:00','12:00')]]));
    }
    public function test_unavailable_class_combination_returns_null(): void
    {
        $this->assertNull(Timetable::choose([1,2],[1=>[$this->slot(11,'08:00','10:00')],2=>[]]));
    }
    public function test_current_term_grade_does_not_satisfy_current_prerequisite(): void
    {
        $r=(new PlannerEngine)->plan([$this->course(1),$this->course(2,true,[1])],[1],3,6,1,3,24,20,'ganjil',[2],null,null,[]);
        $this->assertSame(3,$r['details'][0]['semester_target']);
    }
    public function test_registered_electives_are_preserved_after_credit_target_is_met(): void
    {
        $r=(new PlannerEngine)->plan([$this->course(1,false),$this->course(2,false)],[],0,3,1,1,24,20,'ganjil',[1,2],[1,2]);
        $this->assertSame([1,2],array_column($r['details'],'mata_kuliah_id'));
        $this->assertSame(6,$r['projected_credits']);
    }
}
