/* ====================================================
   auth.js — صفحة تسجيل الدخول وإنشاء الحساب
   مطابق تماماً لتطبيق الطالب والمدرس في Flutter
   يعتمد على ApiClient المزود بأمان التوكنات والربط السلس
   ==================================================== */

document.addEventListener('DOMContentLoaded', () => {
  // التحقق من وجود جلسة سابقة
  const user = ApiClient.getUser();
  const token = ApiClient.getToken();

  if (token && user) {
    if (user.user_type === 'teacher' || user.role === 'teacher') {
      window.location.href = 'teacher.html';
    } else {
      window.location.href = 'index.html';
    }
  }

  // تفعيل النماذج
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', handleLogin);
  }

  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', handleRegister);
  }

  const forgotForm = document.getElementById('forgot-password-form');
  if (forgotForm) {
    forgotForm.addEventListener('submit', handleForgotPassword);
  }
});

// ====================================================
// دالة إظهار/إخفاء كلمة المرور
// ====================================================
function togglePasswordVisibility(inputId) {
  const input = document.getElementById(inputId);
  if (input) {
    input.type = input.type === 'password' ? 'text' : 'password';
  }
}

// ====================================================
// 1. دالة تسجيل الدخول (Login)
// ====================================================
async function handleLogin(event) {
  event.preventDefault();

  const emailInput = document.getElementById('login-email');
  const passwordInput = document.getElementById('login-password');
  const submitBtn = document.getElementById('login-btn');
  const errorDiv = document.getElementById('login-error');

  if (errorDiv) errorDiv.style.display = 'none';

  const email = emailInput ? emailInput.value.trim() : '';
  const password = passwordInput ? passwordInput.value.trim() : '';

  if (!email || !password) {
    showAuthError(errorDiv, 'يرجى تعبئة جميع الحقول المطلوبة');
    return;
  }

  try {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'جاري تسجيل الدخول...';
    }

    const data = await ApiClient.login(email, password);

    // توجيه حسب نوع المستخدم (مدرس أو طالب)
    const user = data.user || {};
    if (user.user_type === 'teacher' || user.role === 'teacher') {
      window.location.href = 'teacher.html';
    } else {
      window.location.href = 'index.html';
    }

  } catch (error) {
    console.error('[Login Error]:', error);
    showAuthError(errorDiv, error.message || 'بيانات الدخول غير صحيحة، يرجى التأكد والمحاولة مرة أخرى');
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'تسجيل الدخول';
    }
  }
}

// ====================================================
// 2. دالة إنشاء الحساب (Registration)
// ====================================================
async function handleRegister(event) {
  event.preventDefault();

  const submitBtn = document.getElementById('register-btn');
  const errorDiv = document.getElementById('register-error');

  if (errorDiv) errorDiv.style.display = 'none';

  const firstName = document.getElementById('reg-firstname')?.value.trim();
  const middleName = document.getElementById('reg-middlename')?.value.trim();
  const lastName = document.getElementById('reg-lastname')?.value.trim();
  const birthDate = document.getElementById('reg-birthdate')?.value.trim();
  const email = document.getElementById('reg-email')?.value.trim();
  const phone = document.getElementById('reg-phone')?.value.trim();
  const country = document.getElementById('reg-country')?.value;
  const password = document.getElementById('reg-password')?.value.trim();
  const passwordConfirm = document.getElementById('reg-password-confirm')?.value.trim();

  // التحقق من الحقول الإلزامية
  if (!firstName || !middleName || !lastName || !birthDate || !email || !phone || !password) {
    showAuthError(errorDiv, 'جميع الحقول المطلوبة يجب تعبئتها');
    return;
  }

  // التحقق من تطابق كلمة المرور
  if (password !== passwordConfirm) {
    showAuthError(errorDiv, 'كلمة المرور وتأكيد كلمة المرور غير متطابقتين');
    return;
  }

  // مطابقة الحقول تماماً لما ينتظره RegisterRequest في السيرفر
  const payload = {
    first_name: firstName,
    middle_name: middleName,
    last_name: lastName,
    birth_date: birthDate,
    email: email,
    phone: phone,
    country: country || 'EG',
    password: password,
    password_confirmation: passwordConfirm
  };

  try {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'جاري إنشاء الحساب...';
    }

    const data = await ApiClient.register(payload);

    alert('تم إنشاء الحساب بنجاح! مرحباً بك في Code Shell');
    
    const user = data.user || {};
    if (user.user_type === 'teacher' || user.role === 'teacher') {
      window.location.href = 'teacher.html';
    } else {
      window.location.href = 'index.html';
    }

  } catch (error) {
    console.error('[Register Error]:', error);
    showAuthError(errorDiv, error.message || 'حدث خطأ أثناء إنشاء الحساب، يرجى التأكد من البيانات والمحاولة مرة أخرى');
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'إنشاء الحساب';
    }
  }
}

function showAuthError(element, message) {
  if (element) {
    element.textContent = message;
    element.style.display = 'block';
  } else {
    alert(message);
  }
}

// ====================================================
// 3. دالة نسيت كلمة المرور (Forgot Password)
// ====================================================
function openForgotPasswordModal() {
  const modal = document.getElementById('forgot-password-modal');
  if (modal) {
    const errorDiv = document.getElementById('forgot-error');
    const successDiv = document.getElementById('forgot-success');
    if (errorDiv) errorDiv.style.display = 'none';
    if (successDiv) successDiv.style.display = 'none';
    modal.classList.add('show');
  }
}

function closeForgotPasswordModal() {
  const modal = document.getElementById('forgot-password-modal');
  if (modal) {
    modal.classList.remove('show');
  }
}

async function handleForgotPassword(event) {
  event.preventDefault();

  const emailInput = document.getElementById('forgot-email-input');
  const submitBtn = document.getElementById('forgot-submit-btn');
  const errorDiv = document.getElementById('forgot-error');
  const successDiv = document.getElementById('forgot-success');

  if (errorDiv) errorDiv.style.display = 'none';
  if (successDiv) successDiv.style.display = 'none';

  const email = emailInput ? emailInput.value.trim() : '';

  if (!email || !email.includes('@') || !email.includes('.')) {
    showAuthError(errorDiv, 'يرجى كتابة بريد إلكتروني صحيح');
    return;
  }

  try {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'جاري إرسال الرسالة... ⏳';
    }

    await ApiClient.forgotPassword(email);

    if (successDiv) {
      successDiv.innerHTML = '✅ <strong>تم إرسال رابط إعادة تعيين كلمة المرور بنجاح!</strong><br/>يرجى مراجعة البريد الإلكتروني (صندوق الوارد Inbox) ومجلد الرسائل غير المرغوب فيها (Spam / Junk Mail).';
      successDiv.style.display = 'block';
    }

    if (emailInput) emailInput.value = '';

  } catch (error) {
    console.error('[Forgot Password Error]:', error);
    showAuthError(errorDiv, error.message || 'حدث خطأ أثناء طلب إعادة تعيين كلمة المرور، يرجى المحاولة لاحقاً.');
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'إرسال رابط التعيين 📩';
    }
  }
}