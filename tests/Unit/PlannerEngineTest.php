<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Services\PlannerEngine;
class PlannerEngineTest extends TestCase {
 public function test_same_semester_prerequisite_is_not_allowed(): void {
  $c=[['id'=>1,'kode'=>'A','sks'=>3,'semester'=>1,'required'=>true,'prerequisites'=>[]],['id'=>2,'kode'=>'B','sks'=>3,'semester'=>1,'required'=>true,'prerequisites'=>[1]]];
  $r=(new PlannerEngine)->plan($c,[],0,6,1,3,24,20,'ganjil',[1,2]);$this->assertSame(1,$r['details'][0]['semester_target']);$this->assertSame(3,$r['details'][1]['semester_target']);$this->assertTrue($r['feasible']);
 }
 public function test_cycle_is_reported_without_recursion_overflow(): void {
  $c=[['id'=>1,'kode'=>'A','sks'=>3,'semester'=>1,'required'=>true,'prerequisites'=>[2]],['id'=>2,'kode'=>'B','sks'=>3,'semester'=>1,'required'=>true,'prerequisites'=>[1]]];$r=(new PlannerEngine)->plan($c,[],0,6,1,4,24,20,'ganjil',[1,2]);$this->assertFalse($r['feasible']);$this->assertEmpty($r['details']);$this->assertNotEmpty($r['warnings']);
 }
}
