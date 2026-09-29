<?php

namespace App\Services;

use App\Models\CourseGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseGroupService
{
    /** الأرقام الترتيبية العربية للمجموعات (مؤنث) */
    public static function arabicOrdinal($n): string
    {
        $map = [
            1 => 'الأولى', 2 => 'الثانية', 3 => 'الثالثة', 4 => 'الرابعة', 5 => 'الخامسة',
            6 => 'السادسة', 7 => 'السابعة', 8 => 'الثامنة', 9 => 'التاسعة', 10 => 'العاشرة',
            11 => 'الحادية عشرة', 12 => 'الثانية عشرة', 13 => 'الثالثة عشرة', 14 => 'الرابعة عشرة',
            15 => 'الخامسة عشرة', 16 => 'السادسة عشرة', 17 => 'السابعة عشرة', 18 => 'الثامنة عشرة',
            19 => 'التاسعة عشرة', 20 => 'العشرون', 21 => 'الحادية والعشرون', 22 => 'الثانية والعشرون',
            23 => 'الثالثة والعشرون', 24 => 'الرابعة والعشرون', 25 => 'الخامسة والعشرون',
            26 => 'السادسة والعشرون', 27 => 'السابعة والعشرون', 28 => 'الثامنة والعشرون',
            29 => 'التاسعة والعشرون', 30 => 'الثلاثون',
        ];
        return $map[$n] ?? ('رقم ' . $n);
    }

    /** استخراج الرقم الترتيبي من اسم مجموعة (الأولى=1، الثانية=2، أو أرقام) */
    public static function ordinalFromName($name): int
    {
        $map = [
            'الأولى' => 1, 'الثانية' => 2, 'الثالثة' => 3, 'الرابعة' => 4, 'الخامسة' => 5,
            'السادسة' => 6, 'السابعة' => 7, 'الثامنة' => 8, 'التاسعة' => 9, 'العاشرة' => 10,
            'الحادية عشرة' => 11, 'الثانية عشرة' => 12, 'الثالثة عشرة' => 13, 'الرابعة عشرة' => 14,
            'الخامسة عشرة' => 15, 'السادسة عشرة' => 16, 'السابعة عشرة' => 17, 'الثامنة عشرة' => 18,
            'التاسعة عشرة' => 19, 'العشرون' => 20, 'الثلاثون' => 30,
        ];
        $name = trim((string) $name);
        foreach ($map as $word => $n) {
            if (mb_strpos($name, $word) !== false) return $n;
        }
        if (preg_match('/(\d+)/', $name, $m)) return (int) $m[1];
        return 0;
    }

    /**
     * تفعيل المجموعة وإغلاق التسجيل بها نهائياً، وإنشاء المجموعة التالية تلقائياً
     * بنفس التسلسل إذا كان الخيار مفعلاً (فور التفعيل).
     */
    public static function activateAndSpawnNext(CourseGroup $group)
    {
        // إذا كانت المجموعة مفعلة مسبقاً، لا داعي للتكرار
        if (in_array($group->status, ['active', 'completed'])) {
            return;
        }

        DB::transaction(function () use ($group) {
            // تفعيل المجموعة الحالية (تصبح مغلقة نهائياً أمام المشتركين الجدد)
            $group->update(['status' => 'active']);

            // إنشاء المجموعة التالية تلقائياً فور التفعيل إذا كان الخيار مفعلاً
            // — إلا إذا كانت هناك بالفعل مجموعة مفتوحة للتسجيل (لتجنب التكرار)
            if ($group->is_auto_create) {
                $hasOpenGroup = CourseGroup::where('course_id', $group->course_id)
                    ->whereIn('status', ['open_for_registration', 'waiting_for_students', 'ready_to_start'])
                    ->where('id', '!=', $group->id)
                    ->exists();

                if (!$hasOpenGroup) {
                    self::spawnNextGroup($group);
                }
            }
        });
    }

    /**
     * إنشاء المجموعة التالية بنفس التسلسل (الأولى → الثانية → الثالثة...)
     * بنفس سعة المجموعة السابقة. تُنشأ دائماً بحالة "مفتوح للتسجيل".
     *
     * @return CourseGroup المجموعة المنشأة
     */
    public static function spawnNextGroup(CourseGroup $previousGroup): CourseGroup
    {
        $count = CourseGroup::where('course_id', $previousGroup->course_id)->count();
        $fromName = self::ordinalFromName($previousGroup->name);
        $nextNumber = max($fromName + 1, $count + 1);

        $newName = 'المجموعة ' . self::arabicOrdinal($nextNumber);

        // منع التكرار بنفس الاسم لنفس الكورس
        $exists = CourseGroup::where('course_id', $previousGroup->course_id)
            ->where('name', $newName)->exists();
        if ($exists) {
            $newName = 'المجموعة ' . self::arabicOrdinal($nextNumber) . ' - ' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(3));
        }

        // حساب تاريخ انتهاء التسجيل الجديد
        $registrationDeadline = null;
        if ($previousGroup->duration_days && $previousGroup->duration_days > 0) {
            $registrationDeadline = now()->addDays($previousGroup->duration_days);
        }

        $newGroup = CourseGroup::create([
            'course_id'             => $previousGroup->course_id,
            'name'                  => $newName,
            'capacity'              => $previousGroup->capacity,
            'duration_days'         => $previousGroup->duration_days,
            'is_auto_create'        => $previousGroup->is_auto_create,
            'teacher_id'            => $previousGroup->teacher_id,
            'registration_deadline' => $registrationDeadline,
            'status'                => 'open_for_registration',
        ]);

        Log::info("Auto-spawned new group '{$newName}' (#{$newGroup->id}) for course {$previousGroup->course_id}");

        return $newGroup;
    }

    /**
     * إلحاق طالب بمجموعة مفتوحة للكورس وفق القواعد الصارمة:
     * 🔒 1. المجموعات المفعّلة (active) والمكتملة (completed) مغلقة نهائياً —
     *       لا يمكن الانضمام لها إطلاقاً، والمشترك الجديد يذهب للمجموعات الجديدة فقط.
     * 🎯 2. سعة المجموعة حرفياً كما حددها الأدمن — لا يمكن تجاوز العدد أبداً.
     * ⚡ 3. عند اكتمال العدد أو عدم وجود أماكن → إنشاء المجموعة التالية تلقائياً
     *       بنفس التسلسل، فلا يفشل اشتراك أي طالب أبداً لعدم وجود مجموعات.
     */
    public static function assignStudentToOpenGroup($user, $courseId)
    {
        // المجموعات المفتوحة فقط — المستبعد نهائياً: active / completed
        $groups = CourseGroup::where('course_id', $courseId)
            ->whereIn('status', ['open_for_registration', 'waiting_for_students', 'ready_to_start'])
            ->orderBy('id', 'asc')
            ->get();

        foreach ($groups as $group) {
            $currentStudents = DB::table('course_subscriptions')->where('group_id', $group->id)->count();
            $capacity = max(1, (int) ($group->capacity ?: 10));

            if ($currentStudents < $capacity) {
                // إلحاق الطالب بالمجموعة
                $user->subscribedCourses()->syncWithoutDetaching([
                    $courseId => ['group_id' => $group->id]
                ]);

                // ⚡ اكتمل العدد؟ إنشاء المجموعة التالية تلقائياً فوراً بنفس التسلسل
                if (($currentStudents + 1) >= $capacity) {
                    self::spawnNextGroup($group);
                }

                return $group;
            }
        }

        // لا توجد مجموعة بها مكان (أو لا توجد مجموعات مفتوحة أصلاً)
        // → إنشاء المجموعة التالية تلقائياً وضم الطالب لها
        $lastGroup = CourseGroup::where('course_id', $courseId)->orderBy('id', 'desc')->first();

        if ($lastGroup) {
            $newGroup = self::spawnNextGroup($lastGroup);
        } else {
            $newGroup = CourseGroup::create([
                'course_id'      => $courseId,
                'name'           => 'المجموعة الأولى',
                'capacity'       => 10,
                'is_auto_create' => true,
                'status'         => 'open_for_registration',
            ]);
        }

        $user->subscribedCourses()->syncWithoutDetaching([
            $courseId => ['group_id' => $newGroup->id]
        ]);

        Log::info("Auto-created group #{$newGroup->id} '{$newGroup->name}' on subscription for course {$courseId}");

        return $newGroup;
    }
}
