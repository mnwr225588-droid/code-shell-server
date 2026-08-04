<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
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
}