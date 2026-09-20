/* ====================================================
   my_courses.js — عرض كروت الكورسات الحالية للطالب
   جلب الكورسات والجروبات التي التحق بها من السيرفر
   ==================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  await loadMyCoursesFromApi();
});

async function loadMyCoursesFromApi() {
  const container = document.getElementById('main-page-container');
  if (!container) return;

  try {
    const coursesRes = await ApiClient.getCourses();
    const courses = Array.isArray(coursesRes) ? coursesRes : (coursesRes.data || []);

    let enrolledCourses = [];

    for (const course of courses) {
      try {
        const subRes = await ApiClient.getSubscriptionStatus(course.id);
        if (subRes.is_subscribed || subRes.status === 'subscribed' || subRes.subscribed) {
          enrolledCourses.push({
            ...course,
            group: subRes.group || null
          });
        }
      } catch (e) {
        // Skip
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
      const desc = c.description || 'تابع المستويات والدروس المباشرة مع مجموعة المدرس';
      const groupName = c.group ? c.group.name : 'المجموعة النشطة';
      const icon = c.icon_url || c.image || 'assets/icons/python.svg';

      html += `
        <div class="my-course-pro-card animate-fadeIn" style="margin-bottom: 20px;">
          <div class="pro-card-content">
            <div class="pro-card-icon-wrapper">
              <img src="${icon}" alt="${title}" style="width: 40px; height: 40px; object-fit: contain;" onerror="this.src='assets/icons/computer_basics.svg'" />
              <div class="pro-card-glow"></div>
            </div>

            <div class="pro-card-info">
              <span class="pro-card-level" style="color: #3B82F6; font-weight: 700;">${groupName}</span>
              <h4 class="pro-card-title">${title}</h4>
              <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">${desc}</p>
            </div>
          </div>

          <div class="pro-card-actions">
            <a href="course-viewer.html?courseId=${c.id}" class="pro-card-btn">
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
