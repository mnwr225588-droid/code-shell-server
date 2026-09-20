/* ====================================================
   courses.js — إدارة صفحة الكورسات والمجالات البرمجية
   يوفر تجربة تفاعلية كاملة مطابقة لتطبيق الطالب Flutter:
   - عرض حالة الاشتراك الحقيقية (مجاني / مدفوع / قريباً / مشترك)
   - تقييد تكرار الاشتراك بحيث يكون الاشتراك في الكورس مرة واحدة فقط
   - واجهة حجز الكورس (Reservation) قبل بدء الدفعة
   - زر إلغاء الاشتراك يتطلب أدخال كلمة مرور الحساب للتحقق والأمان
   ==================================================== */

// عند تحميل عناصر DOM بالكامل، ابدأ جلب البيانات وتفعيل البحث
document.addEventListener('DOMContentLoaded', async () => {
  initCoursesSearch();
  await loadCoursesFromApi();
});

// قائمة الكورسات العامة لحفظ الحالة الحالية في الذاكرة
let allCoursesList = [];

/**
 * ====================================================
 * دالة الحصول على أيقونة الكورس المناسبة
 * تضمن اختيار أيقونة متناسقة مع لغة البرمجة أو المجال
 * ====================================================
 */
function getCourseIcon(course) {
  if (course.icon_url && course.icon_url.startsWith('http')) return course.icon_url;
  if (course.image && course.image.startsWith('http')) return course.image;
  if (course.icon && course.icon.startsWith('http')) return course.icon;

  const name = (course.title || course.name || '').toLowerCase();
  if (name.includes('python') || name.includes('بايثون')) return 'assets/icons/python.svg';
  if (name.includes('flutter') || name.includes('فلاتر')) return 'assets/icons/flutter.svg';
  if (name.includes('dart') || name.includes('دارت')) return 'assets/icons/dart.svg';
  if (name.includes('java') || name.includes('جافا')) return 'assets/icons/java.svg';
  if (name.includes('php') || name.includes('بي إتش بي')) return 'assets/icons/php.svg';
  if (name.includes('html')) return 'assets/icons/html5.svg';
  if (name.includes('css')) return 'assets/icons/css3.svg';
  if (name.includes('computer') || name.includes('حاسوب') || name.includes('أساسيات')) return 'assets/icons/computer_basics.svg';

  return 'assets/icons/python.svg';
}

/**
 * ====================================================
 * دالة تحميل الكورسات من API السيرفر ورسمها بالواجهة
 * ====================================================
 */
async function loadCoursesFromApi() {
  const container = document.getElementById('courses-grid');
  if (!container) return;

  try {
    // طلب جلب الكورسات من السيرفر
    const response = await ApiClient.getCourses();
    let courses = Array.isArray(response) ? response : (response.data || response.courses || []);

    if (courses.length === 0) return;

    // ترتيب الكورسات: المتاحة والمشترك بها تظهر أولاً
    courses.sort((a, b) => {
      const aAvailable = (a.is_active !== false) && !Boolean(a.is_coming_soon);
      const bAvailable = (b.is_active !== false) && !Boolean(b.is_coming_soon);
      const aSub = Boolean(a.is_subscribed);
      const bSub = Boolean(b.is_subscribed);

      if (aAvailable && !bAvailable) return -1;
      if (!aAvailable && bAvailable) return 1;
      if (aSub && !bSub) return -1;
      if (!aSub && bSub) return 1;
      return 0;
    });

    allCoursesList = courses;

    let html = '';
    courses.forEach(course => {
      const isActive = course.is_active !== false;
      const isComingSoon = Boolean(course.is_coming_soon);
      const isFree = Boolean(course.is_free);
      const price = course.price ? parseFloat(course.price) : 0;
      const currency = course.currency_symbol || 'ج.م';
      const isSubscribed = Boolean(course.is_subscribed);

      const title = course.title || course.name || 'كورس برمجي';
      const description = course.description || 'تعلم المهارات والتقنيات البرمجية الحديثة';
      const iconUrl = getCourseIcon(course);
      const courseId = course.id;

      let badgeHtml = '';
      let isAvailable = isActive && !isComingSoon;

      if (!isAvailable) {
        badgeHtml = `<div class="language-card-badge badge-coming">قريباً ⏳</div>`;
      } else if (isSubscribed) {
        badgeHtml = `<div class="language-card-badge" style="background: rgba(34, 197, 94, 0.2); color: #34D399; border-color: rgba(34, 197, 94, 0.3);">مشترك فيه ✅</div>`;
      } else if (isFree) {
        badgeHtml = `<div class="language-card-badge" style="background: rgba(34, 197, 94, 0.2); color: #34D399; border-color: rgba(34, 197, 94, 0.3);">مجاني 🎁</div>`;
      } else {
        badgeHtml = `<div class="language-card-badge" style="background: rgba(59, 130, 246, 0.2); color: #60A5FA; border-color: rgba(59, 130, 246, 0.3);">${price} ${currency} 💳</div>`;
      }

      html += `
        <div onclick="onCourseCardClicked('${courseId}')" style="cursor: pointer; text-decoration: none; color: inherit; display: block; height: 100%;">
          <div class="language-card animate-fadeIn" style="background: linear-gradient(135deg, #1E40AF, #1E293B); box-shadow: 0 10px 28px rgba(30, 64, 175, 0.3); height: 100%;">
            <div class="language-card-circle-1"></div>
            <div class="language-card-circle-2"></div>
            <div class="language-card-icon">
              <img src="${iconUrl}" alt="${title}" onerror="this.src='assets/icons/computer_basics.svg'" />
            </div>
            <div class="language-card-body">
              <div class="language-card-name">${title}</div>
              <div class="language-card-desc">${description}</div>
            </div>
            ${badgeHtml}
          </div>
        </div>
      `;
    });

    container.innerHTML = html;

  } catch (error) {
    console.warn('API courses load error:', error);
  }
}

/**
 * ====================================================
 * دالة الضغط على بطاقة الكورس
 * تفتح نافذة الاشتراك المنبثقة المطابقة للتطبيق
 * ====================================================
 */
async function onCourseCardClicked(courseId) {
  const course = allCoursesList.find(c => String(c.id) === String(courseId));
  if (!course) return;

  const isActive = course.is_active !== false;
  const isComingSoon = Boolean(course.is_coming_soon);

  if (!isActive || isComingSoon) {
    alert('هذا الكورس غير متاح حالياً وسيتم إطلاقه قريباً ⏳');
    return;
  }

  // الاستعلام عن حالة الاشتراك بالسيرفر
  let isSubscribed = Boolean(course.is_subscribed);
  try {
    const subRes = await ApiClient.getSubscriptionStatus(courseId).catch(() => null);
    if (subRes && (subRes.is_subscribed || subRes.status === 'subscribed' || subRes.subscribed)) {
      isSubscribed = true;
      course.is_subscribed = true;
    }
  } catch (e) {}

  // عرض نافذة الاشتراك أو دخول الكورس إذا كان مشترِكاً بالفعل
  showCourseSubscriptionModal(course, isSubscribed);
}

/**
 * ====================================================
 * دالة بناء النافذة المنبثقة للاشتراك والحجز وإلغاء الاشتراك
 * نسخة طبق الأصل من تطبيق Flutter (CourseSubscriptionDialog)
 * ====================================================
 */
function showCourseSubscriptionModal(course, isSubscribed) {
  const existingModal = document.getElementById('course-subscription-modal');
  if (existingModal) existingModal.remove();

  const isFree = Boolean(course.is_free);
  const priceText = isFree ? 'مجاني بالكامل 🎁' : `${course.price || 0} ${course.currency_symbol || 'ج.م'}`;
  const iconUrl = getCourseIcon(course);
  const title = course.title || course.name || 'الكورس البرمجي';

  const modal = document.createElement('div');
  modal.id = 'course-subscription-modal';
  modal.className = 'course-modal-overlay show';
  modal.onclick = (e) => {
    if (e.target === modal) modal.remove();
  };

  modal.innerHTML = `
    <div class="course-modal-card">
      
      <!-- زر الإغلاق -->
      <button class="course-modal-close-btn" onclick="document.getElementById('course-subscription-modal').remove()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>

      <!-- هيدر الكورس -->
      <div class="course-modal-header">
        <div class="course-modal-icon">
          <img src="${iconUrl}" alt="${title}" onerror="this.src='assets/icons/computer_basics.svg'" />
        </div>
        <div class="course-modal-info">
          <h3 class="course-modal-title">${title}</h3>
          <span class="course-modal-badge ${isSubscribed ? 'subscribed' : (isFree ? 'free' : 'paid')}">
            ${isSubscribed ? '✅ أنت مشترك بالفعل' : (isFree ? '🎁 كورس مجاني' : `💳 ${priceText}`)}
          </span>
        </div>
      </div>

      <!-- محتوى الكورس والميزات -->
      <div class="course-modal-body">
        <p class="course-modal-desc">
          ${course.description || 'استمتع بدورة تعليمية شاملة ومميزة لتعلم أساسيات واحتراف المجال البرمجي مع متابعة حية ومشاريع تطبيقية.'}
        </p>

        <div class="course-modal-features">
          <div class="feature-item">
            <span class="feature-icon">✨</span>
            <span>دروس واختبارات تفاعلية مستمرة</span>
          </div>
          <div class="feature-item">
            <span class="feature-icon">👨‍🏫</span>
            <span>متابعة خاصة وتواصل مباشر مع المدرس</span>
          </div>
          <div class="feature-item">
            <span class="feature-icon">📜</span>
            <span>شهادة إتمام معتمدة عند اجتياز الامتحانات</span>
          </div>
        </div>
      </div>

      <!-- الفوتر والأزرار التفاعلية -->
      <div class="course-modal-footer">
        
        ${isSubscribed ? `
          <!-- زر الدخول المباشر إذا كان مشترِكاً بالفعل (الاشتراك يكون مرة واحدة فقط) -->
          <button onclick="window.location.href='course-viewer.html?courseId=${course.id}'" class="btn-action-primary subscribed">
            <span>🚀 أنت مشترك بالفعل - دخول للكورس</span>
          </button>

          <!-- زر إلغاء الاشتراك بشرط إدخال كلمة المرور -->
          <button onclick="openCancelSubscriptionPasswordPrompt('${course.id}', '${title}')" class="btn-action-cancel">
            ❌ إلغاء الاشتراك في الكورس
          </button>
        ` : `
          <!-- زر الاشتراك في الكورس المجاني أو المدفوع -->
          <button id="modal-subscribe-btn" onclick="handleSubscribeClick('${course.id}', ${isFree})" class="btn-action-primary">
            <span>${isFree ? 'اشترك في الكورس الآن مجاناً 🎁' : `اشتراك مدفوع — ${priceText} 💳`}</span>
          </button>

          <!-- زر حجز مقعد في الكورس قبل انطلاق الدفعة (Reservation) -->
          <button id="modal-reserve-btn" onclick="handleReserveClick('${course.id}')" class="btn-action-reserve">
            📌 حجز مقعد في الدفعة القادمة
          </button>
        `}

      </div>
    </div>
  `;

  document.body.appendChild(modal);
}

/**
 * ====================================================
 * دالة تنفيذ الاشتراك (Free or Paid)
 * ====================================================
 */
async function handleSubscribeClick(courseId, isFree) {
  const btn = document.getElementById('modal-subscribe-btn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = 'جاري تسجيل اشتراكك بالسيرفر... ⏳';
  }

  try {
    if (isFree) {
      await ApiClient.subscribeCourse(courseId);
      alert('🎉 تهانينا! تم اشتراكك في الكورس بنجاح.');
      const modal = document.getElementById('course-subscription-modal');
      if (modal) modal.remove();
      window.location.href = `course-viewer.html?courseId=${courseId}`;
    } else {
      await ApiClient.initiatePayment(courseId);
      alert('جاري توجيهك لبوابة الدفع الإلكتروني تماشياً مع السيرفر...');
      window.location.href = `course-viewer.html?courseId=${courseId}`;
    }
  } catch (err) {
    console.error('[Subscription Error]:', err);
    alert('⚠️ ' + (err.message || 'تعذر إتمام عملية الاشتراك، يرجى المحاولة لاحقاً.'));
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = `<span>${isFree ? 'اشترك في الكورس الآن مجاناً 🎁' : 'متابعة عملية الدفع 💳'}</span>`;
    }
  }
}

/**
 * ====================================================
 * دالة تنفيذ حجز مقعد في الكورس (Reservation)
 * ====================================================
 */
async function handleReserveClick(courseId) {
  const btn = document.getElementById('modal-reserve-btn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'جاري الحجز... ⏳';
  }

  try {
    await ApiClient.reserveCourse(courseId);
    alert('📌 تم حجز المقعد بنجاح! سيتم إخطارك فور تفعيل الدفعة الدراسية الجديدة.');
    const modal = document.getElementById('course-subscription-modal');
    if (modal) modal.remove();
  } catch (err) {
    console.error('[Reservation Error]:', err);
    alert('⚠️ ' + (err.message || 'تم تسجيل طلب حجزك بنجاح.'));
    const modal = document.getElementById('course-subscription-modal');
    if (modal) modal.remove();
  }
}

/**
 * ====================================================
 * دالة فتح نافذة إلغاء الاشتراك مع طلب كلمة المرور للأمان
 * ====================================================
 */
function openCancelSubscriptionPasswordPrompt(courseId, courseTitle) {
  const existingPrompt = document.getElementById('password-prompt-modal');
  if (existingPrompt) existingPrompt.remove();

  const promptModal = document.createElement('div');
  promptModal.id = 'password-prompt-modal';
  promptModal.className = 'password-prompt-modal show';

  promptModal.innerHTML = `
    <div class="password-prompt-card">
      <div style="font-size: 36px; margin-bottom: 12px;">🔐</div>
      <h3 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">
        تأكيد إلغاء الاشتراك
      </h3>
      <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.6;">
        هل أنت متأكد من إلغاء اشتراكك في كورس <strong>"${courseTitle}"</strong>؟<br/>
        لأمان حسابك، يرجى أدخال كلمة المرور الحالية لتأكيد الإلغاء.
      </p>

      <div id="cancel-pass-error" style="display:none; background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #EF4444; padding: 10px; border-radius: 10px; font-size: 12.5px; margin-bottom: 14px; font-weight:700;"></div>

      <form id="cancel-pass-form" onsubmit="confirmCancelSubscription(event, '${courseId}')">
        <div style="margin-bottom: 18px; text-align: right;">
          <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">كلمة المرور الحالية</label>
          <input type="password" id="cancel-password-input" style="width: 100%; height: 44px; background: var(--bg-body); border: 1px solid var(--border-light); border-radius: 12px; padding: 0 14px; font-size: 14px; color: var(--text-primary); outline: none;" placeholder="أدخل كلمة مرور حسابك" required />
        </div>

        <div style="display: flex; gap: 10px;">
          <button type="submit" id="cancel-confirm-submit-btn" style="flex: 1; height: 44px; background: #EF4444; color: #FFFFFF; border: none; border-radius: 12px; font-size: 14px; font-weight: 800; cursor: pointer;">
            تأكيد إلغاء الاشتراك
          </button>
          <button type="button" onclick="document.getElementById('password-prompt-modal').remove()" style="background: var(--bg-body); border: 1px solid var(--border-light); color: var(--text-primary); padding: 0 18px; border-radius: 12px; font-size: 13.5px; font-weight: 700; cursor: pointer;">
            إلغاء
          </button>
        </div>
      </form>
    </div>
  `;

  document.body.appendChild(promptModal);
}

/**
 * ====================================================
 * دالة تأكيد إلغاء الاشتراك بالسيرفر بعد التأكد من كلمة المرور
 * ====================================================
 */
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

    // إرسال طلب إلغاء الاشتراك مرفقاً بكلمة المرور للتحقق
    await ApiClient.cancelSubscription(courseId, password);

    alert('تم إلغاء الاشتراك في الكورس بنجاح.');
    
    // إغلاق النوافذ المنبثقة وإعادة تحميل القائمة لتحديث الحالة
    const promptModal = document.getElementById('password-prompt-modal');
    if (promptModal) promptModal.remove();

    const mainModal = document.getElementById('course-subscription-modal');
    if (mainModal) mainModal.remove();

    await loadCoursesFromApi();

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

/**
 * ====================================================
 * دالة تفعيل البحث المباشر في شاشة الكورسات
 * ====================================================
 */
function initCoursesSearch() {
  const searchInput = document.getElementById('top-global-search');
  if (!searchInput) return;

  searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim().toLowerCase();
    const cards = document.querySelectorAll('#courses-grid .language-card');
    
    cards.forEach(card => {
      const text = card.textContent.toLowerCase();
      card.style.display = text.includes(query) ? 'flex' : 'none';
    });
  });
}

// تصدير الدوال على مستوى النافذة العامة لتمكين الاستدعاء من العناصر
window.onCourseCardClicked = onCourseCardClicked;
window.handleSubscribeClick = handleSubscribeClick;
window.handleReserveClick = handleReserveClick;
window.openCancelSubscriptionPasswordPrompt = openCancelSubscriptionPasswordPrompt;
window.confirmCancelSubscription = confirmCancelSubscription;
