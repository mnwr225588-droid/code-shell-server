<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ReservedCourse;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $totalUsers = User::count();
        $subscribedUsers = ReservedCourse::distinct('user_id')->count();
        $totalLanguages = Course::count(); // Assuming Course represents language courses
        $totalLessons = Lesson::count();
        $totalTests = Question::count(); // Assuming tests are related to questions

        // Get the most requested course
        $mostRequested = ReservedCourse::select('course_id', DB::raw('count(*) as total'))
            ->groupBy('course_id')
            ->orderByDesc('total')
            ->first();

        $mostRequestedLanguageName = 'N/A';
        if ($mostRequested) {
            $course = Course::find($mostRequested->course_id);
            if ($course) {
                $mostRequestedLanguageName = $course->title ?? 'N/A';
            }
        }

        return response()->json([
            'message' => 'Dashboard stats fetched successfully',
            'data' => [
                'total_users' => $totalUsers,
                'subscribed_users' => $subscribedUsers,
                'total_languages' => $totalLanguages,
                'total_lessons' => $totalLessons,
                'total_tests' => $totalTests,
                'most_requested_language' => $mostRequestedLanguageName,
            ]
        ], 200);
    }
}
