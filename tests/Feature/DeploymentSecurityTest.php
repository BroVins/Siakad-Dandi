<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\AcademicFixtures;
use Tests\TestCase;

class DeploymentSecurityTest extends TestCase
{
    use RefreshDatabase, AcademicFixtures;

    public function test_reset_url_uses_configured_origin_not_request_host(): void
    {
        config(['app.url'=>'https://portal.campus.example']);
        $this->app->instance('request', Request::create('https://untrusted.example/forgot-password'));
        $mail=(new ResetPassword('safe-token'))->toMail($this->account());
        $this->assertStringStartsWith('https://portal.campus.example/reset-password/safe-token',$mail->actionUrl);
    }

    public function test_explicit_trusted_proxy_honors_forwarded_scheme_only_from_configured_ip(): void
    {
        config(['security.trusted_proxies'=>['10.20.0.10']]);
        $middleware=app(\App\Http\Middleware\TrustConfiguredProxies::class);
        $request=Request::create('http://portal.campus.example/login','GET',[],[],[],['REMOTE_ADDR'=>'10.20.0.10','HTTP_X_FORWARDED_PROTO'=>'https']);
        $middleware->handle($request,fn($r)=>response($r->isSecure()?'secure':'insecure'));
        $this->assertTrue($request->isSecure());
        $request=Request::create('http://portal.campus.example/login','GET',[],[],[],['REMOTE_ADDR'=>'10.20.0.11','HTTP_X_FORWARDED_PROTO'=>'https']);
        $middleware->handle($request,fn($r)=>response('ok'));
        $this->assertFalse($request->isSecure());
    }

    public function test_production_rejects_unconfigured_host(): void
    {
        config(['app.url'=>'https://portal.campus.example']);$original=$this->app['env'];$this->app['env']='production';
        try {$this->withHeader('Host','untrusted.example')->get('/login')->assertStatus(400);}
        finally {$this->app['env']=$original;Request::setTrustedHosts([]);}
    }
}
