# Deploy dan upgrade SIAKAD

## 1. Gerbang rilis

Paket merupakan kandidat staging. Pengujian lokal tidak menggantikan PHPUnit dengan driver PDO native, uji MySQL concurrent writes, SMTP, HTTPS, dan browser end-to-end. Jalankan seluruh gerbang di bawah sebelum memakai data nyata. Contoh server dan workflow CI sudah ditulis, tetapi tidak pernah diterapkan pada server milik Anda dalam pengerjaan ini.

## 2. Persyaratan

Gunakan PHP 8.3/8.4 dengan Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML, XMLWriter, serta **pdo_mysql** untuk MySQL atau **pdo_sqlite** untuk SQLite. ZIP/Intl dapat dipasang untuk toolchain; aplikasi tidak memerlukan FFI. Gunakan Composer 2 dan Node.js 22 untuk rebuild. Lingkungan QA native harus juga memenuhi semua ekstensi dev dependencies Composer.

Target operasional yang disiapkan adalah satu instance Laravel/PHP-FPM dengan MySQL dan sesi/cache file. SQLite disediakan untuk pengembangan/pengujian lokal. Kelayakan kapasitas MySQL, jumlah pengguna, dan konfigurasi worker harus diuji sesuai beban kampus.

## 3. Instalasi produksi baru

Siapkan database kosong `siakad` dan akun khusus `siakad_app`, dengan akses hanya pada database tersebut. Simpan aplikasi di luar root dokumen umum, misalnya `/var/www/siakad`; Nginx/Apache harus menunjuk ke `/var/www/siakad/public`.

```bash
# Dari folder siakad. Hanya pada instalasi baru yang belum memiliki .env:
cp .env.production.example .env
chmod 600 .env
```

Isi APP_URL/domain HTTPS, MySQL, pengirim SMTP, dan rahasia pada `.env` secara privat. Jangan mengunggah `.env` ke repositori, chat, atau direktori publik. `MAIL_SCHEME=smtp` dengan port 587 menggunakan transport SMTP dan negosiasi TLS yang didukung server; layanan SMTPS biasanya menggunakan `MAIL_SCHEME=smtps` dan port 465. Ikuti konfigurasi penyedia, jangan menonaktifkan verifikasi sertifikat.

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
php scripts/check-platform.php mysql
php scripts/setup.php
php artisan db:seed --class=ProductionSeeder --force
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`setup.php` tidak menambahkan demo. Seeder produksi meminta nama/email/password admin pertama, menolak jika admin sudah ada, dan hanya dijalankan pada instalasi baru. Isi master data melalui portal, lalu:

```bash
php artisan siakad:check --production
```

Pada database baru, pemeriksaan semester/profil akan gagal atau memberi peringatan sampai admin melengkapi master. Ini adalah gerbang penggunaan operasional, bukan alasan membuat data kampus fiktif saat instalasi.

## 4. Server, izin, dan HTTPS

Contoh tersedia pada `deploy/nginx.conf.example` dan `deploy/apache.conf.example`. Sesuaikan domain, socket PHP, serta lokasi aplikasi. Contoh HTTP hanya untuk staging privat. Pasang sertifikat yang sah, arahkan port 80 ke HTTPS, dan aktifkan `SESSION_SECURE_COOKIE=true` pada URL HTTPS.

Berikan proses PHP hak tulis hanya pada `storage/` dan `bootstrap/cache/`. Pastikan seluruh direktori `storage/framework/{cache/data,sessions,views}` dan `storage/logs` tersedia. Jangan memakai izin 777. Kode aplikasi, `.env`, vendor, dan backup tidak boleh bisa dimodifikasi oleh pengunjung web.

Jika memakai reverse proxy, isi `TRUSTED_PROXIES` dengan IP/CIDR proxy yang Anda kendalikan, dipisahkan koma. Jangan memakai wildcard atau jaringan seluruh internet. Proxy harus meneruskan host/protokol secara benar. Host produksi dibatasi pada host APP_URL. Deployment pada subdirektori dan multi-domain belum diuji.

Aset belum menggunakan nama berbasis hash. Konfigurasi contoh meminta revalidasi cache; bersihkan cache CDN yang Anda gunakan saat rilis agar JavaScript lama tidak tertinggal.

## 5. Validasi staging wajib

Buat database uji **terpisah**. Jangan pernah menjalankan perintah pengujian berikut pada database operasional.

```bash
composer install
php vendor/bin/phpunit -c phpunit.xml
php scripts/test-planner.php

# Siapkan database siakad_test dan user siakad_test secara khusus terlebih dahulu.
# Kredensial di phpunit.mysql.xml adalah kredensial uji lokal, bukan produksi.
php vendor/bin/phpunit -c phpunit.mysql.xml

npm ci
npm run build
composer audit --locked --no-dev
npm audit --audit-level=high
```

Workflow `.github/workflows/qa.yml` menjalankan matriks PHP 8.3/8.4 dengan SQLite/MySQL serta build/audit aset. Workflow baru dieksekusi setelah kode ditempatkan pada repositori GitHub yang sesuai. Tidak ada hasil CI eksternal yang diklaim dalam paket ini.

Uji manual wajib: lima peran, link reset dari email nyata, session/CSRF lewat HTTPS, dua pengajuan untuk kursi terakhir secara serentak, dua petugas mencatat pembayaran serentak, aktivasi semester, penggantian pembimbing, pencetakan, tampilan desktop/ponsel, backup dan pemulihan. Cocokkan kebijakan nilai/SKS/prasyarat/kelulusan dengan kebijakan kampus.

## 6. Upgrade instalasi lama

Backup database, APP_KEY/.env, dan storage ke lokasi privat sebelum mulai. Uji salinan database lama di staging. Jangan menyalin `.env.example` di atas `.env` lama, jangan memakai `migrate:fresh`, dan jangan melakukan `key:generate` ulang.

```bash
php artisan down
# Salin source rilis baru; pertahankan .env dan storage persisten.
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize:clear
php scripts/setup.php
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan siakad:check --production
php artisan up
```

Hanya jalankan `up` setelah seluruh kegagalan preflight ditangani. Pertahankan salinan source sebelumnya untuk rollback. Pada rollout beberapa mesin, pastikan kode dan migrasi kompatibel serta akses tulis dihentikan sebelum migrasi; mode multi-node belum diuji pada paket ini.

Migrasi `2026_10_16_000001_harden_workflow_links` menambah snapshot/checksum roadmap, referensi roadmap KRS, kelas rekomendasi, token idempotensi tagihan, dan indeks. Nilai, tagihan, pembayaran, serta KRS final tidak dihapus. Roadmap legacy tanpa checksum tidak sah sebagai landasan **pengajuan baru**. Mahasiswa menarik pengajuan pending atau PA mengembalikannya, lalu mahasiswa membuat roadmap baru. Riwayat lama tetap dapat dibaca. Jangan mengisi checksum palsu melalui SQL.

Rollback database bukan langkah otomatis. Uji migrasi turun pada backup staging atau pulihkan backup yang konsisten dengan versi kode sebelumnya; migrasi turun menghapus metadata baru dan belum diverifikasi di MySQL pada lingkungan pengerjaan.

## 7. Operasional

Jadwalkan backup MySQL serta storage dan uji restore, pantau error log privat, ruang disk, sertifikat, keberhasilan SMTP, dan `/up`. `/up` hanya liveness; jalankan preflight untuk pemeriksaan yang lebih luas. Log dapat memuat data sensitif, khususnya saat MAIL_MAILER=log pada lingkungan lokal. Jangan memakai mailer log untuk reset password produksi.

Paket tidak menyediakan worker queue, cron bisnis, payment gateway, atau penyimpanan sesi bersama. Tidak perlu memasang worker fiktif untuk fitur sinkron yang ada; rancang konfigurasi baru bila fitur tersebut benar-benar ditambahkan.

Rujukan primer: dokumentasi Laravel 12, bagian Deployment, Configuration, Authentication, Database, Queries, dan Routing. Implementasi juga diperiksa terhadap source framework yang disertakan dalam vendor. Hasil audit dependensi terbaru harus diperoleh saat deploy, bukan diasumsikan dari nomor versi.
