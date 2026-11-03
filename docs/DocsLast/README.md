# docs/DocsLast — عقود العملاء الخمسة

ملف واحد لكل تطبيق أو لوحة. **افتح ملفك فقط.** التفاصيل الحية للمسارات: `docs/status/`؛ العقد الخام: `docs/api/catalog/`.

| العميل | الملف | الحارس | المنصة / المنفذ | تاريخ العقد |
|---|---|---|---|---|
| لوحة المنصة | [platform.md](platform.md) · [platform.json](platform.json) · [platform.postman_collection.json](platform.postman_collection.json) | `platform` | Next.js **3000** | عربي · **2026-11-03** · 174/174 |
| لوحة القناة | [channel.md](channel.md) · [channel.json](channel.json) · [channel.postman_collection.json](channel.postman_collection.json) | `channel` | Next.js **3001** | عربي · **2026-11-03** · 124/124 · order-flow |
| لوحة المستودع | [warehouse.md](warehouse.md) · [warehouse.postman_collection.json](warehouse.postman_collection.json) | `warehouse` | Next.js **3002** | عربي · **2026-11-03** · 32/32 · order-flow |
| تطبيق المندوب | [flutter-rep.md](flutter-rep.md) · [flutter-rep-field.md](flutter-rep-field.md) · [flutter-rep.json](flutter-rep.json) · [flutter-rep-getx.md](flutter-rep-getx.md) | `app` + kind `rep` | Flutter | عربي |
| تطبيق التاجر | [flutter-retailer.md](flutter-retailer.md) | `app` + kind `retailer` | Flutter | عربي · 2026-09-25 |
| **دورة الطلب (كل الأطراف)** | [order-flow.md](order-flow.md) | مشترك | تشغيل موحّد | عربي · 2026-11-03 |
| ما قبل الرفع | [`../plan/pre-deploy-checklist.md`](../plan/pre-deploy-checklist.md) | — | Ops + بوابات | 2026-11-03 |

## قاعدة الأولوية (لا تُكسر)

1. الكود الحي في `app-modules/`
2. `docs/status/*.md` (مولَّد من `route:list` + الكتالوج)
3. `docs/api/catalog/*.php`
4. ملف DocsLast لهذا العميل

جملة هنا تخالف الكنترولر → الكود يفوز وهذا الملف يُصلَّح.

## ما ليس هنا

- `DocsLast/` في جذر المستودع (إنجليزي، 2026-09-07) **متخلف** — لا تستخدمه للبناء الجديد.
- `docs/status/` مولَّد آلياً؛ لا يُحرَّر يدوياً.

## إعادة التوليد

```bash
php docs/api/generate.php && php docs/status/generate.php
php docs/api/generate-platform-live.php   # platform.json / apps/platform-api.*
php docs/api/generate-channel-live.php    # channel.json
php docs/api/generate-rep-live.php        # flutter-rep.json
php docs/api/generate-dashboard-postman.php  # platform|channel|warehouse.postman_collection.json
```

### Postman — ثلاث مجموعات منفصلة

| مجموعة | ملف | Environment |
|---|---|---|
| سوبر أدمن | [`platform.postman_collection.json`](platform.postman_collection.json) | [`../api/environments/platform-web.postman_environment.json`](../api/environments/platform-web.postman_environment.json) |
| قناة توريد | [`channel.postman_collection.json`](channel.postman_collection.json) | [`../api/environments/channel-web.postman_environment.json`](../api/environments/channel-web.postman_environment.json) |
| مستودع | [`warehouse.postman_collection.json`](warehouse.postman_collection.json) | [`../api/environments/warehouse-desktop.postman_environment.json`](../api/environments/warehouse-desktop.postman_environment.json) |

استورد **المجموعة + الـ environment** معاً. لا تخلط التوكن بين الحراس الثلاثة.
