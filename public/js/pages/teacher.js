/* ====================================================
   teacher.js — المنطق الخاص بنظام لوحة المدرس
   مطابق تماماً لوظائف نظام المدرس في تطبيق Flutter:
   - مجموعات المدرس
   - تفاصيل وقائمة طلاب كل مجموعة (اسم، رقم هاتف، تاريخ ميلاد)
   - انضمام المحاضرة كمدرس (Host Zoom link)
   - أخذ استراحة (Break timer countdown + Notification)
   - طلب تأجيل المحاضرة للأدمن (Postpone request)
   ==================================================== */

let currentTeacherGroups = [];
let selectedGroupId = null;
let breakInterval = null;

document.addEventListener('DOMContentLoaded', async () => {
  const user = ApiClient.getUser();
  const token = ApiClient.getToken();

  if (!token || !user) {
    alert('يرجى تسجيل الدخول بحساب مدرس أولاً');
    window.location.href = 'login.html';
    return;
  }

  // تحديث اسم المدرس في الهيدر
  const welcomeTitle = document.getElementById('teacher-welcome-name');
  if (welcomeTitle) {
    const fullName = `${user.first_name || user.name || 'أستاذ'} ${user.last_name || ''}`.trim();
    welcomeTitle.textContent = `مرحباً بك يا أستاذ ${fullName}`;
  }

  await loadTeacherData();
});

// ====================================================
// 1. تحميل مجموعات المدرس والإحصائيات
// ====================================================
async function loadTeacherData() {
  const container = document.getElementById('teacher-groups-container');
  if (!container) return;

  try {
    const res = await ApiClient.getTeacherGroups();
    currentTeacherGroups = Array.isArray(res) ? res : (res.groups || res.data || []);

    let totalStudents = 0;
    let totalLectures = 0;

    currentTeacherGroups.forEach(g => {
      totalStudents += g.students_count || (g.students ? g.students.length : 0);
      totalLectures += g.online_lectures ? g.online_lectures.length : 0;
    });

    document.getElementById('stat-groups-count').textContent = currentTeacherGroups.length;
    document.getElementById('stat-students-count').textContent = totalStudents;
    document.getElementById('stat-lectures-count').textContent = totalLectures;

    if (currentTeacherGroups.length === 0) {
      container.innerHTML = `
        <div style="background: var(--bg-card); border-radius: 20px; padding: 48px; text-align: center; border: 1px dashed var(--border-color); grid-column: 1 / -1;">
          <div style="font-size: 48px; margin-bottom: 16px;">📚</div>
          <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">لا توجد مجموعات مخصصة لك حالياً</h3>
          <p style="font-size: 14px; color: var(--text-muted); max-width: 400px; margin: 0 auto;">
            عند قيام الأدمن بتعيينك كمدرس لمجموعة دراسية (مثل بايثون، فلاتر..) ستظهر المجموعات هنا فوراً.
          </p>
        </div>
      `;
      return;
    }

    let html = '';
    currentTeacherGroups.forEach(group => {
      const courseTitle = group.course ? (group.course.title || group.course.name) : 'كورس برمجي';
      const studentCount = group.students_count || (group.students ? group.students.length : 0);
      const maxStudents = group.max_students || 30;

      html += `
        <div class="group-card animate-fadeIn" style="cursor: pointer;" onclick="selectGroupDetails(${group.id})">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span style="background: rgba(59, 130, 246, 0.15); color: #3B82F6; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700;">
                ${courseTitle}
              </span>
              <span style="font-size: 12px; color: var(--text-muted);">كود: #${group.id}</span>
            </div>

            <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 12px;">${group.name}</h3>

            <div style="background: var(--bg-body); padding: 12px 16px; border-radius: 14px; font-size: 13px; color: var(--text-secondary); margin-bottom: 16px; display: flex; justify-content: space-between;">
              <span>🎓 عدد الطلاب: <strong>${studentCount} / ${maxStudents}</strong></span>
              <span>📡 المحاضرات: <strong>${group.online_lectures ? group.online_lectures.length : 0}</strong></span>
            </div>
          </div>

          <button class="auth-btn" style="width: 100%; border: none; background: linear-gradient(135deg, #2563EB, #1D4ED8);">
            عرض تفاصيل المجموعة والطلاب 👈
          </button>
        </div>
      `;
    });

    container.innerHTML = html;

  } catch (error) {
    console.error('Error loading teacher groups:', error);
    container.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center; grid-column: 1 / -1;">حدث خطأ أثناء تحميل مجموعات المدرس.</div>`;
  }
}

// ====================================================
// 2. عرض تفاصيل المجموعة المحددة (الطلاب والمحاضرات)
// ====================================================
async function selectGroupDetails(groupId) {
  selectedGroupId = groupId;
  const detailsSection = document.getElementById('group-details-section');
  const group = currentTeacherGroups.find(g => g.id === groupId);

  if (!group || !detailsSection) return;

  detailsSection.style.display = 'block';
  detailsSection.scrollIntoView({ behavior: 'smooth' });

  document.getElementById('selected-group-name').textContent = group.name;
  document.getElementById('selected-group-course-tag').textContent = group.course ? (group.course.title || group.course.name) : 'كورس برمجي';

  // تحميل قائمة طلاب المجموعة
  const tbody = document.getElementById('students-table-body');
  tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">جاري تحميل بيانات الطلاب...</td></tr>`;

  try {
    const studentsRes = await ApiClient.getTeacherGroupStudents(groupId);
    const students = Array.isArray(studentsRes) ? studentsRes : (studentsRes.students || studentsRes.data || []);

    if (students.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">لا يوجد طلاب مسجلون في هذه المجموعة حتى الآن</td></tr>`;
    } else {
      let rows = '';
      students.forEach((std, idx) => {
        const fullName = `${std.first_name || std.name || ''} ${std.middle_name || ''} ${std.last_name || ''}`.trim();
        const phone = std.phone || 'غير مسجل';
        // استخراج تاريخ الميلاد فقط بدون ساعات ISO أو أصفار الملي ثانية
        const birthDate = std.birth_date ? (std.birth_date.split('T')[0].split(' ')[0]) : 'غير محدد';
        const email = std.email || 'غير مسجل';

        const whatsappUrl = phone !== 'غير مسجل' ? `https://wa.me/${phone.replace(/[^0-9]/g, '')}` : '#';

        rows += `
          <tr>
            <td><strong>${idx + 1}</strong></td>
            <td><strong>${fullName}</strong></td>
            <td><span dir="ltr" style="font-weight: 600;">${phone}</span></td>
            <td>${birthDate}</td>
            <td>${email}</td>
            <td>
              ${phone !== 'غير مسجل' ? `
                <a href="${whatsappUrl}" target="_blank" style="background: rgba(34, 197, 94, 0.15); color: #22C55E; padding: 4px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 700;">
                  واتساب 💬
                </a>
              ` : '-'}
            </td>
          </tr>
        `;
      });
      tbody.innerHTML = rows;
    }
  } catch (e) {
    console.error('Error loading students:', e);
    tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #ef4444;">تعذر تحميل قائمة الطلاب</td></tr>`;
  }

  // تحميل محاضرات الأونلاين للمجموعة
  renderGroupLectures(group);
}

function renderGroupLectures(group) {
  const container = document.getElementById('group-lectures-container');
  if (!container) return;

  const lectures = group.online_lectures || [];

  if (lectures.length === 0) {
    container.innerHTML = `
      <div style="background: var(--bg-body); padding: 24px; border-radius: 16px; text-align: center; color: var(--text-muted);">
        لا توجد محاضرات أونلاين مجدولة لهذه المجموعة حالياً
      </div>
    `;
    return;
  }

  let html = '';
  lectures.forEach(lec => {
    html += `
      <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 18px; padding: 20px; margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
          <div>
            <h4 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">${lec.title || 'محاضرة مباشرة'}</h4>
            <div style="font-size: 13px; color: var(--text-muted);">⏰ تاريخ الانطلاق: ${lec.start_time || lec.scheduled_at || 'محددة من الأدمن'}</div>
          </div>

          <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <!-- 1. الانضمام كمدرس -->
            <button onclick="hostJoinLecture(${lec.id})" class="auth-btn" style="background: #2563EB; border: none; padding: 10px 20px; font-size: 13px; border-radius: 12px;">
              🎥 الانضمام كمدرس (Host)
            </button>

            <!-- 2. أخذ استراحة -->
            <button onclick="openBreakModal(${group.id})" class="auth-btn" style="background: #D97706; border: none; padding: 10px 20px; font-size: 13px; border-radius: 12px;">
              ☕ أخذ استراحة (Break)
            </button>

            <!-- 3. طلب تأجيل المحاضرة -->
            <button onclick="openPostponeModal(${lec.id})" class="auth-btn" style="background: #DC2626; border: none; padding: 10px 20px; font-size: 13px; border-radius: 12px;">
              📅 طلب تأجيل
            </button>
          </div>
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

function closeGroupDetails() {
  const detailsSection = document.getElementById('group-details-section');
  if (detailsSection) detailsSection.style.display = 'none';
}

// ====================================================
// 3. أفعال المدرس التفاعلية (Host Join, Break, Postpone)
// ====================================================

// 1. الانضمام كمدرس
async function hostJoinLecture(lectureId) {
  try {
    const res = await ApiClient.joinOnlineLecture(lectureId);
    const hostUrl = res.zoom_host_url || res.zoom_join_url || res.url;
    if (hostUrl) {
      window.open(hostUrl, '_blank');
    } else {
      alert('لم يتم العثور على رابط المدرس كمضيف، يتم تحويلك لرابط الانضمام العام');
      window.open(res.zoom_join_url || res.url, '_blank');
    }
  } catch (error) {
    alert(error.message || 'فشل فتح المحاضرة كمدرس');
  }
}

// 2. إدارة الاستراحة Break
function openBreakModal(groupId) {
  document.getElementById('break-group-id').value = groupId;
  document.getElementById('break-modal').classList.add('active');
}

function closeBreakModal() {
  document.getElementById('break-modal').classList.remove('active');
}

async function startBreakTimer(durationMinutes) {
  const groupId = document.getElementById('break-group-id').value;
  closeBreakModal();

  try {
    await ApiClient.teacherStartBreak(groupId, durationMinutes);
    alert(`تم إرسال إشعار استراحة لمدة ${durationMinutes} دقائق لجميع طلاب المجموعة بنجاح!`);
  } catch (e) {
    // Continue timer on screen regardless
  }

  // تفعيل المؤقت التنازلي على الشاشة
  let secondsRemaining = durationMinutes * 60;
  const banner = document.getElementById('break-active-banner');
  const countdownText = document.getElementById('break-countdown-text');

  if (banner) banner.style.display = 'flex';

  if (breakInterval) clearInterval(breakInterval);

  breakInterval = setInterval(() => {
    secondsRemaining--;

    if (secondsRemaining <= 0) {
      clearInterval(breakInterval);
      endBreakTimer();
      alert('انتهت فترة الاستراحة! يمكنك استكمال المحاضرة الآن.');
      return;
    }

    const mins = Math.floor(secondsRemaining / 60);
    const secs = secondsRemaining % 60;
    if (countdownText) {
      countdownText.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
  }, 1000);
}

function endBreakTimer() {
  if (breakInterval) clearInterval(breakInterval);
  const banner = document.getElementById('break-active-banner');
  if (banner) banner.style.display = 'none';
}

// 3. إدارة طلب التأجيل Postpone
function openPostponeModal(lectureId) {
  document.getElementById('postpone-lecture-id').value = lectureId;
  document.getElementById('postpone-modal').classList.add('active');
}

function closePostponeModal() {
  document.getElementById('postpone-modal').classList.remove('active');
}

async function submitPostponeForm(event) {
  event.preventDefault();
  const lectureId = document.getElementById('postpone-lecture-id').value;
  const reason = document.getElementById('postpone-reason').value.trim();
  const newDate = document.getElementById('postpone-new-date').value;

  if (!reason || !newDate) {
    alert('يرجى تعبئة كافة الحقول المطلوب إرسالها للأدمن');
    return;
  }

  try {
    await ApiClient.teacherRequestPostponement(lectureId, reason, newDate);
    alert('تم إرسال طلب تأجيل المحاضرة للأدمن بنجاح للموافقة عليه!');
    closePostponeModal();
  } catch (error) {
    alert(error.message || 'حدث خطأ أثناء إرسال طلب التأجيل للأدمن');
  }
}

window.selectGroupDetails = selectGroupDetails;
window.closeGroupDetails = closeGroupDetails;
window.hostJoinLecture = hostJoinLecture;
window.openBreakModal = openBreakModal;
window.closeBreakModal = closeBreakModal;
window.startBreakTimer = startBreakTimer;
window.endBreakTimer = endBreakTimer;
window.openPostponeModal = openPostponeModal;
window.closePostponeModal = closePostponeModal;
window.submitPostponeForm = submitPostponeForm;
