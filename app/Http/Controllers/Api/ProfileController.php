<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $userType = 'student';
        if (method_exists($user, 'getTable') && $user->getTable() === 'teachers') {
            $userType = 'teacher';
        } elseif ($user->is_admin ?? false) {
            $userType = 'admin';
        } elseif ($user->teacher !== null) {
            $userType = 'teacher';
        }

        return response()->json([
            'success' => true,
            'user' => $user,
            'user_type' => $userType,
        ]);
    }

    /**
     * Update the authenticated user's profile.
     * Note: email and phone are strictly protected and cannot be changed here.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول',
            ], 401);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:50',
            'middle_name' => 'nullable|string|max:50',
            'last_name' => 'required|string|max:50',
            'birth_date' => 'nullable|date',
            'country' => 'nullable|string|max:100',
        ], [
            'first_name.required' => 'الاسم الأول مطلوب',
            'last_name.required' => 'اسم العائلة مطلوب',
            'birth_date.date' => 'صيغة تاريخ الميلاد غير صحيحة',
        ]);

        // Explicitly only update allowed fields (strictly excluding email and phone)
        $user->first_name = $validated['first_name'];
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->last_name = $validated['last_name'];
        if (array_key_exists('birth_date', $validated)) {
            $user->birth_date = $validated['birth_date'];
        }
        if (array_key_exists('country', $validated)) {
            $user->country = $validated['country'];
        }

        $user->save();
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ وتحديث بيانات الملف الشخصي بنجاح',
            'user' => $user,
        ]);
    }
}