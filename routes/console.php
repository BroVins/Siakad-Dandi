<?php

use App\Services\ReadinessService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('siakad:check {--production : Terapkan persyaratan produksi} {--json : Keluaran JSON tanpa rahasia}', function () {
    $checks = app(ReadinessService::class)->inspect($this->option('production') || app()->environment('production'));
    if ($this->option('json')) {
        $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } else {
        foreach ($checks as $check) $this->line(strtoupper($check['status']).' ['.$check['code'].'] '.$check['message']);
    }
    return collect($checks)->contains('status', 'fail') ? 1 : 0;
})->purpose('Memeriksa runtime, database, aset, akun, dan konfigurasi deployment tanpa mengubah data.');
