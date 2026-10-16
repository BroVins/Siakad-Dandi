<?php
// Standalone tests: php scripts/test-planner.php (does not require vendor/).
require __DIR__.'/../app/Services/PlannerEngine.php';
use App\Services\PlannerEngine;
$engine=new PlannerEngine;$count=0;
$assert=function(bool $condition,string $message)use(&$count){if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";$count++;};
$c=fn($id,$semester=1,$req=true,$pre=[],$sks=3)=>['id'=>$id,'kode'=>'MK'.$id,'semester'=>$semester,'required'=>$req,'prerequisites'=>$pre,'sks'=>$sks];
$r=$engine->plan([$c(1),$c(2,1,true,[1])],[],0,6,1,3,24,20,'ganjil',[1,2]);
$assert(array_column($r['details'],'semester_target')===[1,3],'Prasyarat harus lulus pada semester sebelumnya');
$assert($r['feasible']===true,'Target yang memenuhi prasyarat dinilai feasible');
$r=$engine->plan([$c(1),$c(2,2)],[],0,6,1,2,24,20,'ganjil',[1]);
$assert(array_column($r['details'],'semester_target')===[1,2],'Paritas semester ganjil/genap dipertahankan');
$r=$engine->plan([$c(1),$c(2)],[],0,6,1,1,3,20,'ganjil',[1,2]);
$assert(count($r['details'])===1 && !$r['feasible'],'Batas SKS dan target tidak realistis terdeteksi');
$r=$engine->plan([$c(1),$c(2)],[],0,6,1,1,24,20,'ganjil',[1]);
$assert(count($r['details'])===1,'Semester aktif hanya memakai mata kuliah yang ditawarkan');
$r=$engine->plan([$c(1),$c(2)], [1],3,6,1,1,24,20,'ganjil',[1,2]);
$assert(array_column($r['details'],'mata_kuliah_id')===[2],'Mata kuliah lulus tidak direkomendasikan ulang');
$r=$engine->plan([$c(1,1,true,[2]),$c(2,1,true,[1])],[],0,6,1,4,24,20,'ganjil',[1,2]);
$assert(count($r['warnings'])>=2 && !$r['details'],'Siklus prasyarat tidak menyebabkan loop tak berujung');
$r=$engine->plan([$c(1,1,true,[99])],[],0,3,1,3,24,20,'ganjil',[1]);
$assert(!$r['feasible'] && count($r['warnings'])>=2,'Prasyarat di luar kurikulum dilaporkan');
$r=$engine->plan([$c(1,1,false),$c(2)],[],0,3,1,1,3,20,'ganjil',[1,2]);
$assert($r['details'][0]['mata_kuliah_id']===2,'Wajib didahulukan daripada pilihan');
$r=$engine->plan([$c(1,1,false),$c(2,1,false)],[],0,3,1,1,24,20,'ganjil',[1,2]);
$assert(count($r['details'])===1 && $r['feasible'],'Pilihan berhenti ketika kebutuhan SKS terpenuhi');
$r=$engine->plan([$c(1),$c(2)],[],0,6,1,3,0,3,'ganjil',[]);
$assert(count($r['details'])===1,'Batas konservatif semester mendatang diterapkan');
$r=$engine->plan([$c(1)], [1],3,3,1,3,24,20,'ganjil',[1]);
$assert($r['feasible'] && !$r['details'],'Mahasiswa yang memenuhi semua mata kuliah tidak diberi duplikasi');
$r=$engine->plan([$c(1,2),$c(2,1)],[],0,6,5,6,24,20,'genap',[1]);
$assert(array_column($r['details'],'semester_target')===[5,6],'Paritas mengikuti periode aktif, bukan nomor semester mahasiswa');
$courses=[];$passed=[];for($s=1;$s<=8;$s++)for($i=1;$i<=($s===8?5:6);$i++){$id=$s*100+$i;$courses[]=$c($id,$s,$s<5||$i<5,$s>=3?[($s-2)*100+$i]:[],$s===8&&$i===1?6:3);if($s<=4)$passed[]=$id;}
$r=$engine->plan($courses,$passed,72,144,5,8,24,20,'ganjil',[501,502,503,504,505,506]);
$assert($r['feasible'] && $r['projected_credits']===144 && count($r['details'])===23,'Kurikulum demo 144 SKS selesai dalam delapan semester');
$r=$engine->plan([$c(1),$c(2)],[],0,6,1,3,24,20,'ganjil',[1,2],[1]);
$assert(array_column($r['details'],'semester_target')===[1,3],'KRS submitted/final menjadi acuan tetap untuk semester berjalan');
echo "\n$count tests passed.\n";
