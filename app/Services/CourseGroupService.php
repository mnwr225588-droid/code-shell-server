<?php

namespace App\Services;

use App\Models\CourseGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseGroupService
{
    /**
     * تفعيل المجموعة وإغلاق التسجيل بها، وإنشاء المجموعة التالية تلقائياً إذا كان الخيار مفعلاً.
     */
    public static function activateAndSpawnNext(CourseGroup $group)
    {
        // إذا كانت المجموعة مفعلة مسبقاً، لا داعي للتكرار
        if (in_array($group->status, ['active', 'completed'])) {
            return;
        }

        DB::transaction(function () use ($group) {
            // تفعيل المجموعة الحالية
            $group->update(['status' => 'active']);

            // إنشاء المجموعة التالية إذا كان الخيار مفعلاً
            if ($group->is_auto_create) {
                self::spawnNextGroup($group);
            }
        });
    }

    /**
     * إنشاء المجموعة التالية بناءً على إعدادات المجموعة الحالية.
     */
    public static function spawnNextGroup(CourseGroup $previousGroup)
    {
        // حساب الرقم التالي للمجموعة
        $count = CourseGroup::where('course_id', $previousGroup->course_id)->count();
        $nextNumber = $count + 1;

        // توليد اسم المجموعة الجديدة
        // يمكننا استخراج الاسم الأساسي إذا أردنا، لكن للسهولة سنعتمد على "المجموعة X"
        // أو محاولة استخراج الاسم القديم بدون أرقام:
        $baseName = trim(preg_replace('/[0-9]+/', '', $previousGroup->name));
        if (empty($baseName)) {
            $baseName = 'المجموعة';
        }
        $newName = $baseName . ' ' . $nextNumber;

        // حساب تاريخ الانتهاء الجديد
        $registrationDeadline = null;
        if ($previousGroup->duration_days && $previousGroup->duration_days > 0) {
            $registrationDeadline = now()->addDays($previousGroup->duration_days);
        }

        CourseGroup::create([
            'course_id' => $previousGroup->course_id,
            'name' => $newName,
            'capacity' => $previousGroup->capacity,
            'duration_days' => $previousGroup->duration_days,
            'is_auto_create' => $previousGroup->is_auto_create,
            'registration_deadline' => $registrationDeadline,
            'status' => 'open_for_registration',
        ]);
        
        Log::info("Auto-spawned new group '{$newName}' for course {$previousGroup->course_id}");
    }
    
    /**
     * إلحاق طالب بالمجموعة المفتوحة الحالية للكورس، والتحقق من اكتمال العدد.
     */
    public static function assignStudentToOpenGroup($user, $courseId)
    {
        // البحث عن مجموعة مفتوحة أو نشطة
        $group = CourseGroup::where('course_id', $courseId)
            ->whereIn('status', ['open_for_registration', 'waiting_for_students', 'active'])
            ->orderBy('id', 'asc')
            ->first();
            
        if (!$group) {
            // إنشاء مجموعة أساسية تلقائياً إذا لم توجد أي مجموعة
            $group = CourseGroup::create([
                'course_id' => $courseId,
                'name'      => 'المجموعة الأولى (الأساسية)',
                'capacity'  => 100,
                'status'    => 'active'
            ]);
        }
        
        // إلحاق الطالب بالمجموعة
        $user->subscribedCourses()->syncWithoutDetaching([
            $courseId => ['group_id' => $group->id]
        ]);
        
        // التحقق من اكتمال العدد
        $currentStudents = $group->students()->count();
        if ($currentStudents >= $group->capacity) {
            self::activateAndSpawnNext($group);
        }
        
        return $group;
    }
}
