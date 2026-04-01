# Laravel Dremio Driver (ODBC + API)

[![Latest Stable Version](https://img.shields.io/packagist/v/avia-avian/laravel-dremio-odbc.svg?style=flat-square)](https://packagist.org/packages/avia-avian/laravel-dremio-odbc)
[![Total Downloads](https://img.shields.io/packagist/dt/avia-avian/laravel-dremio-odbc.svg?style=flat-square)](https://packagist.org/packages/avia-avian/laravel-dremio-odbc)
[![License](https://img.shields.io/github/license/avia-avian/laravel-dremio-odbc?style=flat-square)](LICENSE)

Integrasi **Laravel Database Connection** dengan **Dremio** menggunakan dua mode transport:

- **ODBC** (Arrow Flight SQL ODBC)
- **API** (Dremio REST SQL endpoint)

Package ini memudahkan Laravel untuk memilih mode koneksi sesuai kebutuhan deployment.

---

## 🚀 Prasyarat

Jika memakai mode **ODBC**, pastikan sudah menginstal **Dremio ODBC Driver** di server lokal atau server aplikasi:

- [📥 Download Dremio ODBC Driver (Windows, macOS, Linux)](https://www.dremio.com/drivers/)

---

## 📦 Instalasi

Tambahkan package ke project Laravel:

```bash
composer require avia-avian/laravel-dremio-odbc
```

---

## ⚙️ Konfigurasi

### 1. Pilih mode koneksi di `.env`

Pilih salah satu:

```env
DREMIO_CONNECTION=odbc
```

atau

```env
DREMIO_CONNECTION=api
```

### 2A. Konfigurasi mode ODBC di `.env`

```env
DREMIO_DRIVER="Arrow Flight SQL ODBC Driver"
DREMIO_HOST=127.0.0.1
DREMIO_PORT=32010
DREMIO_DATABASE=AVIAN
DREMIO_ENCRYPTION=1
DREMIO_DISABLE_CERTIFICATE_VERIFICATION=1
DREMIO_USERNAME=software.engineer
DREMIO_PASSWORD=secret
```

### 2B. Konfigurasi mode API di `.env`

```env
DREMIO_API_BASE_URL=https://dremio.example.com
DREMIO_API_TOKEN=your_personal_access_token
DREMIO_API_SQL_ENDPOINT=/api/v3/sql
DREMIO_API_TIMEOUT=30
DREMIO_API_VERIFY_SSL=true
# optional: JSON array context
DREMIO_API_CONTEXT=["mySpace","myFolder"]
```

### 3. Tambahkan konfigurasi di `config/database.php`

```php
'connections' => [

    // ... koneksi database lain

    'dremio' => [
        'driver'   => 'dremio',
        'connection' => env('DREMIO_CONNECTION', 'odbc'),

        // ODBC
        'dsn'      => env('DREMIO_DRIVER', 'Arrow Flight SQL ODBC Driver'),
        'host'     => env('DREMIO_HOST', '127.0.0.1'),
        'port'     => env('DREMIO_PORT', '32010'),
        'username' => env('DREMIO_USERNAME'),
        'password' => env('DREMIO_PASSWORD'),
        'database' => env('DREMIO_DATABASE', 'AVIAN'),
        'encryption' => env('DREMIO_ENCRYPTION', 1),
        'disable_cert_verification' => env('DREMIO_DISABLE_CERTIFICATE_VERIFICATION', 1),

        // API
        'api_base_url' => env('DREMIO_API_BASE_URL', ''),
        'api_token' => env('DREMIO_API_TOKEN', ''),
        'api_sql_endpoint' => env('DREMIO_API_SQL_ENDPOINT', '/api/v3/sql'),
        'api_timeout' => env('DREMIO_API_TIMEOUT', 30),
        'api_verify_ssl' => env('DREMIO_API_VERIFY_SSL', true),
        'api_context' => env('DREMIO_API_CONTEXT', null),
    ],

],
```

### 4. Registrasi Service Provider (Laravel < v11)

Jika menggunakan **Laravel 11 ke atas**, package auto-discovery akan berjalan otomatis.  
Namun untuk Laravel versi lama, tambahkan manual di `config/app.php`:

```php
'providers' => [
    // Provider bawaan Laravel...
    App\Providers\AppServiceProvider::class,

    // Tambahkan ini:
    AviaAvian\DremioOdbc\Providers\OdbcServiceProvider::class,
],
```

### 5. Publish Config (opsional)

Jika ingin mengubah konfigurasi default package, jalankan perintah:

```bash
php artisan vendor:publish --provider="AviaAvian\DremioOdbc\Providers\OdbcServiceProvider" --tag=config
```

Ini akan menghasilkan file `config/dremio_odbc.php` yang bisa kamu sesuaikan sesuai kebutuhan.

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

### Error API (`Dremio API base URL is required`)
Pastikan saat `DREMIO_CONNECTION=api`, variabel `DREMIO_API_BASE_URL` sudah diisi.

---

## 📖 Dokumentasi Tambahan

- [Dremio Drivers](https://www.dremio.com/drivers/)  
- [Laravel Database Connections](https://laravel.com/docs/master/database#configuration)  

---

## 📄 License

MIT © Avia-Avian
