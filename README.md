# Geomin Smart Repository (GSR)

Sistem manajemen dokumen terkendali berbasis web untuk PT Antam, dibangun sesuai standar ISO 17025. GSR mengelola siklus hidup dokumen secara lengkap — mulai dari pembuatan, revisi, distribusi, hingga validasi cetak melalui QR code.

## Fitur Utama

- **Pengendalian Dokumen** — Dokumen internal & eksternal dengan riwayat revisi dan alur persetujuan
- **Kepatuhan ISO 17025** — Dokumen terhubung dengan klausul ISO; distribusi salinan terkendali tercatat
- **Validasi QR Code** — Dua strategi: dinamis (selalu mengarah ke revisi terbaru) dan statis (validasi salinan cetak spesifik via token unik)
- **Log Distribusi** — Mencatat siapa yang mengakses/mencetak revisi tertentu, kapan, dan untuk keperluan apa
- **Stempel PDF** — Menghasilkan salinan terkendali/tidak terkendali dengan watermark dan halaman validasi
- **Perbandingan Versi** — Visual diff antar revisi dokumen
- **Permintaan Perubahan** — Workflow pengajuan perubahan dokumen
- **Pemusnahan Dokumen** — Pencatatan dan pengelolaan dokumen yang telah habis masa retensi

## Tech Stack

| Layer       | Teknologi                          |
| ----------- | ---------------------------------- |
| Backend     | PHP 8.3+, Laravel 13               |
| Admin Panel | Filament 5.6                       |
| Database    | MySQL (dual: `gsr` + `mysql_hris`) |
| Frontend    | Tailwind CSS 4, Vite 8             |
| PDF         | FPDF/FPDI, Spatie PDF-to-Text      |
| QR Code     | SimpleSoftwareIO Simple QRCode     |
| Export      | pxlrbt/filament-excel              |

## Persyaratan Sistem

- PHP 8.3+
- MySQL 8.0+
- Node.js 20+
- Composer 2

## Instalasi

```bash
# Clone repository
git clone <repository-url> gsr
cd gsr

# Install dependencies
composer install
npm install

# Konfigurasi environment
cp .env.example .env
php artisan key:generate

# Migrasi & seed database
php artisan migrate --seed

# Build assets
npm run build
```

## Development

```bash
# Jalankan server, queue, log viewer, dan Vite sekaligus
composer dev

# Atau jalankan terpisah:
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
npm run dev
```

## Perintah Berguna

```bash
# Jalankan test
composer test

# Format kode (PSR-12)
./vendor/bin/pint

# Fresh migration dengan seed
php artisan migrate:fresh --seed

# Build assets untuk production
npm run build
```

## Struktur Database

- **`gsr`** — Database utama aplikasi (dokumen, revisi, kategori, distribusi, dll.)
- **`mysql_hris`** — Database HRIS eksternal (tabel users)

## Peran Pengguna

| Role        | Akses                                      |
| ----------- | ------------------------------------------ |
| `admin`     | Akses penuh termasuk edit dokumen          |
| `manajemen` | Akses penuh termasuk edit dokumen          |
| User biasa  | Lihat dokumen, generate salinan terkendali |

## Lisensi

Proprietary — PT Antam. Hak cipta dilindungi.
