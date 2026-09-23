# خارج البادئات — حالة الواجهات

routes outside the five prefixes · مولَّد آلياً في 2026-09-23 من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).

| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |
|---|---|---|---|---|
| 1 | 1 | 0 | 0 | 6 |

## صحة الخدمة — 1/1

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-CORE-001 | SP-00 | `GET` | `/health` | — | Core | فحص الصحة |

## حيّ خارج الكتالوج

مسارات تخدمها الشيفرة ولا يذكرها الكتالوج. إما تُضاف إلى `docs/api/catalog/` أو تُزال — لا ثالث.

| الطريقة | المسار | الصلاحية | الوحدة |
|---|---|---|---|
| `GET` | `/currencies` | — | Reference |
| `GET` | `/currencies/{currency}` | — | Reference |
| `GET` | `/governorates` | — | Reference |
| `GET` | `/governorates/{governorate}` | — | Reference |
| `GET` | `/zones` | — | Reference |
| `GET` | `/zones/{zone}` | — | Reference |

