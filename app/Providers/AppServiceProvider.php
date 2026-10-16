<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\Paginator;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
   \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(fn($user,$token)=>rtrim(config('app.url'),'/').route('password.reset',['token'=>$token],false).'?'.http_build_query(['email'=>$user->getEmailForPasswordReset()], '', '&', PHP_QUERY_RFC3986));
   Paginator::useTailwind();
   View::composer('layouts.app', function ($view) { $view->with('activeTerm', \App\Models\SemesterAkademik::where('aktif',true)->first()); });
 }
}
