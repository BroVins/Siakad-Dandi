<?php

namespace Tests\Unit;

use App\Support\EnvironmentFile;
use PHPUnit\Framework\TestCase;

class EnvironmentFileTest extends TestCase
{
    public function test_setup_generates_a_valid_key_only_when_empty(): void
    {
        $file=tempnam(sys_get_temp_dir(),'siakad-env-');
        try {
            file_put_contents($file,"APP_ENV=local\nAPP_KEY=\nDB_CONNECTION=sqlite\n");
            $this->assertTrue(EnvironmentFile::ensureKey($file));
            $env=\Dotenv\Dotenv::parse(file_get_contents($file));
            $this->assertSame(32,strlen(base64_decode(substr($env['APP_KEY'],7),true)));
            $first=file_get_contents($file);
            $this->assertFalse(EnvironmentFile::ensureKey($file));
            $this->assertSame($first,file_get_contents($file));
        } finally { unlink($file); }
    }
    public function test_existing_quoted_key_and_other_settings_are_preserved(): void
    {
        $file=tempnam(sys_get_temp_dir(),'siakad-env-');
        try {
            $content="APP_KEY=\"base64:".base64_encode(str_repeat('x',32))."\"\nAPP_ENV=production\n";
            file_put_contents($file,$content);
            $this->assertFalse(EnvironmentFile::ensureKey($file));$this->assertSame($content,file_get_contents($file));
        } finally { unlink($file); }
    }
    public function test_missing_key_line_is_added_without_erasing_other_values(): void
    {
        $file=tempnam(sys_get_temp_dir(),'siakad-env-');
        try {
            file_put_contents($file,"APP_NAME=SIAKAD\n");
            $this->assertTrue(EnvironmentFile::ensureKey($file));
            $this->assertStringStartsWith("APP_NAME=SIAKAD\n",file_get_contents($file));
            $this->assertStringContainsString('APP_KEY=base64:',file_get_contents($file));
        } finally { unlink($file); }
    }
}
