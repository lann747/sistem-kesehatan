# Rafflesia Sehat

Sebuah sistem informasi kesehatan yang dikembangkan untuk mengelola data pasien, rekam medis, dan informasi rumah sakit.

## 🚀 Fitur Utama

- **Manajemen Data Pasien**
  - Pendaftaran pasien baru
  - Penyimpanan data demografis pasien
  - Riwayat kunjungan pasien

- **Rekam Medis Elektronik**
  - Input dan penyimpanan catatan medis
  - Riwayat penyakit
  - Data pengobatan dan resep

- **Manajemen Rumah Sakit**
  - Data dokter dan tenaga medis
  - Manajemen jadwal praktek
  - Informasi poliklinik dan ruangan

## 🛠 Teknologi yang Digunakan

- **Backend**: 
  - PHP (Native)
  - MySQL Database

- **Frontend**:
  - HTML5
  - CSS3
  - JavaScript
  - Bootstrap (UI Framework)

- **Lainnya**:
  - XAMPP (Development Environment)

## 📋 Prasyarat Sistem

- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Web Server (Apache/Nginx)
- XAMPP (disarankan untuk development)

## 🚀 Instalasi dan Konfigurasi

### 1. Clone Repository
git clone https://github.com/lann747/sistem-kesehatan.git
cd sistem-kesehatan

### 2. Setup Database
- Import file SQL yang tersedia di folder `database/` ke MySQL
- Konfigurasi koneksi database di file `config.php`

### 3. Konfigurasi Web Server
- Letakkan folder project di directory web server (htdocs untuk XAMPP)
- Pastikan folder memiliki permission yang tepat

### 4. Konfigurasi Aplikasi
Edit file `config.php` dengan detail database Anda:
$host = 'localhost';
$username = 'your_username';
$password = 'your_password';
$database = 'sistem_kesehatan';

### 5. Akses Aplikasi
Buka browser dan akses:
http://localhost/sistem-kesehatan

## 📁 Struktur Project

sistem-kesehatan/
├── assets/          # File CSS, JS, images
├── config/          # Konfigurasi database
├── database/        # File SQL database
├── pages/           # Halaman aplikasi
│   ├── pasien/      # Modul pasien
│   ├── dokter/      # Modul dokter
│   └── rekam-medis/ # Modul rekam medis
├── includes/        # File include PHP
└── index.php        # File utama

## 👥 Penggunaan

### Untuk Admin:
1. Login ke sistem
2. Kelola data pasien dan dokter
3. Lihat laporan dan statistik
4. Manage user accounts

### Untuk Dokter:
1. Akses rekam medis pasien
2. Input catatan medis
3. Lihat jadwal praktek

### Untuk Pasien:
1. Melihat riwayat kunjungan
2. Mengakses informasi medis pribadi

## 🔧 Development

### Menjalankan di Local Environment:
1. Install XAMPP
2. Clone repository ke folder `htdocs`
3. Start Apache dan MySQL di XAMPP
4. Import database dari folder `database/`
5. Akses melalui `http://localhost/sistem-kesehatan`

### Customization:
- Edit file CSS di folder `assets/css/` untuk mengubah tampilan
- Modifikasi logika bisnis di file PHP yang sesuai
- Tambahkan fitur baru di folder `pages/`

## 🤝 Kontribusi

Kontribusi selalu diterima! Silakan:

1. Fork project ini
2. Buat branch fitur baru (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

## 📝 License

Distributed under the Alan inc. Lihat `LICENSE` untuk lebih detail.

## 📞 Kontak

Lann - [GitHub](https://github.com/lann747)

Link Project: [https://github.com/lann747/sistem-kesehatan](https://github.com/lann747/sistem-kesehatan)

## ⚠️ Catatan

- Pastikan backup database secara berkala
- Sistem ini masih dalam tahap pengembangan
- Disarankan untuk testing di environment development terlebih dahulu
