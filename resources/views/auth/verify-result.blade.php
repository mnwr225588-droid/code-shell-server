<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>حالة تفعيل الحساب - Code Shell</title>
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); text-align: center; max-width: 420px; width: 100%; }
        .icon { font-size: 60px; margin-bottom: 20px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        h2 { margin-bottom: 15px; color: #333; }
        p { color: #666; font-size: 15px; line-height: 1.6; }
        .footer { margin-top: 25px; font-size: 12px; color: #999; }
    </style>
</head>
<body>

    <div class="card">
        @if($status == 'success')
            <div class="icon success">✔</div>
            <h2>تم التفعيل بنجاح</h2>
            <p>{{ $message }}</p>
        @elseif($status == 'already')
            <div class="icon warning">ℹ</div>
            <h2>الحساب مفعل مسبقاً</h2>
            <p>{{ $message }}</p>
        @elseif($status == 'expired')
            <div class="icon error">⌛</div>
            <h2>انتهت صلاحية الرابط</h2>
            <p>{{ $message }}</p>
        @else
            <div class="icon error">✖</div>
            <h2>رابط غير صالح</h2>
            <p>{{ $message }}</p>
        @elhse
        @endif
        <div class="footer">منصة Code Shell &copy; 2026</div>
    </div>

</body>
</html>