<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSingleSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('sanctum');
        if ($user && isset($user->current_token_id)) {
            $currentToken = $user->currentAccessToken();
            if ($currentToken && $user->current_token_id && (int)$currentToken->id !== (int)$user->current_token_id) {
                return response()->json([
                    'status' => false,
                    'code' => 'logged_in_elsewhere',
                    'message' => '🔐 تم تسجيل دخول حسابك على جهاز آخر.\nلأمان حسابك تم تسجيل خروجك من هذا الجهاز — يرجى تسجيل الدخول من جديد.'
                ], 401);
            }
        }

        return $next($request);
    }
}
