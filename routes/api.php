<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Services\AcademicService;
// Sanctum cookie/session authentication. No public token creation endpoint.
Route::middleware('auth:sanctum')->group(function(){
 Route::get('/me',fn(Request $r)=>$r->user()->only('id','name','email','role'));
 Route::get('/academic-summary',function(Request $r,AcademicService $a){abort_unless($r->user()->role==='mahasiswa' && $r->user()->mahasiswa,403);return $a->summary($r->user()->mahasiswa);});
});
