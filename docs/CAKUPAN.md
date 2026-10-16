# Peta fitur dan batas verifikasi

| Modul | Jalur utama | Hubungan backend |
|---|---|---|
| Autentikasi | login, logout, lupa/reset, ganti password | users, broker reset, sesi, limiter, token Sanctum |
| Admin | CRUD 10 jenis master | validasi relasi, foreign key, boolean, batas studi, kalender, prasyarat, jadwal |
| Mahasiswa | dashboard, biodata, planner, KRS, nilai, jadwal | profil, kurikulum, nilai terpublikasi, snapshot roadmap, KRS final |
| Dosen PA | review roadmap dan KRS | mahasiswa bimbingan, keputusan/catatan, audit, validasi ulang |
| Dosen pengampu | kelas, nilai, presensi | kelas yang ditugaskan, peserta KRS final, status publikasi/pertemuan |
| Keuangan | tagihan, pembayaran, kuitansi | nominal desimal, kuota saldo, referensi unik, request key, audit |
| Skripsi | penugasan, progres, bimbingan, feedback | pemilik, pembimbing aktif, status/progres konsisten, audit |
| Kaprodi/laporan | ringkasan, filter, ekspor CSV | pembatasan prodi/PA dan escaping formula CSV |
| API | /api/me, /api/academic-summary | Sanctum, data milik pengguna, password tidak diekspos |
| Operasional | /up, siakad:check, installer | liveness, preflight read-only, migrasi aditif, APP_KEY tetap |

“Terhubung” pada tabel berarti handler/relasi pada kode aplikasi, bukan klaim integrasi layanan eksternal. Jumlah/hasil pengujian aktual dan keterbatasannya ada di PENGUJIAN.md. Rendering halaman master diperiksa untuk semua jenis; ini bukan bukti bahwa setiap kombinasi data atau seluruh urutan edit multi-admin sudah dieksplorasi.

Tidak ada integrasi PDDikti, SSO, gateway bank, pengiriman notifikasi massal, unggah dokumen, atau tanda tangan elektronik yang dibuat-buat. Komponen tersebut tidak ditawarkan sebagai fitur selesai pada paket ini.
