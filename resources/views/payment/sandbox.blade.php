<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة الدفع التجريبية - Code Shell</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            color: #e2e8f0;
        }
        .card {
            background: #ffffff;
            color: #0f172a;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.4);
            max-width: 420px;
            width: 100%;
            padding: 28px;
            text-align: center;
        }
        .badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 999px;
            margin-bottom: 16px;
        }
        .title { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
        .subtitle { font-size: 14px; color: #64748b; margin-bottom: 24px; }
        .amount {
            font-size: 42px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
            direction: ltr;
        }
        .currency { font-size: 16px; color: #64748b; margin-bottom: 28px; }
        .row { display: flex; gap: 10px; }
        button {
            flex: 1;
            border: none;
            border-radius: 10px;
            padding: 14px 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: transform .1s ease;
        }
        button:active { transform: scale(.97); }
        .success { background: #16a34a; color: #fff; }
        .success:hover { background: #15803d; }
        .fail { background: #e2e8f0; color: #334155; }
        .fail:hover { background: #cbd5e1; }
        .note {
            margin-top: 20px;
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.7;
        }
        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 3px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            vertical-align: middle;
            margin-inline-start: 6px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">وضع تجريبي (Sandbox)</span>
        <div class="title">{{ $courseTitle }}</div>
        <div class="subtitle">تأكيد عملية الدفع</div>
        <div class="amount">{{ $amount }}</div>
        <div class="currency">{{ $symbol }}</div>

        <div class="row">
            <button class="success" id="payBtn">
                إتمام الدفع
                <span class="spinner" id="successSpinner"></span>
            </button>
            <button class="fail" id="cancelBtn">إلغاء</button>
        </div>

        <div class="note">
            هذه صفحة محاكاة لبوابة دفع لأغراض الاختبار فقط.<br>
            لن تُخصم أي مبالغ حقيقية من أي حساب.
        </div>
    </div>

    <script>
        (function () {
            var webhookUrl = {{ Js::from($webhookUrl) }};
            var completedSigned = {{ Js::from($completedSigned) }};
            var failedSigned = {{ Js::from($failedSigned) }};

            function send(signedBody, btn) {
                var spinner = btn.querySelector('.spinner');
                btn.disabled = true;
                if (spinner) spinner.style.display = 'inline-block';

                fetch(webhookUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: signedBody
                }).then(function (res) { return res.json(); })
                  .then(function (data) {
                      // في حال النجاح أو الفشل نغلق الجلسة فوراً؛
                      // التطبيق يتحقق من النتيجة عبر /payment-status بعد الإغلاق.
                      window.location.href = window.location.origin + '/api/payment/closed';
                  })
                  .catch(function () {
                      alert('تعذر الاتصال بالخادم، حاول مرة أخرى.');
                      btn.disabled = false;
                      if (spinner) spinner.style.display = 'none';
                    });
            }

            document.getElementById('payBtn').addEventListener('click', function (e) {
                send(completedSigned, this);
            });
            document.getElementById('cancelBtn').addEventListener('click', function (e) {
                if (!confirm('هل أنت متأكد من إلغاء الدفع؟')) return;
                send(failedSigned, this);
            });
        })();
    </script>
</body>
</html>
