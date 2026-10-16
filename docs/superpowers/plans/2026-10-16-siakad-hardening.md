# SIAKAD Hardening Implementation Plan

**Goal:** memperbaiki alur yang tersedia dan mengemas aplikasi beserta verifikasi yang jujur.
**Architecture:** mempertahankan Laravel 12, Eloquent, Blade dan aset lokal; menempatkan validasi lintas modul di service agar controller dan UI memakai aturan yang sama.
**Tech Stack:** PHP ^8.3, Laravel 12, MySQL/SQLite, Tailwind 3 dan JavaScript.
**Spec:** `docs/superpowers/specs/2026-10-16-siakad-design.md`.

## Global Constraints
Tidak ada deploy/publish aktual, kredensial baru tidak dikirim di arsip, migrasi harus mempertahankan data lama, tidak mengganti aturan kampus dengan asumsi hukum, shim QA tidak digunakan produksi.

## Review Focus
Input boolean false; nominal/grade lebih dari dua desimal; pergantian semester dengan pengajuan tertunda; dua petugas menyimpan referensi sama; pengguna membuka data milik orang lain.

## Tugas
- [x] 1. Baseline: jalankan planner, bootstrap Laravel dan regresi awal; catat batas runtime.
- [x] 2. Master dan akses: tulis tes boolean/relasi/semester/password, jalankan RED, perbaiki controller/model/rules, jalankan suite.
- [x] 3. Planner/KRS: tes pengajuan lama, prasyarat historis, snapshot, withdrawal dan review setelah jendela KRS; perbaiki service/controller/view dan migrasi; jalankan suite.
- [x] 4. Pengajaran: tes presisi nilai, status publikasi, mahasiswa tidak berhak, presensi GET/edit; perbaiki lalu jalankan suite.
- [x] 5. Keuangan/skripsi: tes nominal dan transaksi, idempotensi, batas peran/progres/pembimbing; perbaiki lalu jalankan suite.
- [x] 6. UI/runtime: render/alur yang dicakup suite, HTTP nyata lintas peran, dan DOM browser terpisah; perbaiki state kosong, form, responsif dan keamanan HTTP. Browser end-to-end masih menjadi gerbang staging karena navigasi diblokir kebijakan.
- [x] 7. Deploy: tes preflight, key tetap, manifest, asset parity; sertakan config server/CI; perbarui laporan dan sanitasi ZIP.

Setiap tugas: tes gagal yang menjelaskan bug, perbaikan terarah, suite penuh, inspeksi diff dan catatan hasil.

## Batas penyelesaian
Implementasi dan verifikasi lokal di atas selesai dalam cakupan bukti yang tersedia. Native MySQL/PDO/PHPUnit, audit dependensi, rebuild Tailwind, SMTP/HTTPS dan browser end-to-end belum lulus atau belum dijalankan. Status paket tetap kandidat staging; lihat `docs/PENGUJIAN.md`.
