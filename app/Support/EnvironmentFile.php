<?php

namespace App\Support;

use Dotenv\Dotenv;

final class EnvironmentFile
{
    /** Preserve existing keys; serialize concurrent installers on the same env file. */
    public static function ensureKey(string $path): bool
    {
        $handle = fopen($path, 'c+');
        if ($handle === false) throw new \RuntimeException('Tidak dapat membuka berkas konfigurasi.');
        try {
            if (!flock($handle, LOCK_EX)) throw new \RuntimeException('Tidak dapat mengunci berkas konfigurasi.');
            $content = stream_get_contents($handle);
            $values = Dotenv::parse($content);
            if (!empty($values['APP_KEY'])) return false;
            $key = 'base64:'.base64_encode(random_bytes(32));
            $line = 'APP_KEY='.$key;
            if (preg_match('/^APP_KEY\s*=.*$/m', $content)) {
                $content = preg_replace('/^APP_KEY\s*=.*$/m', $line, $content);
            } else {
                $content = rtrim($content)."\n".$line."\n";
            }
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $content) !== strlen($content)) {
                throw new \RuntimeException('Gagal menulis konfigurasi. Periksa ruang disk dan izin berkas.');
            }
            fflush($handle);
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
