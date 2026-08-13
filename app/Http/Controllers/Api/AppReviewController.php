<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppComment;
use App\Models\AppRating;
use Illuminate\Http\Request;

class AppReviewController extends Controller
{
    /**
     * جلب تقييمات وتعليقات التطبيق لمنصة معينة (للموقع الإلكتروني).
     * GET /api/app-reviews?platform=android
     */
    public function index(Request $request)
    {
        $platform = $request->input('platform', 'android');

        if (!in_array($platform, ['android', 'windows'])) {
            return response()->json([
                'status' => false,
                'message' => 'منصة غير صالحة. يجب أن تكون android أو windows.'
            ], 422);
        }

        $average = AppRating::where('platform', $platform)->avg('rating');
        $ratingsCount = AppRating::where('platform', $platform)->count();

        // أسماء دقيقة لصدق المعطيات: التقييمات أعلاه، والتعليقات التالية
        $comments = AppComment::where('platform', $platform)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'name' => $comment->name,
                    'comment' => $comment->comment,
                    'created_at' => $comment->created_at?->format('Y-m-d'),
                ];
            });

        return response()->json([
            'status' => true,
            'data' => [
                'platform' => $platform,
                'average_rating' => $average !== null ? round((float) $average, 1) : null,
                'ratings_count' => $ratingsCount,
                'comments' => $comments,
            ],
        ]);
    }

    /**
     * حفظ تقييم و/أو تعليق جديد من زائر الموقع.
     * POST /api/app-reviews
     * الجسم: { platform, rating?, name?, comment? }
     */
    public function store(Request $request)
    {
        $request->validate([
            'platform' => 'required|in:android,windows',
            'rating' => 'nullable|integer|between:1,5',
            'name' => 'nullable|string|max:100',
            'comment' => 'nullable|string|max:1000',
        ]);

        $platform = $request->input('platform');
        $ip = $request->ip() ?: 'unknown';

        $hasRating = $request->filled('rating');
        $hasComment = $request->filled('comment');

        if (!$hasRating && !$hasComment) {
            return response()->json([
                'status' => false,
                'message' => 'أضف تقييماً أو تعليقاً على الأقل.'
            ], 422);
        }

        if ($hasRating) {
            // زائر واحد يعطي تقييماً واحداً فقط لكل منصة — التقييم الجديد يحل محل القديم
            AppRating::updateOrCreate(
                ['platform' => $platform, 'ip_address' => $ip],
                ['rating' => (int) $request->input('rating')]
            );
        }

        if ($hasComment) {
            AppComment::create([
                'platform' => $platform,
                'name' => mb_substr(trim($request->input('name')) ?: 'زائر', 0, 100),
                'comment' => trim($request->input('comment')),
                'ip_address' => $ip,
            ]);
        }

        // إعادة المعدل وعدد التقييمات بعد الحفظ مباشرة كي يُحدَّث الموقع فوراً
        $average = AppRating::where('platform', $platform)->avg('rating');
        $ratingsCount = AppRating::where('platform', $platform)->count();

        return response()->json([
            'status' => true,
            'message' => 'تم حفظ تقييمك بنجاح، شكراً لك!',
            'data' => [
                'platform' => $platform,
                'average_rating' => $average !== null ? round((float) $average, 1) : null,
                'ratings_count' => $ratingsCount,
            ],
        ], 201);
    }
}