<?php

namespace Tests\Feature;

use App\Models\SemesterAkademik;
use App\Services\ReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AcademicFixtures;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    use RefreshDatabase, AcademicFixtures;
    private function checks(): array { return array_column(app(ReadinessService::class)->inspect(true),null,'code'); }

    public function test_preflight_fails_insecure_production_configuration(): void
    {
        config(['app.debug'=>true,'app.url'=>'http://siakad.example.test','session.secure'=>false]);
        $r=$this->checks();
        foreach(['app.debug','app.url','session.secure'] as $code)$this->assertSame('fail',$r[$code]['status']);
    }
    public function test_preflight_detects_demo_accounts_and_missing_active_term(): void
    {
        SemesterAkademik::query()->update(['aktif'=>false]);$r=$this->checks();
        $this->assertSame('fail',$r['academic.term']['status']);
        $this->assertSame('fail',$r['accounts.demo']['status']);
    }
    public function test_preflight_detects_unrecorded_migrations(): void
    {
        $repo=app('migration.repository');
        if(!$repo->repositoryExists())$repo->createRepository();
        \Illuminate\Support\Facades\DB::table('migrations')->delete();
        $r=$this->checks();$this->assertSame('fail',$r['database.migrations']['status']);
        foreach(glob(database_path('migrations/*.php')) as $path)$repo->log(basename($path,'.php'),1);
        $r=$this->checks();$this->assertSame('pass',$r['database.migrations']['status']);
    }
    public function test_preflight_returns_no_secret_values(): void
    {
        config(['database.connections.mysql.password'=>'QA-SUPER-SECRET','mail.mailers.smtp.password'=>'SMTP-SUPER-SECRET']);
        $text=json_encode($this->checks());
        $this->assertStringNotContainsString('QA-SUPER-SECRET',$text);
        $this->assertStringNotContainsString('SMTP-SUPER-SECRET',$text);
    }
}
