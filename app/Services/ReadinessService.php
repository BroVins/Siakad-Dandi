<?php

namespace App\Services;

use App\Models\{Mahasiswa, SemesterAkademik, User};
use App\Support\PlatformRequirements;
use Illuminate\Support\Facades\{DB, Schema};

class ReadinessService
{
    /** Read-only checks. No secrets, migrations, network sends, or configuration changes. */
    public function inspect(bool $production = false): array
    {
        $checks = PlatformRequirements::checks(config('database.default'));
        $add = function (string $code, bool $pass, string $message, bool $warning = false) use (&$checks): void {
            $checks[] = ['code' => $code, 'status' => $pass ? 'pass' : ($warning ? 'warn' : 'fail'), 'message' => $message];
        };
        $key = config('app.key', '');
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $add('app.key', is_string($decoded) && strlen($decoded) === 32, 'APP_KEY harus terisi dengan kunci 32 byte; jangan diganti pada instalasi lama.');
        if ($production) {
            $add('app.environment', app()->environment('production'), 'APP_ENV harus production.');
            $add('app.debug', !config('app.debug'), 'APP_DEBUG harus false.');
            $url = config('app.url', '');
            $host = parse_url($url, PHP_URL_HOST);
            $add('app.url', filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' && !in_array($host, ['localhost', '127.0.0.1']) && !str_ends_with($host ?? '', '.test'), 'APP_URL harus URL HTTPS domain operasional.');
            $add('session.secure', config('session.secure') === true, 'SESSION_SECURE_COOKIE harus true pada HTTPS.');
            $add('mail.transport', config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.host')), 'Produksi memerlukan SMTP terkonfigurasi untuk reset kata sandi.');
            $add('mail.delivery', false, 'Pengiriman, autentikasi, dan penerimaan email harus diuji dengan kotak masuk nyata.', true);
        }
        $add('session.driver', in_array(config('session.driver'), ['file'], true), 'Paket menggunakan sesi file. Sesi database/Redis membutuhkan infrastruktur tambahan.');
        $add('cache.driver', in_array(config('cache.default'), ['file', 'array'], true) && (!$production || config('cache.default') === 'file'), 'Gunakan cache file untuk batas percobaan login lintas permintaan.');
        foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $directory) {
            $add('writable.'.$directory, is_dir(base_path($directory)) && is_writable(base_path($directory)), 'Direktori dapat ditulis proses PHP: '.$directory.'.');
        }
        foreach (['app.css', 'app.js', 'tailwind.css'] as $asset) {
            $add('asset.'.$asset, is_file(public_path('assets/'.$asset)) && filesize(public_path('assets/'.$asset)) > 0, 'Aset lokal tersedia: '.$asset.'.');
        }
        foreach (['css/app.css', 'js/app.js'] as $asset) {
            $compiled = public_path('assets/'.basename($asset));
            $add('asset.sync.'.basename($asset), is_file($compiled) && hash_file('sha256', resource_path($asset)) === hash_file('sha256', $compiled), 'Aset aplikasi sinkron dengan sumber: '.$asset.'.');
        }
        try {
            DB::connection()->select('select 1');
            $add('database.connection', true, 'Koneksi database berhasil.');
            $files = array_map(fn ($file) => basename($file, '.php'), glob(database_path('migrations/*.php')));
            $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
            $add('database.migrations', !array_diff($files, $ran), 'Semua migrasi paket harus tercatat telah diterapkan.');
            if (Schema::hasTable('users')) {
                $add('accounts.admin', User::where('role', 'admin')->exists(), 'Setidaknya satu akun admin harus tersedia.');
                $add('accounts.demo', !$production || !User::where('email', 'like', '%@siakad.test')->exists(), 'Akun demo tidak boleh digunakan pada database operasional.');
                $incomplete = User::where(fn ($q) => $q->where('role', 'mahasiswa')->whereDoesntHave('mahasiswa'))
                    ->orWhere(fn ($q) => $q->where('role', 'dosen')->whereDoesntHave('dosen'))->count();
                $add('accounts.profiles', $incomplete === 0, 'Akun mahasiswa dan dosen memerlukan profil akademik terkait.', true);
            } else {
                $add('accounts.admin', false, 'Tabel pengguna belum tersedia. Terapkan migrasi terlebih dahulu.');
            }
            $add('academic.term', Schema::hasTable('semester_akademik') && SemesterAkademik::where('aktif', true)->count() === 1, 'Harus tepat satu semester akademik aktif.');
            $add('academic.advisors', !Schema::hasTable('mahasiswa') || !Mahasiswa::where('status', 'aktif')->whereNull('dosen_pa_id')->exists(), 'Mahasiswa aktif harus memiliki Dosen PA sebelum mengajukan roadmap/KRS.', true);
        } catch (\Throwable $e) {
            $add('database.connection', false, 'Pemeriksaan database gagal. Periksa driver, koneksi, migrasi, dan log privat; detail kredensial tidak ditampilkan.');
        }
        if ($production) {
            $add('deployment.staging', false, 'Uji MySQL native, konkurensi kuota/pembayaran, SMTP, backup-restore, HTTPS, dan browser end-to-end di staging sebelum go-live.', true);
        }
        return $checks;
    }
}
