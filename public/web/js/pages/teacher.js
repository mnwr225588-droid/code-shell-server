/* ====================================================
   teacher.js — المنطق الخاص بنظام لوحة المدرس
   مطابق تماماً 100% لوظائف نظام المدرس في تطبيق Flutter:
   - TeacherSessionsScreen: جدول المحاضرات، البدء، الانضمام، الاستراحة، طلب التأجيل
   - TeacherGroupsScreen: قائمة المجموعات المخصصة للمدرس والإحصائيات
   - TeacherStudentsScreen: قائمة طلاب المجموعة (الاسم، الهاتف، الميلاد، واتساب)
   ==================================================== */

let currentTeacherGroups = [];
let currentTeacherSessions = [];
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
  await loadTeacherSessions();
});

// ====================================================
// 0. التنقل بين التبويبات (المواعيد | المجموعات)
// ====================================================
function switchTeacherTab(tab) {
  const sessionsSec = document.getElementById('teacher-sessions-section');
  const groupsSec = document.getElementById('teacher-groups-section');
  const btnSessions = document.getElementById('tab-btn-sessions');
  const btnGroups = document.getElementById('tab-btn-groups');

  if (tab === 'sessions') {
    if (sessionsSec) sessionsSec.style.display = 'block';
    if (groupsSec) groupsSec.style.display = 'none';

    if (btnSessions) {
      btnSessions.style.background = '#2563EB';
      btnSessions.style.color = '#FFFFFF';
      btnSessions.style.border = 'none';
    }
    if (btnGroups) {
      btnGroups.style.background = 'var(--bg-card)';
      btnGroups.style.color = 'var(--text-primary)';
      btnGroups.style.border = '1px solid var(--border-color)';
    }
  } else if (tab === 'groups') {
    if (sessionsSec) sessionsSec.style.display = 'none';
    if (groupsSec) groupsSec.style.display = 'block';

    if (btnGroups) {
      btnGroups.style.background = '#2563EB';
      btnGroups.style.color = '#FFFFFF';
      btnGroups.style.border = 'none';
    }
    if (btnSessions) {
      btnSessions.style.background = 'var(--bg-card)';
      btnSessions.style.color = 'var(--text-primary)';
      btnSessions.style.border = '1px solid var(--border-color)';
    }
  }
}

// ====================================================
// 1. تحميل جدول المحاضرات والمواعيد (TeacherSessionsScreen)
// ====================================================
async function loadTeacherSessions() {
  const container = document.getElementById('teacher-sessions-container');
  if (!container) return;

  try {
    const res = await ApiClient.getTeacherSessions().catch(() => null);
    let sessions = Array.isArray(res) ? res : (res && (res.data || res.sessions) ? (res.data || res.sessions) : []);

    currentTeacherSessions = sessions;

    if (sessions.length === 0) {
      container.innerHTML = `
        <div style="background: var(--bg-card); border-radius: 20px; padding: 40px; text-align: center; border: 1px dashed var(--border-color);">
          <div style="font-size: 40px; margin-bottom: 12px;">📡</div>
          <h4 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">لا توجد جلسات أو محاضرات مجدولة حالياً</h4>
          <p style="font-size: 13px; color: var(--text-muted);">عند قيام الأدمن ببرمجة محاضرة أونلاين جديدة ستظهر هنا مع أدوات التحكم مباشرة.</p>
        </div>
      `;
      return;
    }

    let html = '';
    sessions.forEach(session => {
      const title = session.title || 'محاضرة أونلاين';
      const groupName = session.group ? session.group.name : (session.group_name || 'مجموعة دراسية');
      const courseTitle = session.course ? (session.course.title || session.course.name) : 'كورس برمجي';
      const scheduledAt = session.start_time || session.scheduled_at || session.created_at || 'محددة من الأدمن';

      const isLive = session.status === 'live' || session.is_live;
      const isBreak = session.status === 'break';
      const isPostponed = session.status === 'postponed';
      const isEnded = session.status === 'ended';

      let statusBadge = `<span style="background: rgba(59, 130, 246, 0.15); color: #3B82F6; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700;">⏳ قيد الانتظار</span>`;
      if (isLive) {
        statusBadge = `<span style="background: rgba(34, 197, 94, 0.2); color: #22C55E; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 800; animation: pulse 2s infinite;">🔴 جارية الآن</span>`;
      } else if (isBreak) {
        statusBadge = `<span style="background: rgba(245, 158, 11, 0.2); color: #F59E0B; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 800;">☕ في استراحة</span>`;
      } else if (isPostponed) {
        statusBadge = `<span style="background: rgba(245, 158, 11, 0.2); color: #F59E0B; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700;">📅 مؤجلة</span>`;
      } else if (isEnded) {
        statusBadge = `<span style="background: rgba(148, 163, 184, 0.2); color: #94A3B8; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700;">🏁 انتهت</span>`;
      }

      let actionButtons = '';
      if (!isLive && !isBreak && !isEnded) {
        // المحاضرة مجدولة (لم تبدأ بعد)
        actionButtons = `
          <button onclick="hostStartLecture(${session.id})" class="auth-btn" style="background: linear-gradient(135deg, #10B981, #059669); border: none; padding: 10px 18px; font-size: 13px; border-radius: 12px; font-weight: 800;">
            ▶️ بدء المحاضرة (تفعيل الانضمام للطلاب)
          </button>
          <button onclick="openPostponeModal(${session.id})" class="auth-btn" style="background: linear-gradient(135deg, #6B7280, #4B5563); border: none; padding: 10px 18px; font-size: 13px; border-radius: 12px; font-weight: 800;">
            📅 طلب تأجيل
          </button>
        `;
      } else if (isLive) {
        // المحاضرة جارية الآن
        actionButtons = `
          <button onclick="openBreakModal(${session.id})" class="auth-btn" style="background: linear-gradient(135deg, #D97706, #B45309); border: none; padding: 10px 18px; font-size: 13px; border-radius: 12px; font-weight: 800;">
            ☕ أخذ استراحة (Break)
          </button>
          <button onclick="hostEndLecture(${session.id})" class="auth-btn" style="background: linear-gradient(135deg, #DC2626, #991B1B); border: none; padding: 10px 18px; font-size: 13px; border-radius: 12px; font-weight: 800;">
            🛑 إنهاء المحاضرة
          </button>
        `;
      } else if (isBreak) {
        // المحاضرة في استراحة
        actionButtons = `
          <button onclick="teacherEndBreakAction(${session.id})" class="auth-btn" style="background: linear-gradient(135deg, #10B981, #059669); border: none; padding: 10px 18px; font-size: 13px; border-radius: 12px; font-weight: 800;">
            ▶️ إنهاء البريك واستئناف المحاضرة
          </button>
          <button onclick="hostEndLecture(${session.id})" class="auth-btn" style="background: linear-gradient(135deg, #DC2626, #991B1B); border: none; padding: 10px 18px; font-size: 13px; border-radius: 12px; font-weight: 800;">
            🛑 إنهاء المحاضرة
          </button>
        `;
      } else if (isEnded) {
        actionButtons = `
          <span style="font-size: 13px; color: var(--text-muted); padding: 10px;">المحاضرة منتهية</span>
        `;
      }

      // التحقق مما إذا كان الرابط قد تم إدخاله وحفظه مخصصاً من قبل المدرس (وليس رابط عشوائي افتراضي)
      const rawUrl = (session.zoom_join_url || '').trim();
      const isCustomSaved = Boolean(session.is_custom_link || (rawUrl && !rawUrl.includes('rand')));
      const displayUrl = isCustomSaved ? rawUrl : '';
      const hasUrl = Boolean(isCustomSaved && rawUrl);

      const btnText = hasUrl ? '🔄 استبدال الرابط' : '📤 إرسال الرابط للطلاب';
      const btnStyle = hasUrl 
        ? 'background: linear-gradient(135deg, #D97706, #B45309);' 
        : 'background: linear-gradient(135deg, #2563EB, #1D4ED8);';
      const inputDisabled = hasUrl ? 'disabled' : '';

      const zoomLinkBoxHtml = !isEnded ? `
        <div style="margin-top: 14px; background: rgba(37, 99, 235, 0.05); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 14px; padding: 12px 16px;">
          <label style="font-size: 13px; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 6px;">🔗 رابط اجتماع Zoom الخاص بك لهذه المحاضرة:</label>
          <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="url" id="teacher-zoom-input-${session.id}" value="${displayUrl}" ${inputDisabled} placeholder="ضع رابط اجتماع Zoom الخاص بك هنا (مثال: https://zoom.us/j/...)" style="flex: 1; min-width: 240px; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border-color); font-size: 13px; background: var(--bg-body); color: var(--text-primary);" />
            <button id="teacher-zoom-btn-${session.id}" data-has-url="${hasUrl}" data-editing="false" onclick="toggleOrSaveTeacherZoomLink(${session.id})" class="auth-btn" style="${btnStyle} border: none; padding: 10px 18px; font-size: 13px; border-radius: 10px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
              ${btnText}
            </button>
          </div>
        </div>
      ` : '';

      html += `
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); transition: transform 0.2s;" class="animate-fadeIn">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div>
              <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                ${statusBadge}
                <span style="font-size: 12.5px; color: #3B82F6; font-weight: 700; background: rgba(59, 130, 246, 0.1); padding: 3px 10px; border-radius: 10px;">${courseTitle} — ${groupName}</span>
              </div>
              <h3 style="font-size: 19px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">${title}</h3>
              <div style="font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                <span>⏰ الموعد:</span>
                <strong style="color: var(--text-secondary);">${scheduledAt}</strong>
              </div>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
              ${actionButtons}
            </div>
          </div>
          ${zoomLinkBoxHtml}
        </div>
      `;
    });

    container.innerHTML = html;

  } catch (err) {
    console.error('Error loading teacher sessions:', err);
    container.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center;">حدث خطأ أثناء تحميل جدول المحاضرات والمواعيد.</div>`;
  }
}

// ====================================================
// 2. تحميل مجموعات المدرس والإحصائيات (TeacherGroupsScreen)
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

    const statGroups = document.getElementById('stat-groups-count');
    const statStudents = document.getElementById('stat-students-count');
    const statLectures = document.getElementById('stat-lectures-count');

    if (statGroups) statGroups.textContent = currentTeacherGroups.length;
    if (statStudents) statStudents.textContent = totalStudents;
    if (statLectures) statLectures.textContent = totalLectures;

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
// 3. عرض تفاصيل المجموعة المحددة (TeacherStudentsScreen)
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
// 4. أفعال المدرس التفاعلية (Host Join, Break, Postpone)
// ====================================================

// 0. حفظ أو استبدال وإرسال رابط زوم الخاص بالمدرس
async function toggleOrSaveTeacherZoomLink(lectureId) {
  const input = document.getElementById(`teacher-zoom-input-${lectureId}`);
  const btn = document.getElementById(`teacher-zoom-btn-${lectureId}`);
  if (!input || !btn) return;

  const isEditing = btn.getAttribute('data-editing') === 'true';
  const hasSavedUrl = btn.getAttribute('data-has-url') === 'true';

  // إذا كان الرابط محفوظاً بالفعل والمدرس يضغط "استبدال الرابط"
  if (!isEditing && hasSavedUrl) {
    input.disabled = false;
    input.focus();
    input.select();
    btn.setAttribute('data-editing', 'true');
    btn.style.background = 'linear-gradient(135deg, #10B981, #059669)';
    btn.innerHTML = '📤 إرسال الرابط الجديد للطلاب';
    return;
  }

  // تنفيذ حفظ / استبدال الرابط
  const zoomUrl = input.value.trim();
  if (!zoomUrl) {
    alert('⚠️ يرجى إدخال رابط اجتماع زوم أولاً قبل الحفظ والإرسال');
    return;
  }

  btn.disabled = true;
  btn.innerText = 'جاري الإرسال... ⏳';

  try {
    await ApiClient.teacherUpdateZoomLink(lectureId, zoomUrl);
    alert('✅ تم إرسال وحفظ رابط زوم الجديد للطلاب بنجاح!');

    input.disabled = true;
    btn.disabled = false;
    btn.setAttribute('data-editing', 'false');
    btn.setAttribute('data-has-url', 'true');
    btn.style.background = 'linear-gradient(135deg, #D97706, #B45309)';
    btn.innerHTML = '🔄 استبدال الرابط';

    // إعادة تحميل المحاضرات لتثبيت التحديث نهائياً في التخزين بقاعدة البيانات
    if (typeof loadTeacherSessions === 'function') {
      await loadTeacherSessions();
    }
  } catch (error) {
    btn.disabled = false;
    btn.style.background = isEditing ? 'linear-gradient(135deg, #10B981, #059669)' : (hasSavedUrl ? 'linear-gradient(135deg, #D97706, #B45309)' : 'linear-gradient(135deg, #2563EB, #1D4ED8)');
    btn.innerHTML = hasSavedUrl ? '🔄 استبدال الرابط' : '📤 إرسال الرابط للطلاب';
    alert(error.message || 'فشل إرسال وتأكيد حفظ الرابط');
  }
}

window.toggleOrSaveTeacherZoomLink = toggleOrSaveTeacherZoomLink;

// 1. بدء المحاضرة كمدرس (تحويل الحالة لمباشر وتفعيل زوم للطلاب)
async function hostStartLecture(lectureId) {
  const input = document.getElementById(`teacher-zoom-input-${lectureId}`);
  const zoomUrl = input ? input.value.trim() : '';

  try {
    await ApiClient.teacherStartLecture(lectureId, zoomUrl);
    alert('🟢 تم بدء المحاضرة وتفعيل زر الانضمام للطلاب بنجاح!');
    await loadTeacherSessions();
  } catch (error) {
    alert(error.message || 'تعذر بدء المحاضرة حالياً');
  }
}

window.saveTeacherZoomLink = saveTeacherZoomLink;

// 3. إنهاء المحاضرة
async function hostEndLecture(lectureId) {
  if (!confirm('هل أنت تأكد من إنهاء هذه المحاضرة؟ سيظهر للطلاب أن المحاضرة قد انتهت.')) return;
  try {
    await ApiClient.teacherEndLecture(lectureId);
    alert('تم إنهاء المحاضرة بنجاح!');
    await loadTeacherSessions();
  } catch (error) {
    alert(error.message || 'تعذر إنهاء المحاضرة');
  }
}

// 4. إدارة الاستراحة Break
function openBreakModal(lectureId) {
  const el = document.getElementById('break-group-id');
  if (el) el.value = lectureId;
  const modal = document.getElementById('break-modal');
  if (modal) modal.classList.add('active');
}

function closeBreakModal() {
  const modal = document.getElementById('break-modal');
  if (modal) modal.classList.remove('active');
}

async function startBreakTimer(durationMinutes) {
  const lectureId = document.getElementById('break-group-id').value;
  closeBreakModal();

  try {
    await ApiClient.teacherStartBreak(lectureId, durationMinutes);
    alert(`تم تحويل المحاضرة لوضع الاستراحة وإرسال إشعار بريك لمدة ${durationMinutes} دقيقة للطلاب!`);
    await loadTeacherSessions();
  } catch (e) {
    alert(e.message || 'فشل تفعيل الاستراحة');
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
      alert('انتهت فترة الاستراحة! يمكنك استئناف المحاضرة الآن برابط جديد.');
      return;
    }

    const mins = Math.floor(secondsRemaining / 60);
    const secs = secondsRemaining % 60;
    if (countdownText) {
      countdownText.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
  }, 1000);
}

async function teacherEndBreakAction(lectureId) {
  try {
    await ApiClient.teacherEndBreak(lectureId);
    alert('تم إنهاء الاستراحة وتوليد رابط زوم جديد للمحاضرة بنجاح!');
    endBreakTimer();
    await loadTeacherSessions();
  } catch (e) {
    alert(e.message || 'حدث خطأ أثناء استئناف المحاضرة');
  }
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

window.switchTeacherTab = switchTeacherTab;
window.loadTeacherSessions = loadTeacherSessions;
window.loadTeacherData = loadTeacherData;
window.selectGroupDetails = selectGroupDetails;
window.closeGroupDetails = closeGroupDetails;
window.hostStartLecture = hostStartLecture;
window.hostJoinLecture = hostJoinLecture;
window.hostEndLecture = hostEndLecture;
window.teacherEndBreakAction = teacherEndBreakAction;
window.openBreakModal = openBreakModal;
window.closeBreakModal = closeBreakModal;
window.startBreakTimer = startBreakTimer;
window.endBreakTimer = endBreakTimer;
window.openPostponeModal = openPostponeModal;
window.closePostponeModal = closePostponeModal;
window.submitPostponeForm = submitPostponeForm;


