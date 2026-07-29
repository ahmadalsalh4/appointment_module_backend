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
| **Çoklu rol** | `multi@test.com` | `multi123` | Eğitim (hem müşteri hem personel — rol değiştirme panelini test etmek için) |

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

> **Not:** `categories` ve `appointments` typo'ları düzeltildi. `catagory_id` kolon adı (FK) **artık `category_id`** olarak yeniden adlandırıldı — `2026_08_15_000001_rename_catagory_to_category` migrasyonu tüm ortamlarda otomatik olarak çalışır.

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
- Çoklu rol kullanıcısı için `other_roles` doğrulaması
- **State machine** geçişleri (pending → confirmed → completed, terminal koruması)
- **Strict ID karşılaştırmaları** (admin başka admin'in staff'ına erişemez)
- **Self-service category_id reddi** (personel kendi kategorisini değiştiremez)
- **Telefon unique ihlali → 422 translation**
- **Soft-delete randevu geçmişini korur**
- **Filtre validation** (bilinmeyen parametre → 422)

CI: GitHub Actions `.github/workflows/api-ci.yml` Postgres service container ile çalışır.

## Production Deployment (Render)

`render.yaml` Blueprint üç servisi tanımlar:

1. **Postgres** (`pserv`, Starter): 7 günlük PITR.
2. **Web** (`web`, Docker): `Dockerfile` üzerinden derlenir; ortam değişkenleri dashboard link ile sağlanır.
3. **Job** (`job`, Docker): bir seferlik artisan komutları çalıştırmak için `Dockerfile.job`. Dashboard → Manual Run ile tetiklenir.

Healthcheck `/up`. `RUN_MIGRATIONS=true` her container açılışında `migrate --force` çalıştırır; `SEED_DATABASE=true` yalnızca `APP_ENV=local` ile seed adımını çalıştırır, aksi hâlde entrypoint hata verir. `APP_KEY` Render env group'tan sağlanır; mevcut .env üzerine yazılmaz.

Netlify frontend için `CORS_ALLOWED_ORIGINS` env değişkenine Netlify production URL'i + `*.netlify.app` regex'i `config/cors.php` içinde izinlidir.

### Render'da shell yok — nasıl artisan çalıştırılır?

Render web servisleri Shell erişimi sağlamaz. Bunun yerine iki yol var:

**Yol A — HTTP üzerinden (önerilen).** `INTERNAL_ARTISAN_TOKEN` env değişkenini Render dashboard'da ayarlayın; ardından dışarıdan (CI, kendi terminaliniz) şu çağrıyı yapabilirsiniz:

```bash
TOKEN=$(printenv INTERNAL_ARTISAN_TOKEN)
curl -s -X POST https://appointment-module-api.onrender.com/api/internal/artisan \
  -H "X-Internal-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"command":"migrate","flags":["--force"]}'

curl ... -d '{"command":"migrate-status"}'
curl ... -d '{"command":"app-status"}'
curl ... -d '{"command":"dedupe-preview"}'
```

`command` alanı kapalı bir allowlist'ten seçilir (bilinmeyen → 422). `migrate-fresh` ayrıca `CONFIRM_RESET_DB=YES` env değerini ister. Boş token → endpoint 404 döner, varlığı gizlidir.

İzin verilen komutlar:

| `command` (public)        | Altında çalışan                            | Notlar |
|---------------------------|--------------------------------------------|--------|
| `migrate`                 | `migrate --force --no-interaction`         | |
| `migrate-fresh`           | `migrate:fresh --force --no-interaction`   | `CONFIRM_RESET_DB=YES` gerekli |
| `migrate-rollback`        | `migrate:rollback --force --no-interaction` | |
| `migrate-status`          | `migrate:status`                           | salt okunur |
| `config-clear`            | `config:clear`                             | |
| `cache-clear`             | `cache:clear`                              | |
| `route-clear`             | `route:clear`                              | |
| `view-clear`              | `view:clear`                               | |
| `event-clear`             | `event:clear`                              | |
| `optimize`                | `optimize`                                 | |
| `optimize-clear`          | `optimize:clear`                           | |
| `storage-link`            | `storage:link`                             | |
| `queue-work-once`         | `queue:work --once --stop-when-empty`      | |
| `app-status`              | `app:status`                               | salt okunur hata özeti |
| `dedupe-preview`          | `data:dedupe-before-unique --dry-run`      | salt okunur |

**Yol B — Render Job (bir kereye mahsus).** `render.yaml` Blueprint zaten `appointment-module-oneshot` adlı bir `type: job` hizmeti tanımlar. Render dashboard → Job → "Manual Run" ile tetikleyin; çalışacak komut `JOB_COMMAND` env'inden okunur. Örnekler:

```
migrate --force --no-interaction
migrate:fresh --force --no-interaction       (önce CONFIRM_RESET_DB=YES ekleyin)
data:dedupe-before-unique --dry-run
app:status
```

> **Uyarı:** `JOB_COMMAND` boşsa job exit code 64 ile çıkar ve dashboard "failed" gösterir. Render, Job için env var'ı tetikleme zamanında override edebilmenizi sağlar.

## Lisans

MIT
