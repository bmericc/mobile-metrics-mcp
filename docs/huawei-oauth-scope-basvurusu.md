# Huawei AppGallery Connect — OAuth scope başvurusu (taslak)

Huawei, Connect API'ye OAuth client modunda erişimi yalnızca başvurusu onaylanan platform
geliştiricilerine açıyor (bkz. Connect API > Getting Started > OAuth Client Mode). Başvuru
`agconnect@huawei.com` adresine e-posta ile yapılır; ekte Huawei'nin "AppGallery Connect API Scope
Application Form.xlsx" şablonu doldurulup gönderilir. Şablon dokümantasyon sayfasından indirilir.

**Gönderildi: 2026-10-23**, bmericc@gmail.com → agconnect@huawei.com (Gmail thread `1a16955c7f347c58`). Ek: `AppGallery Connect API Scope Application Form - Kalehost Metrics.xlsx` (bu klasörde). Şirket adı olarak "Bahri CANLI (bireysel)" kullanıldı.

## Bilinen değerler

| Alan | Değer |
|---|---|
| Developer account | Bahri CANLI (AGC developer ID `30090000034213549`) |
| Server app | Kalehost Metrics Web — App ID `101653523127285960` (AGC projesi "Kalehost Metrics") |
| OAuth client ID (Huawei ID) | `119484473` |
| Redirect URL | `https://metrics.kalehost.net/auth/huawei/callback` (bağlantı için ayrı callback eklenecek: `https://metrics.kalehost.net/connect/app_gallery/callback`) |
| İstenen scope | `https://www.huawei.com/auth/agc/report/read` (Reports API) |
| Ek soru | Comments API (`/api/reviews/v1/manage/dev/reviews`) için OAuth scope'u var mı? Dokümandaki scope listesinde yok. |

## E-posta

**To:** agconnect@huawei.com
**Subject:** `[OAuth service request]-[<Company name>]-[30090000034213549]-[101653523127285960]`

```
Hello AppGallery Connect team,

We would like to apply for OAuth client mode access to the AppGallery Connect API.

Product: Kalehost Metrics (https://metrics.kalehost.net)
Kalehost Metrics is a web dashboard that lets app developers see their app statistics
from Google Play, the App Store and AppGallery in one place, and analyse them with AI
assistants. Today our users connect their AppGallery account by uploading a Service
Account key file. We would like to replace this with "Sign in with HUAWEI ID" so that
developers can authorise read-only access with their own team role, without handling keys.

Requested scope:
- https://www.huawei.com/auth/agc/report/read  (Reports API: download and installation reports)

We only read data; we do not publish apps or change any settings.

Question: is there an OAuth scope for the Comments API
(GET /api/reviews/v1/manage/dev/reviews)? We would like to show users' reviews as well.
If so, please include it in this application.

Developer account: 30090000034213549
Server app ID: 101653523127285960 (project "Kalehost Metrics")
Redirect URL: https://metrics.kalehost.net/connect/app_gallery/callback
Contact: <name>, <e-mail>, <phone>

The completed application form is attached.

Best regards,
<name>
<Company name>
```

## Form (xlsx) için notlar

Şablondaki alanlar Huawei tarafında değişebilir; doldururken yukarıdaki tabloyu kullan.
Scope sütununa yalnızca `report/read` yaz; yayın/geliştirme scope'ları (`publish`, `develop`,
`project`, `product`) istenmiyor, istenmesi onayı zorlaştırır.

## Onay gelirse yapılacaklar

- `StoreConnectController`'a `app_gallery` dalı: Huawei ID ile `report/read` scope'u isteyip
  refresh token saklamak; çağrılarda `teamId` + `oauth2Token` başlıkları (Report API'nin OAuth
  client modu bu başlıkları istiyor), takım listesi "Obtaining the team account list" API'sinden.
- Yorumlar için scope verilmezse yorumlar Service Account / API client ile toplanmaya devam eder.
