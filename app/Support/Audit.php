<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
class Audit {
 public static function record(string $action, object $subject, array $context=[]): void {
   DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'action'=>$action,'subject_type'=>class_basename($subject),'subject_id'=>$subject->id,'context'=>json_encode($context),'created_at'=>now()]);
 }
}
