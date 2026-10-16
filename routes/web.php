<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,AcademicController,MasterController,PlannerController,KrsController,TeachingController,FinanceController,ThesisController};
Route::redirect('/','/dashboard');
Route::middleware('guest')->group(function(){
 Route::view('/login','auth.login')->name('login');
 Route::post('/login',[AuthController::class,'login'])->middleware('throttle:10,1');
 Route::view('/forgot-password','auth.forgot')->name('password.request');
 Route::post('/forgot-password',[AuthController::class,'forgot'])->middleware('throttle:5,1')->name('password.email');
 Route::get('/reset-password/{token}',fn(string $token)=>view('auth.reset',['token'=>$token]))->name('password.reset');
 Route::post('/reset-password',[AuthController::class,'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware(['auth','auth.session'])->group(function(){
 Route::post('/logout',[AuthController::class,'logout'])->name('logout');
 Route::get('/dashboard',[AcademicController::class,'dashboard'])->name('dashboard');
 Route::patch('/biodata/password',[AuthController::class,'changePassword'])->middleware('throttle:5,1')->name('profile.password');
 Route::get('/biodata',[AcademicController::class,'profile'])->name('profile');
 Route::patch('/biodata',[AcademicController::class,'updateProfile'])->middleware('role:mahasiswa')->name('profile.update');
 Route::get('/jadwal',[AcademicController::class,'schedule'])->middleware('role:admin,mahasiswa,dosen,kaprodi')->middleware('active.term')->name('schedule');
 Route::get('/laporan',[AcademicController::class,'reports'])->middleware('role:admin,dosen,kaprodi')->name('reports');
 Route::middleware('role:admin')->prefix('master/{entity}')->name('master.')->group(function(){
  Route::get('/',[MasterController::class,'index'])->name('index');Route::get('/create',[MasterController::class,'create'])->name('create');Route::post('/',[MasterController::class,'store'])->name('store');Route::get('/{id}/edit',[MasterController::class,'edit'])->whereNumber('id')->name('edit');Route::get('/{id}',[MasterController::class,'show'])->whereNumber('id')->name('show');Route::put('/{id}',[MasterController::class,'update'])->whereNumber('id')->name('update');Route::delete('/{id}',[MasterController::class,'destroy'])->whereNumber('id')->name('destroy');
 });
 Route::middleware('role:mahasiswa')->group(function(){
  Route::get('/planner',[PlannerController::class,'index'])->middleware('active.term')->name('planner.index');Route::post('/planner',[PlannerController::class,'generate'])->middleware('active.term')->name('planner.generate');Route::post('/planner/{roadmap}/submit',[PlannerController::class,'submit'])->name('planner.submit');
  Route::post('/planner/{roadmap}/withdraw',[PlannerController::class,'withdraw'])->name('planner.withdraw');
  Route::post('/krs/{krs}/withdraw',[KrsController::class,'withdraw'])->name('krs.withdraw');
  Route::get('/krs',[KrsController::class,'index'])->middleware('active.term')->name('krs.index');Route::post('/krs',[KrsController::class,'store'])->middleware('active.term')->name('krs.store');Route::get('/nilai',[AcademicController::class,'grades'])->name('grades');
 });
 Route::get('/roadmap/{roadmap}',[PlannerController::class,'show'])->middleware('role:admin,mahasiswa,dosen,kaprodi')->name('planner.show');
 Route::post('/roadmap/{roadmap}/review',[PlannerController::class,'review'])->middleware('role:dosen')->name('planner.review');
 Route::get('/krs/{krs}',[KrsController::class,'show'])->middleware('role:admin,mahasiswa,dosen,kaprodi')->name('krs.show');
 Route::post('/krs/{krs}/review',[KrsController::class,'review'])->middleware('role:dosen')->name('krs.review');
 Route::middleware('role:dosen')->group(function(){Route::get('/mengajar',[TeachingController::class,'index'])->name('teaching.index');Route::get('/mengajar/{kelas}',[TeachingController::class,'show'])->name('teaching.show');Route::post('/mengajar/{kelas}/nilai',[TeachingController::class,'grades'])->name('teaching.grades');Route::post('/mengajar/{kelas}/presensi',[TeachingController::class,'attendance'])->name('teaching.attendance');});
 Route::middleware('role:admin,keuangan,mahasiswa')->group(function(){Route::get('/pembayaran',[FinanceController::class,'index'])->name('finance.index');Route::get('/pembayaran/{pembayaran}/kwitansi',[FinanceController::class,'receipt'])->name('finance.receipt');});
 Route::middleware('role:admin,keuangan')->group(function(){Route::post('/tagihan',[FinanceController::class,'bill'])->name('finance.bill');Route::post('/tagihan/{tagihan}/bayar',[FinanceController::class,'pay'])->name('finance.pay');});
 Route::middleware('role:admin,mahasiswa,dosen,kaprodi')->group(function(){Route::get('/skripsi',[ThesisController::class,'index'])->name('thesis.index');Route::post('/skripsi',[ThesisController::class,'store'])->middleware('role:admin')->name('thesis.store');Route::patch('/skripsi/{skripsi}',[ThesisController::class,'update'])->name('thesis.update');Route::post('/skripsi/{skripsi}/bimbingan',[ThesisController::class,'note'])->name('thesis.note');Route::patch('/skripsi/{skripsi}/bimbingan/{bimbingan}',[ThesisController::class,'feedback'])->name('thesis.feedback');});
});
