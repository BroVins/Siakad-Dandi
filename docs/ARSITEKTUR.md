# Arsitektur dan invariant

Browser menggunakan Blade dan formulir HTTP biasa, bukan frontend statis yang berpura-pura menyimpan data. Route menjalankan middleware autentikasi/peran, controller memvalidasi input, service memeriksa aturan lintas entitas, dan Eloquent menulis database dalam transaksi.

`AcademicService` menghitung transkrip terbaik, SKS lulus, IPS, batas KRS, dan semester aktif. `PlannerEngine` menyusun urutan prasyarat/SKS; `Timetable` mencari kombinasi kelas semester berjalan dengan pencarian terbatas. `PlannerService` menyimpan snapshot, menangani submit/review/withdraw dan ketersediaan. `KrsService` menangani pilihan, kapasitas, referensi roadmap, status, serta persetujuan.

Penguncian mahasiswa/KRS/kelas/tagihan menjaga perubahan transaksi utama. Klaim keselamatan konkurensi tetap membutuhkan pengujian MySQL nyata; SQLite FFI tidak membuktikan karakteristik row-lock MySQL. Draft KRS tidak mereservasi kuota; submitted dan approved mereservasi. KRS approved tidak dapat diubah mahasiswa.

Nilai hanya dapat ditulis pengampu untuk peserta final. Nilai terpublikasi masuk ke ringkasan mahasiswa; nilai draf tidak. Prasyarat KRS harus lulus sebelum term saat ini. `Money` mengubah nominal ke integer sen sebelum perbandingan/penjumlahan saldo; nilai yang dikirim ke database dinormalisasi ke dua desimal.

Keamanan meliputi session authentication, CSRF, pembatasan peran/objek, rate limiter, hash kata sandi, rotasi token, canonical reset URL, host/proxy allowlist, header browser, dan normalisasi input lama setelah validasi. Audit tidak mencatat kata sandi atau token. Skripsi mempertahankan catatan lama saat admin mengganti pembimbing, tetapi otorisasi feedback mengikuti pembimbing sekarang.

Adapter FFI di `tools/qa/offline/` terpisah dari bootstrap aplikasi. Produksi selalu membutuhkan PDO native. Konfigurasi sesi/cache default menggunakan file, sehingga bukan arsitektur siap horizontal scaling tanpa pengembangan tambahan.
