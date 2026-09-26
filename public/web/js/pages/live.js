/* ====================================================
   live.js — إدارة المحاضرات المباشرة واللايفات للطالب
   يقوم بجلب المحاضرات وتفعيل رابط الانضمام التفاعلي عبر زوم
   ==================================================== */

let countdownInterval = null;

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
    let allLectures = [];

    // 1. المحاولة عبر نقطة النهاية المباشرة /my-lectures
    try {
      const myRes = await ApiClient.getMyLectures();
      const list = Array.isArray(myRes) ? myRes : (myRes.data || []);
      list.forEach(lec => {
        allLectures.push({
          ...lec,
          courseName: lec.course ? (lec.course.title || lec.course.name) : 'كورس برمجي',
          groupName: lec.group ? lec.group.name : 'المجموعة النشطة',
          teacherName: lec.teacher ? (lec.teacher.first_name + ' ' + lec.teacher.last_name) : 'المدرس الرئيسي'
        });
      });
    } catch (e) {
      console.warn('Fallback to courses groups for lectures:', e);
    }

    // 2. الطريقة الاحتياطية عبر الكورسات والمجموعات
    if (allLectures.length === 0) {
      const coursesRes = await ApiClient.getCourses();
      const courses = Array.isArray(coursesRes) ? coursesRes : (coursesRes.data || []);
      
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
                  teacherName: lec.teacher ? (lec.teacher.first_name + ' ' + lec.teacher.last_name) : (group.teacher ? (group.teacher.first_name + ' ' + group.teacher.last_name) : 'المدرس الرئيسي')
                });
              });
            }
          }
        } catch (err) {
          // Skip
        }
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
      const startTimeStr = lec.start_date_time || lec.start_time || lec.scheduled_at;
      const durationMins = lec.duration_minutes || lec.duration || 60;

      let formattedDate = 'قريباً';
      let formattedTime = '';
      if (startTimeStr) {
        const d = new Date(startTimeStr);
        formattedDate = d.toLocaleDateString('ar-EG', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        formattedTime = d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
      }

      const teacherDisplayName = lec.teacherName || (lec.teacher ? (lec.teacher.name || `${lec.teacher.first_name || ''} ${lec.teacher.last_name || ''}`.trim()) : 'المدرس المحاضر');

      html += `
        <div class="live-card animate-fadeIn" data-start-time="${startTimeStr || ''}" data-lecture-id="${lec.id}" data-status="${lec.status || 'scheduled'}">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div>
              <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;" id="badge-container-${lec.id}">
                <div class="live-card-status status-upcoming" id="status-badge-${lec.id}">📅 قادمة</div>
                <span style="font-size: 13px; font-weight: 700; color: #3B82F6;">${lec.courseName} — ${lec.groupName}</span>
              </div>
              <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">${lec.title || lec.topic || 'محاضرة أونلاين'}</h3>
              
              <div style="font-size: 13.5px; color: var(--text-secondary); display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; background: var(--bg-body); padding: 12px 16px; border-radius: 14px; border: 1px solid var(--border-color);">
                <span>📅 تاريخ المحاضرة: <strong>${formattedDate}</strong></span>
                <span>⏰ وقت البدء: <strong style="color: #2563EB;">الساعة ${formattedTime}</strong></span>
                <span>⏱️ مدة المحاضرة: <strong>${durationMins} دقيقة</strong></span>
                <span>👨‍🏫 المدرس المحاضر: <strong>${teacherDisplayName}</strong></span>
                <div style="margin-top: 4px;">⏳ العد التنازلي: <span class="countdown-timer" id="countdown-${lec.id}">جاري حساب الموعد...</span></div>
              </div>
            </div>

            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
              <button id="join-btn-${lec.id}" onclick="joinStudentLecture(${lec.id})" class="auth-btn" disabled style="background: #9CA3AF; border: none; padding: 12px 28px; border-radius: 14px; font-weight: 700; cursor: not-allowed; opacity: 0.6;">
                ⏳ في انتظار وقت الموعد
              </button>
              ${lec.zoom_join_url ? `<a href="${lec.zoom_join_url}" target="_blank" style="font-size: 12px; color: #2563EB; font-weight: 700; text-decoration: underline;">🔗 فتح أو نسخ رابط المحاضرة</a>` : ''}
            </div>
          </div>
        </div>
      `;
    });

    container.innerHTML = html;

    // Start countdown timer interval
    if (countdownInterval) clearInterval(countdownInterval);
    updateCountdowns();
    countdownInterval = setInterval(updateCountdowns, 1000);

    // Poll lecture statuses every 15 seconds for real-time break/ended/live detection
    if (window._livePollInterval) clearInterval(window._livePollInterval);
    window._livePollInterval = setInterval(pollLiveLectureStatuses, 15000);

  } catch (error) {
    console.error('Error loading live lectures:', error);
    container.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center;">حدث خطأ أثناء تحميل المحاضرات المباشرة.</div>`;
  }
}

function updateCountdowns() {
  const cards = document.querySelectorAll('.live-card[data-start-time]');
  const now = new Date().getTime();

  cards.forEach(card => {
    const lectureId = card.getAttribute('data-lecture-id');
    const startTimeStr = card.getAttribute('data-start-time');
    const status = card.getAttribute('data-status');
    const countdownEl = document.getElementById(`countdown-${lectureId}`);
    const joinBtn = document.getElementById(`join-btn-${lectureId}`);
    const statusBadge = document.getElementById(`status-badge-${lectureId}`);

    if (!countdownEl || !joinBtn) return;

    // 1. إذا كانت المحاضرة في بريك (استراحة)
    if (status === 'break') {
      countdownEl.innerHTML = '<span style="color: #F59E0B; font-weight: 800;">☕ المحاضرة حالياً في استراحة (بريك)</span>';
      if (statusBadge) {
        statusBadge.className = 'live-card-status status-upcoming';
        statusBadge.style.background = 'rgba(245, 158, 11, 0.2)';
        statusBadge.style.color = '#F59E0B';
        statusBadge.innerHTML = '☕ استراحة';
      }
      joinBtn.disabled = true;
      joinBtn.style.background = '#D97706';
      joinBtn.style.cursor = 'not-allowed';
      joinBtn.style.opacity = '0.8';
      joinBtn.innerHTML = '☕ المحاضرة في استراحة (بريك)';
      return;
    }

    // 2. إذا كانت المحاضرة منتهية
    if (status === 'ended') {
      countdownEl.innerHTML = '<span style="color: #9CA3AF; font-weight: 800;">🏁 تم إنهاء هذه المحاضرة</span>';
      if (statusBadge) {
        statusBadge.className = 'live-card-status status-upcoming';
        statusBadge.style.background = 'rgba(148, 163, 184, 0.2)';
        statusBadge.style.color = '#94A3B8';
        statusBadge.innerHTML = '🏁 منتهية';
      }
      joinBtn.disabled = true;
      joinBtn.style.background = '#6B7280';
      joinBtn.style.cursor = 'not-allowed';
      joinBtn.style.opacity = '0.6';
      joinBtn.innerHTML = '🏁 المحاضرة منتهية';
      return;
    }

    // 3. المحاضرة جارية الآن (live)
    if (status === 'live') {
      countdownEl.innerHTML = '<span style="color: #EF4444; font-weight: 800;">🔴 المحاضرة جارية الآن (مباشر)</span>';
      if (statusBadge) {
        statusBadge.className = 'live-card-status status-live';
        statusBadge.innerHTML = '<span class="pulse-dot"></span> مباشر الآن';
      }
      joinBtn.disabled = false;
      joinBtn.style.background = 'linear-gradient(135deg, #2563EB, #1D4ED8)';
      joinBtn.style.cursor = 'pointer';
      joinBtn.style.opacity = '1';
      joinBtn.innerHTML = '🎥 الانضمام للمحاضرة';
      return;
    }

    // 4. المحاضرة مجدولة قادمة
    if (!startTimeStr) return;
    const startTime = new Date(startTimeStr).getTime();
    if (isNaN(startTime)) {
      countdownEl.innerText = 'الموعد غير محدد بدقة';
      return;
    }

    const diff = startTime - now;

    if (diff <= 0) {
      countdownEl.innerHTML = '<span style="color: #10B981; font-weight: 800;">🟢 الموعد حان (في انتظار بدء المدرس للمحاضرة)</span>';
      joinBtn.disabled = true;
      joinBtn.style.background = '#9CA3AF';
      joinBtn.style.cursor = 'not-allowed';
      joinBtn.style.opacity = '0.7';
      joinBtn.innerHTML = '⏳ في انتظار بدء المحاضرة من المدرس';
    } else {
      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((diff % (1000 * 60)) / 1000);

      let timeText = '';
      if (days > 0) timeText += `${days} يوم `;
      timeText += `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

      countdownEl.innerText = timeText;

      joinBtn.disabled = true;
      joinBtn.style.background = '#9CA3AF';
      joinBtn.style.cursor = 'not-allowed';
      joinBtn.style.opacity = '0.6';
      joinBtn.innerHTML = '⏳ في انتظار موعد المحاضرة';
    }
  });
}

async function joinStudentLecture(lectureId) {
  try {
    const res = await ApiClient.joinOnlineLecture(lectureId);
    const zoomUrl = res.data?.join_url || res.data?.zoom_join_url || res.zoom_join_url || res.join_url || res.url;
    if (zoomUrl) {
      window.open(zoomUrl, '_blank');
    } else {
      alert('لم يتم العثور على رابط المحاضرة، يرجى التواصل مع المدرس');
    }
  } catch (error) {
    alert(error.message || 'تعذر الانضمام للمحاضرة حالياً');
  }
}

window.joinStudentLecture = joinStudentLecture;

/**
 * جلب حالة المحاضرات من السيرفر كل 15 ثانية لتحديث البطاقات تلقائياً
 * (بريك / مباشر / منتهية) بدون إعادة تحميل الصفحة
 */
async function pollLiveLectureStatuses() {
  const cards = document.querySelectorAll('.live-card[data-lecture-id]');
  if (cards.length === 0) return;

  try {
    const myRes = await ApiClient.getMyLectures();
    const list = Array.isArray(myRes) ? myRes : (myRes.data || []);

    cards.forEach(card => {
      const lectureId = card.getAttribute('data-lecture-id');
      const freshLec = list.find(l => String(l.id) === String(lectureId));
      if (freshLec && freshLec.status) {
        const oldStatus = card.getAttribute('data-status');
        if (oldStatus !== freshLec.status) {
          card.setAttribute('data-status', freshLec.status);
          updateCountdowns();
        }
      }
    });
  } catch (e) {
    console.warn('Live lecture status poll error:', e);
  }
}
