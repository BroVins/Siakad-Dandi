# SIAKAD | Revisi alur, keamanan, dan QA

Paket revisi 16 Oktober 2026. Aplikasi mempertahankan Laravel, Blade, Tailwind, JavaScript, dan seluruh modul awal. Versi yang tercatat dalam lock: Laravel **12.69.2**, Sanctum **4.3.3**, PHPUnit **11.5.56**, Tailwind **3.4.17**. Ini bukan pernyataan bahwa semua versi tersebut bebas kerentanan saat Anda melakukan deploy.

## Status yang perlu dibaca terlebih dahulu

**Kode revisi dan pengujian lokal telah disiapkan. Status rilis: kandidat untuk verifikasi staging, bukan sertifikasi produksi tanpa bug.**

Pengujian di lingkungan pengerjaan menggunakan Laravel/Eloquent/Blade sesungguhnya, tetapi driver database dijalankan melalui adapter SQLite FFI khusus QA karena ekstensi PDO native tidak tersedia. Browser juga membatasi navigasi; pengujian HTTP bersesi dan pengujian DOM/JavaScript dilakukan secara terpisah. Tidak ada adapter QA yang dipakai aplikasi produksi.

Hasil akhir, metode, jumlah pengujian, dan batas verifikasi dicatat dalam `docs/PENGUJIAN.md` dan `docs/qa/`. Jangan melewati pengujian native MySQL, SMTP, konkurensi, HTTPS, backup-restore, dan browser end-to-end di staging.

## Menjalankan lokal

Memerlukan PHP 8.3/8.4 dan ekstensi pada `php scripts/check-platform.php sqlite`. Composer 2 digunakan untuk memverifikasi atau memasang dependensi. Aset CSS/JS siap pakai dan `vendor/` dari paket awal disertakan. Tidak perlu Node.js hanya untuk membuka aplikasi dengan aset tersebut.

Dari folder proyek:

```bash
composer install
php scripts/check-platform.php sqlite
php scripts/setup.php --demo
php artisan serve
```

Buka `http://127.0.0.1:8000`. Installer membuat `.env` dari contoh jika belum ada, membuat database SQLite lokal, menghasilkan APP_KEY **hanya jika kosong**, menerapkan migrasi tanpa menghapus data, lalu menambahkan demo karena opsi `--demo` diberikan. Pada Windows gunakan perintah PHP yang sama di PowerShell/Terminal.

Bila `.env` sudah ada, installer mempertahankan pilihan database dan kunci di dalamnya. Ubah konfigurasi secara sengaja sebelum menjalankan setup. Jangan menyalin `.env` contoh di atas konfigurasi operasional.

### Akun demo lokal saja

| Peran | Email |
|---|---|
| Admin | `admin@siakad.test` |
| Mahasiswa | `mahasiswa@siakad.test` |
| Mahasiswa kedua | `nadia@siakad.test` |
| Dosen/PA/pembimbing | `dosen@siakad.test` |
| Kaprodi | `kaprodi@siakad.test` |
| Keuangan | `keuangan@siakad.test` |

Kata sandi demo: `DemoSiakad2026!`, atau nilai `DEMO_PASSWORD` sebelum seed. Seeder menolak lingkungan `production`. Gunakan database produksi bersih dan akun admin pribadi; mengganti password demo saja tidak cukup untuk memisahkan data contoh dari data nyata.

## Alur penggunaan

1. Admin melengkapi fakultas, prodi, akun dan profil, kurikulum/prasyarat, semester aktif, kelas, dosen PA, serta pengumuman.
2. Mahasiswa membuat roadmap. Rekomendasi semester berjalan memeriksa prasyarat historis, batas SKS, kuota yang masih tersedia, dan kombinasi jadwal. Mahasiswa mengajukan roadmap; PA menyetujui atau mengembalikan dengan catatan.
3. Mahasiswa memilih kelas dalam roadmap yang disetujui. Draft belum memesan kuota. Pengajuan KRS memesan kuota dan menyimpan referensi roadmap. PA menetapkan KRS final; jadwal, roster dosen, dan dokumen KRS mengikuti data final.
4. Dosen mengisi nilai/presensi peserta kelasnya. Publikasi nilai merupakan tindakan eksplisit; penyimpanan biasa mempertahankan status publikasi sebelumnya. Mahasiswa melihat nilai yang terbit dan presensinya.
5. Keuangan/admin membuat tagihan dan mencatat pembayaran manual. Mahasiswa melihat tagihan dan kuitansinya. Pembayaran tidak boleh melebihi saldo, kehilangan presisi desimal, atau memakai referensi yang sama.
6. Admin menetapkan skripsi/pembimbing; mahasiswa menambahkan bimbingan; dosen yang ditugaskan memberi umpan balik dan memperbarui progres. Tahap selesai konsisten dengan progres 100%.

Pengajuan roadmap/KRS yang masih menunggu dapat ditarik oleh pemiliknya. KRS final tidak dapat diedit lewat formulir mahasiswa. Perubahan nilai, kurikulum, atau semester menandai roadmap lama sebagai tidak lagi sesuai, tanpa menghapus riwayatnya.

## Pemeriksaan dan build

```bash
php vendor/bin/phpunit
php scripts/test-planner.php
php artisan siakad:check
php artisan siakad:check --production --json
composer audit --locked --no-dev
npm ci
npm run build
npm audit --audit-level=high
```

`siakad:check` hanya memeriksa; tidak mengubah data. Kode keluar 1 berarti ada pemeriksaan gagal. Peringatan SMTP/staging tetap memerlukan verifikasi manusia walaupun kode keluar 0. `/up` adalah pemeriksaan liveness Laravel, bukan bukti kesiapan database dan seluruh fitur.

Jangan menjalankan test suite terhadap database operasional. `phpunit.xml` menggunakan SQLite `:memory:`. `phpunit.mysql.xml` menggunakan database khusus **siakad_test** dan kredensial uji lokal yang harus dipisahkan dari produksi. Tes upgrade secara sengaja menghapus tabel pada database uji tersebut.

## Deploy dan upgrade

Baca `docs/DEPLOYMENT.md`. Contoh `.env.production.example`, Nginx, Apache, dan workflow CI disertakan. Root web wajib `public/`. Simpan `.env`, APP_KEY, database, backup, dan log di luar akses web. Deployment sebenarnya, sertifikat, kredensial SMTP/MySQL, dan backup tidak dibuat otomatis oleh paket ini.

Migrasi tambahan bersifat aditif. Roadmap lama tidak diberi checksum palsu; jika perlu digunakan untuk pengajuan baru, tarik/kembalikan pengajuannya lalu buat rencana baru. KRS final dan riwayat nilai/pembayaran lama dipertahankan.

## Aturan dan batas produk

Ambang nilai, batas SKS, serta asumsi semester mendatang berada di `config/academic.php`. Admin mengatur syarat SKS dan batas studi per prodi. Aturan tersebut harus disahkan kampus, bukan dianggap peraturan universal. Planner merupakan heuristik, bukan optimasi global atau jaminan kelulusan. Penawaran dan kelulusan semester mendatang merupakan asumsi.

Paket tidak menambahkan payment gateway, SSO, integrasi PDDikti, unggah dokumen, tanda tangan digital, pendaftaran akun publik, atau infrastruktur multi-node karena fitur/integrasi tersebut tidak tersedia pada kode awal. Pembayaran tetap **pencatatan manual**. Sesi/cache file ditujukan pada satu instance aplikasi; desain penyimpanan bersama diperlukan sebelum horizontal scaling.
