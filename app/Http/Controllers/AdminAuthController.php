<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة'
            ], 401);
        }

        // إنشاء توكن خاص بحارس الـ admin باستخدام Sanctum
        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل دخول المشرف بنجاح',
            'token' => $token,
            'admin' => $admin
        ]);
    }
}