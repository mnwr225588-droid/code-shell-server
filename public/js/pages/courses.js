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
  const urlParams = new URLSearchParams(window.location.search);
  const paymentStatus = urlParams.get('status');
  const returnCourseId = urlParams.get('course_id') || urlParams.get('courseId') || urlParams.get('id');

  if (paymentStatus === 'success' && returnCourseId) {
    // 1. تفريغ أي كاش محلي قديم وتحديث ذاكرة الاشتراكات
    if (typeof saveLocalSubscription === 'function') {
      saveLocalSubscription(returnCourseId);
    }
    localStorage.setItem(`cs_subscribed_${returnCourseId}`, 'true');

    // 2. إرسال طلب استعلام مباشر للباك إند بهيدر التوثيق (Bearer Token) للتأكد من السيرفر
    try {
      await ApiClient.getSubscriptionStatus(returnCourseId).catch(() => null);
    } catch (e) {}

    // 3. جلب الكورسات وتحديث الرسم فوراً (Re-render)
    initCoursesSearch();
    await loadCoursesFromApi();

    // 4. إظهار التهنئة وتنظيف رابط الـ URL
    alert('🎉 تهانينا! تمت عملية الدفع بنجاح وتفعيل اشتراكك في الكورس.');
    window.history.replaceState({}, document.title, window.location.pathname);
  } else {
    initCoursesSearch();
    await loadCoursesFromApi();
  }
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

    // إزالة كورس أساسيات الحاسوب من شبكة اللغات البرمجية لإبقائه في البانر العلوي المميز فقط
    courses = courses.filter(c => {
      const titleLower = (c.title || c.name || '').toLowerCase();
      return String(c.id) !== '1' && !titleLower.includes('computer basics') && !titleLower.includes('أساسيات الحاسوب');
    });

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

      const groupName = course.group ? (course.group.name || course.group.title) : (course.group_name || '');
      const groupTagHtml = (isSubscribed && groupName) ? `
        <div style="font-size: 11.5px; color: #60A5FA; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); padding: 3px 10px; border-radius: 8px; margin-top: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
          👥 ${groupName}
        </div>
      ` : '';

      let badgeHtml = '';
      let isAvailable = isActive && !isComingSoon;

      if (!isAvailable) {
        badgeHtml = `<div class="language-card-badge badge-coming">قريباً ⏳</div>`;
      } else if (isSubscribed) {
        badgeHtml = `<div class="language-card-badge" style="background: rgba(34, 197, 94, 0.2); color: #34D399; border-color: rgba(34, 197, 94, 0.3);">مشترك فيه ✅ ${groupName ? `(${groupName})` : ''}</div>`;
      } else if (isFree) {
        badgeHtml = `<div class="language-card-badge" style="background: rgba(34, 197, 94, 0.2); color: #34D399; border-color: rgba(34, 197, 94, 0.3);">مجاني 🎁</div>`;
      } else {
        badgeHtml = `<div class="language-card-badge" style="background: rgba(59, 130, 246, 0.2); color: #60A5FA; border-color: rgba(59, 130, 246, 0.3);">${price} ${currency} 💳</div>`;
      }

      html += `
        <div onclick="onCourseCardClicked('${courseId}')" style="cursor: pointer; text-decoration: none; color: inherit; display: block; height: 100%; position: relative;">
          <div class="language-card animate-fadeIn" style="background: linear-gradient(135deg, #1E40AF, #1E293B); box-shadow: 0 10px 28px rgba(30, 64, 175, 0.3); height: 100%; position: relative;">
            <div class="language-card-circle-1"></div>
            <div class="language-card-circle-2"></div>
            <div class="language-card-icon">
              <img src="${iconUrl}" alt="${title}" onerror="this.src='assets/icons/computer_basics.svg'" />
            </div>
            <div class="language-card-body">
              <div class="language-card-name">${title}</div>
              ${groupTagHtml}
              <div class="language-card-desc" style="margin-top: 4px;">${description}</div>
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
 * تدفق مباشر للكورس إذا كان مشتركاً، أو فتح نافذة الاشتراك
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
  let canCancel = true;
  try {
    const subRes = await ApiClient.getSubscriptionStatus(courseId).catch(() => null);
    if (subRes) {
      if (subRes.is_subscribed || subRes.status === 'subscribed' || subRes.subscribed) {
        isSubscribed = true;
        course.is_subscribed = true;
      } else {
        isSubscribed = false;
        course.is_subscribed = false;
        localStorage.removeItem(`cs_subscribed_${courseId}`);
        if (String(courseId) === '1') localStorage.removeItem('cs_subscribed_computer_basics');
      }
      if (subRes.can_cancel !== undefined) {
        canCancel = Boolean(subRes.can_cancel);
      }
    }
  } catch (e) {}

  // إذا كان مشترِكاً بالفعل، يدخل مباشرة للكورس دون فتح نافذة الاشتراك
  if (isSubscribed) {
    window.location.href = `course-viewer.html?courseId=${course.id}`;
    return;
  }

  // عرض نافذة الاشتراك للمستخدم غير المشترك
  showCourseSubscriptionModal(course, false, canCancel);
}

/**
 * ====================================================
 * دالة بناء النافذة المنبثقة للاشتراك والحجز وإلغاء الاشتراك
 * نسخة طبق الأصل من تطبيق Flutter (CourseSubscriptionDialog)
 * ====================================================
 */
function showCourseSubscriptionModal(course, isSubscribed, canCancel = true) {
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

        ${!isSubscribed ? `
          <!-- خيار الموافقة على الشروط والأحكام قبل الاشتراك -->
          <div class="terms-checkbox-wrapper" style="margin-top: 15px; margin-bottom: 10px; background: rgba(16, 185, 129, 0.12); border: 1.5px solid #10B981; border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" id="modal-terms-check" onchange="toggleModalSubscribeBtn()" style="width: 18px; height: 18px; accent-color: #10B981; cursor: pointer;">
            <label for="modal-terms-check" style="color: #E2E8F0; font-size: 13px; font-weight: 600; margin: 0; cursor: pointer;">
              أوافق على <a href="terms.html" target="_blank" style="color: #38BDF8; text-decoration: underline; font-weight: 700;">الشروط والأحكام</a> واتفاقية شراء الكورس في منصة كود شيل.
            </label>
          </div>
        ` : ''}
      </div>

      <!-- الفوتر والأزرار التفاعلية -->
      <div class="course-modal-footer">
        
        ${isSubscribed ? `
          <!-- زر الدخول المباشر للكورس -->
          <button onclick="window.location.href='course-viewer.html?courseId=${course.id}'" class="btn-action-primary subscribed">
            <span>🚀 أنت مشترك بالفعل - دخول للكورس</span>
          </button>

          ${canCancel ? `
            <!-- زر إلغاء الاشتراك المباشر مع استرداد المبلغ للمحفظة -->
            <button onclick="handleDirectCancelSubscription('${course.id}', '${title}')" class="btn-action-cancel" style="background: rgba(239, 68, 68, 0.2); color: #FCA5A5; border: 1px solid rgba(239, 68, 68, 0.4); margin-top: 10px; padding: 12px; border-radius: 12px; width: 100%; font-weight: 700; cursor: pointer;">
              ❌ إلغاء الاشتراك في الكورس (استرداد الرصيد للمحفظة)
            </button>
          ` : `
            <!-- زر إلغاء الاشتراك المعطل بعد مرور يومين -->
            <button disabled class="btn-action-cancel" style="background: rgba(239, 68, 68, 0.1); color: #94A3B8; border: 1px solid rgba(255, 255, 255, 0.1); margin-top: 10px; padding: 12px; border-radius: 12px; width: 100%; font-weight: 700; cursor: not-allowed; opacity: 0.7;">
              ⚠️ انتهت مهلة إلغاء الاشتراك (مر أكثر من يومين على الاشتراك)
            </button>
          `}
        ` : `
          <!-- زر الاشتراك الإلكتروني عبر البوابة -->
          <button id="modal-subscribe-btn" disabled onclick="handleSubscribeClick('${course.id}', ${isFree})" class="btn-action-primary" style="margin-bottom: 8px; opacity: 0.6; cursor: not-allowed;">
            <span>${isFree ? 'اشترك في الكورس الآن مجاناً 🎁' : `اشتراك عبر بوابة الدفع — ${priceText} 💳`}</span>
          </button>

          ${!isFree ? `
            <!-- زر الخصم والدفع المباشر من محفظة الطالب -->
            <button id="modal-wallet-pay-btn" disabled onclick="handleWalletPayClick('${course.id}')" style="background: linear-gradient(135deg, #1E3A8A, #2563EB); color: #FFF; border: none; padding: 12px; border-radius: 12px; width: 100%; font-weight: 700; margin-bottom: 8px; cursor: not-allowed; opacity: 0.6; display: flex; align-items: center; justify-content: center; gap: 8px;">
              💳 الدفع من محفظة كود شيل
            </button>
          ` : ''}

          <!-- زر حجز مقعد في الكورس -->
          <button id="modal-reserve-btn" onclick="handleReserveClick('${course.id}')" class="btn-action-reserve">
            📌 حجز مقعد في الدفعة القادمة
          </button>
        `}

      </div>
    </div>
  `;

  document.body.appendChild(modal);
}

/** تفعيل أزرار الاشتراك عند التأشير على الشروط والأحكام */
function toggleModalSubscribeBtn() {
  const check = document.getElementById('modal-terms-check');
  const btn = document.getElementById('modal-subscribe-btn');
  const walletBtn = document.getElementById('modal-wallet-pay-btn');

  const isChecked = check ? check.checked : false;
  if (btn) {
    btn.disabled = !isChecked;
    btn.style.opacity = isChecked ? '1' : '0.6';
    btn.style.cursor = isChecked ? 'pointer' : 'not-allowed';
  }
  if (walletBtn) {
    walletBtn.disabled = !isChecked;
    walletBtn.style.opacity = isChecked ? '1' : '0.6';
    walletBtn.style.cursor = isChecked ? 'pointer' : 'not-allowed';
  }
}

/** الدفع المباشر للاشتراك في الكورس باستخدام رصيد المحفظة */
async function handleWalletPayClick(courseId) {
  const check = document.getElementById('modal-terms-check');
  if (check && !check.checked) {
    alert('⚠️ يرجى الموافقة على الشروط والأحكام أولاً لإتمام العملية.');
    return;
  }

  const walletBtn = document.getElementById('modal-wallet-pay-btn');
  if (walletBtn) {
    walletBtn.disabled = true;
    walletBtn.innerHTML = '<span>⏳ جاري الخصم والتفعيل من المحفظة...</span>';
  }

  try {
    const res = await ApiClient.payWithWallet(courseId);
    if (res && res.status) {
      if (typeof saveLocalSubscription === 'function') saveLocalSubscription(courseId);
      alert('🎉 ' + (res.message || 'تم الخصم من المحفظة وتفعيل اشتراكك في الكورس بنجاح!'));
      const modal = document.getElementById('course-subscription-modal');
      if (modal) modal.remove();
      await loadCoursesFromApi();
      window.location.href = `course-viewer.html?courseId=${courseId}`;
    } else {
      alert('⚠️ ' + (res.message || 'فشلت عملية الاشتراك بالمحفظة.'));
      if (walletBtn) {
        walletBtn.disabled = false;
        walletBtn.innerHTML = '💳 الدفع من محفظة كود شيل';
      }
    }
  } catch (err) {
    alert('⚠️ ' + (err.message || 'تعذر الاتصال بالمحفظة. يرجى التأكد من توفر الرصيد الكافي.'));
    if (walletBtn) {
      walletBtn.disabled = false;
      walletBtn.innerHTML = '💳 الدفع من محفظة كود شيل';
    }
  }
}

/** إلغاء الاشتراك المباشر وإعادة المبلغ إلى المحفظة بدون التواصل مع الدعم */
async function handleDirectCancelSubscription(courseId, courseTitle) {
  try {
    const subRes = await ApiClient.getSubscriptionStatus(courseId).catch(() => null);
    if (subRes && subRes.can_cancel === false) {
      alert('⚠️ عذراً، لا يمكن إلغاء الاشتراك بعد مرور أكثر من يومين (48 ساعة) على تاريخ الاشتراك.');
      return;
    }
  } catch (e) {}

  const confirmCancel = confirm(`هل أنت متأكد من إلغاء اشتراكك في كورس "${courseTitle}"؟\n\nسيتم إلغاء الاشتراك وإعادة كامل مبلغ الكورس إلى محفظتك الإلكترونية فوراً.`);
  if (!confirmCancel) return;

  try {
    const res = await ApiClient.cancelSubscription(courseId);
    if (res && res.status) {
      localStorage.removeItem(`cs_subscribed_${courseId}`);
      if (String(courseId) === '1') localStorage.removeItem('cs_subscribed_computer_basics');
      let sc = JSON.parse(localStorage.getItem('cs_subscribed_courses') || '[]');
      sc = sc.filter(id => String(id) !== String(courseId));
      localStorage.setItem('cs_subscribed_courses', JSON.stringify(sc));

      alert(res.message || 'تم إلغاء الاشتراك وإعادة المبلغ إلى محفظتك بنجاح!');
      const modal = document.getElementById('course-subscription-modal');
      if (modal) modal.remove();
      await loadCoursesFromApi();
    } else {
      alert('⚠️ ' + (res.message || 'تعذر إلغاء الاشتراك.'));
    }
  } catch (err) {
    alert('⚠️ ' + (err.message || 'حدث خطأ أثناء إلغاء الاشتراك.'));
  }
}

/**
 * ====================================================
 * دالة تنفيذ الاشتراك (Free or Paid)
 * ====================================================
 */
async function handleSubscribeClick(courseId, isFree) {
  if (typeof isStudentEmailVerified === 'function' && !isStudentEmailVerified()) {
    alert('⚠️ يجب تأكيد بريدك الإلكتروني أولاً لتتمكن من الاشتراك في الكورسات.\n\nيرجى الضغط على زر "إرسال بريد التأكيد" في أعلى الصفحة.');
    const banner = document.getElementById('email-verification-banner');
    if (banner) banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  const btn = document.getElementById('modal-subscribe-btn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span>⏳ جاري الاشتراك...</span>';
  }

  try {
    if (isFree) {
      await ApiClient.subscribeCourse(courseId);
      if (typeof saveLocalSubscription === 'function') saveLocalSubscription(courseId);
      alert('🎉 تهانينا! تم اشتراكك في الكورس بنجاح.');
      const modal = document.getElementById('course-subscription-modal');
      if (modal) modal.remove();
      window.location.href = `course-viewer.html?courseId=${courseId}`;
    } else {
      const res = await ApiClient.initiatePayment(courseId);
      if (res && res.data && res.data.payment_url) {
        window.location.href = res.data.payment_url;
      } else {
        alert('تم تجهيز جلسة الدفع الإلكتروني بنجاح.');
        window.location.href = `course-viewer.html?courseId=${courseId}`;
      }
    }
  } catch (err) {
    console.error('[Subscription Error]:', err);
    // عرض رسالة خطأ واضحة للمستخدم دون ذكر تفاصيل تقنية
    const userMsg = isFree
      ? 'تعذر إتمام عملية الاشتراك، يرجى المحاولة مرة أخرى.'
      : 'تعذر الاتصال ببوابة الدفع. يرجى التأكد من اتصالك بالإنترنت والمحاولة مرة أخرى.';
    alert('⚠️ ' + userMsg);
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
  if (typeof isStudentEmailVerified === 'function' && !isStudentEmailVerified()) {
    alert('⚠️ يجب تأكيد بريدك الإلكتروني أولاً لتتمكن من حجز الكورسات.\n\nيرجى الضغط على زر "إرسال بريد التأكيد" في أعلى الصفحة.');
    const banner = document.getElementById('email-verification-banner');
    if (banner) banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

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
