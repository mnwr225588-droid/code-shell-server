/* ====================================================
   live.js — إدارة المحاضرات المباشرة واللايفات للطالب
   يقوم بجلب المحاضرات وتفعيل رابط الانضمام التفاعلي عبر زوم
   ==================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  const user = ApiClient.getUser();
  if (user && (user.user_type === 'teacher' || user.role === 'teacher')) {
    const teacherBtn = document.getElementById('teacher-nav-item');
    if (teacherBtn) teacherBtn.style.display = 'flex';
  }

  await loadLiveLectures();
});

async function loadLiveLectures() {
  const container = document.getElementById('live-lectures-container');
  if (!container) return;

  try {
    // جلب كورسات الطالب ومجموعاته
    const coursesRes = await ApiClient.getCourses();
    const courses = Array.isArray(coursesRes) ? coursesRes : (coursesRes.data || []);
    
    let allLectures = [];

    // جلب مجموعات كل كورس لمحاضرات الأونلاين
    for (const course of courses) {
      try {
        const groupsRes = await ApiClient.getCourseGroups(course.id);
        const groups = Array.isArray(groupsRes) ? groupsRes : (groupsRes.data || []);
        
        for (const group of groups) {
          if (group.online_lectures && Array.isArray(group.online_lectures)) {
            group.online_lectures.forEach(lec => {
              allLectures.push({
                ...lec,
                courseName: course.title || course.name,
                groupName: group.name,
                teacherName: group.teacher ? (group.teacher.first_name + ' ' + group.teacher.last_name) : 'المدرس الرئيسي'
              });
            });
          }
        }
      } catch (e) {
        // Skip course without groups
      }
    }

    if (allLectures.length === 0) {
      container.innerHTML = `
        <div style="background: var(--bg-card); border-radius: 20px; padding: 48px; text-align: center; border: 1px dashed var(--border-color);">
          <div style="font-size: 48px; margin-bottom: 16px;">🎥</div>
          <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">لا توجد محاضرات مباشرة حالياً</h3>
          <p style="font-size: 14px; color: var(--text-muted); max-width: 400px; margin: 0 auto 20px;">
            سيظهر هنا الموعد ورابط الانضمام فور قيام الأدمن أو المدرس بجدولة محاضرة أونلاين لمجموعتك.
          </p>
          <a href="courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 28px; text-decoration: none;">تصفح الكورسات والمجموعات</a>
        </div>
      `;
      return;
    }

    let html = '';
    allLectures.forEach(lec => {
      const isLiveNow = lec.status === 'live' || lec.is_active;
      const statusBadge = isLiveNow 
        ? `<div class="live-card-status status-live"><span class="pulse-dot"></span> مباشر الآن</div>`
        : `<div class="live-card-status status-upcoming">📅 قادمة</div>`;

      html += `
        <div class="live-card animate-fadeIn">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div>
              <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                ${statusBadge}
                <span style="font-size: 13px; font-weight: 700; color: #3B82F6;">${lec.courseName} — ${lec.groupName}</span>
              </div>
              <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">${lec.title || 'محاضرة أونلاين'}</h3>
              <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 12px;">المدرس المحاضر: <strong>${lec.teacherName}</strong></p>
              <div style="font-size: 13px; color: var(--text-secondary); display: flex; gap: 16px;">
                <span>⏰ تاريخ البدء: ${lec.start_time || lec.scheduled_at || 'قريباً'}</span>
              </div>
            </div>

            <div>
              <button onclick="joinStudentLecture(${lec.id})" class="auth-btn" style="background: linear-gradient(135deg, #2563EB, #1D4ED8); border: none; padding: 12px 28px; border-radius: 14px; font-weight: 700; cursor: pointer;">
                🎥 الانضمام للمحاضرة
              </button>
            </div>
          </div>
        </div>
      `;
    });

    container.innerHTML = html;

  } catch (error) {
    console.error('Error loading live lectures:', error);
    container.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center;">حدث خطأ أثناء تحميل المحاضرات المباشرة.</div>`;
  }
}

async function joinStudentLecture(lectureId) {
  try {
    const res = await ApiClient.joinOnlineLecture(lectureId);
    if (res.zoom_join_url || res.url || res.join_url) {
      const zoomUrl = res.zoom_join_url || res.url || res.join_url;
      window.open(zoomUrl, '_blank');
    } else {
      alert('لم يتم العثور على رابط المحاضرة، يرجى التواصل مع المدرس');
    }
  } catch (error) {
    alert(error.message || 'تعذر الانضمام للمحاضرة حالياً');
  }
}

window.joinStudentLecture = joinStudentLecture;
