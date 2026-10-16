# Laporan QA SIAKAD revisi

Tanggal verifikasi: **16 Oktober 2026**. Status: **kandidat rilis untuk staging, belum tersertifikasi siap produksi**. Tidak ada deployment ke server pengguna, audit keamanan independen, atau klaim seluruh kemungkinan bug sudah dieliminasi.

## 1. Hasil yang benar-benar dijalankan

| Pemeriksaan | Hasil | Apa yang dibuktikan dan batasnya |
|---|---|---|
| Suite aplikasi/regresi | **90 metode tes lulus; 423 assertion; 0 kegagalan** | Laravel HTTP kernel, Eloquent, validasi, Blade, migrasi, dan data SQLite sungguhan melalui adapter FFI khusus QA. Assertion PHPUnit dijalankan melalui reflection, bukan runner PHPUnit native. |
| Planner mandiri | **15 skenario lulus** | Eksekusi mesin PHP langsung, tanpa database. |
| Alur HTTP lintas peran | **14 pemeriksaan lulus; 0 HTTP 5xx** | Form/action/CSRF diekstrak dari respons HTML aktual, session cookie nyata, POST/PATCH dan reload data melalui server Laravel lokal. Klien requests, bukan navigasi browser. Database memakai adapter SQLite FFI yang sama. |
| Tampilan/interaksi Chromium | **10 pemeriksaan lulus; 0 exception JavaScript yang teramati** | HTML hasil HTTP, CSS dan JS aplikasi dirender di Chromium. Desktop 1440px dan ponsel 390px; menu, counter SKS, dialog, pemberitahuan, guard submit, pemanggilan print. DOM terpisah, bukan browser end-to-end. |
| Sintaks PHP source, migrasi, tes, dan tooling | **93 berkas lulus** | `php -l`, tidak termasuk vendor. |
| Compiler Blade Laravel asli | **26 view lulus kompilasi dan lint PHP hasil kompilasi** | Tidak memakai translasi direktif buatan. Render halaman juga dicakup suite/HTTP sesuai skenario. |
| Router/config | **50 route terdaftar; 45 route bernama; 0 referensi literal route Blade yang hilang** | Serialisasi/kompilasi router dan ekspor config diuji, lalu PHP hasilnya dilint. Bukan bukti boot produksi dari cache konfigurasi. |
| Manifest dan tooling | **Lulus** | JSON Composer/npm, dua XML PHPUnit, sintaks YAML CI, sintaks JS dan shell installer. CI belum dijalankan. |
| Konsistensi aset | **Lulus** | app.js dan app.css pada resources sama dengan salinan public. Tailwind hasil build bawaan disertakan. |
| Integritas vendor/lock | **8.483 berkas vendor sama dengan ZIP asal** | composer.lock, package-lock.json, source Tailwind dan Tailwind terkompilasi tidak berubah. Bukan audit kerentanan dependensi. |

Tidak ada persentase code coverage yang diukur. Jumlah pemeriksaan di beberapa lapisan saling tumpang tindih; jangan menafsirkannya sebagai semua kombinasi fitur telah diuji. Log dan JSON tersedia di `docs/qa/`.

## 2. Cakupan regresi backend

**Akademik dan planner:** validasi semester aktif, histori prasyarat sebelum periode berjalan, batas SKS, kurikulum, kelas penuh, konflik waktu, kombinasi kelas alternatif, kelas pilihan yang sudah terdaftar, snapshot roadmap, perubahan data setelah roadmap dibuat, penarikan pengajuan, pengajuan semester lama, review PA, dan keterkaitan roadmap yang tepat pada KRS.

**KRS dan pengajaran:** draft/pengajuan/final, reservasi kuota submitted/final, kepemilikan/PA/pengampu kelas, final tidak dapat diedit, persetujuan setelah jendela pengisian ditutup dengan semester tetap aktif, validasi presisi nilai, simpan tanpa tidak sengaja membatalkan publikasi, daftar peserta yang sah, reload/edit presensi, dan konsistensi tanggal pertemuan.

**Master, autentikasi, keamanan:** false pada boolean, relasi dan status akademik yang sudah dipakai, email tidak peka kapital, ganti kata sandi dan reset, panjang byte bcrypt, pencabutan token API dan remember token, reset oleh admin, batas peran/data prodi, input array yang salah bentuk, URL reset kanonis, header privat, daftar proxy tepercaya, dan CSRF melalui HTTP nyata.

**Keuangan dan skripsi:** nominal dua desimal dan pelunasan pecahan, tidak lebih bayar, referensi unik, idempotensi tagihan, pembatasan kuitansi mahasiswa, progres/status skripsi konsisten, pembimbing/otorisasi, catatan bimbingan dan umpan balik, serta paginasi. Pengujian transaksi dilakukan berurutan; lock MySQL serentak belum dibuktikan.

**Instalasi dan upgrade:** APP_KEY hanya dibuat ketika kosong, nilai lama termasuk quoted key tetap, gerbang kesiapan/konfigurasi produksi, dan migrasi additive dari skema lama dengan data demo serta roadmap legacy tetap ada. Migrasi naik legacy diuji pada SQLite QA; migrasi turun dan upgrade database operasional MySQL belum diuji.

## 3. Bukti alur pengguna

Pengujian HTTP menjalankan urutan mahasiswa membuat roadmap, PA menyetujui, mahasiswa menyimpan/mengajukan/menarik/mengajukan ulang KRS, PA memfinalkan, jadwal dan dokumen cetak terisi, dosen mengisi/menerbitkan/mengedit nilai serta presensi, mahasiswa melihat hasil, petugas membuat tagihan dan pembayaran, mahasiswa membaca kuitansi, serta catatan bimbingan dan balasan dosen tersimpan. CRUD master, ganti password, logout/login ulang, penolakan CSRF, dan header keamanan juga diperiksa.

Navigasi Chromium di lingkungan ini diblokir oleh kebijakan browser (`URLBlocklist`). Kebijakan tidak diubah atau dilewati. Oleh karena itu alur HTTP menggunakan klien requests, sedangkan render/event JavaScript menggunakan `set_content` dari HTML respons tersebut. Form submission native browser, jaringan browser, cookie browser lintas navigasi, fokus setelah redirect, dan dialog cetak sistem tetap memerlukan uji end-to-end di staging. Tangkapan layar data demo tersedia pada `docs/qa/screenshots/`.

## 4. Pemeriksaan yang terhambat, bukan lulus

| Gerbang | Status dan sebab |
|---|---|
| `php vendor/bin/phpunit` native | **Gagal mulai**: ekstensi dom, mbstring, dan xmlwriter tidak tersedia. Tidak ada klaim runner ini lulus. |
| Native PDO SQLite/MySQL dan perilaku concurrency MySQL | **Belum dijalankan**: driver PDO tidak tersedia; server MySQL target juga belum diuji. |
| `php scripts/check-platform.php mysql` | **Exit 1 sesuai keadaan lingkungan**: curl, dom, mbstring, xml, xmlwriter dan pdo_mysql belum tersedia. |
| `npm run build` baru | **Gagal mulai, exit 127**: tailwindcss/node_modules tidak tersedia; akses unduhan terblokir. CSS Tailwind hasil build asal tidak diubah. |
| Composer install/platform validation penuh dan audit CVE | **Belum dijalankan**: executable Composer serta akses unduhan tidak tersedia. Vendor asal dipakai dengan integritas terverifikasi. |
| SMTP, HTTPS produksi, load/concurrency, backup/restore | **Belum diverifikasi**: membutuhkan konfigurasi dan lingkungan operasional sebenarnya. |
| Workflow GitHub Actions serta config Nginx/Apache | **Disiapkan, belum dieksekusi/deploy**. Sintaks YAML telah diparse; bukan validasi server nginx/apache. |

PHP tersedia versi 8.4.24. Adapter QA menggunakan libsqlite3 sistem melalui FFI; aplikasi produksi **tidak** memuat adapter itu dan tidak membutuhkan FFI. Adapter memanggil test method dan assertion yang asli tetapi tidak mencakup seluruh lifecycle runner PHPUnit. Suppression pesan deprecation di harness bukan audit kompatibilitas deprecation produksi.

## 5. Reproduksi dan gerbang sebelum go-live

Pada salinan staging dengan PHP/ekstensi native, Composer, Node, SMTP uji, dan database khusus:

```bash
composer install
composer check-platform-reqs
php vendor/bin/phpunit -c phpunit.xml
php vendor/bin/phpunit -c phpunit.mysql.xml
php scripts/test-planner.php
npm ci
npm run build
composer audit --locked --no-dev
npm audit --audit-level=high
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan siakad:check --production
```

`phpunit.mysql.xml` memakai database uji `siakad_test`. Suite membuat/menghapus tabel di database uji; jangan arahkan ke database operasional. Perintah produksi harus dijalankan dengan `.env` produksi yang benar pada staging, tidak dengan konfigurasi demo. Petunjuk lengkap dan urutan upgrade ada pada `DEPLOYMENT.md`; reproduksi adapter/HTTP/DOM tambahan ada pada `tools/qa/README.md`.

Uji penerimaan manual wajib mencakup lima peran, email reset nyata, token sekali pakai, dua pengajuan serentak untuk kursi terakhir, dua petugas membayar tagihan yang sama secara serentak, mutasi semester dan pembimbing, print, keyboard/ponsel, serta pemulihan backup. Setujui parameter akademik dengan pengelola kampus. Jangan mengaktifkan data nyata hanya berdasarkan jumlah tes lokal.
