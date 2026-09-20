/* ====================================================
   app.js — الملف الرئيسي للتطبيق (JavaScript Core Logic)
   يتولى حماية التطبيق ومنع الدخول إلا بعد تسجيل الدخول
   ==================================================== */

const AppState = {
  sidebarCollapsed: false,
  sidebarMobileOpen: false,
  currentPage: 'home',
  theme: 'light',
};

// ====================================================
// حارس المصادقة والتوجيه الإجباري (Strict Auth Guard)
// ====================================================
function enforceAuthGuard() {
  const token = localStorage.getItem('cs_token');
  const userRaw = localStorage.getItem('cs_user');
  const path = window.location.pathname.toLowerCase();

  const isAuthPage = path.includes('login.html') || path.includes('register.html');

  // إذا لم يكن المستخدم مسجلاً للدخول وحاول دخول أي صفحة غير تسجيل الدخول أو التنسيق
  if (!token || !userRaw) {
    if (!isAuthPage) {
      window.location.href = 'login.html';
      return false;
    }
  } else {
    // إذا كان مسجلاً بالفعل وفصل لصفحة تسجيل الدخول، يتم تحويله للرئيسية أو المدرس
    if (isAuthPage) {
      try {
        const user = JSON.parse(userRaw);
        if (user.user_type === 'teacher' || user.role === 'teacher') {
          window.location.href = 'teacher.html';
        } else {
          window.location.href = 'index.html';
        }
        return false;
      } catch (e) {
        localStorage.removeItem('cs_token');
        localStorage.removeItem('cs_user');
        window.location.href = 'login.html';
        return false;
      }
    }
  }

  return true;
}

document.addEventListener('DOMContentLoaded', () => {
  // 1. فحص حارس المصادقة أولاً قبل تنفيذ أي شيء
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
function checkEmailVerificationBanner(user) {
  const isTeacher = user.user_type === 'teacher' || user.role === 'teacher';
  const isVerified = user.email_verified_at || user.is_email_verified;

  if (!isTeacher && !isVerified) {
    let banner = document.getElementById('email-verification-banner');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'email-verification-banner';
      banner.style.cssText = 'background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(234, 88, 12, 0.15) 100%); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 16px; padding: 14px 20px; margin: 16px 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 14px; color: #F59E0B; backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.1);';
      banner.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="font-size: 22px;">📧</span>
          <div>
            <strong style="color: #FBBF24; font-size: 15px;">لم يتم تأكيد البريد الإلكتروني بعد!</strong>
            <div style="font-size: 12px; color: #CBD5E1; margin-top: 2px;">يرجى تفعيل حسابك عبر رابط التأكيد المرسل إلى إيميلك (تأكد من فحص مجلد Spam/Junk).</div>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <button onclick="resendEmailVerificationPrompt()" id="resend-email-btn" style="background: #F59E0B; color: #0F172A; border: none; padding: 8px 14px; border-radius: 10px; font-size: 12px; font-weight: 800; cursor: pointer; transition: all 0.2s ease;">إعادة إرسال البريد 📩</button>
          <button onclick="checkEmailVerificationStatus()" id="check-email-btn" style="background: rgba(255,255,255,0.1); color: #FFFFFF; border: 1px solid rgba(255,255,255,0.2); padding: 8px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s ease;">تحديث الحالة 🔄</button>
        </div>
      `;
      const mainContent = document.getElementById('main-content') || document.querySelector('.main-content') || document.body;
      if (mainContent.firstChild) {
        mainContent.insertBefore(banner, mainContent.firstChild);
      } else {
        mainContent.appendChild(banner);
      }
    }
  }
}

async function resendEmailVerificationPrompt() {
  const btn = document.getElementById('resend-email-btn');
  try {
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'جاري الإرسال... ⏳';
    }
    await ApiClient.resendVerification();
    alert('✅ تم إرسال رسالة التأكيد بنجاح إلى بريدك الإلكتروني!\n\nيرجى فتح صندوق الوارد (Inbox) أو مجلد الرسائل غير المرغوب فيها (Spam / Junk Mail).');
  } catch (err) {
    console.error('[Resend Verification Error]:', err);
    alert('⚠️ ' + (err.message || 'فشل إرسال بريد التأكيد، يرجى المحاولة لاحقاً.'));
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = 'إعادة إرسال البريد 📩';
    }
  }
}

async function checkEmailVerificationStatus() {
  const btn = document.getElementById('check-email-btn');
  try {
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'جاري التحديث... ⏳';
    }
    const profileRes = await ApiClient.getProfile();
    const freshUser = profileRes.user || profileRes;

    const currentSessionUser = ApiClient.getUser() || {};
    const updatedUser = { ...currentSessionUser, ...freshUser };
    localStorage.setItem('cs_user', JSON.stringify(updatedUser));

    if (updatedUser.email_verified_at || updatedUser.is_email_verified) {
      alert('🎉 مبروك! تم تأكيد بريدك الإلكتروني بنجاح.');
      const banner = document.getElementById('email-verification-banner');
      if (banner) banner.remove();
    } else {
      alert('⚠️ لم يتم تأكيد البريد الإلكتروني بعد.\n\nيرجى مراجعة بريدك الإلكتروني (بما في ذلك مجلد الرسائل غير المرغوب فيها Spam) والضغط على رابط التفعيل المرفق.');
    }
  } catch (err) {
    console.error('[Check Verification Status Error]:', err);
    alert('⚠️ حدث خطأ أثناء فحص حالة التفعيل.');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = 'تحديث الحالة 🔄';
    }
  }
}
