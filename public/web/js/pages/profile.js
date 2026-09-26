/* ====================================================
   profile.js — إدارة صفحة الملف الشخصي للطالب/المدرس
   جلب بيانات المستخدم الحقيقية والكورسات المشترك بها من السيرفر
   ==================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  await loadUserProfileData();
});

async function loadUserProfileData() {
  try {
    let user = null;

    // جلب البروفايل المحدث من السيرفر
    const profileRes = await ApiClient.getProfile().catch(() => null);
    if (profileRes && (profileRes.user || profileRes.data)) {
      user = profileRes.user || profileRes.data;
      ApiClient.setSession(null, user);
    } else {
      user = ApiClient.getUser();
    }

    if (!user) {
      window.location.href = 'login.html';
      return;
    }

    // 1. تحديث الأفاتار والاسم والبريد
    const firstName = user.first_name || user.name || 'طالب';
    const fatherName = user.middle_name || '-';
    const familyName = user.last_name || '-';
    const fullName = `${firstName} ${fatherName !== '-' ? fatherName : ''} ${familyName !== '-' ? familyName : ''}`.trim();

    const nameEl = document.getElementById('profile-user-name');
    if (nameEl) nameEl.textContent = fullName;

    const letterEl = document.getElementById('profile-avatar-letter');
    if (letterEl) letterEl.textContent = firstName.charAt(0).toUpperCase();

    // 2. تحديث الحقول التفصيلية في الواجهة
    const fNameEl = document.getElementById('profile-first-name');
    if (fNameEl) fNameEl.textContent = firstName;

    const fatherEl = document.getElementById('profile-father-name');
    if (fatherEl) fatherEl.textContent = fatherName;

    const familyEl = document.getElementById('profile-family-name');
    if (familyEl) familyEl.textContent = familyName;

    // تنسيق تاريخ الميلاد لاستخراج تاريخ اليوم فقط بدون أجزاء التوقيت والملي ثانية الزائدة ISO
    const dobEl = document.getElementById('profile-dob');
    if (dobEl) dobEl.textContent = formatBirthDateOnly(user.birth_date);

    const emailEl = document.getElementById('profile-email');
    if (emailEl) emailEl.textContent = user.email || 'غير مسجل';

    const phoneEl = document.getElementById('profile-phone-number');
    if (phoneEl) phoneEl.textContent = user.phone || 'غير مسجل';

    const countryEl = document.getElementById('profile-country');
    if (countryEl) countryEl.textContent = user.country || 'مصر 🇪🇬';

    // 3. جلب الكورسات المشترك فيها من السيرفر
    await loadEnrolledCourses();

  } catch (error) {
    console.error('Error loading profile:', error);
  }
}

/**
 * دالة الحصول على أيقونة الكورس المناسبة باللوجو الخاص بها
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

async function loadEnrolledCourses() {
  const container = document.getElementById('enrolled-courses-container');
  if (!container) return;

  try {
    const coursesRes = await ApiClient.getCourses();
    const courses = Array.isArray(coursesRes) ? coursesRes : (coursesRes.data || []);

    let enrolledList = [];

    for (const course of courses) {
      try {
        const subRes = await ApiClient.getSubscriptionStatus(course.id);
        if (subRes.is_subscribed || subRes.status === 'subscribed' || subRes.subscribed) {
          enrolledList.push({
            ...course,
            groupName: subRes.group ? subRes.group.name : 'المجموعة الدراسية'
          });
        }
      } catch (e) {
        // Skip
      }
    }

    const countStat = document.getElementById('stat-subscribed-count');
    if (countStat) countStat.textContent = enrolledList.length;

    if (enrolledList.length === 0) {
      container.innerHTML = `
        <div style="background: var(--bg-card); border-radius: 16px; padding: 32px; text-align: center; border: 1px dashed var(--border-color);">
          <div style="font-size: 36px; margin-bottom: 12px;">📚</div>
          <h4 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">غير مشترك في أي كورس حالياً</h4>
          <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">تصفح المجموعات والكورسات المتاحة واطلب الانضمام لبدء التعلم</p>
          <a href="courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 10px 24px; text-decoration: none; font-size: 13px;">تصفح الكورسات المتاحة</a>
        </div>
      `;
      return;
    }

    let html = '';
    enrolledList.forEach(c => {
      const title = c.title || c.name || 'كورس برمجي';
      const icon = getCourseIcon(c);

      html += `
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
          <div style="display: flex; align-items: center; gap: 16px;">
            <img src="${icon}" alt="${title}" style="width: 44px; height: 44px; object-fit: contain;" onerror="this.src='assets/icons/computer_basics.svg'" />
            <div>
              <h4 style="font-size: 16px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">${title}</h4>
              <span style="font-size: 12px; color: #3B82F6; font-weight: 700;">مشترك في: ${c.groupName}</span>
            </div>
          </div>

          <a href="course-viewer.html?courseId=${c.id}" class="auth-btn" style="padding: 8px 20px; font-size: 13px; text-decoration: none;">
            متابعة الدراسة 👈
          </a>
        </div>
      `;
    });

    container.innerHTML = html;

  } catch (e) {
    console.error('Error loading enrolled courses:', e);
  }
}

/**
 * ====================================================
 * دالة تنسيق تاريخ الميلاد (Birth Date Formatter)
 * تقوم بتحويل النص المكتوب بصيغة ISO الكاملة (مثل: 2008-04-03T00:00:00.000000Z)
 * إلى تاريخ فقط بدون أرقام الوقت الزائدة (مثال: 2008-04-03).
 * ====================================================
 */
function formatBirthDateOnly(rawDate) {
  if (!rawDate) return 'غير محدد';
  
  try {
    // إذا كان يحتوي على حرف T الخاص بالوقت في ISO 8601، نقسم السلسلة ونأخذ الجزء الأول
    if (typeof rawDate === 'string' && rawDate.includes('T')) {
      return rawDate.split('T')[0];
    }
    // إذا كان يحتوي على مسافة بين التاريخ والوقت
    if (typeof rawDate === 'string' && rawDate.includes(' ')) {
      return rawDate.split(' ')[0];
    }
    return rawDate;
  } catch (err) {
    console.error('[Format Date Error]:', err);
    return rawDate;
  }
}
