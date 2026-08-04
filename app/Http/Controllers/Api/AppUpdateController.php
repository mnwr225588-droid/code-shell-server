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
}