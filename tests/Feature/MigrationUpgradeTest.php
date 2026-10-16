<?php

namespace Tests\Feature;

use App\Models\{Krs,Nilai,Roadmap,Tagihan};
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationUpgradeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection=\Illuminate\Support\Facades\DB::connection();
        if($connection->getDriverName()!=='sqlite' && !str_ends_with($connection->getDatabaseName(),'_test')) {
            throw new \RuntimeException('Upgrade tests require a dedicated database ending in _test.');
        }
        Schema::dropAllTables();
    }
    protected function tearDown(): void
    {
        Schema::dropAllTables();
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false;
        parent::tearDown();
    }

    public function test_additive_upgrade_preserves_existing_academic_financial_and_roadmap_records(): void
    {
        (require database_path('migrations/2026_01_01_000000_create_academic_schema.php'))->up();
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $roadmap=Roadmap::create(['mahasiswa_id'=>1,'semester_akademik_id'=>5,'target_lulus'=>8,'status'=>'submitted']);
        $before=[Krs::count(),Nilai::count(),Tagihan::count(),Roadmap::count()];
        (require database_path('migrations/2026_10_16_000001_harden_workflow_links.php'))->up();
        $this->assertSame($before,[Krs::count(),Nilai::count(),Tagihan::count(),Roadmap::count()]);
        $this->assertSame('submitted',$roadmap->fresh()->status);
        $this->assertNull($roadmap->fresh()->context_hash);
        $this->assertTrue(Schema::hasColumn('krs','roadmap_id'));
        $this->assertTrue(Schema::hasColumn('tagihan','request_key'));
        $this->assertSame(24,Nilai::where('published',true)->count());
    }
}
