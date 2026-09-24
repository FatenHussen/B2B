# خارج البادئات — حالة الواجهات

routes outside the five prefixes · مولَّد آلياً في 2026-09-24 من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).

| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |
|---|---|---|---|---|
| 7 | 7 | 0 | 0 | 0 |

## صحة الخدمة — 1/1

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-CORE-001 | SP-00 | `GET` | `/health` | — | Core | فحص الصحة |

## أخرى — 6/6

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SH-REF-001 | SP-03 | `GET` | `/governorates` | — | Reference | محافظات (مشترك) |
| ✅ | EP-SH-REF-002 | SP-03 | `GET` | `/governorates/{id}` | — | Reference | محافظة (مشترك) |
| ✅ | EP-SH-REF-003 | SP-03 | `GET` | `/zones` | — | Reference | مناطق (مشترك) |
| ✅ | EP-SH-REF-004 | SP-03 | `GET` | `/zones/{id}` | — | Reference | منطقة (مشترك) |
| ✅ | EP-SH-REF-005 | SP-03 | `GET` | `/currencies` | — | Reference | عملات (مشترك) |
| ✅ | EP-SH-REF-006 | SP-03 | `GET` | `/currencies/{id}` | — | Reference | عملة (مشترك) |

