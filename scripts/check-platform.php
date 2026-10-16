<?php
// Safe to run before Composer/Laravel can boot.
require __DIR__.'/../app/Support/PlatformRequirements.php';
$driver = $argv[1] ?? 'mysql';
if (!in_array($driver, ['mysql', 'sqlite'], true)) { fwrite(STDERR, "Driver harus mysql atau sqlite.\n"); exit(2); }
$failed = false;
foreach (\App\Support\PlatformRequirements::checks($driver) as $check) {
    echo strtoupper($check['status']).' '.$check['message'].PHP_EOL;
    $failed = $failed || $check['status'] === 'fail';
}
exit($failed ? 1 : 0);
