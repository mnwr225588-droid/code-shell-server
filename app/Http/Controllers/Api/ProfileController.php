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
     */
    public function update(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Coming Soon'
        ]);
    }
}