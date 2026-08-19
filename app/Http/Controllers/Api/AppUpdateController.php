<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use App\Models\DownloadSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AppUpdateController extends Controller
{
    /**
     * رابط الملف العام بحروف https إجبارياً (بعض الشبكات تمنع الروابط النصية http).
     */
    private function secureFileUrl(string $filePath): string
    {
        // إذا كان الملف مخزناً على R2 (s3)، فإن Storage::disk('r2_updates')->url() ستعيد الرابط العام للملف
        // بناءً على المتغير R2_PUBLIC_URL في ملف .env
        return Storage::disk('r2_updates')->url($filePath);
    }

    /**
     * 1. فحص ما إذا كان هناك تحديث جديد متوفر للتطبيق
     */
    public function checkVersion(Request $request)
    {
        $platform = $request->input('platform'); // android أو windows
        $currentVersionCode = (int) $request->input('current_version', 0);

        // جلب أحدث إصدار مرفوع لهذه المنصة (فقط الإصدارات المفعّلة كتحديثات)
        $latestVersion = AppVersion::where('platform', $platform)
            ->where('is_update', true)
            ->orderBy('version_code', 'desc')
            ->first();

        if (!$latestVersion) {
            return response()->json([
                'update_available' => false,
                'message' => 'لا توجد إصدارات مسجلة حتى الآن.'
            ], 200);
        }

        // مقارنة الإصدار الحالي للإصدار الموجود على السيرفر
        if ($latestVersion->version_code > $currentVersionCode) {
            return response()->json([
                'update_available' => true,
                'version_code' => $latestVersion->version_code,
                'version_name' => $latestVersion->version_name,
                'changelog' => $latestVersion->changelog,
                'downloads_count' => (int) $latestVersion->downloads_count,
                'file_url' => $this->secureFileUrl($latestVersion->file_path), // الرابط المباشر للتحميل من السيرفر
            ], 200);
        }

        return response()->json([
            'update_available' => false,
            'message' => 'تطبيقك هو الإحدث.'
        ], 200);
    }

    /**
     * 2. رفع إصدار جديد من قبل الأدمن وتخزينه على السيرفر
     */
    public function uploadVersion(Request $request)
    {
        $request->validate([
            'platform' => 'required|in:android,windows',
            'version_code' => 'required|integer',
            'version_name' => 'required|string',
            'app_file' => 'required|file', // استقبال الملف (APK أو EXE)
            'changelog' => 'nullable|string',
            'is_update' => 'nullable|boolean',
        ]);

        // رفع الملف إلى R2 داخل مجلد app_releases
        $file = $request->file('app_file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('app_releases', $fileName, 'r2_updates');

        // حفظ بيانات الإصدار في قاعدة البيانات
        $appVersion = AppVersion::create([
            'platform' => $request->platform,
            'version_code' => $request->version_code,
            'version_name' => $request->version_name,
            'file_path' => $filePath,
            'changelog' => $request->changelog,
            'is_update' => $request->boolean('is_update', true),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم رفع التحديث بنجاح وإتاحته للمستخدمين.',
            'data' => $appVersion
        ], 201);
    }

    /**
     * 2b. استقبال جزء واحد من ملف التطبيق (رفع مجزأ بمقاطع صغيرة).
     *
     * يرفع تطبيق الأدمن الملف على شكل مقاطع (1MB) لتجنب أي حد لحجم
     * الطلب أو انقطاع الاتصال عند رفع ملف كبير دفعة واحدة، ثم يستدعي
     * upload-complete لتجميعها وحفظ الإصدار.
     * POST /api/admin/upload-chunk
     */
    public function uploadChunk(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|string|max:100',
            'index' => 'required|integer|min:0|max:10000',
            'data' => 'required|file',
        ]);

        $path = $request->file('data')->storeAs(
            'chunks/' . $request->upload_id,
            (int) $request->index . '.part',
            'local'
        );

        return response()->json([
            'status' => true,
            'received' => (int) $request->index,
            'path' => $path,
        ], 200);
    }

    /**
     * 2c. تجميع المقاطع المرفوعة وإنشاء الإصدار الجديد.
     * POST /api/admin/upload-complete
     */
    public function completeChunkedUpload(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|string|max:100',
            'platform' => 'required|in:android,windows',
            'version_code' => 'required|integer',
            'version_name' => 'required|string|max:255',
            'changelog' => 'nullable|string',
            'total_chunks' => 'required|integer|min:1|max:10000',
            'filename' => 'required|string|max:255',
            'is_update' => 'nullable|boolean',
        ]);

        $uploadId = $request->upload_id;
        $totalChunks = (int) $request->total_chunks;
        $chunkDir = 'chunks/' . $uploadId;
        $disk = Storage::disk('local');

        // التأكد من استلام كل المقاطع
        $missing = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!$disk->exists($chunkDir . '/' . $i . '.part')) {
                $missing[] = $i;
            }
        }
        if (!empty($missing)) {
            return response()->json([
                'status' => false,
                'message' => 'لم تكتمل المقاطع المرفوعة بعد. أعد المحاولة.',
                'missing' => $missing,
            ], 422);
        }

        // تجميع المقاطع بالترتيب في ملف واحد مؤقت
        $mergedTmp = $disk->path($chunkDir . '/merged.tmp');
        $out = @fopen($mergedTmp, 'wb');
        if (!$out) {
            return response()->json([
                'status' => false,
                'message' => 'تعذر إنشاء ملف مؤقت للتجميع.',
            ], 500);
        }
        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $in = @fopen($disk->path($chunkDir . '/' . $i . '.part'), 'rb');
                if (!$in) {
                    throw new \RuntimeException('مقطع مفقود أثناء التجميع');
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        // الحد الأقصى المسموح لحجم ملف التطبيق: 700 ميجابايت
        $maxBytes = 734003200; // 700 * 1024 * 1024
        if (filesize($mergedTmp) > $maxBytes) {
            $disk->deleteDirectory($chunkDir);
            return response()->json([
                'status' => false,
                'message' => 'حجم الملف يتجاوز الحد الأقصى المسموح (700 ميجابايت).',
            ], 422);
        }

        // نقل الملف المجمّع إلى R2
        $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $request->filename);
        $r2Path = 'app_releases/' . $safeName;
        $r2Disk = Storage::disk('r2_updates');
        
        $in = @fopen($mergedTmp, 'rb');
        if (!$in || !$r2Disk->writeStream($r2Path, $in)) {
            @fclose($in);
            $disk->deleteDirectory($chunkDir);
            return response()->json([
                'status' => false,
                'message' => 'تعذر حفظ الملف المجمّع على السيرفر.',
            ], 500);
        }
        fclose($in);

        // تنظيف المقاطع المؤقتة
        $disk->deleteDirectory($chunkDir);

        // إنشاء سجل الإصدار الجديد
        $appVersion = AppVersion::create([
            'platform' => $request->platform,
            'version_code' => $request->version_code,
            'version_name' => $request->version_name,
            'file_path' => $r2Path,
            'changelog' => $request->changelog,
            'is_update' => $request->boolean('is_update', true),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم رفع التحديث بنجاح وإتاحته للمستخدمين.',
            'data' => $appVersion,
        ], 201);
    }

    /**
     * الرفع في الخلفية: يستقبل الملف كاملاً دفعة واحدة.
     */
    public function uploadReleaseBackground(Request $request)
    {
        $request->validate([
            'platform' => 'required|string|in:android,windows',
            'version_code' => 'required|integer',
            'version_name' => 'required|string',
            'app_file' => 'required|file|max:716800', // max 700MB in KB
            'changelog' => 'nullable|string',
            'is_update' => 'nullable|boolean',
        ]);

        $file = $request->file('app_file');
        
        $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $r2Path = 'app_releases/' . $safeName;
        
        $path = $file->storeAs('app_releases', $safeName, 'r2_updates');

        if (!$path) {
            return response()->json([
                'status' => false,
                'message' => 'تعذر حفظ الملف على السيرفر.',
            ], 500);
        }

        // إنشاء سجل الإصدار الجديد
        $appVersion = AppVersion::create([
            'platform' => $request->platform,
            'version_code' => $request->version_code,
            'version_name' => $request->version_name,
            'file_path' => $r2Path,
            'changelog' => $request->changelog,
            'is_update' => $request->boolean('is_update', true),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم رفع التحديث بنجاح وإتاحته للمستخدمين.',
            'data' => $appVersion,
        ], 201);
    }

    /**
     * 3. معلومات الإصدار الأحدث لمنصة معينة (للموقع الإلكتروني)
     * GET /api/app-info?platform=android
     */
    public function appInfo(Request $request)
    {
        $platform = $request->input('platform');

        if (!in_array($platform, ['android', 'windows'])) {
            return response()->json([
                'status' => false,
                'message' => 'منصة غير صالحة. يجب أن تكون android أو windows.'
            ], 422);
        }

        $latestVersion = AppVersion::where('platform', $platform)
            ->orderBy('version_code', 'desc')
            ->first();

        if (!$latestVersion) {
            return response()->json([
                'status' => false,
                'message' => 'لا توجد إصدارات متاحة لهذه المنصة بعد.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'platform' => $latestVersion->platform,
                'version_code' => $latestVersion->version_code,
                'version_name' => $latestVersion->version_name,
                'changelog' => $latestVersion->changelog,
                'downloads_count' => (int) $latestVersion->downloads_count,
                'file_url' => $this->secureFileUrl($latestVersion->file_path),
                'updated_at' => $latestVersion->updated_at?->format('Y-m-d'),
            ]
        ], 200);
    }

    /**
     * 4. تنزيل ملف التطبيق مع عدّ التحميلات الحقيقي
     * GET /api/download/{platform}
     */
    public function download(Request $request, $platform)
    {
        if (!in_array($platform, ['android', 'windows'])) {
            return response()->json([
                'status' => false,
                'message' => 'منصة غير صالحة. يجب أن تكون android أو windows.'
            ], 422);
        }

        // إذا كان الأدمن أوقف التنزيل لهذه المنصة من تطبيق الأدمن — يُمنع
        // حتى التنزيل المباشر عبر الرابط، وليس فقط إخفاء الزر من الموقع.
        $setting = DownloadSetting::where('platform', $platform)->first();
        if (!$setting || !$setting->download_enabled) {
            return response()->json([
                'status' => false,
                'message' => 'التنزيل متوقف حالياً لهذه المنصة من لوحة التحكم.'
            ], 403);
        }

        $latestVersion = AppVersion::where('platform', $platform)
            ->orderBy('version_code', 'desc')
            ->first();

        if (!$latestVersion || !Storage::disk('r2_updates')->exists($latestVersion->file_path)) {
            return response()->json([
                'status' => false,
                'message' => 'لا توجد نسخة متاحة للتنزيل لهذه المنصة بعد.'
            ], 404);
        }

        // زيادة عداد التنزيلات الحقيقي (مرة واحدة لكل طلب تنزيل فعلي)
        $latestVersion->increment('downloads_count');

        $extension = pathinfo($latestVersion->file_path, PATHINFO_EXTENSION);
        $downloadName = 'codeshell_' . $platform . '_v' . $latestVersion->version_name . '.' . $extension;

        // لتوفير الباندويث الخاص بالسيرفر ولأن الملفات كبيرة، نقوم بتوجيه المستخدم للرابط المباشر للملف في R2
        return redirect()->away($this->secureFileUrl($latestVersion->file_path));
    }

    /**
     * 5. جلب جميع التحديثات المرفوعة (لصفحة إدارة التحديثات في تطبيق الأدمن)
     * GET /api/admin/releases
     */
    public function getUpdates(Request $request)
    {
        $platform = $request->input('platform');

        $query = AppVersion::orderBy('platform', 'asc')->orderBy('version_code', 'desc');

        if (in_array($platform, ['android', 'windows'])) {
            $query->where('platform', $platform);
        }

        $versions = $query->get()->map(function ($version) {
            return [
                'id' => $version->id,
                'platform' => $version->platform,
                'version_code' => (int) $version->version_code,
                'version_name' => $version->version_name,
                'changelog' => $version->changelog,
                'downloads_count' => (int) $version->downloads_count,
                'file_url' => $this->secureFileUrl($version->file_path),
                'created_at' => $version->created_at?->format('Y-m-d H:i'),
                'file_size' => $this->fileSize($version->file_path),
                'is_update' => (bool) $version->is_update,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $versions,
        ]);
    }

    /**
     * 6. حذف تحديث من صفحة الإدارة.
     *
     * ملاحظة: الحذف يزيل التحديث من السيرفر (لا يعود يُقترح على من لم
     * يُحدّث بعد)، ولا يؤثر أبداً على المستخدمين الذين حدّثوا تطبيقاتهم
     * بالفعل — التطبيق المثبّت لديهم يبقى كما هو.
     * DELETE /api/admin/releases/{id}
     */
    public function deleteUpdate(Request $request, $id)
    {
        $version = AppVersion::findOrFail($id);

        // حذف الملف من R2 (إن وُجد) ثم حذف السجل
        if ($version->file_path && Storage::disk('r2_updates')->exists($version->file_path)) {
            Storage::disk('r2_updates')->delete($version->file_path);
        }

        $version->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف التحديث بنجاح. المستخدمون الذين حدّثوا أجهزتهم يحتفظون بالنسخة الجديدة.',
        ]);
    }

    /**
     * حجم ملف التحديث بالبايت (أو null إن لم يوجد).
     */
    private function fileSize(?string $filePath): ?int
    {
        if (!$filePath || !Storage::disk('r2_updates')->exists($filePath)) {
            return null;
        }
        return (int) Storage::disk('r2_updates')->size($filePath);
    }

    /**
     * 7. حالة تفعيل التنزيل لكل منصة (للموقع الإلكتروني وتطبيق الأدمن)
     * GET /api/download-settings
     */
    public function downloadSettings(Request $request)
    {
        $settings = DownloadSetting::all()->pluck('download_enabled', 'platform');

        return response()->json([
            'status' => true,
            'data' => [
                'android' => (bool) ($settings['android'] ?? true),
                'windows' => (bool) ($settings['windows'] ?? true),
            ],
        ]);
    }

    /**
     * 8. تحديث حالة تفعيل/إيقاف التنزيل لكل منصة من تطبيق الأدمن.
     * PUT /api/admin/download-settings
     * الجسم: { "android": true, "windows": false }
     */
    public function updateDownloadSettings(Request $request)
    {
        $request->validate([
            'android' => 'required|boolean',
            'windows' => 'required|boolean',
        ]);

        foreach (['android', 'windows'] as $platform) {
            DownloadSetting::updateOrCreate(
                ['platform' => $platform],
                ['download_enabled' => (bool) $request->input($platform)]
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث إعدادات التحميل بنجاح.',
            'data' => [
                'android' => (bool) $request->input('android'),
                'windows' => (bool) $request->input('windows'),
            ],
        ]);
    }
}