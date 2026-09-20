/* ====================================================
   groups.js — إدارة المجموعات الدراسية
   جلب المجموعات التفاعلية وعرض المدرس وعدد الطلاب والاشتراك
   ==================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  const user = ApiClient.getUser();
  if (user && (user.user_type === 'teacher' || user.role === 'teacher')) {
    const teacherBtn = document.getElementById('teacher-nav-item');
    if (teacherBtn) teacherBtn.style.display = 'flex';
  }

  await loadAllGroups();
});

async function loadAllGroups() {
  const container = document.getElementById('groups-list-container');
  if (!container) return;

  try {
    const coursesRes = await ApiClient.getCourses();
    const courses = Array.isArray(coursesRes) ? coursesRes : (coursesRes.data || []);

    let allGroups = [];

    for (const course of courses) {
      try {
        const groupsRes = await ApiClient.getCourseGroups(course.id);
        const groups = Array.isArray(groupsRes) ? groupsRes : (groupsRes.data || []);
        
        groups.forEach(g => {
          allGroups.push({
            ...g,
            courseId: course.id,
            courseTitle: course.title || course.name
          });
        });
      } catch (e) {
        // Skip course without groups
      }
    }

    if (allGroups.length === 0) {
      container.innerHTML = `
        <div style="background: var(--bg-card); border-radius: 20px; padding: 48px; text-align: center; border: 1px dashed var(--border-color); grid-column: 1 / -1;">
          <div style="font-size: 48px; margin-bottom: 16px;">👥</div>
          <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">لا توجد مجموعات دراسية مفتوحة حالياً</h3>
          <p style="font-size: 14px; color: var(--text-muted); max-width: 400px; margin: 0 auto 20px;">
            يقوم الأدمن بإضافة مجموعات جديدة بانتظام للكورسات والتقنيات البرمجية.
          </p>
          <a href="courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 28px; text-decoration: none;">تصفح الكورسات</a>
        </div>
      `;
      return;
    }

    let html = '';
    allGroups.forEach(group => {
      const teacherName = group.teacher ? `${group.teacher.first_name || ''} ${group.teacher.last_name || ''}`.trim() : 'المدرس الرئيسي';
      const maxStudents = group.max_students || 30;
      const enrolledCount = group.students_count || group.subscriptions_count || 0;
      const isOpen = group.is_active !== false && (enrolledCount < maxStudents);

      const statusBadge = isOpen 
        ? `<span style="background: rgba(34, 197, 94, 0.15); color: #22C55E; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700;">باب التقديم مفتوح</span>`
        : `<span style="background: rgba(239, 68, 68, 0.15); color: #EF4444; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700;">مكتملة العدد</span>`;

      html += `
        <div class="group-card animate-fadeIn">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
              <span style="font-size: 13px; font-weight: 700; color: #3B82F6;">${group.courseTitle}</span>
              ${statusBadge}
            </div>
            <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">${group.name || 'المجموعة الدراسية'}</h3>
            <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 16px;">
              👨‍🏫 المدرس المحاضر: <strong>${teacherName}</strong>
            </p>
            
            <div style="background: var(--bg-body); padding: 12px 16px; border-radius: 12px; font-size: 13px; color: var(--text-secondary); margin-bottom: 20px; display: flex; justify-content: space-between;">
              <span>سعة المجموعة: <strong>${maxStudents} طالب</strong></span>
              <span>المسجلون حالياً: <strong>${enrolledCount}</strong></span>
            </div>
          </div>

          <div>
            ${isOpen ? `
              <button onclick="subscribeToGroup(${group.courseId}, ${group.id})" class="auth-btn" style="width: 100%; border: none; cursor: pointer;">
                طلب الانضمام للمجموعة
              </button>
            ` : `
              <button disabled class="auth-btn" style="width: 100%; background: var(--border-color); color: var(--text-muted); cursor: not-allowed; border: none;">
                المجموعة مكتملة
              </button>
            `}
          </div>
        </div>
      `;
    });

    container.innerHTML = html;

  } catch (error) {
    console.error('Error loading groups:', error);
    container.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center; grid-column: 1 / -1;">حدث خطأ أثناء تحميل المجموعات الدراسية.</div>`;
  }
}

async function subscribeToGroup(courseId, groupId) {
  const token = ApiClient.getToken();
  if (!token) {
    alert('يرجى تسجيل الدخول أولاً للانضمام للمجموعة');
    window.location.href = 'login.html';
    return;
  }

  try {
    await ApiClient.subscribeCourse(courseId, groupId);
    alert('تم إرسال طلب الانضمام للمجموعة بنجاح!');
    await loadAllGroups();
  } catch (error) {
    alert(error.message || 'حدث خطأ أثناء طلب الانضمام');
  }
}

window.subscribeToGroup = subscribeToGroup;
