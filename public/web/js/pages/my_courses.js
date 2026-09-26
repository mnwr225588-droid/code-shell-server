/* ====================================================
   my_courses.js — عرض كروت الكورسات الحالية للطالب
   جلب الكورسات والجروبات التي التحق بها من السيرفر باللوجو الخاص بها وبطريقة احترافيه
   ==================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  await loadMyCoursesFromApi();
});

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

async function loadMyCoursesFromApi() {
  const container = document.getElementById('main-page-container');
  if (!container) return;

  container.innerHTML = `
    <div style="margin-bottom: 24px;">
      <h3 class="section-title">كورساتي الحالية 💡</h3>
      <p class="section-subtitle">جاري تحميل كورساتك المشترك بها...</p>
    </div>
    <div style="text-align:center; padding: 60px 20px; color: var(--text-muted);">
      <div style="font-size: 36px; margin-bottom: 12px; animation: pulse 1.5s infinite;">⏳</div>
      <p style="font-size: 15px; font-weight: 600;">جاري تحميل الكورسات...</p>
    </div>
  `;

  try {
    const coursesRes = await ApiClient.getCourses();
    const courses = Array.isArray(coursesRes) ? coursesRes : (coursesRes.data || []);

    let enrolledCourses = [];
    let localSubscribedCourses = [];
    try {
      localSubscribedCourses = JSON.parse(localStorage.getItem('cs_subscribed_courses') || '[]');
    } catch (e) {}

    for (const course of courses) {
      let isSubscribed = Boolean(course.is_subscribed);
      let groupObj = course.group || null;

      if (String(course.id) === '1' && localStorage.getItem('cs_subscribed_computer_basics') === 'true') {
        isSubscribed = true;
      }

      if (localSubscribedCourses.includes(String(course.id)) || localSubscribedCourses.includes(Number(course.id)) || localStorage.getItem(`cs_subscribed_${course.id}`) === 'true') {
        isSubscribed = true;
      }

      try {
        const subRes = await ApiClient.getSubscriptionStatus(course.id);
        if (subRes && (subRes.is_subscribed || subRes.status === 'subscribed' || subRes.subscribed)) {
          isSubscribed = true;
          if (subRes.group) groupObj = subRes.group;
          else if (subRes.group_name) groupObj = { name: subRes.group_name };
          if (typeof saveLocalSubscription === 'function') {
            saveLocalSubscription(course.id);
          }
        } else if (subRes && subRes.is_subscribed === false) {
          isSubscribed = false;
          localStorage.removeItem(`cs_subscribed_${course.id}`);
          if (String(course.id) === '1') {
            localStorage.removeItem('cs_subscribed_computer_basics');
          }
          let sc = JSON.parse(localStorage.getItem('cs_subscribed_courses') || '[]');
          sc = sc.filter(id => String(id) !== String(course.id));
          localStorage.setItem('cs_subscribed_courses', JSON.stringify(sc));
        }
      } catch (e) {
        // Fallback
      }

      if (isSubscribed) {
        enrolledCourses.push({
          ...course,
          group: groupObj
        });
      }
    }

    // إذا اشترك الطالب في كورس أساسيات الحاسوب محلياً
    if (localStorage.getItem('cs_subscribed_computer_basics') === 'true' || localSubscribedCourses.includes('1') || localSubscribedCourses.includes(1)) {
      const alreadyHasCb = enrolledCourses.some(c => String(c.id) === '1');
      if (!alreadyHasCb) {
        enrolledCourses.unshift({
          id: 1,
          title: 'Computer Basics — أساسيات الحاسوب',
          description: 'فهم مكونات الحاسوب ونظم التشغيل والشبكات ومفهوم البرمجة مع 12 درس واختبار تفاعلي',
          icon_url: 'assets/icons/computer_basics.svg',
          group: { name: 'المجموعة الأولى (الأساسية)' }
        });
      }
    }

    if (enrolledCourses.length === 0) {
      container.innerHTML = `
        <div style="margin-bottom: 24px;">
          <h3 class="section-title">كورساتي الحالية 💡</h3>
          <p class="section-subtitle">الكورسات التي التحقت بمجموعاتها وتدرسها الآن</p>
        </div>
        <div style="background: var(--bg-card); border-radius: 20px; padding: 48px; text-align: center; border: 1px dashed var(--border-color);">
          <div style="font-size: 48px; margin-bottom: 16px;">🎓</div>
          <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">لم تفتح أو تشترك في أي كورس بعد</h3>
          <p style="font-size: 14px; color: var(--text-muted); max-width: 400px; margin: 0 auto 20px;">
            تصفح صفحة الكورسات والمجموعات واشترك في المجموعات المتاحة لبدء مشوارك التعليمي.
          </p>
          <a href="courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 28px; text-decoration: none;">تصفح الكورسات والمجموعات</a>
        </div>
      `;
      return;
    }

    let html = `
      <div style="margin-bottom: 24px;">
        <h3 class="section-title">الكورسات التي تدرسها الآن (${enrolledCourses.length}) 💡</h3>
        <p class="section-subtitle">تابع تقدمك واستكمل دروسك التعليمية من حيث توقفت</p>
      </div>
    `;

    enrolledCourses.forEach(c => {
      const title = c.title || c.name || 'كورس برمجي';
      const titleClean = (title || '').replace(/'/g, "\\'");
      const desc = c.description || 'تابع المستويات والدروس المباشرة مع مجموعة المدرس';
      const groupName = c.group ? (c.group.name || c.group.title || 'المجموعة النشطة') : 'المجموعة النشطة';
      const icon = getCourseIcon(c);
      const isComputerBasics = String(c.id) === '1' || title.toLowerCase().includes('computer basics') || title.includes('أساسيات الحاسوب');
      const courseLink = isComputerBasics ? 'computer-basics.html' : `course-viewer.html?courseId=${c.id}`;

      html += `
        <div class="my-course-pro-card animate-fadeIn" style="margin-bottom: 20px; position: relative;">
          <div class="pro-card-content">
            <div class="pro-card-icon-wrapper" style="background: rgba(37, 99, 235, 0.12); border: 1px solid rgba(37, 99, 235, 0.25);">
              <img src="${icon}" alt="${title}" style="width: 44px; height: 44px; object-fit: contain;" onerror="this.src='assets/icons/computer_basics.svg'" />
              <div class="pro-card-glow"></div>
            </div>

            <div class="pro-card-info">
              <span class="pro-card-level" style="background: rgba(59, 130, 246, 0.15); color: #60A5FA; border: 1px solid rgba(59, 130, 246, 0.3); padding: 4px 12px; border-radius: 10px; font-weight: 800; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px;">👥 المجموعة: ${groupName}</span>
              <h4 class="pro-card-title" style="margin-top: 6px;">${title}</h4>
              <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">${desc}</p>
            </div>
          </div>

          <div class="pro-card-actions" style="display: flex; align-items: center; gap: 10px;">
            <a href="${courseLink}" class="pro-card-btn">
              <span>متابعة الدروس 👈</span>
            </a>
          </div>
        </div>
      `;
    });

    container.innerHTML = html;

  } catch (error) {
    console.error('Error loading my courses:', error);
  }
}
