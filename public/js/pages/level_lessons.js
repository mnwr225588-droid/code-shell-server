// js/pages/level_lessons.js
// صفحة عرض دروس المستوى ببطاقات احترافية ونظام اشتراك مطابق للتطبيق

let globalCourseInfo = null;
let isUserSubscribedToCourse = false;

document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const courseId = urlParams.get('courseId');
  const levelIdx = urlParams.get('levelIdx');
  const container = document.getElementById('level-lessons-container');
  const navTitle = document.getElementById('level-navbar-title');

  if (!courseId || levelIdx === null) {
    container.innerHTML = `
      <div style="text-align:center; padding: 80px 20px;">
        <div style="font-size: 56px; margin-bottom: 16px;">⚠️</div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">لم يتم تحديد الكورس أو المستوى</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;">يرجى العودة واختيار المستوى مرة أخرى.</p>
        <a href="courses.html" style="background: #2563EB; color: #FFF; padding: 12px 28px; border-radius: 12px; text-decoration: none; font-weight: 700;">الرجوع للكورسات</a>
      </div>`;
    return;
  }

  try {
    // جلب بيانات الكورس وحالة الاشتراك من السيرفر
    const [levelsRes, subRes, courseRes] = await Promise.all([
      ApiClient.getCourseLevels(courseId).catch(() => []),
      ApiClient.getSubscriptionStatus(courseId).catch(() => ({ is_subscribed: false })),
      ApiClient.getCourseDetails(courseId).catch(() => null)
    ]);

    isUserSubscribedToCourse = Boolean(subRes.is_subscribed);
    if (courseRes && (courseRes.data || courseRes.id || courseRes.title)) {
      globalCourseInfo = courseRes.data || courseRes.course || courseRes;
    } else {
      globalCourseInfo = { id: courseId, title: 'الكورس', is_free: true };
    }

    if (levelsRes && levelsRes.is_waiting) {
      container.innerHTML = `
        <div style="background: var(--bg-card); border-radius: 28px; padding: 56px 28px; text-align: center; border: 1px solid var(--border-color); max-width: 640px; margin: 40px auto; box-shadow: 0 20px 50px rgba(0,0,0,0.06);" class="animate-fadeIn">
          <div style="width: 80px; height: 80px; border-radius: 24px; background: rgba(245, 158, 11, 0.15); color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 20px;">
            ⏳
          </div>
          <h2 style="font-size: 22px; font-weight: 900; color: var(--text-primary); margin-bottom: 12px;">المجموعة قيد الانتظار والتفعيل</h2>
          <p style="font-size: 15px; color: var(--text-secondary); line-height: 1.7; margin-bottom: 28px; max-width: 520px; margin-left: auto; margin-right: auto;">
            تم تسجليك في هذه المجموعة بنجاح! سيتم إتاحة جميع الدروس والمستويات فور تفعيل المجموعة من قبل الأدمن.
          </p>
          <a href="my-courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 32px; text-decoration: none; border-radius: 14px;">العودة لكورساتي الحالية</a>
        </div>
      `;
      return;
    }

    const levels = Array.isArray(levelsRes) ? levelsRes : (levelsRes.levels || levelsRes.data || []);
    let level = levels[parseInt(levelIdx, 10)];
    
    if (!level && levels.length > 0) {
      level = levels.find(l => String(l.id) === String(levelIdx) || String(l.order_num) === String(levelIdx)) || levels[0];
    }

    if (!level) {
      container.innerHTML = `
        <div style="text-align:center; padding: 80px 20px;">
          <div style="font-size: 56px; margin-bottom: 16px;">📭</div>
          <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">المستوى غير موجود</h3>
          <p style="color: var(--text-muted); margin-bottom: 24px;">عذراً، لم نتمكن من العثور على هذا المستوى.</p>
          <a href="javascript:history.back()" style="background: #2563EB; color: #FFF; padding: 12px 28px; border-radius: 12px; text-decoration: none; font-weight: 700;">العودة</a>
        </div>`;
      return;
    }

    const levelNum = parseInt(levelIdx, 10) + 1;
    const levelTitle = level.title || level.name || `المستوى ${levelNum}`;
    const levelDesc = level.description || level.subtitle || 'دروس بالفيديو واختبارات قياس المستوى';
    
    let lessons = level.lessons || [];
    if (level.id && (!lessons || lessons.length === 0)) {
      const directLessonsRes = await ApiClient.getLevelLessons(level.id).catch(() => []);
      lessons = Array.isArray(directLessonsRes) ? directLessonsRes : (directLessonsRes.data || []);
    }

    if (navTitle) navTitle.textContent = levelTitle;
    document.title = `Code Shell — ${levelTitle}`;

    let html = '';

    // بانر المستوى
    html += `
      <div style="background: linear-gradient(135deg, #1E3A8A 0%, #1E293B 100%); border-radius: 24px; padding: 32px 28px; color: #FFFFFF; position: relative; overflow: hidden; box-shadow: 0 16px 36px rgba(30, 58, 138, 0.3); border: 1px solid rgba(255,255,255,0.12); margin-bottom: 32px;" class="animate-fadeIn">
        <div style="position: absolute; top: -50px; left: -50px; width: 200px; height: 200px; border-radius: 50%; background: rgba(59, 130, 246, 0.15); pointer-events: none; filter: blur(25px);"></div>
        <div style="position: absolute; bottom: -30px; right: -30px; width: 140px; height: 140px; border-radius: 50%; background: rgba(96, 165, 250, 0.1); pointer-events: none;"></div>
        
        <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
          <div style="display: flex; align-items: center; gap: 20px;">
            <button onclick="window.history.back()" style="width: 48px; height: 48px; border-radius: 14px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); color: #FFF; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; flex-shrink: 0;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
              <i class="fas fa-arrow-right" style="font-size: 16px;"></i>
            </button>
            <div>
              <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(96, 165, 250, 0.25); padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-bottom: 8px; border: 1px solid rgba(96, 165, 250, 0.3);">
                📚 المستوى ${levelNum} ${isUserSubscribedToCourse ? '• ✅ أمتلك صلاحية الوصول' : '• 🔒 يتطلب الاشتراك'}
              </div>
              <h1 style="font-size: 24px; font-weight: 900; margin-bottom: 4px; color: #FFFFFF;">${levelTitle}</h1>
              <p style="font-size: 14px; opacity: 0.85; color: #CBD5E1; margin: 0;">${levelDesc}</p>
            </div>
          </div>
          
          <div style="background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); padding: 14px 24px; border-radius: 16px; backdrop-filter: blur(10px); text-align: center;">
            <div style="font-size: 22px; font-weight: 900; color: #60A5FA;">${lessons.length}</div>
            <div style="font-size: 12px; font-weight: 700; opacity: 0.85;">درس تعليمي</div>
          </div>
        </div>
      </div>
    `;

    if (lessons.length === 0) {
      html += `
        <div style="text-align: center; padding: 60px 20px; background: var(--bg-card); border-radius: 20px; border: 1px dashed var(--border-color);" class="animate-fadeIn">
          <div style="font-size: 56px; margin-bottom: 16px;">📚</div>
          <h3 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">لا توجد دروس مضافة لهذا المستوى بعد</h3>
          <p style="color: var(--text-muted); font-size: 14px;">سيتم إضافة الدروس قريباً، ترقبوا!</p>
        </div>
      `;
      container.innerHTML = html;
      return;
    }

    // شبكة بطاقات الدروس
    html += `<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">`;

    lessons.forEach((lesson, idx) => {
      const lessonTitle = lesson.title || lesson.name || `الدرس ${idx + 1}`;
      
      // حساب المدة الفعلية للدرس من السيرفر
      let duration = 'غير محدد';
      if (lesson.duration_seconds) {
        const sec = parseInt(lesson.duration_seconds, 10);
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        duration = `${m}:${s < 10 ? '0' : ''}${s}`;
      } else if (lesson.duration_minutes) {
        duration = `${lesson.duration_minutes} دقيقة`;
      } else if (lesson.duration && lesson.duration !== '10:00' && lesson.duration !== '10 دقائق') {
        duration = lesson.duration;
      } else if (lesson.duration) {
        duration = lesson.duration;
      }

      const lessonDesc = lesson.description || levelDesc;
      const thumbnail = lesson.thumbnail_url || lesson.thumbnail || null;
      const hasThumb = thumbnail && thumbnail.length > 5;

      const gradients = [
        'linear-gradient(135deg, #1E40AF, #3B82F6)',
        'linear-gradient(135deg, #7C3AED, #A78BFA)',
        'linear-gradient(135deg, #059669, #34D399)',
        'linear-gradient(135deg, #DC2626, #F87171)',
        'linear-gradient(135deg, #D97706, #FBBF24)',
        'linear-gradient(135deg, #0891B2, #67E8F9)',
      ];
      const grad = gradients[idx % gradients.length];

      html += `
        <div class="animate-fadeIn" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.06); transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column;" onmouseover="this.style.transform='translateY(-6px)'; this.style.boxShadow='0 20px 40px rgba(37,99,235,0.15)'; this.style.borderColor='rgba(37,99,235,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.06)'; this.style.borderColor='var(--border-color)'">
          
          <!-- صورة الدرس المصغرة (Thumbnail من R2) -->
          <div style="width: 100%; height: 200px; background: ${grad}; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center;">
            ${hasThumb 
              ? `<img src="${thumbnail}" alt="${lessonTitle}" style="width: 100%; height: 100%; object-fit: cover;" />`
              : `<div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                   <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; backdrop-filter: blur(10px); border: 2px solid rgba(255,255,255,0.3);">
                     <i class="fas fa-play" style="font-size: 24px; color: #FFF; margin-right: -2px;"></i>
                   </div>
                   <span style="color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 700;">الدرس ${idx + 1}</span>
                 </div>`
            }
            
            <div style="position: absolute; top: 14px; left: 14px; background: rgba(0,0,0,0.65); color: #FFF; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; backdrop-filter: blur(5px); display: flex; align-items: center; gap: 4px;">
              <i class="far fa-clock" style="font-size: 11px;"></i> ${duration}
            </div>
            
            <div style="position: absolute; top: 14px; right: 14px; background: rgba(255,255,255,0.2); color: #FFF; width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 900; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.3);">
              ${idx + 1}
            </div>
          </div>

          <!-- محتوى البطاقة -->
          <div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column;">
            <h3 style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px; font-family: 'Cairo', sans-serif; line-height: 1.5;">${lessonTitle}</h3>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6; margin-bottom: 16px; flex-grow: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">${lessonDesc}</p>
            
            <!-- زر الدخول للدرس -->
            <button onclick="openLessonWithSubscriptionCheck('${courseId}', ${levelIdx}, ${idx})" style="width: 100%; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #FFF; border: none; padding: 13px; border-radius: 14px; font-size: 15px; font-weight: 800; cursor: pointer; transition: all 0.25s; box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3); display: flex; align-items: center; justify-content: center; gap: 8px;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 25px rgba(37,99,235,0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 6px 20px rgba(37,99,235,0.3)'">
              <i class="fas fa-play-circle"></i>
              الدخول للدرس
            </button>
          </div>
        </div>
      `;
    });

    html += `</div>`;
    container.innerHTML = html;

  } catch (err) {
    console.error('Error loading level lessons:', err);
    container.innerHTML = `
      <div style="text-align:center; padding: 80px 20px;">
        <div style="font-size: 56px; margin-bottom: 16px;">⚠️</div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">${err.message || 'حدث خطأ'}</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;">يرجى التأكد من الرابط والمحاولة مرة أخرى.</p>
        <a href="courses.html" style="background: #2563EB; color: #FFF; padding: 12px 28px; border-radius: 12px; text-decoration: none; font-weight: 700;">الرجوع للكورسات</a>
      </div>`;
  }
});

// دالة فحص الاشتراك والدخول للدرس (مثل التطبيق)
async function openLessonWithSubscriptionCheck(courseId, levelIdx, lessonIdx) {
  if (isUserSubscribedToCourse) {
    window.location.href = `lesson-viewer.html?courseId=${courseId}&levelIdx=${levelIdx}&lessonIdx=${lessonIdx}`;
    return;
  }

  // إظهار نافذة التوجيه والاشتراك الفوري
  showAppSubscriptionModal(globalCourseInfo || { id: courseId, title: 'الكورس', is_free: true }, () => {
    isUserSubscribedToCourse = true;
    window.location.href = `lesson-viewer.html?courseId=${courseId}&levelIdx=${levelIdx}&lessonIdx=${lessonIdx}`;
  });
}

function showAppSubscriptionModal(course, onSuccess) {
  const isFree = Boolean(course.is_free !== false);
  const priceText = isFree ? 'مجاني بالكامل 🎁' : `${course.price || ''} ${course.currency_symbol || 'ج.م'} 💳`;

  const modal = document.createElement('div');
  modal.id = 'app-sub-modal';
  modal.style.cssText = `
    position: fixed; top:0; left:0; right:0; bottom:0;
    background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: center;
    z-index: 99999; padding: 20px; animation: fadeIn 0.3s ease;
  `;

  modal.innerHTML = `
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 28px; width: 100%; max-width: 480px; padding: 36px 28px; box-shadow: 0 30px 70px rgba(0,0,0,0.4); text-align: center; position: relative;">
      
      <div style="width: 76px; height: 76px; border-radius: 26px; background: ${isFree ? 'rgba(34,197,94,0.15)' : 'rgba(59,130,246,0.15)'}; color: ${isFree ? '#22C55E' : '#3B82F6'}; display: flex; align-items: center; justify-content: center; font-size: 38px; margin: 0 auto 20px;">
        ${isFree ? '🎁' : '💳'}
      </div>

      <h2 style="font-size: 22px; font-weight: 900; color: var(--text-primary); margin-bottom: 8px;">
        ${isFree ? 'اشترك مجاناً لمشاهدة الدرس' : 'اشترك في الكورس لمتابعة الدروس'}
      </h2>
      
      <p style="font-size: 14.5px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.5;">
        ${course.title || course.name || 'الكورس البرمجي'}<br>
        <span style="display: inline-block; margin-top: 6px; font-weight: 800; color: ${isFree ? '#22C55E' : '#3B82F6'}; background: ${isFree ? 'rgba(34,197,94,0.1)' : 'rgba(59,130,246,0.1)'}; padding: 4px 16px; border-radius: 10px;">
          ${priceText}
        </span>
      </p>

      <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 28px; background: var(--bg-body); padding: 14px; border-radius: 14px; border: 1px solid var(--border-color); line-height: 1.6;">
        ${isFree 
          ? 'بكبسة زر واحدة سيتم إضافة هذا الكورس إلى قائمتك، وتخصيص مجموعة لك مع المدرس مع حفظ تقدمك بالسيرفر!' 
          : 'يتطلب هذا الكورس اشتراكاً مفككاً لتفعيل المجموعة والمتابعة مع المدرس والمشرفين.'}
      </p>

      <div style="display: flex; gap: 12px;">
        <button id="modal-confirm-btn" class="auth-btn" style="background: ${isFree ? 'linear-gradient(135deg, #16A34A, #15803D)' : 'linear-gradient(135deg, #2563EB, #1D4ED8)'}; flex: 1; padding: 14px; border-radius: 14px; font-weight: 800;">
          ${isFree ? '✨ تأكيد الاشتراك المجاني الآن' : '💳 متابعة عملية الدفع'}
        </button>
        <button onclick="document.getElementById('app-sub-modal').remove()" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-primary); padding: 14px 20px; border-radius: 14px; font-weight: 700; cursor: pointer;">
          إلغاء
        </button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  document.getElementById('modal-confirm-btn').addEventListener('click', async () => {
    const btn = document.getElementById('modal-confirm-btn');
    btn.disabled = true;
    btn.innerHTML = 'جاري التسجيل في السيرفر...';

    try {
      if (isFree) {
        await ApiClient.subscribeCourse(course.id);
        modal.remove();
        if (onSuccess) onSuccess();
      } else {
        await ApiClient.initiatePayment(course.id);
        modal.remove();
        alert('جاري توجيهك لبوابة الدفع...');
        if (onSuccess) onSuccess();
      }
    } catch (err) {
      btn.disabled = false;
      btn.innerHTML = isFree ? '✨ تأكيد الاشتراك المجاني الآن' : '💳 متابعة عملية الدفع';
      alert(err.message || 'تعذر إتمام عملية الاشتراك');
    }
  });
}

window.openLessonWithSubscriptionCheck = openLessonWithSubscriptionCheck;
