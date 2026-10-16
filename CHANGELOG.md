# Perubahan 16 Oktober 2026

## Akademik
- Perbaikan sintaks Blade pada jadwal dan pemisahan direktif yang menempel pada teks.
- Penanganan semester aktif kosong/ganda dengan pesan yang dapat ditindaklanjuti.
- Roadmap dibatasi pada semester yang sesuai, menyimpan snapshot semester dan checksum input akademik, serta memisahkan riwayat lama.
- Rekomendasi kelas menggunakan kuota submitted/final, penugasan jadwal nonbentrok dengan backtracking terbatas, dan prasyarat yang lulus sebelum semester aktif.
- Penarikan pengajuan roadmap/KRS, referensi roadmap pada KRS, dan validasi ulang sebelum persetujuan.
- Review KRS dapat dilakukan PA setelah jendela pengisian mahasiswa tutup, selama semester tetap aktif dan seluruh syarat masih sesuai.
- Kuota dilepas saat pengajuan ditarik/ditolak; kelas final tidak dapat diubah jadwalnya melalui CRUD biasa. Penambahan kapasitas tetap diperbolehkan.

## Nilai dan presensi
- Roster hanya peserta dengan KRS final; otorisasi dan validasi seluruh input dilakukan sebelum penulisan transaksi.
- Nilai maksimal dua desimal; penyimpanan, publikasi, dan draft memiliki tindakan yang jelas.
- Presensi pertemuan dapat dibuka kembali dan diedit; validasi tanggal, mahasiswa, serta konsistensi tanggal pertemuan.

## Keuangan dan skripsi
- Perhitungan saldo dengan satuan integer sen; validasi desimal dan pelunasan pecahan nominal.
- Idempotency key tagihan dan perlindungan referensi pembayaran duplikat; perubahan beserta audit berada dalam transaksi.
- Penugasan ulang pembimbing oleh admin, pembatasan dosen lama, konsistensi status/progres, dan audit catatan/umpan balik.
- Isian formulir keuangan dan skripsi dipertahankan hanya pada formulir yang mengalami kesalahan.

## Keamanan, master, dan deploy
- Login menormalkan email tanpa mengubah riwayat akun secara massal.
- Ganti kata sandi pribadi, pembatasan byte bcrypt, validasi reset, rotasi remember token, dan pencabutan token API saat reset oleh pemilik/admin.
- URL reset memakai APP_URL; host produksi dibatasi; proxy hanya dari konfigurasi IP/CIDR.
- Header keamanan/no-store, input flash aman dari array yang tidak sesuai, dan penjagaan submit ganda dengan nilai action tetap terkirim.
- Boolean false tampil benar; tahun akademik berurutan; penurunan batas studi/perubahan semester yang merusak KRS dicegah.
- Installer mempertahankan APP_KEY, preflight read-only, contoh konfigurasi server, tes native SQLite/MySQL, CI, dan dokumentasi deployment/upgrade.

## Catatan integrasi
Tidak ada fitur gateway atau layanan eksternal yang dinyatakan terintegrasi tanpa implementasi. Tidak ada perubahan pada file vendor atau versi lock dependensi untuk menyiasati keterbatasan runtime QA.
