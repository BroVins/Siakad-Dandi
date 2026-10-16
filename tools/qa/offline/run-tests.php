<?php
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__,3);
if (getenv('SIAKAD_OFFLINE_QA') !== '1' || is_file($root.'/bootstrap/cache/config.php')) { fwrite(STDERR, "Gunakan salinan QA tanpa config cache dan set SIAKAD_OFFLINE_QA=1.\n"); exit(2); }
chdir($root);
foreach (['APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_KEY'=>'base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>':memory:','CACHE_STORE'=>'array','SESSION_DRIVER'=>'array','MAIL_MAILER'=>'array','BCRYPT_ROUNDS'=>'4'] as $key=>$value) {putenv("$key=$value"); $_ENV[$key]=$value; $_SERVER[$key]=$value;}
require $root.'/vendor/autoload.php';
(new \PHPUnit\TextUI\Configuration\Builder)->build(['phpunit','--no-configuration']);
require __DIR__.'/SqlitePdo.php';
if (!function_exists('mb_split')) { function mb_split($pattern, $string, $limit = -1) { return preg_split('~'.str_replace('~', '\\~', $pattern).'~u', $string, $limit); } }
trait OfflineFeatureRuntime {
    public function createApplication() {
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$inMemoryConnections = [];
        $app = parent::createApplication();
        $app['db']->extend('sqlite', function($config, $name) {
            $config['name'] = $name;
            return new \Illuminate\Database\SQLiteConnection(new OfflineSqlitePdo($config['database']), $config['database'], $config['prefix'] ?? '', $config);
        });
        return $app;
    }
    protected function migrateDatabases() {
        foreach (glob(database_path('migrations/*.php')) as $path) (require $path)->up();
    }
    public function seed($class = 'Database\\Seeders\\DatabaseSeeder') {
        foreach ((array)$class as $seeder) $this->app->make($seeder)->setContainer($this->app)->run();
        return $this;
    }
}
$classes = [];
foreach (glob($root.'/tests/{Feature,Unit}/*Test.php', GLOB_BRACE) as $file) {
    require_once $file;
    $namespace = str_contains($file, '/Feature/') ? 'Tests\\Feature\\' : 'Tests\\Unit\\';
    $base = $namespace.basename($file,'.php');
    if (!class_exists($base)) continue;
    $name = 'Offline_'.basename($file,'.php');
    if (is_subclass_of($base, \Tests\TestCase::class)) {eval("class $name extends \\$base { use \\OfflineFeatureRuntime; }"); $classes[] = [$name,$base];}
    else $classes[] = [$base,$base];
}
$results=[];$start=microtime(true);$failed=0;
foreach ($classes as [$class,$base]) {
    foreach ((new ReflectionClass($base))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->name,'test_')) continue;
        $name=$base.'::'.$method->name;
        if (isset($argv[1]) && !str_contains($name,$argv[1])) continue;
        $test = new $class($method->name);
        try {
            (new ReflectionMethod($class,'setUp'))->invoke($test);
            $test->{$method->name}();
            (new ReflectionMethod($class,'tearDown'))->invoke($test);
            echo "PASS $name\n";
            $results[]=['name'=>$name,'passed'=>true];
        } catch (Throwable $e) {
            $failed++;
            $message=$e->getMessage();
            echo "FAIL $name\n".get_class($e).": $message\n";
            echo substr($e->getTraceAsString(),0,2500)."\n";
            $results[]=['name'=>$name,'passed'=>false,'error'=>get_class($e).': '.$message];
            try {(new ReflectionMethod($class,'tearDown'))->invoke($test);} catch(Throwable) {}
        }
    }
}
$out=['runtime'=>'PHP '.PHP_VERSION.'; Laravel actual HTTP kernel; system SQLite via QA-only FFI PDO adapter; PHPUnit assertions invoked by reflection (not native PHPUnit runner)','tests'=>count($results),'failures'=>$failed,'assertions'=>\PHPUnit\Framework\Assert::getCount(),'duration'=>round(microtime(true)-$start,3),'results'=>$results];
file_put_contents((getenv('SIAKAD_QA_OUTPUT') ?: sys_get_temp_dir().'/siakad-qa-results.json'),json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "\n".json_encode(array_diff_key($out,['results'=>1]),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failed ? 1 : 0);
