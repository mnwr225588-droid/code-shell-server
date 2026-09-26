/* ====================================================
   app.js — الملف الرئيسي للتطبيق (JavaScript Core Logic)
   يتولى حماية التطبيق ومنع الدخول إلا بعد تسجيل الدخول
   ==================================================== */

const CURRENT_APP_VERSION = '2026.09.26.v5';
if (localStorage.getItem('cs_app_version') !== CURRENT_APP_VERSION) {
  localStorage.setItem('cs_app_version', CURRENT_APP_VERSION);
  if (window.location.search.indexOf('v_reloaded=1') === -1) {
    const sep = window.location.search ? '&' : '?';
    window.location.replace(window.location.href + sep + 'v_reloaded=1');
  }
}

const AppState = {
  sidebarCollapsed: false,
  sidebarMobileOpen: false,
  currentPage: 'home',
  theme: 'light',
};

// ====================================================
// حارس المصادقة والتوجيه الإجباري (Strict Auth Guard)
// ====================================================
// حارس المصادقة والتوجيه الإجباري (Strict Auth Guard)
// يضمن توجيه المدرس إلى لوحة المدرس حصراً وعدم اختلاط الواجهات
// ====================================================
function enforceAuthGuard() {
  const token = localStorage.getItem('cs_token');
  const userRaw = localStorage.getItem('cs_user');
  const path = window.location.pathname.toLowerCase();

  const isAuthPage = path.includes('login.html') || path.includes('register.html') || path.includes('privacy-policy.html') || path.includes('terms-conditions.html');
  const isTeacherPage = path.includes('teacher.html');

  // إذا لم يكن المستخدم مسجلاً للدخول وحاول دخول أي صفحة غير تسجيل الدخول
  if (!token || !userRaw) {
    if (!isAuthPage) {
      window.location.replace('login.html');
      return false;
    }
  } else {
    try {
      const user = JSON.parse(userRaw);
      const isTeacher = user.user_type === 'teacher' || user.role === 'teacher';

      // حماية حساب المدرس: إذا كان مدرس وحاول فتح أي صفحة من صفحات الطالب يتم تحويله فوراً لصفحة المدرس
      if (isTeacher && !isTeacherPage && !isAuthPage) {
        window.location.replace('teacher.html');
        return false;
      }

      // إذا كان مسجلاً بالفعل وحاول فتح صفحة تسجيل الدخول
      if (isAuthPage) {
        if (isTeacher) {
          window.location.replace('teacher.html');
        } else {
          window.location.replace('index.html');
        }
        return false;
      }
    } catch (e) {
      localStorage.removeItem('cs_token');
      localStorage.removeItem('cs_user');
      window.location.replace('login.html');
      return false;
    }
  }

  return true;
}

// تنفيذ حارس المصادقة فورا عند قراءة السكريبت دون انتظار DOMContentLoaded
enforceAuthGuard();

document.addEventListener('DOMContentLoaded', () => {
  // 1. إعادة فحص حارس المصادقة للتأكد
  if (!enforceAuthGuard()) return;

  // 2. استرجاع نمط المظهر
  const savedTheme = localStorage.getItem('cs_theme') || 'light';
  setTheme(savedTheme);

  // 3. استرجاع حالة طي القائمة
  const savedCollapsed = localStorage.getItem('cs_sidebar_collapsed');
  if (savedCollapsed === 'true') {
    toggleSidebar();
  }

  // 4. تفعيل القائمة الجانبية وإظهار أزرار الرتب
  initSidebar();
  checkUserSessionUI();

  // 5. تفعيل البحث الفوري
  initGlobalSearch();

  // 6. تحديد الصفحة النشطة
  const path = window.location.pathname;
  let currentPage = 'home';
  if (path.includes('courses.html')) currentPage = 'courses';
  else if (path.includes('computer-basics.html')) currentPage = 'courses';
  else if (path.includes('my-courses.html')) currentPage = 'my-courses';
  else if (path.includes('community.html')) currentPage = 'community';
  else if (path.includes('profile.html')) currentPage = 'profile';
  else if (path.includes('live.html')) currentPage = 'live';
  else if (path.includes('groups.html')) currentPage = 'groups';
  else if (path.includes('teacher.html')) currentPage = 'teacher';

  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.remove('active');
  });
  const activeNavItem = document.querySelector(`.nav-item[href="${path.split('/').pop()}"]`);
  if (activeNavItem) {
    activeNavItem.classList.add('active');
  }
});

function checkUserSessionUI() {
  const token = localStorage.getItem('cs_token');
  const userRaw = localStorage.getItem('cs_user');

  if (token && userRaw) {
    try {
      const user = JSON.parse(userRaw);
      
      // فحص بانر تأكيد البريد الإلكتروني
      checkEmailVerificationBanner(user);

      // إظهار زر نظام المدرس إذا كان المستخدم مدرس
      if (user.user_type === 'teacher' || user.role === 'teacher') {
        let teacherItem = document.getElementById('teacher-nav-item');
        if (!teacherItem) {
          const sidebarNav = document.querySelector('.sidebar-nav');
          if (sidebarNav) {
            teacherItem = document.createElement('a');
            teacherItem.id = 'teacher-nav-item';
            teacherItem.className = 'nav-item';
            teacherItem.href = 'teacher.html';
            teacherItem.style.background = 'rgba(59, 130, 246, 0.15)';
            teacherItem.style.color = '#3B82F6';
            teacherItem.innerHTML = `
              <span class="nav-item-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                  <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
              </span>
              <span class="nav-item-text">👨‍🏫 نظام المدرس</span>
            `;
            sidebarNav.appendChild(teacherItem);
          }
        } else {
          teacherItem.style.display = 'flex';
        }
      }
    } catch (e) {
      console.error(e);
    }
  }

  // تفعيل عناصر المحفظة الإلكترونية في الشريط العلوي والقائمة الجانبية
  initWalletUI();
}

/**
 * ====================================================
 * دالة إنشاء وتأمين عرض المحفظة في الشريط العلوي والقائمة الجانبية
 * ====================================================
 */
function initWalletUI() {
  const token = localStorage.getItem('cs_token');
  if (!token) return;

  // 1. إضافة زر المحفظة الإلكترونية إلى الشريط العلوي (Top Navbar)
  const topNavLeft = document.querySelector('.top-nav-left');
  if (topNavLeft && !document.getElementById('top-wallet-btn')) {
    const walletBtn = document.createElement('a');
    walletBtn.id = 'top-wallet-btn';
    walletBtn.href = 'wallet.html';
    walletBtn.className = 'top-nav-wallet-btn';
    walletBtn.title = 'محفظة كود شيل';
    walletBtn.style.cssText = `
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255, 255, 255, 0.18);
      border: 1px solid rgba(255, 255, 255, 0.35);
      color: #FFFFFF;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      backdrop-filter: blur(8px);
      transition: all 0.25s ease;
      white-space: nowrap;
      margin-left: 6px;
    `;
    walletBtn.innerHTML = `
      <span style="font-size: 14px;">💳</span>
      <span id="top-wallet-balance-num" style="font-family: 'Outfit', sans-serif;">0.00 ج.م</span>
    `;
    
    // إدراج عنصر المحفظة قبل المظهر أو الأفاتار
    const themeBtn = document.getElementById('top-theme-btn') || topNavLeft.firstChild;
    topNavLeft.insertBefore(walletBtn, themeBtn);
  }

  // 2. إضافة عنصر المحفظة إلى القائمة الجانبية (Sidebar)
  const sidebarNav = document.querySelector('.sidebar-nav');
  if (sidebarNav && !document.getElementById('sidebar-wallet-item')) {
    const walletItem = document.createElement('a');
    walletItem.id = 'sidebar-wallet-item';
    walletItem.className = 'nav-item';
    walletItem.href = 'wallet.html';
    walletItem.innerHTML = `
      <span class="nav-item-icon" style="background: rgba(37, 99, 235, 0.15); color: #3B82F6; border-radius: 12px; padding: 6px; display: flex; align-items: center; justify-content: center;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="5" width="20" height="14" rx="2"/>
          <line x1="2" y1="10" x2="22" y2="10"/>
        </svg>
      </span>
      <span class="nav-item-text" style="font-weight: 700;">المحفظة</span>
      <span class="sidebar-wallet-badge" id="sidebar-wallet-badge" style="margin-right: auto; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #FFFFFF; padding: 4px 12px; border-radius: 12px; font-size: 11.5px; font-weight: 800; box-shadow: 0 4px 12px rgba(37,99,235,0.3);">0.00 ج.م</span>
    `;

    // إدراج عنصر المحفظة قبل رابط الحساب الشخصي
    const profileItem = sidebarNav.querySelector('a[href="profile.html"]');
    if (profileItem) {
      sidebarNav.insertBefore(walletItem, profileItem);
    } else {
      sidebarNav.appendChild(walletItem);
    }
  }

  // 3. تحديث الصفحة الحالية في القائمة الجانبية إذا كانت صفحة المحفظة
  if (window.location.pathname.includes('wallet.html')) {
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    const walletNav = document.getElementById('sidebar-wallet-item');
    if (walletNav) walletNav.classList.add('active');
  }

  // 4. استدعاء رصيد المحفظة من الباك إند
  fetchAndUpdateWalletBalance();
}

/**
 * جلب واستبدال أرقام رصيد المحفظة الحي من API السيرفر
 */
async function fetchAndUpdateWalletBalance() {
  if (typeof ApiClient === 'undefined') return;
  try {
    const data = await ApiClient.request('/wallet/balance').catch(() => null);
    if (data && data.status) {
      const bal = parseFloat(data.wallet_balance || 0).toFixed(2);
      const text = `${bal} ج.م`;

      const topNum = document.getElementById('top-wallet-balance-num');
      if (topNum) topNum.textContent = text;

      const sideBadge = document.getElementById('sidebar-wallet-badge');
      if (sideBadge) sideBadge.textContent = text;

      const curBal = document.getElementById('current-wallet-balance');
      if (curBal) curBal.innerHTML = `${bal} <span style="font-size: 20px;">ج.م</span>`;
    }
  } catch (e) {}
}

function initSidebar() {
  const toggleBtn = document.getElementById('sidebar-toggle');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', toggleSidebar);
  }

  document.querySelectorAll('.nav-item[href]').forEach(item => {
    item.addEventListener('click', () => {
      if (window.innerWidth <= 768) {
        closeMobileSidebar();
      }
    });
  });

  const mobileBtn = document.getElementById('mobile-menu-btn');
  if (mobileBtn) {
    mobileBtn.addEventListener('click', toggleMobileSidebar);
  }

  const overlay = document.getElementById('sidebar-overlay');
  if (overlay) {
    overlay.addEventListener('click', closeMobileSidebar);
  }
}

function toggleSidebar() {
  if (window.innerWidth <= 768) {
    closeMobileSidebar();
    return;
  }

  const sidebar = document.getElementById('sidebar');
  if (!sidebar) return;
  AppState.sidebarCollapsed = !AppState.sidebarCollapsed;
  sidebar.classList.toggle('collapsed', AppState.sidebarCollapsed);
  document.body.classList.toggle('sidebar-collapsed', AppState.sidebarCollapsed);

  localStorage.setItem('cs_sidebar_collapsed', AppState.sidebarCollapsed);
}

function logout() {
  if (confirm('هل أنت تأكد أنك تريد تسجيل الخروج؟')) {
    localStorage.removeItem('cs_user');
    localStorage.removeItem('cs_token');
    alert('تم تسجيل الخروج بنجاح');
    window.location.href = 'login.html';
  }
}

function toggleMobileSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!sidebar || !overlay) return;
  AppState.sidebarMobileOpen = !AppState.sidebarMobileOpen;
  sidebar.classList.toggle('mobile-open', AppState.sidebarMobileOpen);
  overlay.classList.toggle('active', AppState.sidebarMobileOpen);
}

function closeMobileSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!sidebar || !overlay) return;
  AppState.sidebarMobileOpen = false;
  sidebar.classList.remove('mobile-open');
  overlay.classList.remove('active');
}

function setTheme(theme) {
  AppState.theme = theme;
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('cs_theme', theme);
}

function toggleTheme() {
  const currentTheme = document.documentElement.getAttribute('data-theme') || AppState.theme || 'light';
  const newTheme = currentTheme === 'light' ? 'dark' : 'light';
  setTheme(newTheme);
}

function initGlobalSearch() {
  const searchInput = document.getElementById('top-global-search');
  if (!searchInput) return;

  searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim().toLowerCase();
    const cards = document.querySelectorAll('.language-card, .featured-card, .group-card, .live-card');
    cards.forEach(card => {
      const text = card.textContent.toLowerCase();
      card.style.display = text.includes(query) ? '' : 'none';
    });
  });
}

// ====================================================
// نظام تأكيد البريد الإلكتروني (Email Verification Service)
// ====================================================
// ====================================================
// نظام تأكيد البريد الإلكتروني (Email Verification Service)
// ====================================================
let autoVerificationPollingTimer = null;
let verificationCooldownTimer = null;

function checkEmailVerificationBanner(user) {
  const isTeacher = user.user_type === 'teacher' || user.role === 'teacher';
  const isVerified = Boolean(user.email_verified_at || user.is_email_verified);

  if (!isTeacher && !isVerified) {
    let banner = document.getElementById('email-verification-banner');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'email-verification-banner';
      banner.style.cssText = 'background: #0F172A; border: 2.5px solid #F59E0B; border-radius: 20px; padding: 24px 32px; margin: 32px 24px 20px 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6); position: relative; z-index: 1000;';
      banner.innerHTML = `
        <div style="display: flex; align-items: center; gap: 18px;">
          <div style="width: 60px; height: 60px; background: rgba(245, 158, 11, 0.25); border: 2px solid #F59E0B; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 32px; flex-shrink: 0;">
            📬
          </div>
          <div>
            <strong style="color: #FBBF24; font-size: 20px; font-weight: 900; display: block; margin-bottom: 6px;">تم إرسال رسالة تأكيد البريد الإلكتروني! ⚠️</strong>
            <div style="font-size: 15px; color: #FFFFFF; font-weight: 800; margin-bottom: 4px;">يرجى مراجعة بريدك الإلكتروني والضغط على رابط التفعيل المرفق لإلغاء قيود الحساب.</div>
            <div style="font-size: 13px; color: #CBD5E1;">إذا لم تجد الرسالة في صندوق الوارد (Inbox)، يرجى التحقق من مجلد الرسائل غير المرغوب فيها (Spam / Junk Mail).</div>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
          <button onclick="resendEmailVerificationPrompt()" id="resend-email-btn" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); color: #0F172A; border: none; padding: 14px 26px; border-radius: 14px; font-size: 14px; font-weight: 900; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 18px rgba(245, 158, 11, 0.45); font-family: inherit;">
            إعادة إرسال رسالة تأكيد البريد الإلكتروني 📩
          </button>
        </div>
      `;
      const mainContent = document.getElementById('main-content') || document.querySelector('.main-content') || document.body;
      if (mainContent.firstChild) {
        mainContent.insertBefore(banner, mainContent.firstChild);
      } else {
        mainContent.appendChild(banner);
      }

      // بدء الفحص التلقائي للحالة في الخلفية كل 5 ثوانٍ
      startAutoVerificationPolling();
    }
  }
}

async function resendEmailVerificationPrompt() {
  const btn = document.getElementById('resend-email-btn');
  try {
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'جاري إعادة الإرسال... ⏳';
    }

    await ApiClient.resendVerification();

    alert('✅ تم إعادة إرسال رسالة التأكيد بنجاح إلى بريدك الإلكتروني!\n\nيرجى فتح صندوق الوارد (Inbox) أو مجلد الرسائل غير المرغوب فيها (Spam / Junk Mail) والضغط على رابط التفعيل.');

    // بدء العد التنازلي لمدة 60 ثانية
    startVerificationCooldown(60);

  } catch (err) {
    console.error('[Resend Verification Error]:', err);
    alert('⚠️ ' + (err.message || 'فشل إرسال بريد التأكيد، يرجى المحاولة لاحقاً.'));
    if (btn) {
      btn.disabled = false;
      btn.textContent = 'إعادة إرسال رسالة تأكيد البريد الإلكتروني 📩';
    }
  }
}

function startVerificationCooldown(seconds) {
  const btn = document.getElementById('resend-email-btn');
  if (!btn) return;

  if (verificationCooldownTimer) clearInterval(verificationCooldownTimer);

  let remaining = seconds;
  btn.disabled = true;
  btn.style.opacity = '0.7';
  btn.textContent = `إعادة الإرسال بعد (${remaining}) ثانية ⏳`;

  verificationCooldownTimer = setInterval(() => {
    remaining -= 1;
    if (remaining <= 0) {
      clearInterval(verificationCooldownTimer);
      verificationCooldownTimer = null;
      btn.disabled = false;
      btn.style.opacity = '1';
      btn.textContent = 'إعادة إرسال رسالة تأكيد البريد الإلكتروني 📩';
    } else {
      btn.textContent = `إعادة الإرسال بعد (${remaining}) ثانية ⏳`;
    }
  }, 1000);
}

function startAutoVerificationPolling() {
  if (autoVerificationPollingTimer) return;

  autoVerificationPollingTimer = setInterval(async () => {
    try {
      const profileRes = await ApiClient.getProfile().catch(() => null);
      if (!profileRes) return;

      const freshUser = profileRes.user || profileRes;
      if (freshUser.email_verified_at || freshUser.is_email_verified) {
        clearInterval(autoVerificationPollingTimer);
        autoVerificationPollingTimer = null;

        const currentSessionUser = ApiClient.getUser() || {};
        const updatedUser = { ...currentSessionUser, ...freshUser };
        localStorage.setItem('cs_user', JSON.stringify(updatedUser));

        const banner = document.getElementById('email-verification-banner');
        if (banner) {
          banner.style.transition = 'all 0.4s ease';
          banner.style.opacity = '0';
          banner.style.transform = 'translateY(-10px)';
          setTimeout(() => banner.remove(), 400);
        }

        alert('🎉 مبروك! تم تأكيد بريدك الإلكتروني بنجاح.\nيمكنك الآن الاشتراك في كافة الكورسات والدورات البرمجية.');
      }
    } catch (e) {}
  }, 5000);
}

// ====================================================
// إدارة قوائم خيارات الكورس وإلغاء الاشتراك (Course Card Options & Unsubscribe)
// ====================================================
function toggleCourseOptionsMenu(event, courseId) {
  if (event) event.stopPropagation();
  const targetMenu = document.getElementById(`course-options-menu-${courseId}`);
  const isCurrentlyOpen = targetMenu && targetMenu.style.display === 'block';
  
  closeAllCourseOptionsMenus();

  if (targetMenu && !isCurrentlyOpen) {
    targetMenu.style.display = 'block';
  }
}

function closeAllCourseOptionsMenus() {
  document.querySelectorAll('.course-options-dropdown').forEach(el => {
    el.style.display = 'none';
  });
}

document.addEventListener('click', () => {
  closeAllCourseOptionsMenus();
});

function openCancelSubscriptionPasswordPrompt(courseId, courseTitle) {
  const existingPrompt = document.getElementById('password-prompt-modal');
  if (existingPrompt) existingPrompt.remove();

  const promptModal = document.createElement('div');
  promptModal.id = 'password-prompt-modal';
  promptModal.className = 'password-prompt-modal show';
  promptModal.style.cssText = 'position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 10000; padding: 20px; animation: fadeIn 0.2s ease;';

  promptModal.innerHTML = `
    <div class="password-prompt-card" style="background: #1E293B; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 20px; padding: 28px; width: 100%; max-width: 420px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6); text-align: center;">
      <div style="font-size: 36px; margin-bottom: 12px;">🔐</div>
      <h3 style="font-size: 18px; font-weight: 800; color: #FFFFFF; margin-bottom: 8px;">
        تأكيد إلغاء الاشتراك
      </h3>
      <p style="font-size: 13px; color: #94A3B8; margin-bottom: 20px; line-height: 1.6;">
        هل أنت متأكد من إلغاء اشتراكك في كورس <strong>"${courseTitle}"</strong>؟<br/>
        لأمان حسابك، يرجى أدخال كلمة المرور الحالية لتأكيد الإلغاء.
      </p>

      <div id="cancel-pass-error" style="display:none; background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #EF4444; padding: 10px; border-radius: 10px; font-size: 12.5px; margin-bottom: 14px; font-weight:700;"></div>

      <form id="cancel-pass-form" onsubmit="confirmCancelSubscription(event, '${courseId}')">
        <div style="margin-bottom: 18px; text-align: right;">
          <label style="display: block; font-size: 12.5px; font-weight: 700; color: #E2E8F0; margin-bottom: 6px;">كلمة المرور الحالية</label>
          <input type="password" id="cancel-password-input" style="width: 100%; height: 44px; background: #0F172A; border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 0 14px; font-size: 14px; color: #FFFFFF; outline: none;" placeholder="أدخل كلمة مرور حسابك" required />
        </div>

        <div style="display: flex; gap: 10px;">
          <button type="submit" id="cancel-confirm-submit-btn" style="flex: 1; height: 44px; background: #EF4444; color: #FFFFFF; border: none; border-radius: 12px; font-size: 14px; font-weight: 800; cursor: pointer;">
            تأكيد إلغاء الاشتراك
          </button>
          <button type="button" onclick="document.getElementById('password-prompt-modal').remove()" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #FFFFFF; padding: 0 18px; border-radius: 12px; font-size: 13.5px; font-weight: 700; cursor: pointer;">
            إلغاء
          </button>
        </div>
      </form>
    </div>
  `;

  document.body.appendChild(promptModal);
}

async function confirmCancelSubscription(event, courseId) {
  event.preventDefault();

  const passwordInput = document.getElementById('cancel-password-input');
  const errorDiv = document.getElementById('cancel-pass-error');
  const submitBtn = document.getElementById('cancel-confirm-submit-btn');

  if (errorDiv) errorDiv.style.display = 'none';

  const password = passwordInput ? passwordInput.value.trim() : '';
  if (!password) {
    if (errorDiv) {
      errorDiv.textContent = 'يرجى كتابة كلمة المرور الحالية.';
      errorDiv.style.display = 'block';
    }
    return;
  }

  try {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'جاري التحقق والإلغاء... ⏳';
    }

    await ApiClient.cancelSubscription(courseId, password);

    localStorage.removeItem(`cs_subscribed_${courseId}`);
    if (String(courseId) === '1') localStorage.removeItem('cs_subscribed_computer_basics');
    let sc = JSON.parse(localStorage.getItem('cs_subscribed_courses') || '[]');
    sc = sc.filter(id => String(id) !== String(courseId));
    localStorage.setItem('cs_subscribed_courses', JSON.stringify(sc));

    alert('تم إلغاء الاشتراك في الكورس بنجاح.');
    
    const promptModal = document.getElementById('password-prompt-modal');
    if (promptModal) promptModal.remove();

    const mainModal = document.getElementById('course-subscription-modal');
    if (mainModal) mainModal.remove();

    if (typeof loadCoursesFromApi === 'function') {
      await loadCoursesFromApi();
    } else if (typeof loadMyCoursesFromApi === 'function') {
      await loadMyCoursesFromApi();
    } else {
      window.location.reload();
    }

  } catch (err) {
    console.error('[Cancel Subscription Error]:', err);
    if (errorDiv) {
      errorDiv.textContent = err.message || 'كلمة المرور غير صحيحة أو تعذر إلغاء الاشتراك.';
      errorDiv.style.display = 'block';
    }
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'تأكيد إلغاء الاشتراك';
    }
  }
}

function isStudentEmailVerified() {
  const user = ApiClient.getUser();
  if (!user) return false;
  const isTeacher = user.user_type === 'teacher' || user.role === 'teacher';
  if (isTeacher) return true;
  return Boolean(user.email_verified_at || user.is_email_verified);
}

// ====================================================
// حفظ واسترجاع تقدم الدروس وتأكيد التزامن مع السيرفر بعد تسجيل الدخول
// ====================================================
async function syncUserProgressFromServer() {
  const token = localStorage.getItem('cs_token');
  if (!token) return;

  try {
    const res = await ApiClient.getProgress().catch(() => null);
    if (res) {
      const serverCompleted = Array.isArray(res) ? res : (res.completed_lesson_ids || res.lessons || res.data || []);
      const localCompleted = getCompletedLessons();
      const merged = Array.from(new Set([...localCompleted.map(String), ...serverCompleted.map(String)]));
      localStorage.setItem('cs_completed_lessons', JSON.stringify(merged));
    }
  } catch (e) {}
}

function getCompletedLessons() {
  try {
    const saved = localStorage.getItem('cs_completed_lessons');
    return saved ? JSON.parse(saved) : [];
  } catch (e) {
    return [];
  }
}

function isLessonCompleted(lessonId) {
  if (!lessonId) return false;
  const completed = getCompletedLessons();
  return completed.includes(String(lessonId)) || completed.includes(Number(lessonId));
}

async function markLessonAsCompleted(lessonId) {
  if (!lessonId) return;
  const strId = String(lessonId);
  const completed = getCompletedLessons();
  if (!completed.includes(strId)) {
    completed.push(strId);
    localStorage.setItem('cs_completed_lessons', JSON.stringify(completed));
  }
  try {
    await ApiClient.markLessonComplete(lessonId).catch(() => null);
  } catch (e) {}
}

function saveLocalSubscription(courseId) {
  try {
    const list = JSON.parse(localStorage.getItem('cs_subscribed_courses') || '[]');
    if (!list.includes(String(courseId)) && !list.includes(Number(courseId))) {
      list.push(courseId);
      localStorage.setItem('cs_subscribed_courses', JSON.stringify(list));
    }
    localStorage.setItem(`cs_subscribed_${courseId}`, 'true');
    if (String(courseId) === '1') {
      localStorage.setItem('cs_subscribed_computer_basics', 'true');
    }
  } catch (e) {}
}

function removeLocalSubscription(courseId) {
  try {
    localStorage.removeItem(`cs_subscribed_${courseId}`);
    if (String(courseId) === '1') {
      localStorage.removeItem('cs_subscribed_computer_basics');
    }
    let list = JSON.parse(localStorage.getItem('cs_subscribed_courses') || '[]');
    list = list.filter(id => String(id) !== String(courseId));
    localStorage.setItem('cs_subscribed_courses', JSON.stringify(list));
  } catch (e) {}
}

function initDoubleTapSeek(wrapperEl, videoEl, getPlayer) {
  if (!wrapperEl || !videoEl) return;

  let leftIndicator = wrapperEl.querySelector('.seek-indicator-left');
  if (!leftIndicator) {
    leftIndicator = document.createElement('div');
    leftIndicator.className = 'seek-indicator-left';
    leftIndicator.innerHTML = '<span>⏪ -10s</span>';
    leftIndicator.style.cssText = `
      position: absolute; top: 50%; left: 20%; transform: translate(-50%, -50%) scale(0);
      background: rgba(0, 0, 0, 0.75); color: #fff; padding: 10px 18px; border-radius: 30px;
      font-size: 15px; font-weight: 800; pointer-events: none; z-index: 50;
      transition: transform 0.2s ease, opacity 0.2s ease; opacity: 0; backdrop-filter: blur(4px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    `;
    wrapperEl.appendChild(leftIndicator);
  }

  let rightIndicator = wrapperEl.querySelector('.seek-indicator-right');
  if (!rightIndicator) {
    rightIndicator = document.createElement('div');
    rightIndicator.className = 'seek-indicator-right';
    rightIndicator.innerHTML = '<span>+10s ⏩</span>';
    rightIndicator.style.cssText = `
      position: absolute; top: 50%; right: 20%; transform: translate(50%, -50%) scale(0);
      background: rgba(0, 0, 0, 0.75); color: #fff; padding: 10px 18px; border-radius: 30px;
      font-size: 15px; font-weight: 800; pointer-events: none; z-index: 50;
      transition: transform 0.2s ease, opacity 0.2s ease; opacity: 0; backdrop-filter: blur(4px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    `;
    wrapperEl.appendChild(rightIndicator);
  }

  let lastTapTime = 0;

  wrapperEl.addEventListener('click', (e) => {
    if (e.target.closest('.plyr__controls') || e.target.closest('.plyr__control')) return;

    const currentTime = new Date().getTime();
    const tapLength = currentTime - lastTapTime;

    if (tapLength < 350 && tapLength > 0) {
      const rect = wrapperEl.getBoundingClientRect();
      const clickX = e.clientX - rect.left;
      const isLeft = clickX < rect.width / 2;

      const player = typeof getPlayer === 'function' ? getPlayer() : null;
      const targetVideo = player ? (player.media || videoEl) : videoEl;

      if (targetVideo) {
        if (isLeft) {
          targetVideo.currentTime = Math.max(0, targetVideo.currentTime - 10);
          showIndicator(leftIndicator, true);
        } else {
          targetVideo.currentTime = Math.min(targetVideo.duration || targetVideo.currentTime + 10, targetVideo.currentTime + 10);
          showIndicator(rightIndicator, false);
        }
      }
      lastTapTime = 0;
    } else {
      lastTapTime = currentTime;
    }
  });

  function showIndicator(el, isLeft) {
    el.style.transform = `translate(${isLeft ? '-50%' : '50%'}, -50%) scale(1.15)`;
    el.style.opacity = '1';
    setTimeout(() => {
      el.style.transform = `translate(${isLeft ? '-50%' : '50%'}, -50%) scale(0)`;
      el.style.opacity = '0';
    }, 450);
  }
}

// استدعاء مزامنة التقدم من السيرفر فور تحميل أي صفحة
document.addEventListener('DOMContentLoaded', () => {
  syncUserProgressFromServer();
  initWalletUI();
});

setInterval(() => {
  if (localStorage.getItem('cs_token')) {
    if (!document.getElementById('top-wallet-btn') || !document.getElementById('sidebar-wallet-item')) {
      initWalletUI();
    }
  }
}, 800);

window.isStudentEmailVerified = isStudentEmailVerified;
window.toggleCourseOptionsMenu = toggleCourseOptionsMenu;
window.closeAllCourseOptionsMenus = closeAllCourseOptionsMenus;
window.openCancelSubscriptionPasswordPrompt = openCancelSubscriptionPasswordPrompt;
window.confirmCancelSubscription = confirmCancelSubscription;
window.syncUserProgressFromServer = syncUserProgressFromServer;
window.getCompletedLessons = getCompletedLessons;
window.isLessonCompleted = isLessonCompleted;
window.markLessonAsCompleted = markLessonAsCompleted;
window.saveLocalSubscription = saveLocalSubscription;
window.removeLocalSubscription = removeLocalSubscription;
window.initDoubleTapSeek = initDoubleTapSeek;



