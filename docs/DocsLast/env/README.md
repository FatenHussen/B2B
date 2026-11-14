# ملفات بيئة الواجهة — React (Vite)

الواجهات في ريبو **`b2b-frontend`** (Vite + React)، مو Next.  
المتغير الذي يقرأه العميل (`packages/b2b-api-client`):

```text
VITE_API_BASE_URL=<host كامل بما فيه /api/v1>
```

مثال محلي: `http://127.0.0.1:8000/api/v1`  
مثال سيرفر: `https://api.sentraxsy.com/api/v1`

| لوحة | منفذ `npm run dev` | ملف المثال |
|---|---|---|
| أدمن المنصة | **3000** | [`platform-web.env.local.example`](platform-web.env.local.example) |
| قناة التوريد | **3001** | [`channel-web.env.local.example`](channel-web.env.local.example) |
| المستودع | **3002** | [`warehouse-web.env.local.example`](warehouse-web.env.local.example) |

انسخ المحتوى إلى **`b2b-frontend/.env.local`** (ملف واحد للتطبيق إن كان مونوريبو لثلاث لوحات، أو حسب ما عندكم).

بعد التعديل: أوقف Vite وأعد `npm run dev` — `VITE_*` تُحمَّل عند الإقلاع فقط.

---

## تأكد من رابط الباكند

```powershell
# محلي — شغّل أولاً من ريبو الباكند: php artisan serve
curl http://127.0.0.1:8000/api/v1/health

# السيرفر
curl https://api.sentraxsy.com/api/v1/health
```

المتوقع: `"status":"ok"`.

---

## متى أي قيمة؟

| هدف | `VITE_API_BASE_URL` |
|---|---|
| واجهة محلية + باكند محلي | `http://127.0.0.1:8000/api/v1` |
| واجهة محلية + باكند السيرفر | `https://api.sentraxsy.com/api/v1` |

«تعذر الاتصال بالخادم» = Vite ما وصل لهذا العنوان (باكند مطفي، رابط غلط، أو CORS من متصفح لـ API بعيد).

دخول المنصة بعد ما يشتغل الرابط: `admin@platform.sy` / `password`.

---

## تطبيق التاجر (Flutter)

ملف المثال: [`retailer-android.env.example`](retailer-android.env.example)

| | محلي (`OTP_BYPASS=true`) | سيرفر `api.sentraxsy.com` |
|---|---|---|
| `API_BASE_URL` | `http://127.0.0.1:8000/api/v1` (محاكي Android: `10.0.2.2`) | `https://api.sentraxsy.com/api/v1` |
| هاتف بذرة | `+963931000001` / `0931000001` | نفس الرقم إن البذرة موجودة على السيرفر |
| OTP | النص `"000000"` (مو رقم) مع `X-Client: retailer-android` | **مو ثابت** على production. على staging/local: ست أصفار مثل المندوب |
| `X-Client` | `retailer-android` أو `retailer-ios` | نفس الشي |
| `X-Channel-Id` | لا ترسله | لا ترسله |

التاجر (`retailer-android`) والمندوب (`rep-android`) على بيئة غير production يأخذان أصفار ثابتة بطول `OTP_LENGTH` (عادة `000000`). Production يرسل رمزاً عشوائياً.

---

## Firebase

هذا الريبو **ما فيه** مفاتيح Firebase ولا `google-services.json`.  
الباكند يسجّل رمز الجهاز فقط: `POST /api/v1/app/devices/push-token`.

من Firebase Console → Project settings:

1. أنشئ تطبيق Android / iOS.
2. نزّل `google-services.json` و `GoogleService-Info.plist` وضعها في مشروع Flutter.
3. انسخ `projectId` / `apiKey` / `appId` / `messagingSenderId` إلى المتغيرات الفارغة في ملف التاجر إن كنت تستخدم `--dart-define` بدل الملفات.
4. بعد OTP والجلسة: أرسل توكن FCM الحقيقي (مو `fcm:xxxxx` التجريبي).
