<?php

namespace App\Support;

final class PlatformRequirements
{
    public static function checks(string $driver = 'mysql'): array
    {
        $checks = [['code' => 'runtime.php', 'status' => PHP_VERSION_ID >= 80300 && PHP_VERSION_ID < 90000 ? 'pass' : 'fail', 'message' => 'PHP '.PHP_VERSION.'; memerlukan PHP 8.3 atau lebih baru dalam seri 8.x.']];
        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pcre', 'PDO', 'session', 'tokenizer', 'xml', 'xmlwriter'] as $extension) {
            $checks[] = ['code' => 'extension.'.$extension, 'status' => extension_loaded($extension) ? 'pass' : 'fail', 'message' => 'Ekstensi '.$extension.'.'];
        }
        $checks[] = ['code' => 'extension.driver', 'status' => in_array($driver, \PDO::getAvailableDrivers(), true) ? 'pass' : 'fail', 'message' => 'Driver PDO native untuk '.$driver.'.'];
        return $checks;
    }
}
