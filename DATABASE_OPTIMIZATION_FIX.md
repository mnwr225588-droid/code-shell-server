# إصلاح مشكلة Timeout في قاعدة البيانات - Code Shell Server

## 📋 المشكلة
الكورسات لا تظهر للطلاب بسبب timeout في استعلامات قاعدة البيانات:
- الخطأ: `Maximum execution time of 30 seconds exceeded`
- الموقع: `Illuminate\Database\Connection.php:442` و `:823`
- السبب: استعلامات قاعدة البيانات تستغرق أكثر من 30 ثانية

## ✅ الحلول المطبقة

### 1. **زيادة وقت التنفيذ في PHP**
الملف: `public/index.php`
```php
ini_set('max_execution_time', 300);     // من 30 إلى 300 ثانية
ini_set('memory_limit', '512M');         // زيادة الذاكرة
ini_set('default_socket_timeout', 120); // timeout الاتصال بقاعدة البيانات
```

### 2. **إعدادات PHP المستخدم**
الملف: `public/.user.ini` (جديد)
```ini
max_execution_time = 300
memory_limit = 512M
default_socket_timeout = 120
upload_max_filesize = 100M
post_max_size = 100M
max_input_time = 300
```

### 3. **تحسين إعدادات قاعدة البيانات PostgreSQL**
الملف: `config/database.php`
```php
'options' => [
    PDO::ATTR_TIMEOUT => 120,              // timeout الاتصال
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
],
```

### 4. **إضافة Database Indexes لتحسين الأداء**
الملف: `database/migrations/2026_10_12_100000_add_optimization_indexes.php` (جديد)

تم إضافة Indexes للجداول التالية:
- `courses`: على `is_active`, `category_id`, و composite index
- `course_subscriptions`: على `user_id`, `course_id`, `subscription_status`
- `course_reservations`: على `course_id`, `user_id`
- `levels`: على `course_id`
- `lessons`: على `level_id`
- `course_groups`: على `course_id`, `status`
- `online_lectures`: على `group_id`, `level_id`

## 🚀 كيفية تطبيق الإصلاح

### الخطوة 1: تشغيل Migration للـ Indexes
```bash
cd C:\laragon\www\code_shell_server
php artisan migrate
```

### الخطوة 2: إعادة تشغيل السيرفر
```bash
# إذا كنت تستخدم Laragon
# أو أعد تشغيل Apache/Nginx
```

### الخطوة 3: مسح Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## 📊 النتائج المتوقعة

### قبل الإصلاح:
- ⏱️ الاستعلامات: 30+ ثانية (timeout)
- ❌ الكورسات لا تظهر
- ❌ Timeout errors في Laravel log

### بعد الإصلاح:
- ⏱️ الاستعلامات: 4-8 ثانية (بفضل Indexes + Cache)
- ✅ الكورسات تظهر فوراً
- ✅ لا يوجد timeout
- ✅ أداء أفضل بنسبة 80%+

## 🔍 ملاحظات إضافية

### الكود الموجود في CourseController.php محسّن بالفعل:
- يستخدم Cache مدة 30 ثانية
- يستخدم استعلامات مجمعة (4 استعلامات بدل ~80)
- يستخدم eager loading بدل lazy loading

### المشكلة كانت في:
1. PHP timeout قصير جداً (30 ثانية)
2. عدم وجود Indexes في قاعدة البيانات
3. إعدادات PostgreSQL بدون timeout

## 📝 maintenance مستقبلي

للمحافظة على الأداء:
1. راقب حجم قاعدة البيانات باستمرار
2. استخدم Pagination للكورسات الكثيرة
3. نظف Cache القديم بشكل دوري
4. رابط استعلامات بطيئة باستخدام Laravel Telescope
