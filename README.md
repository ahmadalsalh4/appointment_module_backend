# Randevu Modülü — API Endpoint Listesi

Toplam: **32 endpoint**

Base URL: `http://localhost:8000/api`

---

## 🔓 Herkese Açık (Login Gerekmez)

| Method | Endpoint                                    | Açıklama                                                      |
| ------ | ------------------------------------------- | ------------------------------------------------------------- |
| POST   | `/customer/register`                        | Müşteri kaydı oluşturur                                       |
| POST   | `/customer/login`                           | Müşteri girişi, token döner                                   |
| POST   | `/staff/login`                              | Personel girişi, token döner                                  |
| POST   | `/admin/login`                              | Admin girişi, token döner                                     |
| GET    | `/categories`                               | Tüm kategorileri listeler                                     |
| GET    | `/categories/{id}`                          | Tek kategori detayı                                           |
| GET    | `/services`                                 | Tüm hizmetleri listeler (`?catagory_id=` ile filtrelenebilir) |
| GET    | `/services/{id}`                            | Tek hizmet detayı                                             |
| GET    | `/availability?staff_id=&service_id=&date=` | Belirli personel + hizmet + tarih için boş saatleri döner     |

---

## 🔒 Müşteri Girişi Gerektirir (`auth:customer`)

| Method | Endpoint                    | Açıklama                                                  |
| ------ | --------------------------- | --------------------------------------------------------- |
| POST   | `/customer/logout`          | Çıkış yapar, token'ı iptal eder                           |
| GET    | `/customer/profile`         | profil goster                                             |
| POST   | `/appointments`             | Yeni randevu oluşturur (kendi adına, çakışma kontrolüyle) |
| GET    | `/appointments/{id}`        | Tek randevu                                               |
| PATCH  | `/appointments/{id}/cancel` | Kendi randevusunu iptal eder                              |
| GET    | `/my-appointments`          | Sadece kendi randevularını listeler                       |

---

## 🔒 Personel Girişi Gerektirir (`auth:staff`)

Sıradan personel — sadece kendi üzerine atanmış randevulara erişebilir. Kategori/hizmet/personel yönetimi ve diğer personelin randevularına erişimi **yok**.

| Method | Endpoint                          | Açıklama                                                                                          |
| ------ | --------------------------------- | ------------------------------------------------------------------------------------------------- |
| POST   | `/staff/logout`                   | Çıkış yapar                                                                                       |
| GET    | `/staff/profile`                  | profile goster                                                                                    |
| GET    | `/staff/appointments`             | Sadece kendi randevularını listeler (`?status_id=`, `?date=` ile filtrelenebilir)                 |
| GET    | `/appointments/{id}`              | Tek randevu                                                                                       |
| PATCH  | `/staff/appointments/{id}/status` | Kendi randevusunun durumunu günceller (örn. tamamlandı işaretleme). Body: `{ "state_id": <int> }` |

---

## 🔒 Admin Girişi Gerektirir (`auth:admin`)

Admin — sadece kendi yönettiği personellerin (`staff.admin_id` ile bağlı) randevularını görebilir/yönetebilir, ayrıca kategori/hizmet/personel yönetimi yapabilir.

| Method | Endpoint              | Açıklama                                                                                                        |
| ------ | --------------------- | --------------------------------------------------------------------------------------------------------------- |
| POST   | `/admin/logout`       | Çıkış yapar                                                                                                     |
| GET    | `/admin/profile`      | profile goster                                                                                                  |
| POST   | `/categories`         | Yeni kategori oluşturur                                                                                         |
| PUT    | `/categories/{id}`    | Kategori günceller                                                                                              |
| DELETE | `/categories/{id}`    | Kategori siler                                                                                                  |
| POST   | `/services`           | Yeni hizmet oluşturur                                                                                           |
| PUT    | `/services/{id}`      | Hizmet günceller                                                                                                |
| DELETE | `/services/{id}`      | Hizmet siler                                                                                                    |
| GET    | `/staff-members`      | Tüm personeli listeler                                                                                          |
| POST   | `/staff-members`      | Yeni personel oluşturur (person + staff kaydı, otomatik olarak giriş yapan admin'e bağlanır)                    |
| GET    | `/staff-members/{id}` | Tek personel detayı                                                                                             |
| PUT    | `/staff-members/{id}` | Personel bilgisi günceller                                                                                      |
| DELETE | `/staff-members/{id}` | Personel siler                                                                                                  |
| GET    | `/appointments`       | Sadece yönettiği ekibin randevularını listeler (`?status_id=`, `?date=`, `?customer_name=` ile filtrelenebilir) |
| GET    | `/appointments/{id}`  | Tek randevu detayı (yetki kontrolüyle — kendi ekibi değilse 403)                                                |
| PUT    | `/appointments/{id}`  | Randevu durumu günceller. Body: `{ "state_id": <int> }`                                                         |
| DELETE | `/appointments/{id}`  | Randevu siler (yetki kontrolüyle)                                                                               |

---

## Yetkilendirme Kuralları — Özet

Sistemde **üç ayrı, birbirinden bağımsız guard** var (`auth:customer`, `auth:staff`, `auth:admin`) — her biri kendi tablosundan (`customers`, `staff`, `admin`) login olur, ortak bir "staff üzerinden admin" mantığı yoktur.

- **Müşteri**: sadece kendi oluşturduğu randevuları görebilir/iptal edebilir.
- **Sıradan personel (staff)**: sadece kendi üzerine atanmış randevuları görebilir, durumunu güncelleyebilir. Kategori/hizmet/personel yönetimine erişemez.
- **Admin**: sadece kendi yönettiği personellerin (`staff.admin_id` ile bağlı) randevularını görebilir/yönetebilir, ayrıca kategori/hizmet/personel CRUD işlemlerini yapabilir. Başka bir admin'in ekibine erişemez.

## Query Parametreleri (Filtreleme)

`GET /appointments`, `GET /staff/appointments` ve `GET /my-appointments` üzerinde kullanılabilir:

- `status_id` — duruma göre filtrele
- `date` — tarihe göre filtrele (`YYYY-MM-DD`)
- `customer_name` — müşteri adına göre arama (sadece `/appointments`'ta)

## Durum (Status) ID'leri

```
1 = pending (bekliyor)
2 = confirmed (onaylandı)
3 = completed (tamamlandı)
4 = cancelled (iptal edildi)
```
