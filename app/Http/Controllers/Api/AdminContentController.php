<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Level;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;

class AdminContentController extends Controller
{
    // 0️⃣ جلب جميع الكورسات (بما فيها غير المنشورة)
    public function getCourses(Request $request)
    {
        // Admin needs to see all courses to manage them
        $courses = Course::with('category')->orderBy('id', 'desc')->get();
        return response()->json([
            'status' => true,
            'data'   => $courses
        ]);
    }

    // 1️⃣ إضافة/تحديث قسم أو لغة برمجة مع الأيقونة
    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('categories', 'public');
        }

        $category = Category::create([
            'name' => $request->name,
            'icon' => $iconPath,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تمت إضافة القسم بنجاح',
            'data'    => $category
        ]);
    }

    // 2️⃣ إضافة كورس مع الغلاف والنوع (مجاني/مدفوع/قريباً)
    public function storeCourse(Request $request)
    {
        $request->validate([
            'category_id'    => 'required|exists:categories,id',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'thumbnail'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'is_free'        => 'required|boolean',
            'price'          => 'nullable|numeric',
            'is_coming_soon' => 'boolean',
        ]);

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        $course = Course::create([
            'category_id'    => $request->category_id,
            'title'          => $request->title,
            'description'    => $request->description,
            'thumbnail'      => $thumbnailPath,
            'is_free'        => $request->is_free,
            'price'          => $request->is_free ? 0 : $request->price,
            'is_coming_soon' => $request->is_coming_soon ?? false,
            'is_active'      => true,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تمت إضافة الكورس بنجاح',
            'data'    => $course
        ]);
    }

    // 3️⃣ إضافة مستوى داخل كورس
    public function storeLevel(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title'     => 'required|string|max:255',
            'order_num' => 'required|integer',
            'is_optional' => 'boolean'
        ]);

        $data = $request->all();
        $data['is_optional'] = $request->is_optional ?? false;
        
        $level = Level::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'تم إنشاء المستوى بنجاح',
            'data'    => $level
        ]);
    }

    // 4️⃣ إضافة درس + فيديو + أسئلة الاختبار التابع له
    public function storeLessonWithQuiz(Request $request)
    {
        $request->validate([
            'level_id'     => 'required|exists:levels,id',
            'title'        => 'required|string|max:255',
            'video'        => 'nullable|file|mimes:mp4,mov,avi,wmv|max:500000', // دعم الفيديوهات حتى 500MB
            'video_url'    => 'nullable|string',
            'thumbnail'    => 'nullable|image|max:2048',
            'order_num'    => 'required|integer',
            'is_optional'  => 'boolean',
            'questions'    => 'nullable|array',
        ]);

        // رفع ملف الفيديو إن وجد أو أخذ الرابط
        $videoPath = null;
        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('lessons/videos', 'public');
        } elseif ($request->filled('video_url')) {
            $videoPath = $request->video_url;
        }

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('lessons/thumbnails', 'public');
        }
        
        $isOptional = $request->is_optional === 'true' || $request->is_optional === '1' || $request->is_optional === true || $request->is_optional === 1;

        $lesson = Lesson::create([
            'level_id'   => $request->level_id,
            'title'      => $request->title,
            'video_url'  => $videoPath,
            'thumbnail'  => $thumbnailPath,
            'order_num'  => $request->order_num,
            'is_optional'=> $isOptional,
        ]);

        // إضافة الأسئلة إن وجدت مع الدرس بداخل نفس النموذج
        if ($request->has('questions') && is_array($request->questions)) {
            foreach ($request->questions as $qData) {
                $question = $lesson->questions()->create([
                    'question_text' => $qData['question_text']
                ]);

                if (isset($qData['options']) && is_array($qData['options'])) {
                    foreach ($qData['options'] as $optData) {
                        $question->options()->create([
                            'option_text' => $optData['option_text'],
                            'is_correct'  => $optData['is_correct'] ?? false,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'تم حفظ الدرس مع الفيديو والأسئلة بنجاح',
            'data'    => $lesson->load('questions.options')
        ]);
    }

    // 5️⃣ عرض كافة المستخدمين واشتراكاتهم المدفوعة فقط
    public function getUsers(Request $request)
    {
        $users = User::select('id', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'birth_date', 'created_at')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $users
        ]);
    }

    // 6️⃣ تفعيل/إلغاء تفعيل الكورس (Publish Toggle)
    public function togglePublish($id)
    {
        $course = Course::findOrFail($id);
        $course->is_coming_soon = !$course->is_coming_soon;
        $course->save();

        return response()->json([
            'status' => true,
            'message' => $course->is_coming_soon ? 'تم إخفاء الكورس (وضع الانتظار)' : 'تم نشر الكورس بنجاح',
            'data' => $course
        ]);
    }
}