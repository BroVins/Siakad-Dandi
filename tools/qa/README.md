# Alat reproduksi QA tambahan

Gunakan salinan proyek dan database khusus yang dapat dibuang. Jangan menjalankan script perubahan data terhadap instalasi nyata. Test utama tetap `php vendor/bin/phpunit`, bukan adapter ini.

## Runtime terbatas/offline

Lingkungan pengerjaan tidak mempunyai pdo_sqlite, pdo_mysql, DOM, XMLWriter, maupun Mbstring native yang dibutuhkan; unduhan paket terblokir. `offline/SqlitePdo.php` adalah adapter QA berbasis system SQLite/FFI, bukan driver produksi. `offline/run-tests.php` menjalankan metode test dan assertion PHPUnit melalui reflection. Ia tidak menggantikan seluruh lifecycle runner PHPUnit, native PDO, atau perilaku locking MySQL.

Pada PHP dengan FFI dan libsqlite3.so.0, tanpa config cache:

```bash
SIAKAD_OFFLINE_QA=1 php -d ffi.enable=1 tools/qa/offline/run-tests.php
```

Hasil JSON default ada di direktori temporary sistem, `siakad-qa-results.json`. Set `SIAKAD_QA_OUTPUT` untuk lokasi lain. Adapter memakai database in-memory, menjalankan migrasi/seed langsung, dan polyfill mb_split khusus QA. Bootstrap produksi tidak memuat file ini.

## Alur HTTP nyata dan DOM terpisah

Script HTTP memakai token CSRF/action yang diekstrak dari HTML aktual, cookie session, dan koneksi ke server lokal. Script tersebut membuat/mengubah data demo, termasuk password mahasiswa. Siapkan database demo sekali pakai `database/database_qa.sqlite`, arahkan `.env` salinan QA ke file itu, dan jalankan server lokal. Jangan memakai database yang ingin dipertahankan. Hentikan dan buat ulang database uji untuk pengulangan bersih.

```bash
python -m pip install -r tools/qa/requirements.txt
# Pada salinan QA: .env harus APP_ENV=local, APP_URL sesuai port,
# dan DB_DATABASE berisi path absolut menuju database/database_qa.sqlite.
php scripts/setup.php --demo
php artisan serve --host=127.0.0.1 --port=8000
# Terminal kedua:
SIAKAD_HTTP_QA=1 python tools/qa/http-user-flows.py
python -m playwright install chromium
python tools/qa/render-ui.py
```

Variabel tambahan: `SIAKAD_BASE_URL`, `SIAKAD_TEST_DATABASE`, `SIAKAD_QA_DIR`, dan `CHROMIUM_EXECUTABLE`. Windows dapat mengatur variabel melalui `$env:NAMA='nilai'` sebelum menjalankan perintah.

DOM test memakai HTML dari hasil HTTP dan memasukkan CSS/JS lokal ke Chromium. Ini membuktikan render dan event handler terpisah, **bukan browser end-to-end dengan navigasi dan submit native**. Navigasi browser pada lingkungan pengerjaan diblokir oleh kebijakan browser; kebijakan tersebut tidak diubah. Uji browser end-to-end tetap wajib di staging.

Berkas HTML/hasil script dapat memuat data demo dan CSRF token sesi uji. Simpan output privat, jangan upload sebagai aset publik, dan hapus setelah selesai. Screenshot yang disertakan dalam docs hanya memakai data demo.
