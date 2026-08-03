<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حالة تفعيل الحساب - Code Shell</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl p-8 text-center relative overflow-hidden">
        
        <!-- تأثيرات جمالية للخلفية -->
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl"></div>

        <!-- 🚀 شعار التطبيق -->
        <div class="flex justify-center mb-6">
            <div class="w-16 h-16 bg-slate-800 border border-slate-700 rounded-2xl flex items-center justify-center shadow-lg overflow-hidden p-2">
                <img src="https://i.ibb.co/pjv2YPF0/hero.png" alt="Code Shell Logo" class="w-full h-full object-contain">
            </div>
        </div>

        <!-- 👤 معلومات المستخدم (صورة دائرية + الاسم إن وجد) -->
        @if(isset($user) && $user)
            <div class="flex items-center justify-center gap-3 bg-slate-800/40 border border-slate-800 rounded-2xl p-3 mb-6">
                <!-- صورة دائرية للملف الشخصي (توليد افتراضي بأول حرف من الاسم أو صورة رمزية احترافية) -->
                <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white font-bold text-lg shadow-md border border-indigo-400/30">
                    {{ mb_substr($user->name ?? 'U', 0, 1) }}
                </div>
                <div class="text-right">
                    <h3 class="text-white font-bold text-sm">{{ $user->name ?? 'مستخدم Code Shell' }}</h3>
                    <p class="text-slate-400 text-xs dir-ltr text-right">{{ $user->email ?? '' }}</p>
                </div>
            </div>
        @endif

        <!-- حالات التفعيل -->
        @if($status == 'success')
            <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-4 border border-emerald-500/30 shadow-lg shadow-emerald-500/10">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2">تم التفعيل بنجاح</h2>
            <p class="text-slate-400 text-sm mb-6 leading-relaxed">{{ $message }}</p>
            <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-3 text-xs text-emerald-300">
                يمكنك الآن إغلاق هذه الصفحة والعودة إلى التطبيق للاستمتاع بتجربة التعلم.
            </div>

        @elseif($status == 'already')
            <div class="w-16 h-16 bg-amber-500/20 text-amber-400 rounded-full flex items-center justify-center mx-auto mb-4 border border-amber-500/30 shadow-lg shadow-amber-500/10">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2">الحساب مفعل مسبقاً</h2>
            <p class="text-slate-400 text-sm mb-6 leading-relaxed">{{ $message }}</p>

        @elseif($status == 'expired')
            <div class="w-16 h-16 bg-rose-500/20 text-rose-400 rounded-full flex items-center justify-center mx-auto mb-4 border border-rose-500/30 shadow-lg shadow-rose-500/10">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0 z"></path></svg>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2">انتهت صلاحية الرابط</h2>
            <p class="text-slate-400 text-sm mb-6 leading-relaxed">{{ $message }}</p>

        @else
            <div class="w-16 h-16 bg-rose-500/20 text-rose-400 rounded-full flex items-center justify-center mx-auto mb-4 border border-rose-500/30 shadow-lg shadow-rose-500/10">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2">رابط غير صالح</h2>
            <p class="text-slate-400 text-sm mb-6 leading-relaxed">{{ $message }}</p>
        @endif

        <div class="mt-8 pt-6 border-t border-slate-800/80 text-xs text-slate-500">
            منصة Code Shell &copy; 2026
        </div>
    </div>

</body>
</html>