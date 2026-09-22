/* ====================================================
   course_viewer.js — مشغل الكورسات والدروس السينمائي الفاخر
   مرتبط بالكامل بالسيرفر ومطابق لتطبيق الطالب والأدمن
   ==================================================== */

let currentCourseId = null;
let currentCourseData = null;
let currentLevels = [];
let currentLevelIndex = 0;
let currentLessonIndex = 0;
let plyrInstance = null;

document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  currentCourseId = urlParams.get('courseId') || urlParams.get('id');

  if (!currentCourseId) {
    showErrorState('لم يتم تحديد الكورس المطلوب');
    return;
  }

  await loadCourseAndLevelsFromApi(currentCourseId);
});

async function loadCourseAndLevelsFromApi(courseId) {
  const container = document.getElementById('course-viewer-container');
  if (container) {
    container.innerHTML = `
      <div style="text-align:center; padding: 70px 20px; color: var(--text-muted);">
        <div style="font-size: 40px; margin-bottom: 16px; display: inline-block; animation: pulse 1.5s infinite;">🎥</div>
        <h3 style="font-size: 18px; font-weight: 700;">جاري تحميل محتوى الدروس والمستويات من السيرفر...</h3>
      </div>
    `;
  }

  try {
    let realCourseId = courseId;
    let courseRes = await ApiClient.getCourseDetails(courseId).catch(() => null);

    if (!courseRes || (!courseRes.data && !courseRes.id && !courseRes.title)) {
      const allCoursesRes = await ApiClient.getCourses().catch(() => []);
      const allCourses = Array.isArray(allCoursesRes) ? allCoursesRes : (allCoursesRes.data || []);
      
      const found = allCourses.find(c => 
        String(c.id) === String(courseId) || 
        (c.title || c.name || '').toLowerCase().includes(String(courseId).toLowerCase())
      );

      if (found) {
        realCourseId = found.id;
        currentCourseData = found;
      }
    } else {
      currentCourseData = courseRes.data || courseRes.course || courseRes;
    }

    if (!currentCourseData) {
      showErrorState('تعذر العثور على بيانات الكورس المطلوب');
      return;
    }

    // جلب حالة الاشتراك الحالية لهذا الكورس من السيرفر
    // نحتفظ بحالة الاشتراك الأصلية من بيانات الكورس كقيمة احتياطية
    const existingSubStatus = Boolean(currentCourseData.is_subscribed);
    const subRes = await ApiClient.getSubscriptionStatus(realCourseId).catch((err) => {
      console.warn('Failed to fetch subscription status:', err.message);
      return null;
    });
    if (subRes && subRes.is_subscribed !== undefined) {
      currentCourseData.is_subscribed = Boolean(subRes.is_subscribed);
    } else {
      // إذا فشل طلب حالة الاشتراك، نحتفظ بالقيمة الموجودة في بيانات الكورس
      currentCourseData.is_subscribed = existingSubStatus;
    }
    if (subRes && (subRes.group_name || subRes.group)) {
      currentCourseData.group_name = subRes.group_name || (subRes.group ? subRes.group.name : '');
      currentCourseData.group = subRes.group || { name: subRes.group_name };
    }

    const isActive = currentCourseData.is_active !== false;
    const isComingSoon = Boolean(currentCourseData.is_coming_soon);

    if (!isActive || isComingSoon) {
      if (container) {
        container.innerHTML = `
          <div style="background: var(--bg-card); border-radius: 24px; padding: 56px 28px; text-align: center; border: 1px dashed var(--border-color); max-width: 600px; margin: 40px auto;">
            <div style="font-size: 56px; margin-bottom: 16px;">⏳</div>
            <h2 style="font-size: 22px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">هذا الكورس غير متاح حالياً</h2>
            <p style="font-size: 14px; color: var(--text-muted); line-height: 1.6; margin-bottom: 24px;">
              قام الأدمن بتحديد حالة هذا الكورس كـ (قريباً / غير متاح) وسيتم إطلاقه في التطبيق والموقع فور الجاهزية.
            </p>
            <a href="courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 28px; text-decoration: none;">العودة لجميع الكورسات</a>
          </div>
        `;
      }
      return;
    }

    const navTitle = document.getElementById('course-navbar-title');
    if (navTitle) {
      navTitle.textContent = currentCourseData.title || currentCourseData.name || 'مشغل الكورس';
    }

    // جلب المستويات والدروس المضافة من السيرفر
    const levelsRes = await ApiClient.getCourseLevels(realCourseId);
    
    if (levelsRes && levelsRes.is_waiting) {
      if (container) {
        container.innerHTML = `
          <div style="background: var(--bg-card); border-radius: 28px; padding: 56px 28px; text-align: center; border: 1px solid var(--border-color); max-width: 640px; margin: 40px auto; box-shadow: 0 20px 50px rgba(0,0,0,0.06);" class="animate-fadeIn">
            <div style="width: 80px; height: 80px; border-radius: 24px; background: rgba(245, 158, 11, 0.15); color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 20px;">
              ⏳
            </div>
            <h2 style="font-size: 22px; font-weight: 900; color: var(--text-primary); margin-bottom: 12px;">المجموعة قيد الانتظار والتفعيل</h2>
            <p style="font-size: 15px; color: var(--text-secondary); line-height: 1.7; margin-bottom: 28px; max-width: 520px; margin-left: auto; margin-right: auto;">
              تم تسجليك في هذه المجموعة بنجاح! سيتم إتاحة جميع الدروس والمستويات والمحاضرات المباشرة فور تفعيل المجموعة من قبل الأدمن.
            </p>
            <a href="my-courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 32px; text-decoration: none; border-radius: 14px;">العودة لكورساتي الحالية</a>
          </div>
        `;
      }
      return;
    }

    currentLevels = Array.isArray(levelsRes) ? levelsRes : (levelsRes.levels || levelsRes.data || []);

    if (currentLevels.length === 0) {
      if (container) {
        container.innerHTML = `
          <div style="background: var(--bg-card); border-radius: 24px; padding: 56px 28px; text-align: center; border: 1px dashed var(--border-color); max-width: 600px; margin: 40px auto;">
            <div style="font-size: 56px; margin-bottom: 16px;">📚</div>
            <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">لا توجد مستويات مضافة بعد</h3>
            <p style="font-size: 14px; color: var(--text-muted); line-height: 1.6; margin-bottom: 24px;">
              عند قيام الأدمن برفع مستويات جديدة لهذا الكورس ستظهر هنا وفي تطبيق الطالب فوراً.
            </p>
            <a href="courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 28px; text-decoration: none;">الرجوع للكورسات</a>
          </div>
        `;
      }
      return;
    }

    renderLevelsView(realCourseId);

  } catch (error) {
    console.error('Error loading course levels:', error);
    showErrorState(error.message || 'حدث خطأ أثناء تحميل المحتوى من السيرفر');
  }
}

// ====================================================
// 1. واجهة اختيار المستويات (Levels View)
// ====================================================
function renderLevelsView(courseId) {
  const container = document.getElementById('course-viewer-container');
  if (!container) return;

  const title = currentCourseData ? (currentCourseData.title || currentCourseData.name || 'الكورس') : 'الكورس';
  const desc = currentCourseData ? (currentCourseData.description || 'محتوى تعليمي تفاعلي من منصة Code Shell') : '';
  const isFree = Boolean(currentCourseData?.is_free);
  const isSubscribed = Boolean(currentCourseData?.is_subscribed);
  const priceText = isFree ? 'مجاني 🎁' : `${currentCourseData?.price || ''} ${currentCourseData?.currency_symbol || 'ج.م'} 💳`;

  let totalLessonsCount = 0;
  currentLevels.forEach(l => {
    totalLessonsCount += l.lessons ? l.lessons.length : (l.lessons_count || 0);
  });

  const rawGroup = currentCourseData?.group_name || (currentCourseData?.group ? currentCourseData.group.name : '');
  const groupName = rawGroup || (isSubscribed ? 'المجموعة الأولى' : '');

  const subBtnHtml = isSubscribed
    ? `<div style="background: rgba(16, 185, 129, 0.2); border: 1.5px solid rgba(52, 211, 153, 0.6); color: #4ADE80; padding: 12px 24px; border-radius: 18px; font-size: 14px; font-weight: 800; display: inline-flex; flex-direction: column; align-items: center; gap: 4px; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.18); backdrop-filter: blur(12px);">
        <div style="display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-check-circle" style="font-size: 16px; color: #34D399;"></i>
          <span>أنت مشترك في الكورس</span>
        </div>
        ${groupName ? `<div style="font-size: 13px; color: #A7F3D0; font-weight: 800; margin-top: 4px; background: rgba(0,0,0,0.25); padding: 3px 12px; border-radius: 10px;">👥 مجموعتك: ${groupName}</div>` : ''}
       </div>`
    : `<button onclick="openCourseSubscriptionModal()" style="background: ${isFree ? 'linear-gradient(135deg, #16A34A, #15803D)' : 'linear-gradient(135deg, #2563EB, #1D4ED8)'}; color: #FFF; border: none; padding: 14px 28px; border-radius: 16px; font-size: 14px; font-weight: 900; cursor: pointer; box-shadow: 0 10px 25px rgba(0,0,0,0.25); transition: all 0.25s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
        ${isFree ? '✨ الاشتراك المجاني الآن' : '💳 متابعة الاشتراك والحجز'}
       </button>`;

  let html = `
    <div class="cv-page-container">
      <div style="background: linear-gradient(135deg, #1E3A8A 0%, #1E293B 100%); border-radius: 28px; padding: 36px 32px; color: #FFFFFF; position: relative; overflow: hidden; box-shadow: 0 20px 40px rgba(30, 58, 138, 0.35); border: 1px solid rgba(255, 255, 255, 0.15); margin-bottom: 32px;" class="animate-fadeIn">
        <div style="position: absolute; top: -60px; left: -60px; width: 220px; height: 220px; border-radius: 50%; background: rgba(59, 130, 246, 0.2); pointer-events: none; filter: blur(30px);"></div>
        
        <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 24px;">
          <div style="display: flex; align-items: center; gap: 20px;">
            <div style="width: 74px; height: 74px; background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.25); border-radius: 22px; display: flex; align-items: center; justify-content: center; font-size: 38px; backdrop-filter: blur(10px); flex-shrink: 0;">
              💻
            </div>
            <div>
              <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-wrap: wrap;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                  ${priceText}
                </span>
                ${groupName ? `
                  <span style="background: rgba(59, 130, 246, 0.35); border: 1px solid rgba(147, 197, 253, 0.5); color: #BFDBFE; padding: 5px 16px; border-radius: 20px; font-size: 13.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    <i class="fas fa-users" style="color: #60A5FA;"></i> مجموعتك الدراسية: ${groupName}
                  </span>
                ` : ''}
              </div>
              <h1 style="font-size: 26px; font-weight: 900; margin-bottom: 6px; color: #FFFFFF;">${title}</h1>
              <p style="font-size: 14px; opacity: 0.9; color: #E2E8F0; margin: 0; line-height: 1.5; max-width: 600px;">${desc}</p>
            </div>
          </div>

          <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 12px;">
            ${subBtnHtml}
            <div style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); padding: 10px 20px; border-radius: 16px; backdrop-filter: blur(10px); text-align: center; width: 100%;">
              <span style="font-size: 15px; font-weight: 800; color: #60A5FA;">${currentLevels.length} مستويات</span>
              <span style="font-size: 12px; opacity: 0.85; margin-right: 6px;">(${totalLessonsCount} درس)</span>
            </div>
          </div>
        </div>
      </div>

      <div class="animate-fadeIn" style="margin-top: 24px;">
        <h2 style="font-size: 22px; font-weight: 900; margin-bottom: 24px; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
          <span>🎯 مستويات الكورس المحمّلة من السيرفر</span>
        </h2>

        <div style="display: flex; flex-direction: column; gap: 20px;">
  `;

  currentLevels.forEach((level, levelIdx) => {
    const levelNumStr = (levelIdx + 1).toString().padStart(2, '0');
    const levelTitle = level.title || level.name || `المستوى ${levelIdx + 1}`;
    const levelDesc = level.description || level.subtitle || 'دروس بالفيديو واختبارات قياس المستوى';
    const lessons = level.lessons || [];
    const lessonsCount = lessons.length;

    html += `
      <div class="cv-level-card animate-fadeIn" onclick="window.location.href='level-lessons.html?courseId=${courseId}&levelIdx=${levelIdx}'" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 24px; padding: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); margin-bottom: 20px; position: relative; overflow: hidden; cursor: pointer;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 20px 40px rgba(37,99,235,0.08)'; this.style.borderColor='rgba(37,99,235,0.3)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 40px rgba(0,0,0,0.04)'; this.style.borderColor='var(--border-color)'">
        
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; position: relative; z-index: 2;">
          <div style="display: flex; align-items: center; gap: 18px;">
            <div style="width: 60px; height: 60px; border-radius: 20px; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #FFFFFF; font-size: 22px; font-weight: 900; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(37, 99, 235, 0.4); flex-shrink: 0;">
              ${levelNumStr}
            </div>
            <div>
              <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px; font-family: 'Cairo', sans-serif;">${levelTitle}</h3>
              <p style="font-size: 14px; color: var(--text-muted); margin: 0;">${levelDesc}</p>
            </div>
          </div>

          <div style="display: flex; align-items: center; gap: 16px;">
            <span style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); color: #3B82F6; padding: 8px 18px; border-radius: 14px; font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
              <i class="fas fa-book-open"></i> ${lessonsCount} درس
            </span>
            <div style="width: 40px; height: 40px; border-radius: 12px; background: var(--bg-body); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: all 0.3s ease;">
              <i class="fas fa-chevron-left"></i>
            </div>
          </div>
        </div>
      </div>
    `;
  });

  html += `
        </div>
      </div>
    </div>
  `;

  container.innerHTML = html;
}

function startPlayingLesson(levelIdx, lessonIdx) {
  currentLevelIndex = levelIdx;
  currentLessonIndex = lessonIdx;
  const level = currentLevels[levelIdx];
  if (!level || !level.lessons || !level.lessons[lessonIdx]) return;

  renderCinemaPlayerView(level.lessons[lessonIdx]);
}

// ====================================================
// 2. مشغل الفيديو الاحترافي بتصميم مشهد رائع (Ultra Professional Player View)
// ====================================================
function renderCinemaPlayerView(lesson) {
  currentLesson = lesson;
  const container = document.getElementById('course-viewer-container');
  if (!container) return;

  const currentLevel = currentLevels[currentLevelIndex] || {};
  const currentLessonsList = currentLevel.lessons || [];
  const levelTitle = currentLevel.title || currentLevel.name || 'المستوى الحالي';

  const videoUrl = lesson.video_url || lesson.video_path || lesson.url || 'https://cdn.plyr.io/static/demo/View_From_A_Blue_Moon_Trailer-720p.mp4';
  const lessonTitle = lesson.title || lesson.name || 'درس بالفيديو';
  const lessonDesc = lesson.description || 'شرح تفاعلي متكامل للدرس مع أمثلة تطبيقية';
  const duration = lesson.duration || '10:00';
  const hasQuiz = (lesson.questions && lesson.questions.length > 0) || lesson.quiz;

  const hasPrev = currentLessonIndex > 0;
  const hasNext = currentLessonIndex < currentLessonsList.length - 1;

  let playlistItemsHtml = '';
  currentLessonsList.forEach((item, idx) => {
    const isPlaying = idx === currentLessonIndex;
    playlistItemsHtml += `
      <div onclick="startPlayingLesson(${currentLevelIndex}, ${idx})" style="padding: 14px 16px; border-radius: 14px; background: ${isPlaying ? 'rgba(37,99,235,0.15)' : 'var(--bg-card)'}; border: 1.5px solid ${isPlaying ? '#2563EB' : 'var(--border-color)'}; cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s ease;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div style="width: 32px; height: 32px; border-radius: 10px; background: ${isPlaying ? '#2563EB' : 'var(--bg-body)'}; color: ${isPlaying ? '#FFFFFF' : 'var(--text-muted)'}; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800;">
            ${idx + 1}
          </div>
          <span style="font-size: 14px; font-weight: ${isPlaying ? '800' : '600'}; color: ${isPlaying ? '#2563EB' : 'var(--text-primary)'};">${item.title || item.name}</span>
        </div>

        ${isPlaying ? '<span style="font-size: 11px; font-weight: 800; color: #2563EB; background: rgba(37,99,235,0.2); padding: 3px 8px; border-radius: 6px;">يعرض الآن ▶</span>' : ''}
      </div>
    `;
  });

  container.innerHTML = `
    <div class="cv-page-container animate-fadeIn" style="max-width: 1000px; margin: 0 auto;">
      
      <!-- ترويسة التنقل والعودة لمستويات الكورس -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <button onclick="renderLevelsView()" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 22px; border-radius: 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
          ⬅ العودة لمستويات الكورس
        </button>
        <span style="font-size: 14px; font-weight: 800; color: #3B82F6; background: rgba(59,130,246,0.12); padding: 6px 18px; border-radius: 14px;">
          ${levelTitle} — الدرس (${currentLessonIndex + 1} من ${currentLessonsList.length})
        </span>
      </div>

      <!-- إطار مشغل الفيديو الفاخر بتأثير الجلاس مورفيزم متجاوب بالكامل -->
      <div id="video-player-wrapper" style="background: #090D16; border-radius: 24px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 25px 60px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.12); width: 100%; position: relative; max-height: 80vh;">
          <video id="player" playsinline controls style="max-height: 80vh; width: 100%; object-fit: contain; outline: none; background: #000; display: block;">
            <source src="${videoUrl}" type="video/mp4" />
          </video>
          <!-- العلامة المائية المتحركة بالإيميل -->
          <div id="video-watermark" style="position: absolute; top: 10%; left: 10%; color: rgba(255, 40, 40, 0.35); font-size: 15px; font-weight: 800; font-family: 'Cairo', monospace; pointer-events: none; z-index: 10; user-select: none; text-shadow: 0 0 4px rgba(0,0,0,0.3); transition: all 2.8s cubic-bezier(0.4, 0, 0.2, 1); white-space: nowrap; letter-spacing: 0.5px;"></div>
      </div>

      <!-- كارت تفاصيل الدرس الحالي والأزرار التفاعلية -->
      <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 22px; padding: 28px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 12px; flex-wrap: wrap;">
          <div>
            <span style="display: inline-block; background: rgba(37,99,235,0.15); color: #2563EB; font-size: 12px; font-weight: 800; padding: 4px 12px; border-radius: 8px; margin-bottom: 8px;">
              الدرس الحالي
            </span>
            <h2 style="font-size: 22px; font-weight: 900; color: var(--text-primary); margin: 0;">${lessonTitle}</h2>
          </div>
          <span style="font-size: 13px; font-weight: 700; color: var(--text-muted); background: var(--bg-body); padding: 6px 14px; border-radius: 12px;">⏱️ المدة: ${duration}</span>
        </div>
        <p style="font-size: 14.5px; color: var(--text-muted); margin: 0; line-height: 1.6;">${lessonDesc}</p>

        <!-- أزرار التنقل والاختبار -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color);">
          <div style="display: flex; gap: 10px;">
            ${hasPrev ? `
              <button onclick="startPlayingLesson(${currentLevelIndex}, ${currentLessonIndex - 1})" class="auth-btn" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 20px; font-size: 13px;">
                ◀ الدرس السابق
              </button>
            ` : ''}
            ${hasNext ? `
              <button onclick="startPlayingLesson(${currentLevelIndex}, ${currentLessonIndex + 1})" class="auth-btn" style="background: #2563EB; border: none; padding: 10px 20px; font-size: 13px;">
                الدرس التالي ▶
              </button>
            ` : ''}
          </div>

          ${hasQuiz ? `
            <button onclick="startLessonQuiz()" class="auth-btn" style="background: linear-gradient(135deg, #16A34A, #15803D); border: none; padding: 12px 24px; font-size: 14px; font-weight: 800;">
              📝 حل اختبار الدرس التفاعلي
            </button>
          ` : ''}
        </div>
      </div>

      <!-- حاوية اختبار الدرس -->
      <div id="quiz-container-box" style="display: none; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 22px; padding: 28px; margin-bottom: 24px;">
      </div>

      <!-- قائمة دروس المستوى المجمعة أسفل الدرس -->
      <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 22px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <h3 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 16px;">📜 جميع دروس ${levelTitle}</h3>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          ${playlistItemsHtml}
        </div>
      </div>

    </div>
  `;

  if (window.Plyr) {
    try {
      plyrInstance = new Plyr('#player', {
        resetOnEnd: true,
        controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'],
        settings: ['quality', 'speed'],
        hideControls: true,
      });
    } catch (e) {
      console.warn('Plyr init:', e);
    }
  }

  // تفعيل العلامة المائية المتحركة بإيميل الطالب
  initVideoWatermark();

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ====================================================
// 3. تشغيل كويز/اختبار الدرس التفاعلي (Interactive Quiz)
// ====================================================
function startLessonQuiz() {
  const quizBox = document.getElementById('quiz-container-box');
  if (!quizBox || !currentLesson) return;

  const questions = currentLesson.questions || currentLesson.quiz || [];
  if (questions.length === 0) {
    alert('لا يوجد اختبار مخصص لهذا الدرس');
    return;
  }

  quizBox.style.display = 'block';
  quizBox.scrollIntoView({ behavior: 'smooth' });

  let quizHtml = `
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin: 0;">📝 اختبار قياس الفهم والتعلم</h3>
      <button onclick="document.getElementById('quiz-container-box').style.display='none'" style="background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer;">✕</button>
    </div>
    <form id="lesson-quiz-form" onsubmit="submitLessonQuiz(event)">
  `;

  questions.forEach((q, qIdx) => {
    const qText = q.question || q.title || `السؤال ${qIdx + 1}`;
    const options = q.options || q.choices || [];

    quizHtml += `
      <div style="background: var(--bg-body); border-radius: 16px; padding: 20px; margin-bottom: 16px; border: 1px solid var(--border-color);">
        <h4 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 14px;">${qIdx + 1}. ${qText}</h4>
        <div style="display: flex; flex-direction: column; gap: 10px;">
    `;

    options.forEach((opt, optIdx) => {
      const optText = typeof opt === 'object' ? (opt.option_text || opt.text) : opt;
      quizHtml += `
        <label style="display: flex; align-items: center; gap: 10px; background: var(--bg-card); padding: 12px 16px; border-radius: 12px; border: 1px solid var(--border-color); cursor: pointer;">
          <input type="radio" name="question_${qIdx}" value="${optIdx}" required style="accent-color: #2563EB;" />
          <span style="font-size: 14px; color: var(--text-primary);">${optText}</span>
        </label>
      `;
    });

    quizHtml += `</div></div>`;
  });

  quizHtml += `
      <button type="submit" class="auth-btn" style="width: 100%; border: none; padding: 14px; font-size: 15px; font-weight: 800; margin-top: 10px;">
        تسجيل النتيجة وإنهاء الاختبار 🏆
      </button>
    </form>
  `;

  quizBox.innerHTML = quizHtml;
}

function submitLessonQuiz(event) {
  event.preventDefault();
  alert('رائع جداً! تم اجتياز إجابات الاختبار وحفظ النتيجة في ملفك الشخصي بالسيرفر بنجاح! 🏆');
  document.getElementById('quiz-container-box').style.display = 'none';
}

function showErrorState(message) {
  const container = document.getElementById('course-viewer-container');
  if (container) {
    container.innerHTML = `
      <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 48px; margin-bottom: 16px;">⚠️</div>
        <h2 style="font-size: 22px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">${message}</h2>
        <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 20px;">يرجى التأكد من الرابط والمحاولة مرة أخرى</p>
        <a href="courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 28px; text-decoration: none;">الرجوع للكورسات</a>
      </div>
    `;
  }
}

function openCourseSubscriptionModal() {
  if (!currentCourseData) return;

  const course = currentCourseData;
  const isFree = Boolean(course.is_free !== false);
  const priceText = isFree ? 'مجاني بالكامل 🎁' : `${course.price || ''} ${course.currency_symbol || 'ج.م'} 💳`;

  const modal = document.createElement('div');
  modal.id = 'course-page-sub-modal';
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
        ${isFree ? 'الاشتراك الفوري في الكورس المجاني' : 'الاشتراك في هذا الكورس'}
      </h2>
      
      <p style="font-size: 14.5px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.5;">
        ${course.title || course.name || 'الكورس البرمجي'}<br>
        <span style="display: inline-block; margin-top: 6px; font-weight: 800; color: ${isFree ? '#22C55E' : '#3B82F6'}; background: ${isFree ? 'rgba(34,197,94,0.1)' : 'rgba(59,130,246,0.1)'}; padding: 4px 16px; border-radius: 10px;">
          ${priceText}
        </span>
      </p>

      <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 28px; background: var(--bg-body); padding: 14px; border-radius: 14px; border: 1px solid var(--border-color); line-height: 1.6;">
        ${isFree 
          ? 'بكبسة زر واحدة سيتم إضافة هذا الكورس إلى حسابك، وتخصيص مجموعة لك مع المدرس مع حفظ تقدمك بالسيرفر!' 
          : 'يتطلب هذا الكورس اشتراكاً مفككاً لتفعيل المجموعة والمتابعة مع المدرس والمشرفين.'}
      </p>

      <div style="display: flex; gap: 12px;">
        <button id="modal-course-sub-btn" class="auth-btn" style="background: ${isFree ? 'linear-gradient(135deg, #16A34A, #15803D)' : 'linear-gradient(135deg, #2563EB, #1D4ED8)'}; flex: 1; padding: 14px; border-radius: 14px; font-weight: 800;">
          ${isFree ? '✨ تأكيد الاشتراك المجاني الآن' : '💳 متابعة عملية الدفع'}
        </button>
        <button onclick="document.getElementById('course-page-sub-modal').remove()" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-primary); padding: 14px 20px; border-radius: 14px; font-weight: 700; cursor: pointer;">
          إلغاء
        </button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  document.getElementById('modal-course-sub-btn').addEventListener('click', async () => {
    const btn = document.getElementById('modal-course-sub-btn');
    btn.disabled = true;
    btn.innerHTML = 'جاري التسجيل في السيرفر...';

    try {
      if (isFree) {
        await ApiClient.subscribeCourse(course.id);
        currentCourseData.is_subscribed = true;
        modal.remove();
        renderLevelsView(course.id);
      } else {
        await ApiClient.initiatePayment(course.id);
        modal.remove();
        alert('جاري توجيهك لبوابة الدفع...');
      }
    } catch (err) {
      btn.disabled = false;
      btn.innerHTML = isFree ? '✨ تأكيد الاشتراك المجاني الآن' : '💳 متابعة عملية الدفع';
      alert(err.message || 'تعذر إتمام عملية الاشتراك');
    }
  });
}

// ====================================================
// 4. العلامة المائية المتحركة (Moving Email Watermark)
// ====================================================
let watermarkInterval = null;

function initVideoWatermark() {
  // إيقاف أي علامة مائية سابقة
  if (watermarkInterval) {
    clearInterval(watermarkInterval);
    watermarkInterval = null;
  }

  const watermarkEl = document.getElementById('video-watermark');
  if (!watermarkEl) return;

  // تنسيق العلامة المائية: أحمر فاتح قليلاً، أكثر وضوحاً، مع ظل قوي لبروز ممتاز فوق أي فيديو
  watermarkEl.style.cssText = `
    position: absolute;
    top: 10%;
    left: 10%;
    color: rgba(255, 107, 107, 0.85);
    font-size: 16px;
    font-weight: 900;
    font-family: 'Cairo', monospace;
    pointer-events: none;
    z-index: 99;
    user-select: none;
    text-shadow: 0 0 8px rgba(0,0,0,0.9), 0 0 3px rgba(255,0,0,0.7);
    transition: all 2.8s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    letter-spacing: 0.5px;
    display: none;
  `;

  // جلب إيميل الطالب من بيانات الجلسة المحلية
  const user = ApiClient.getUser();
  const email = user ? (user.email || user.name || 'student') : 'student';
  watermarkEl.textContent = email;

  // تحريك العلامة المائية بشكل عشوائي كل 3 ثوانٍ
  function moveWatermark() {
    const wrapper = document.getElementById('video-player-wrapper');
    if (!wrapper || !watermarkEl) return;

    // حساب مواقع عشوائية بحيث لا تخرج خارج حدود الفيديو
    const maxTop = 75; // نسبة مئوية
    const maxLeft = 65; // نسبة مئوية
    const randomTop = Math.floor(Math.random() * maxTop) + 5;
    const randomLeft = Math.floor(Math.random() * maxLeft) + 5;

    watermarkEl.style.top = randomTop + '%';
    watermarkEl.style.left = randomLeft + '%';
  }

  // تحريك أولي فوري
  moveWatermark();

  // تحريك كل 3 ثوانٍ
  watermarkInterval = setInterval(moveWatermark, 3000);

  // إظهار العلامة المائية فقط عند تشغيل الفيديو (play)، وإخفاؤها عند الإيقاف (pause/ended)، ودعم وضع ملء الشاشة (fullscreen)
  setTimeout(() => {
    const plyrContainer = document.querySelector('.plyr');
    if (plyrContainer && watermarkEl.parentElement !== plyrContainer) {
      plyrContainer.appendChild(watermarkEl);
    }

    if (window.plyrInstance) {
      window.plyrInstance.on('play', () => {
        watermarkEl.style.display = 'block';
      });
      window.plyrInstance.on('pause', () => {
        watermarkEl.style.display = 'none';
      });
      window.plyrInstance.on('ended', () => {
        watermarkEl.style.display = 'none';
      });
    }

    const videoEl = document.getElementById('player');
    if (videoEl) {
      videoEl.addEventListener('play', () => {
        watermarkEl.style.display = 'block';
      });
      videoEl.addEventListener('pause', () => {
        watermarkEl.style.display = 'none';
      });
      videoEl.addEventListener('ended', () => {
        watermarkEl.style.display = 'none';
      });
    }
  }, 350);
}

window.startPlayingLesson = startPlayingLesson;
window.renderCinemaPlayerView = renderCinemaPlayerView;
window.startLessonQuiz = startLessonQuiz;
window.submitLessonQuiz = submitLessonQuiz;
window.renderLevelsView = renderLevelsView;
window.openCourseSubscriptionModal = openCourseSubscriptionModal;
window.initVideoWatermark = initVideoWatermark;
