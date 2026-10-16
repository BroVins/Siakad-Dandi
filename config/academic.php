<?php
return [
 'passing_points'=>2.0,
 'first_semester_limit'=>20,
 'future_semester_limit'=>20,
 'limits'=>[['min'=>3.0,'sks'=>24],['min'=>2.5,'sks'=>21],['min'=>2.0,'sks'=>18],['min'=>1.5,'sks'=>15],['min'=>0,'sks'=>12]],
 'grades'=>[['min'=>85,'grade'=>'A','points'=>4],['min'=>80,'grade'=>'A-','points'=>3.7],['min'=>75,'grade'=>'B+','points'=>3.3],['min'=>70,'grade'=>'B','points'=>3],['min'=>65,'grade'=>'B-','points'=>2.7],['min'=>60,'grade'=>'C','points'=>2],['min'=>45,'grade'=>'D','points'=>1],['min'=>0,'grade'=>'E','points'=>0]],
 'demo_password'=>env('DEMO_PASSWORD','DemoSiakad2026!'),
];
