# Laravel Dremio ODBC Driver

[![Latest Stable Version](https://img.shields.io/packagist/v/avia-avian/laravel-dremio-odbc.svg?style=flat-square)](https://packagist.org/packages/avia-avian/laravel-dremio-odbc)
[![Total Downloads](https://img.shields.io/packagist/dt/avia-avian/laravel-dremio-odbc.svg?style=flat-square)](https://packagist.org/packages/avia-avian/laravel-dremio-odbc)
[![License](https://img.shields.io/github/license/avia-avian/laravel-dremio-odbc?style=flat-square)](LICENSE)

Integrasi **Laravel Database Connection** dengan **Dremio (Arrow Flight SQL ODBC)**.  
Package ini memudahkan Laravel untuk terkoneksi ke **Dremio Data Lakehouse** melalui **ODBC Driver**.

---

## 🚀 Prasyarat

Sebelum menggunakan package ini, pastikan sudah menginstal **Dremio ODBC Driver** di server lokal atau server aplikasi:

- [📥 Download Dremio ODBC Driver (Windows, macOS, Linux)](https://www.dremio.com/drivers/)

---

## 📦 Instalasi

Tambahkan package ke project Laravel:

```bash
composer require avia-avian/laravel-dremio-odbc
```

---

## ⚙️ Konfigurasi

### 1. Tambahkan koneksi database di `.env`

```env
DREMIO_DRIVER="Arrow Flight SQL ODBC Driver"
DREMIO_HOST=127.0.0.1
DREMIO_PORT=32010
DREMIO_ENCRYPTION=1
DREMIO_DISABLE_CERTIFICATE_VERIFICATION=1
DREMIO_USERNAME=software.engineer
DREMIO_PASSWORD=secret
```

### 2. Tambahkan konfigurasi di `config/database.php`

```php
'connections' => [

    // ... koneksi database lain

    'dremio' => [
        'driver'   => 'odbc',
        'dsn'      => env('DREMIO_DRIVER', 'Arrow Flight SQL ODBC Driver'),
        'host'     => env('DREMIO_HOST', '127.0.0.1'),
        'port'     => env('DREMIO_PORT', '32010'),
        'username' => env('DREMIO_USERNAME'),
        'password' => env('DREMIO_PASSWORD'),
        'database' => env('DREMIO_DATABASE', 'AVIAN'),
        'options'  => [],
    ],

],
```

### 3. Registrasi Service Provider (Laravel < v11)

Jika menggunakan **Laravel 11 ke atas**, package auto-discovery akan berjalan otomatis.  
Namun untuk Laravel versi lama, tambahkan manual di `config/app.php`:

```php
'providers' => [
    // Provider bawaan Laravel...
    App\Providers\AppServiceProvider::class,

    // Tambahkan ini:
    AviaAvian\Odbc\OdbcServiceProvider::class,
],
```

---

## 🛠️ Contoh Penggunaan

Gunakan connection `dremio` seperti koneksi database biasa di Laravel:

```php
$results = DB::connection('dremio')
    ->select('SELECT * FROM Samples."samples.dremio.com"."NYC-taxi-trips" LIMIT 10');

foreach ($results as $row) {
    dump($row);
}
```

---

## ❗ Troubleshooting

### Error `Data source name not found`
Pastikan ODBC driver sudah diinstal dengan benar.

### Error SSL / Certificate
Atur variabel `.env`:
```env
DREMIO_ENCRYPTION=1
DREMIO_DISABLE_CERTIFICATE_VERIFICATION=1
```

### Tidak bisa connect ke Dremio
Periksa apakah port `32010` terbuka dan service Dremio aktif.

---

## 📖 Dokumentasi Tambahan

- [Dremio Drivers](https://www.dremio.com/drivers/)  
- [Laravel Database Connections](https://laravel.com/docs/master/database#configuration)  

---

## 📄 License

MIT © Avia-Avian
