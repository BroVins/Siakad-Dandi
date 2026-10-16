# SIAKAD: audit dan penyempurnaan aplikasi

## Tujuan
Memperbaiki fitur yang sudah tersedia tanpa mengganti arsitektur Laravel/Blade, menjaga hak akses lima peran, menghubungkan alur akademik, serta menyediakan paket dan bukti pengujian yang dapat direproduksi. Data sumber dan ZIP asli tidak diubah.

## Ruang lingkup dan keputusan
1. Pengguna: login, logout, reset dan perubahan kata sandi; data pribadi dan batas akses konsisten.
2. Master: validasi relasi, boolean, riwayat akademik, periode, prasyarat dan jadwal.
3. Planner dan KRS: prasyarat historis, snapshot rencana, kuota, jadwal, persetujuan, penarikan pengajuan dan transisi semester.
4. Pengajaran: nilai, publikasi yang disengaja, pembulatan dan presensi dapat diperbarui tanpa menghapus data lain.
5. Keuangan: nominal eksak hingga dua desimal, transaksi atomik dan referensi unik, tampilan sisa serta kwitansi. Pembayaran tetap manual, bukan integrasi gateway yang belum diberikan.
6. Skripsi: penugasan, revisi pembimbing oleh admin, progres konsisten, catatan dan umpan balik berbasis kepemilikan.
7. Operasional: rahasia tidak dikemas, instalasi berulang tidak mengganti APP_KEY, pemeriksaan kesiapan, konfigurasi server, CI dan panduan deploy.

## Batas verifikasi
PHP 8.4 CLI tersedia, tetapi pdo_sqlite/pdo_mysql, DOM, XMLWriter dan mbstring tidak tersedia; unduhan jaringan container gagal. Gunakan adapter SQLite FFI **hanya untuk pengujian** guna menjalankan aplikasi Laravel aktual. Adapter bukan runtime produksi dan bukan pengganti uji native PDO/MySQL. Uji SMTP eksternal, server tujuan, concurrency MySQL serta audit advisori dependensi tetap memerlukan lingkungan dengan jaringan dan ekstensi lengkap. Tidak boleh menyatakan produksi bebas bug.

## Kriteria penerimaan
Setiap perbaikan memiliki regresi yang gagal sebelum perubahan dan lulus setelahnya; semua role/halaman diuji sejauh runtime memungkinkan; perubahan source terpisah dari shim QA; ZIP tidak berisi kredensial, sesi, database atau log privat. Uji native dan deployment yang belum dijalankan dinyatakan eksplisit.
