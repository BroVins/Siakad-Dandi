<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
 ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
 ->withMiddleware(function (Middleware $middleware): void {
     $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class, 'active.term' => \App\Http\Middleware\EnsureActiveTerm::class]);
     $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
     $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, \App\Http\Middleware\TrustConfiguredProxies::class);
     $middleware->trustHosts(at: fn()=>['^'.preg_quote(parse_url(config('app.url'),PHP_URL_HOST) ?: 'invalid.local','$').'$'], subdomains: false);
     $middleware->web(append: [\App\Http\Middleware\SanitizeFlashedInput::class]);
     $middleware->statefulApi();
 })
 ->withExceptions(function (Exceptions $exceptions): void {})
 ->create();
