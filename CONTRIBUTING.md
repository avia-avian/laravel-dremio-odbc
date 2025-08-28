# Contributing Guide

Terima kasih sudah tertarik untuk berkontribusi di **Laravel Dremio ODBC Driver** 🚀  
Berikut panduan singkat agar kontribusi kamu lebih mudah dan terstruktur.

---

## 🐛 Melaporkan Bug
1. Pastikan bug belum pernah dilaporkan di [Issues](../../issues).
2. Sertakan langkah reproduksi yang jelas, versi Laravel, versi PHP, dan environment.
3. Jika bisa, tambahkan screenshot atau log error.

---

## 💡 Mengajukan Fitur Baru
1. Buat [issue baru](../../issues) dengan label `enhancement`.
2. Jelaskan use-case dan alasan fitur tersebut penting.

---

## 🔧 Mengembangkan / Menambahkan Kode
1. Fork repository ini.
2. Clone repository hasil fork:
   ```bash
   git clone https://github.com/<username>/laravel-dremio-odbc.git
   ```
3. Buat branch baru untuk perubahanmu:
   ```bash
   git checkout -b feature/nama-fitur
   ```
4. Install dependencies:
   ```bash
   composer install
   ```
5. Lakukan perubahan dan commit dengan pesan yang jelas:
   ```bash
   git commit -m "feat: menambahkan konfigurasi encryption dari env"
   ```
6. Push branch:
   ```bash
   git push origin feature/nama-fitur
   ```
7. Buat Pull Request (PR) ke repository utama.

---

## 📜 Kode Style
- Ikuti standar PSR-12 untuk PHP.
- Gunakan nama variabel dan method yang jelas.
- Tambahkan PHPDoc bila perlu.

---

## ✅ Testing
- Sebelum membuat PR, pastikan semua test berjalan lancar:
  ```bash
  ./vendor/bin/phpunit
  ```

---

## 🤝 Code of Conduct
Harap tetap sopan dan profesional saat berdiskusi.  
Kami ingin menjaga komunitas yang ramah untuk semua orang.

---

Terima kasih atas kontribusinya 🙌  
