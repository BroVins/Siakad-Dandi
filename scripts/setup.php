<?php
/** Explicit installer. Never drops tables, replaces APP_KEY, or seeds demo data implicitly. */
$root = dirname(__DIR__);
chdir($root);
if (!is_file('vendor/autoload.php')) { fwrite(STDERR, "Jalankan composer install terlebih dahulu.\n"); exit(1); }
require 'vendor/autoload.php';
$options = array_slice($argv, 1);
foreach ($options as $option) {
    if (!in_array($option, ['--demo'], true)) { fwrite(STDERR, "Opsi tidak dikenal. Gunakan --demo hanya untuk database lokal kosong.\n"); exit(2); }
}
if (!file_exists('.env')) { copy('.env.example', '.env'); @chmod('.env', 0600); }
try {
    $env = \Dotenv\Dotenv::parse(file_get_contents('.env'));
    if (is_file('bootstrap/cache/config.php')) {
        throw new RuntimeException('Cache konfigurasi masih ada. Jalankan php artisan config:clear lalu ulangi setup.');
    }
    $driver = $env['DB_CONNECTION'] ?? 'mysql';
    $failures = array_filter(\App\Support\PlatformRequirements::checks($driver), fn ($check) => $check['status'] === 'fail');
    if ($failures) throw new RuntimeException('Persyaratan PHP belum lengkap. Jalankan php scripts/check-platform.php '.$driver.'.');
    $demo = in_array('--demo', $options, true);
    if ($demo && !in_array($env['APP_ENV'] ?? 'production', ['local', 'testing'], true)) throw new RuntimeException('Data demo hanya boleh untuk lingkungan local/testing.');
    \App\Support\EnvironmentFile::ensureKey($root.'/.env');
    if ($driver === 'sqlite') {
        $database = $env['DB_DATABASE'] ?? 'database/database.sqlite';
        if ($database === ':memory:') throw new RuntimeException('Instalasi persisten tidak boleh memakai database in-memory.');
        $absolute = str_starts_with($database, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $database);
        $path = $absolute ? $database : $root.'/'.$database;
        if (!is_file($path) && !touch($path)) throw new RuntimeException('Tidak dapat membuat database SQLite.');
        @chmod($path, 0660);
    }
    $run = function (array $args): void {
        $process = proc_open([PHP_BINARY, 'artisan', ...$args], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Perintah Artisan gagal; periksa keluaran sebelumnya.');
    };
    $run(['migrate', '--force']);
    if ($demo) $run(['db:seed', '--class=DemoSeeder', '--force']);
    echo "Setup selesai; APP_KEY lama dipertahankan.\n";
    echo $demo ? "Data demo lokal tersedia. Jalankan php artisan serve.\n" : "Database baru: buat admin dengan php artisan db:seed --class=ProductionSeeder --force.\n";
    echo "Setelah master data lengkap: php artisan siakad:check\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Setup dihentikan: '.$e->getMessage().PHP_EOL);
    exit(1);
}
