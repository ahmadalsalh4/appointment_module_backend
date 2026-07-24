# Randevu Yönetim Modülü — Backend (API)

Kullanıcıların randevularını görüntüleyebildiği ve yönetebildiği randevu yönetim modülünün **backend** tarafıdır. **Laravel 13** ile geliştirilmiş, çoklu guard (müşteri / personel / admin) yapısına sahip bir REST API sunar.

> Frontend: [`appointment-module-frontend`](../appointment-module-frontend) (React 19 + Vite + TypeScript + Tailwind CSS)

## Kullanılan Teknolojiler

- **PHP 8.3+**
- **Laravel 13**
- **Laravel Sanctum** — token tabanlı kimlik doğrulama
- **SQLite** (varsayılan) / MySQL / PostgreSQL
- **Pest** — test
- **Vite + Tailwind CSS** — Laravel ile gelen varlıklar için

## Modül Kapsamı (API Tarafı)

- Üç ayrı rol için kimlik doğrulama: `customer`, `staff`, `admin`
- Hizmet, kategori ve personel yönetimi (admin)
- **Randevu** oluşturma, düzenleme, iptal etme
- Randevu listeleme; **durum**, **personel** ve **tarih** filtreleme
- **Müşteri adına** göre arama
- **Müsaitlik** kontrolü ile daha önce rezerve edilmiş / uygun olmayan saatlerin seçilememesi
- Personel çalışma saatleri kontrolü (09:00-12:00, 13:00-17:00, öğle arası kapalı)
- Şifre doğrulama (`password_confirmation` zorunlu)
- Mobil, tablet ve masaüstü cihazlara uyumlu JSON çıktıları

## Gereksinimler

- PHP **8.3** veya üzeri
- Composer
- Node.js & npm
- SQLite (varsayılan) veya desteklenen başka bir veritabanı

## Kurulum

```bash
# 1. PHP bağımlılıklarını yükle
composer install

# 2. Ortam dosyasını kopyala ve uygulama anahtarı üret
cp .env.example .env
php artisan key:generate

# 3. Veritabanı ayarı (.env)
#    Varsayılan: DB_CONNECTION=sqlite
touch database/database.sqlite

# 4. Migrasyonları çalıştır
php artisan migrate

# 5. Örnek verileri yükle (kategoriler, hizmetler, personeller, müşteriler, randevular)
php artisan db:seed

# 6. (İsteğe bağlı) JS bağımlılıkları ve varlıklar
npm install
npm run build
```

Veya sıfırdan tam kurulum:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
```

## Çalıştırma

```bash
# Tüm servisleri birlikte (server + queue + log + vite)
composer dev

# Veya tek tek
php artisan serve            # API: http://localhost:8000
php artisan queue:listen
php artisan pail             # log izleyici
npm run dev                  # vite
```

API taban URL'si: `http://localhost:8000/api` (Laravel Valet/Herd kullanıyorsanız `http://appointment_module_backend.test/api`)

## Test Hesapları (seed sonrası)

| Rol | E-posta | Şifre | Kategori |
| --- | --- | --- | --- |
| Admin | `admin@test.com` | `admin123` | — |
| Personel | `selin@test.com` | `staff123` | Eğitim (Matematik Öğretmeni) |
| Personel | `murat@test.com` | `staff123` | Eğitim (İngilizce Eğitmeni) |
| Personel | `ahmet@test.com` | `staff123` | Yazılım (Yazılım Geliştirici) |
| Personel | `burcu@test.com` | `staff123` | Yazılım (UI/UX Tasarımcısı) |
| Personel | `huseyin@test.com` | `staff123` | Temizlik (Temizlik Uzmanı) |
| Personel | `sevgi@test.com` | `staff123` | Temizlik (Ev Temizlik Personeli) |
| Müşteri | `ahmad@test.com` | `customer123` | — |
| Müşteri | `elif@test.com` | `customer123` | — |
| Müşteri | `burak@test.com` | `customer123` | — |

## API Uç Noktaları Özeti

Tüm kimlik doğrulama gerektiren uç noktalar `Authorization: Bearer <token>` başlığı ile kullanılır.

### Herkese Açık — Kimlik Doğrulama

| Method | Endpoint               | Açıklama                  |
| ------ | ---------------------- | ------------------------- |
| POST   | `/customer/register`   | Yeni müşteri kaydı (şifre doğrulama zorunlu) |
| POST   | `/customer/login`      | Müşteri girişi            |
| POST   | `/staff/login`         | Personel girişi           |
| POST   | `/admin/login`         | Admin girişi              |

### Herkese Açık — Katalog & Müsaitlik

| Method | Endpoint                              | Açıklama                              |
| ------ | ------------------------------------- | ------------------------------------- |
| GET    | `/categories`                         | Kategorileri listele                  |
| GET    | `/categories/{category}`              | Kategori detayı                       |
| GET    | `/services`                           | Hizmetleri listele                    |
| GET    | `/services/{service}`                 | Hizmet detayı                         |
| GET    | `/services/{service}/staff`           | Hizmete uygun personelleri listele    |
| GET    | `/categories/{category}/staff`        | Kategorideki personelleri listele     |
| GET    | `/availability`                       | Tarih/saat müsaitlik kontrolü         |

### Müşteri (`auth:customer`)

| Method | Endpoint                       | Açıklama                          |
| ------ | ------------------------------ | --------------------------------- |
| POST   | `/customer/logout`             | Çıkış                             |
| POST   | `/appointments`                | **Yeni randevu oluştur**          |
| PATCH  | `/appointments/{id}/cancel`    | **Randevuyu iptal et**            |
| GET    | `/my-appointments`             | Kendi randevularını listele       |
| GET    | `/my-appointments/{id}`        | Randevu detayı                    |
| GET    | `/customer/profile`            | Profil görüntüle                  |
| PUT    | `/customer/profile`            | Profil güncelle                   |

### Personel (`auth:staff`)

| Method | Endpoint                                | Açıklama                          |
| ------ | --------------------------------------- | --------------------------------- |
| POST   | `/staff/logout`                         | Çıkış                             |
| GET    | `/staff/appointments`                   | Atanmış randevuları listele       |
| GET    | `/staff/appointments/{id}`              | Randevu detayı                    |
| PATCH  | `/staff/appointments/{id}/status`       | Randevu durumunu güncelle         |
| GET    | `/staff/profile`                        | Profil görüntüle                  |
| PUT    | `/staff/profile`                        | Profil güncelle                   |

### Admin (`auth:admin`)

| Method | Endpoint                                | Açıklama                          |
| ------ | --------------------------------------- | --------------------------------- |
| POST   | `/admin/logout`                         | Çıkış                             |
| GET    | `/admin/profile`                        | Profil görüntüle                  |
| PUT    | `/admin/profile`                        | Profil güncelle                   |
| GET/POST/PUT/DELETE | `/categories`           | Kategori yönetimi                 |
| GET/POST/PUT/DELETE | `/services`             | Hizmet yönetimi                   |
| GET/POST/PUT/DELETE | `/staff-members`        | Personel yönetimi                 |
| GET    | `/appointments`                         | Tüm randevuları listele           |
| GET    | `/appointments/{id}`                    | Randevu detayı                    |
| PUT    | `/appointments/{id}`                    | **Randevu düzenle**               |
| DELETE | `/appointments/{id}`                    | Randevu sil                       |

## Filtreleme & Arama (Randevu Listesi)

Admin (`GET /appointments`), müşteri (`GET /my-appointments`) ve personel (`GET /staff/appointments`) uç noktaları aşağıdaki sorgu parametrelerini destekler:

| Parametre        | Açıklama                            | Admin | Müşteri | Personel |
| ---------------- | ----------------------------------- | :---: | :-----: | :------: |
| `status_id`      | Randevu durumuna göre filtrele      | ✅ | ✅ | ✅ |
| `staff_id`       | Personele göre filtrele             | ✅ | ✅ | — |
| `date`           | Tarihe göre filtrele                | ✅ | ✅ | ✅ |
| `customer_name`  | Müşteri adına göre arama            | ✅ | — | ✅ |

Örnekler:

```
GET /api/appointments?status_id=1&staff_id=3&date=2026-07-24&customer_name=ahmet
GET /api/my-appointments?status_id=2&staff_id=2&date=2026-07-25
GET /api/staff/appointments?status_id=1&customer_name=elif
```

## Proje Yapısı

```
app/
├── Http/Controllers/      # API controller'ları
│   ├── AdminAuthController.php
│   ├── AdminProfileController.php
│   ├── AppointmentController.php
│   ├── AvailabilityController.php
│   ├── CategoryController.php
│   ├── CustomerAuthController.php
│   ├── CustomerProfileController.php
│   ├── ServiceController.php
│   ├── StaffAuthController.php
│   ├── StaffController.php
│   └── StaffProfileController.php
└── Models/                # Eloquent modelleri (Admin, Staff, Customer, Appointment, Category, Service, Person, Status, User)
database/
├── migrations/            # 12 migrasyon (categories, appointments, …)
└── seeders/               # 7 seeder
routes/
├── api.php                # API rotaları (ana giriş noktası)
├── web.php
└── console.php
config/
```

### Veritabanı Tabloları

| Tablo | Açıklama |
| --- | --- |
| `persons` | Tüm kullanıcıların ortak kişisel bilgileri |
| `admin` | Admin hesapları |
| `staff` | Personel hesapları |
| `customers` | Müşteri hesapları |
| `categories` | Hizmet kategorileri (Eğitim, Yazılım, Temizlik) |
| `services` | Hizmetler (kategorilere bağlı) |
| `statuses` | Randevu durumları (pending, confirmed, completed, cancelled) |
| `appointments` | Randevular |

> **Not:** `catagorys` ve `appointmets` typo'ları düzeltildi — artık `categories` ve `appointments`. Ancak `catagory_id` kolon adı (FK) mevcut veriyle uyumluluk için olduğu gibi bırakıldı.

## Testler

```bash
composer test
# veya
php artisan test
```

Testler **Pest** ile yazılmıştır. Mevcut testler:

- Çalışma saatleri dışında randevu almayı reddetme
- Öğle arasını kapsayan randevuyu reddetme
- Çalışma saatleri içinde randevu oluşturma
- Admin'ler arası personel izolasyonu
- Profil güncelleme uç noktaları
- Tamamlanmış randevuyu iptal etmeyi reddetme

## Lisans

MIT
