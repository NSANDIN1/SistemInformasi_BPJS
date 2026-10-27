# Sistem Internal BPJS Ketenagakerjaan Kanwil Sumbagsel

## Struktur (file utama langsung di root repo)
```
index.html            <- halaman login (wajib di root)
dbpelayanan.html
dbkepesertaan.html
.nojekyll
js/auth-guard.js
kepesertaan/
pelayanan/
kearsipan/            <- PHP, tidak jalan di GitHub Pages
database/
```

## Deploy di GitHub Pages
1. Upload ISI folder ini (bukan zip, bukan folder pembungkus) ke root repo.
2. Settings -> Pages -> Branch `main`, folder `/ (root)`.
3. Buka `https://USERNAME.github.io/NAMA-REPO/`.

## Yang berfungsi di GitHub Pages
Login, Kepesertaan, Pelayanan (tampilan & laporan dari `data.js`).

## Butuh server PHP
Modul Kearsipan (`kearsipan/*.php`, `database/kearsipan.db`) dan fitur unggah Pelayanan (`pelayanan/api.php`).
Jalankan lokal: `php -S localhost:8000`
