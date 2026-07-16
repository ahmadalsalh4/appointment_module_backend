# Randevu Modülü — API Endpoint Listesi

Toplam: **28 endpoint**

Base URL: `http://localhost:8000/api`

---

## 🔓 Herkese Açık (Login Gerekmez)

| Method | Endpoint                                    | Açıklama                                                      |
| ------ | ------------------------------------------- | ------------------------------------------------------------- |
| POST   | `/customer/register`                        | Müşteri kaydı oluşturur                                       |
| POST   | `/customer/login`                           | Müşteri girişi, token döner                                   |
| POST   | `/staff/login`                              | Personel/admin girişi, token döner                            |
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
| POST   | `/appointments`             | Yeni randevu oluşturur (kendi adına, çakışma kontrolüyle) |
| PATCH  | `/appointments/{id}/cancel` | Kendi randevusunu iptal eder                              |
| GET    | `/my-appointments`          | Sadece kendi randevularını listeler                       |

---

## 🔒 Personel/Admin Girişi Gerektirir (`auth:staff`, Rol Bazlı Filtreli)

| Method | Endpoint              | Açıklama                                                                                       |
| ------ | --------------------- | ---------------------------------------------------------------------------------------------- |
| POST   | `/staff/logout`       | Çıkış yapar                                                                                    |
| POST   | `/categories`         | Yeni kategori oluşturur                                                                        |
| PUT    | `/categories/{id}`    | Kategori günceller                                                                             |
| DELETE | `/categories/{id}`    | Kategori siler                                                                                 |
| POST   | `/services`           | Yeni hizmet oluşturur                                                                          |
| PUT    | `/services/{id}`      | Hizmet günceller                                                                               |
| DELETE | `/services/{id}`      | Hizmet siler                                                                                   |
| GET    | `/staff-members`      | Tüm personeli listeler                                                                         |
| POST   | `/staff-members`      | Yeni personel oluşturur (person + staff kaydı)                                                 |
| GET    | `/staff-members/{id}` | Tek personel detayı                                                                            |
| PUT    | `/staff-members/{id}` | Personel bilgisi günceller                                                                     |
| DELETE | `/staff-members/{id}` | Personel siler                                                                                 |
| GET    | `/appointments`       | **Sıradan personel:** sadece kendi randevuları. **Admin:** sadece yönettiği ekibin randevuları |
| GET    | `/appointments/{id}`  | Tek randevu detayı (aynı yetki kontrolü — yetkisi yoksa 403)                                   |
| PUT    | `/appointments/{id}`  | Randevu düzenleme (aynı yetki kontrolü + çakışma kontrolü)                                     |
| DELETE | `/appointments/{id}`  | Randevu silme (aynı yetki kontrolü)                                                            |

---

## Yetkilendirme Kuralları — Özet

- **Müşteri**: sadece kendi oluşturduğu randevuları görebilir/iptal edebilir, başkasının randevusuna erişemez.
- **Sıradan personel (staff)**: sadece kendi üzerine atanmış randevuları görebilir/yönetebilir.
- **Admin**: sadece kendi yönettiği personellerin (`staff.admin_id` ile bağlı) randevularını görebilir. Başka bir admin'e bağlı personelin randevularını göremez.

## Query Parametreleri (Filtreleme)

`GET /appointments` ve `GET /my-appointments` üzerinde kullanılabilir:

- `status_id` — duruma göre filtrele
- `date` — tarihe göre filtrele (`YYYY-MM-DD`)
- `customer_name` — müşteri adına göre arama
