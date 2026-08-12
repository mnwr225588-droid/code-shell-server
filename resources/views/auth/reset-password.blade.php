<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - Code Shell</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .input-field:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.2); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl p-8 relative overflow-hidden">

        <!-- تأثيرات جمالية -->
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-violet-500/10 rounded-full blur-3xl"></div>

        <!-- شعار التطبيق -->
        <div class="flex justify-center mb-6">
            <div class="w-16 h-16 bg-slate-800 border border-slate-700 rounded-2xl flex items-center justify-center shadow-lg overflow-hidden p-2">
                <img src="https://ibb.co/QvtkNCWV" alt="Code Shell Logo" class="w-full h-full object-contain">
            </div>
        </div>

        @if(isset($expired) && $expired)
            <!-- ⏰ رابط منتهي الصلاحية -->
            <div class="text-center">
                <div class="w-16 h-16 bg-amber-500/20 text-amber-400 rounded-full flex items-center justify-center mx-auto mb-4 border border-amber-500/30">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">انتهت صلاحية الرابط</h2>
                <p class="text-slate-400 text-sm leading-relaxed">عذراً، انتهت صلاحية رابط إعادة تعيين كلمة المرور. الرابط صالح لمدة 5 دقائق فقط.</p>
                <p class="text-slate-500 text-xs mt-4">يرجى طلب رابط جديد من خلال التطبيق.</p>
            </div>

        @elseif(isset($invalid) && $invalid)
            <!-- ❌ رابط غير صحيح -->
            <div class="text-center">
                <div class="w-16 h-16 bg-red-500/20 text-red-400 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-500/30">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">رابط غير صالح</h2>
                <p class="text-slate-400 text-sm leading-relaxed">رابط إعادة تعيين كلمة المرور غير صحيح أو تم استخدامه مسبقاً.</p>
                <p class="text-slate-500 text-xs mt-4">يرجى طلب رابط جديد من خلال التطبيق.</p>
            </div>

        @elseif(isset($success) && $success)
            <!-- ✅ تم تغيير كلمة المرور بنجاح -->
            <div class="text-center">
                <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-4 border border-emerald-500/30 shadow-lg shadow-emerald-500/10">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">تم تغيير كلمة المرور</h2>
                <p class="text-slate-400 text-sm mb-6 leading-relaxed">تم تغيير كلمة المرور بنجاح! يمكنك الآن تسجيل الدخول بكلمة المرور الجديدة.</p>
                <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-3 text-xs text-emerald-300">
                    🔐 حسابك محمي — لم يتم مشاركة كلمة مرورك الجديدة مع أي طرف آخر.
                </div>
            </div>

        @else
            <!-- 📝 نموذج إعادة تعيين كلمة المرور -->
            <div class="text-center mb-6">
                <div class="w-14 h-14 bg-indigo-500/20 text-indigo-400 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-indigo-500/30">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-white">تعيين كلمة مرور جديدة</h2>
                <p class="text-slate-400 text-sm mt-1">أدخل كلمة المرور الجديدة لحسابك</p>
            </div>

            @if(isset($errors) && $errors->any())
                <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-3 mb-4 text-sm text-red-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="/reset-password/{{ $token }}" method="POST" class="space-y-4">
                @csrf

                <!-- كلمة المرور الجديدة -->
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">كلمة المرور الجديدة</label>
                    <div class="relative">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            minlength="8"
                            required
                            placeholder="8 أحرف على الأقل"
                            class="input-field w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm transition-all duration-200 pr-10"
                        >
                        <button type="button" onclick="togglePassword('password', this)" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-300">
                            <svg class="w-5 h-5 eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- تأكيد كلمة المرور -->
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">تأكيد كلمة المرور</label>
                    <div class="relative">
                        <input
                            type="password"
                            name="password_confirmation"
                            id="password_confirmation"
                            minlength="8"
                            required
                            placeholder="أعد كتابة كلمة المرور"
                            class="input-field w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm transition-all duration-200 pr-10"
                        >
                        <button type="button" onclick="togglePassword('password_confirmation', this)" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-300">
                            <svg class="w-5 h-5 eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- زر الإرسال -->
                <button
                    type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-bold py-3 px-6 rounded-xl transition-all duration-200 shadow-lg shadow-indigo-500/20 mt-2"
                >
                    تعيين كلمة المرور الجديدة
                </button>
            </form>

            <p class="text-center text-xs text-slate-500 mt-4">
                ⏱ هذا الرابط صالح لمدة 5 دقائق فقط
            </p>
        @endif
    </div>

    <script>
        function togglePassword(fieldId, btn) {
            const field = document.getElementById(fieldId);
            const isHidden = field.type === 'password';
            field.type = isHidden ? 'text' : 'password';
            const icon = btn.querySelector('.eye-icon');
            if (isHidden) {
                icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;
            } else {
                icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
            }
        }
    </script>
</body>
</html>
