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
     * 1. فحص ما إذا كان هناك تحديث جديد متوفر للتطبيق
     */
    public function checkVersion(Request $request)
    {
        $platform = $request->input('platform'); // android أو windows
        $currentVersionCode = (int) $request->input('current_version', 0);

        // جلب أحدث إصدار مرفوع لهذه المنصة
        $latestVersion = AppVersion::where('platform', $platform)
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
                'file_url' => asset('storage/' . $latestVersion->file_path), // الرابط المباشر للتحميل من السيرفر
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
        ]);

        // رفع الملف إلى مجلد storage/app_releases داخل السيرفر
        $file = $request->file('app_file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('app_releases', $fileName, 'public');

        // حفظ بيانات الإصدار في قاعدة البيانات
        $appVersion = AppVersion::create([
            'platform' => $request->platform,
            'version_code' => $request->version_code,
            'version_name' => $request->version_name,
            'file_path' => $filePath,
            'changelog' => $request->changelog,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم رفع التحديث بنجاح وإتاحته للمستخدمين.',
            'data' => $appVersion
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
                'file_url' => asset('storage/' . $latestVersion->file_path),
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

        if (!$latestVersion || !Storage::disk('public')->exists($latestVersion->file_path)) {
            return response()->json([
                'status' => false,
                'message' => 'لا توجد نسخة متاحة للتنزيل لهذه المنصة بعد.'
            ], 404);
        }

        // زيادة عداد التنزيلات الحقيقي (مرة واحدة لكل طلب تنزيل فعلي)
        $latestVersion->increment('downloads_count');

        $extension = pathinfo($latestVersion->file_path, PATHINFO_EXTENSION);
        $downloadName = 'codeshell_' . $platform . '_v' . $latestVersion->version_name . '.' . $extension;

        return Storage::disk('public')->download($latestVersion->file_path, $downloadName);
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
                'file_url' => asset('storage/' . $version->file_path),
                'created_at' => $version->created_at?->format('Y-m-d H:i'),
                'file_size' => $this->fileSize($version->file_path),
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

        // حذف الملف من التخزين (إن وُجد) ثم حذف السجل
        if ($version->file_path && Storage::disk('public')->exists($version->file_path)) {
            Storage::disk('public')->delete($version->file_path);
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
        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            return null;
        }
        return (int) Storage::disk('public')->size($filePath);
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