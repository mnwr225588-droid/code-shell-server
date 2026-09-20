/* ====================================================
   computer_basics.js — صفحة دروس ومستويات كورس Computer Basics
   مطابقة بنسبة 100% لطريقة عمل تطبيق الطالب (Flutter App):
   1. فتح الدروس في شاشة مستقلة كاملة (Full Page Viewer).
   2. القفل التلقائي: لا ينفتح الدرس التالي إلا بعد إكمال الدرس الحالي.
   3. الإكمال والتهنئة المنبثقة التلقائية:
      - عند حل أسئلة الدرس أو الاختبار في الـ HTML، يُحفظ التقدم فوراً.
      - ينفتح الدرس التالي أوتوماتيكياً.
      - تظهر نافذة تهنئة منبثقة تخبر الطالب بإكمال الدرس وفتح الدرس التالي مع خيار الانتقال إليه فوراً.
   ==================================================== */

const COMPUTER_BASICS_DATA = {
  title: 'Computer Basics',
  subtitle: 'أساسيات الحاسوب',
  icon: '💻',
  description: 'رحلة شاملة لفهم أساسيات الحاسوب، من التعريف إلى المكونات المادية والبرمجية ونظام التشغيل، بأسلوب شيق ومبسط.',
  levels: [
    {
      number: 1,
      title: 'المستوى الأول',
      subtitle: 'أساسيات الحاسوب والمكونات ونظام التشغيل (12 درس واختبار)',
      isAvailable: true,
      itemsCount: 12,
    },
    {
      number: 2,
      title: 'المستوى الثاني',
      subtitle: 'متقدم في الحاسوب والشبكات (قريباً)',
      isAvailable: false,
      isComingSoon: true,
      itemsCount: 0,
    }
  ],
  items: [
    {
      id: 'lesson_1',
      order: 1,
      title: 'ما هو الحاسوب؟',
      subtitle: 'تعريف الحاسوب ودورة عمله',
      htmlFile: 'learning_content/lesson1.html',
      type: 'lesson'
    },
    {
      id: 'lesson_2',
      order: 2,
      title: 'أنواع الحواسيب',
      subtitle: 'الأنواع والمميزات والأحجام',
      htmlFile: 'learning_content/lesson2.html',
      type: 'lesson'
    },
    {
      id: 'lesson_3',
      order: 3,
      title: 'المكونات الأساسية للحاسوب',
      subtitle: 'دورة عمل الحاسوب (IPO)',
      htmlFile: 'learning_content/lesson3.html',
      type: 'lesson'
    },
    {
      id: 'lesson_4',
      order: 4,
      title: 'المكونات المادية والبرمجيات',
      subtitle: 'Hardware و Software بالتفصيل',
      htmlFile: 'learning_content/lesson4.html',
      type: 'lesson'
    },
    {
      id: 'lesson_5',
      order: 5,
      title: 'البيانات داخل الحاسوب',
      subtitle: 'أنواع البيانات ووحدات القياس (Bit & Byte)',
      htmlFile: 'learning_content/lesson5.html',
      type: 'lesson'
    },
    {
      id: 'lesson_6',
      order: 6,
      title: 'الذاكرة والتخزين',
      subtitle: 'RAM و SSD و HDD والفرق بينهم',
      htmlFile: 'learning_content/lesson6.html',
      type: 'lesson'
    },
    {
      id: 'exam_final',
      order: 7,
      title: 'الاختبار التراكمي الأول 📝',
      subtitle: '25 سؤالاً - مراجعة الدروس من 1 إلى 6',
      htmlFile: 'learning_content/final-exam.html',
      type: 'exam'
    },
    {
      id: 'lesson_7',
      order: 8,
      title: 'كيف يعمل الحاسوب؟',
      subtitle: 'الأجهزة والبرمجيات والتكامل التشغيلي',
      htmlFile: 'learning_content/lesson7.html',
      type: 'lesson'
    },
    {
      id: 'lesson_8',
      order: 9,
      title: 'نظام التشغيل (Operating System)',
      subtitle: 'ما هو نظام التشغيل ولماذا نحتاجه؟',
      htmlFile: 'learning_content/lesson8.html',
      type: 'lesson'
    },
    {
      id: 'lesson_9',
      order: 10,
      title: 'الوظائف الأساسية لنظام التشغيل',
      subtitle: 'إدارة العمليات والذاكرة والملفات والأمان',
      htmlFile: 'learning_content/lesson9.html',
      type: 'lesson'
    },
    {
      id: 'lesson_10',
      order: 11,
      title: 'كيف تعمل المكونات معًا؟',
      subtitle: 'مراجعة عامة وتطبيق عملي شامل',
      htmlFile: 'learning_content/lesson10.html',
      type: 'lesson'
    },
    {
      id: 'exam_2',
      order: 12,
      title: 'الاختبار التراكمي الثاني 📝',
      subtitle: '8 أسئلة - مراجعة الدروس 8 و 9 و 10',
      htmlFile: 'learning_content/exam2.html',
      type: 'exam'
    }
  ]
};

// --- إدارة التخزين وإكمال الدروس (Local Storage) ---
let handledCompletionsMap = {};

function getCompletedLessons() {
  try {
    const saved = localStorage.getItem('cs_completed_lessons');
    return saved ? JSON.parse(saved) : [];
  } catch (_) {
    return [];
  }
}

function isLessonCompleted(lessonId) {
  const completed = getCompletedLessons();
  return completed.includes(lessonId);
}

function isLessonUnlocked(item) {
  if (item.order === 1) return true; // الدرس الأول مفتوح دائماً
  const completed = getCompletedLessons();
  const prevItem = COMPUTER_BASICS_DATA.items.find(i => i.order === item.order - 1);
  return prevItem ? completed.includes(prevItem.id) : false;
}

// --- حفظ التقدم الفوري وإظهار رسالة الإكمال والتهنئة ---
function markLessonCompletedInStorage(lessonId) {
  const completed = getCompletedLessons();
  if (!completed.includes(lessonId)) {
    completed.push(lessonId);
    localStorage.setItem('cs_completed_lessons', JSON.stringify(completed));
  }

  // منع التكرار لنفس الجلسة
  if (handledCompletionsMap[lessonId]) return;
  handledCompletionsMap[lessonId] = true;

  const currentItem = COMPUTER_BASICS_DATA.items.find(i => i.id === lessonId);
  const nextItem = COMPUTER_BASICS_DATA.items.find(i => i.order === currentItem.order + 1);

  // إظهار نافذة التهنئة والإكمال المنبثقة
  showLessonCompletedDialog(currentItem, nextItem);
}

// ====================================================
// 1. نافذة التهنئة والإكمال المنبثقة (Completion Popup Dialog)
// ====================================================
function showLessonCompletedDialog(currentItem, nextItem) {
  let modalContainer = document.getElementById('lesson-completed-modal');
  if (!modalContainer) {
    modalContainer = document.createElement('div');
    modalContainer.id = 'lesson-completed-modal';
    document.body.appendChild(modalContainer);
  }

  const hasNext = !!nextItem;

  modalContainer.innerHTML = `
    <div class="modal-overlay">
      <div class="app-modal-dialog animate-modalPop" style="max-width: 440px; text-align: center;">
        
        <!-- أيقونة التهنئة -->
        <div class="modal-icon-container" style="background: rgba(34, 197, 94, 0.12); color: #22C55E; width: 84px; height: 84px;">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
        </div>

        <div class="modal-pill-badge success-badge">
          🎉 تم إكمال الدرس وحفظ التقدم
        </div>

        <h2 class="modal-title" style="color: #22C55E; font-size: 22px;">أحسنت بطل! 🚀</h2>
        
        <p class="modal-desc" style="font-size: 14px; color: var(--text-primary); margin-bottom: 24px; line-height: 1.6;">
          ${hasNext 
            ? `لقد أنهيت حل أسئلة <strong>"${currentItem.title}"</strong> بنجاح! تم حفظ تقدمك وانفتح الدرس التالي <strong>("${nextItem.title}")</strong>.` 
            : `مبروك! لقد أتممت جميع دروس واختبارات المستوى الأول بنجاح! 🏆`
          }
        </p>

        <div class="modal-actions">
          ${hasNext ? `
            <button class="modal-btn-primary" style="background: #22C55E; box-shadow: 0 8px 24px rgba(34, 197, 94, 0.3);" onclick="closeCompletedModal(); handleBasicsLessonClick('${nextItem.id}');">
              <span>الانتقال للدرس التالي 🚀</span>
            </button>
          ` : ''}

          <button class="modal-btn-secondary" onclick="closeCompletedModal(); navigateTo('computer-basics');">
            الرجوع لقائمة الدروس 📚
          </button>
        </div>

      </div>
    </div>
  `;
}

function closeCompletedModal() {
  const modalContainer = document.getElementById('lesson-completed-modal');
  if (modalContainer) modalContainer.innerHTML = '';
}

// ====================================================
// 2. رندر صفحة مستويات ودروس الكورس الرئيسي
// ====================================================
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('cb-lessons-list-container');
  if (container) {
    container.innerHTML = renderBasicsLessonsList(COMPUTER_BASICS_DATA.items);
    updateComputerBasicsHeaderStats();
  }
});

function updateComputerBasicsHeaderStats() {
  const completed = getCompletedLessons();
  const completedCount = completed.length;
  const totalCount = COMPUTER_BASICS_DATA.items.length;
  const badge = document.getElementById('cb-lessons-progress-badge');
  if (badge) {
    badge.textContent = `${completedCount} من ${totalCount} مكتمل`;
  }
  const countElem = document.getElementById('cb-lessons-progress-count');
  if (countElem) {
    countElem.textContent = `${completedCount} / ${totalCount}`;
  }
}

function renderComputerBasicsPage(container) {
  const completed = getCompletedLessons();
  const completedCount = completed.length;
  const totalCount = COMPUTER_BASICS_DATA.items.length;
  const progressPercent = Math.round((completedCount / totalCount) * 100);

  container.innerHTML = `
    <!-- الهيدر مع زر الرجوع -->
    <div class="page-header animate-fadeIn">
      <div style="display: flex; align-items: center; gap: 14px;">
        <button class="header-action-btn" onclick="navigateTo('home')" title="الرجوع للرئيسية">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <div>
          <h1 class="page-title">${COMPUTER_BASICS_DATA.title}</h1>
          <p class="page-greeting">${COMPUTER_BASICS_DATA.subtitle} • قائمة الدروس والمستويات</p>
        </div>
      </div>
    </div>

    <!-- كارت نسبة التقدم الشاملة -->
    <div class="featured-card animate-fadeIn delay-1" style="background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); margin-bottom: 28px;">
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 26px;">
            💻
          </div>
          <div>
            <h3 style="font-size: 18px; font-weight: 800; color: #FFFFFF;">نسبة تقدمك في الكورس</h3>
            <p style="font-size: 12.5px; color: rgba(255,255,255,0.85);">${completedCount} من ${totalCount} درس واختبار مكتمل</p>
          </div>
        </div>
        <div style="font-size: 22px; font-weight: 900; color: #FFFFFF;">${progressPercent}%</div>
      </div>

      <!-- شريط التقدم -->
      <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.25); border-radius: 10px; overflow: hidden;">
        <div style="width: ${progressPercent}%; height: 100%; background: #FFFFFF; transition: width 0.4s ease;"></div>
      </div>
    </div>

    <!-- قائمة المستويات -->
    <div style="display: flex; flex-direction: column; gap: 24px;" class="animate-fadeIn delay-2">
      
      <!-- المستوى الأول -->
      <div style="background: var(--bg-card); border-radius: 24px; padding: 24px; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
          <div>
            <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; background: rgba(37, 99, 235, 0.1); color: var(--primary); font-size: 12px; font-weight: 800; margin-bottom: 6px;">
              المستوى 1
            </span>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--text-primary);">أساسيات الحاسوب والمكونات ونظام التشغيل</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">محتوى تفاعلي كامل واختبارات (ينفتح الدرس التالي بعد إكمال الحالي)</p>
          </div>
          <span style="font-size: 13px; font-weight: 700; color: var(--primary);">12 درس واختبار</span>
        </div>

        <!-- قائمة الدروس مع القفل والفتح التلقائي -->
        <div style="display: flex; flex-direction: column; gap: 10px;">
          ${renderBasicsLessonsList(COMPUTER_BASICS_DATA.items)}
        </div>
      </div>

      <!-- المستوى الثاني (قريباً) -->
      <div style="background: var(--bg-card); border-radius: 24px; padding: 24px; border: 1px solid var(--border-light); opacity: 0.75;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; background: rgba(156, 163, 175, 0.15); color: var(--text-muted); font-size: 12px; font-weight: 800; margin-bottom: 6px;">
              المستوى 2 • قريباً
            </span>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--text-primary);">متقدم في الحاسوب والشبكات</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">فيديوهات وشرح عملي سيتوفر قريباً من السيرفر 🎬</p>
          </div>
          <span style="padding: 6px 14px; border-radius: 20px; background: var(--bg-hover); color: var(--text-muted); font-size: 12px; font-weight: 700;">
            ⏳ قريباً
          </span>
        </div>
      </div>

    </div>
  `;
}

// --- توليد عناصر قائمة الدروس الواضحة التفاعلية للغاية ---
function renderBasicsLessonsList(items) {
  return items.map(item => {
    const isExam = item.type === 'exam';
    const isCompleted = isLessonCompleted(item.id);
    const isUnlocked = isLessonUnlocked(item);
    
    let statusBadgeHtml = '';
    if (isCompleted) {
      statusBadgeHtml = `
        <span style="background: rgba(34, 197, 94, 0.12); color: #16A34A; border: 1px solid rgba(34, 197, 94, 0.3); font-size: 12px; font-weight: 800; padding: 6px 14px; border-radius: 12px; display: inline-flex; align-items: center; gap: 6px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
          مكتمل
        </span>`;
    } else if (isUnlocked) {
      statusBadgeHtml = `
        <span style="background: rgba(37, 99, 235, 0.12); color: #2563EB; border: 1px solid rgba(37, 99, 235, 0.25); font-size: 12px; font-weight: 800; padding: 6px 14px; border-radius: 12px; display: inline-flex; align-items: center; gap: 6px;">
          ▶️ ابدأ الدرس
        </span>`;
    } else {
      statusBadgeHtml = `
        <span style="background: rgba(100, 116, 139, 0.1); color: #64748B; border: 1px solid rgba(100, 116, 139, 0.2); font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 12px; display: inline-flex; align-items: center; gap: 6px;">
          🔒 مغلق
        </span>`;
    }

    // تجهيز أيقونة الكارت
    let iconSvg = '';
    if (isExam) {
      iconSvg = `
        <div style="width: 52px; height: 52px; border-radius: 16px; background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); display: flex; align-items: center; justify-content: center; color: #FFFFFF; font-size: 24px; box-shadow: 0 8px 18px rgba(239, 68, 68, 0.28); flex-shrink: 0;">
          📝
        </div>`;
    } else {
      iconSvg = `
        <div style="width: 52px; height: 52px; border-radius: 16px; background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); display: flex; align-items: center; justify-content: center; color: #FFFFFF; font-size: 24px; box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28); flex-shrink: 0;">
          💻
        </div>`;
    }

    if (!isUnlocked) {
      iconSvg = `
        <div style="width: 52px; height: 52px; border-radius: 16px; background: rgba(148, 163, 184, 0.2); border: 1px solid rgba(148, 163, 184, 0.3); display: flex; align-items: center; justify-content: center; color: #64748B; font-size: 22px; flex-shrink: 0;">
          🔒
        </div>`;
    }

    const cardCursor = isUnlocked ? 'pointer' : 'not-allowed';
    const activeTransform = isUnlocked ? "this.style.transform='translateY(-3px) scale(1.005)'; this.style.boxShadow='0 12px 28px rgba(37, 99, 235, 0.12)'; this.style.borderColor='rgba(37, 99, 235, 0.4)';" : "";
    const resetTransform = isUnlocked ? "this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 2px 10px rgba(0, 0, 0, 0.04)'; this.style.borderColor='var(--border)';" : "";

    return `
      <div class="cb-lesson-item animate-fadeIn"
           style="background: var(--bg-card); border: 1.5px solid var(--border); border-radius: 20px; padding: 18px 22px; display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px; cursor: ${cardCursor}; transition: all 0.25s ease; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04); ${!isUnlocked ? 'opacity: 0.68;' : ''}"
           onmouseover="${activeTransform}"
           onmouseout="${resetTransform}"
           onclick="handleBasicsLessonClick('${item.id}')">
        
        <div style="display: flex; align-items: center; gap: 18px; flex: 1;">
          ${iconSvg}

          <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
              <span style="background: rgba(37, 99, 235, 0.08); color: var(--primary); font-size: 11px; font-weight: 800; padding: 2px 10px; border-radius: 8px;">
                الدرس ${item.order}
              </span>
              <h3 style="font-size: 16.5px; font-weight: 800; color: var(--text-primary); margin: 0;">${item.title}</h3>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0; font-weight: 500;">${item.subtitle}</p>
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 14px; flex-shrink: 0;">
          ${statusBadgeHtml}
          
          <div style="width: 36px; height: 36px; border-radius: 12px; background: var(--bg-hover); display: flex; align-items: center; justify-content: center; color: var(--primary); transition: background 0.2s ease;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="15 18 9 12 15 6"/>
            </svg>
          </div>
        </div>

      </div>
    `;
  }).join('');
}

// --- التعامل مع الضغط على الدرس (تحقق من القفل) ---
function handleBasicsLessonClick(lessonId) {
  const item = COMPUTER_BASICS_DATA.items.find(i => i.id === lessonId);
  if (!item) return;

  if (!isLessonUnlocked(item)) {
    showToast('🔒 أكمل الدرس السابق أولاً لفتح هذا الدرس!', 'warning');
    return;
  }

  // فتح شاشة الدرس الكاملة (Full Page) وليس نافذة منبثقة
  const pageContainer = document.getElementById('main-page-container') || document.getElementById('main-content');
  renderLessonViewPage(pageContainer, item);
}

// ====================================================
// 3. رندر شاشة عرض الدرس المستقلة بالكامل (Full Page Viewer)
// ====================================================
function renderLessonViewPage(container, item) {
  container.innerHTML = `
    <!-- هيدر شاشة الدرس مع زر الرجوع وقسم الإكمال التلقائي -->
    <div class="page-header animate-fadeIn" style="margin-bottom: 20px;">
      <div style="display: flex; align-items: center; gap: 14px;">
        <button class="header-action-btn" onclick="navigateTo('computer-basics')" title="الرجوع للدروس">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <div>
          <h1 class="page-title" style="font-size: 20px;">${item.order}. ${item.title}</h1>
          <p class="page-greeting">${item.subtitle}</p>
        </div>
      </div>

      <div>
        <span style="font-size: 13px; font-weight: 700; color: var(--primary); padding: 6px 14px; border-radius: 20px; background: rgba(37, 99, 235, 0.1);">
          ${item.type === 'exam' ? '📝 اختبار تفاعلي' : '📖 درس قراءة'}
        </span>
      </div>
    </div>

    <!-- إطار ملف الـ HTML التفاعلي كاملاً بدون زر إنهاء يدوي -->
    <div style="width: 100%; height: calc(100vh - 180px); min-height: 650px; border-radius: 24px; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);" class="animate-fadeIn delay-1">
      <iframe id="lesson-iframe" src="${item.htmlFile}" style="width: 100%; height: 100%; border: none;" title="${item.title}"></iframe>
    </div>
  `;

  // التمرير لأعلى الصفحة عند بدء الفتح
  window.scrollTo({ top: 0, behavior: 'smooth' });

  // تتبع حل الأسئلة والإكمال التلقائي من داخل الـ iframe
  initAutomaticCompletionDetector(item.id);
}

// ====================================================
// 4. مراقب الإكمال التلقائي (الاستماع لأسئلة واختبارات الـ HTML)
// ====================================================
function initAutomaticCompletionDetector(lessonId) {
  const iframe = document.getElementById('lesson-iframe');
  if (!iframe) return;

  iframe.addEventListener('load', () => {
    try {
      const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
      if (!iframeDoc) return;

      const checkCompletion = () => {
        // 1. مراقبة ظهور النتيجة أو زر النتيجة في أسئلة الدرس (quizResult)
        const quizResult = iframeDoc.getElementById('quizResult');
        if (quizResult && (quizResult.style.display === 'block' || window.getComputedStyle(quizResult).display !== 'none')) {
          markLessonCompletedInStorage(lessonId);
          return;
        }

        // 2. مراقبة زر إنهاء قراءة الدرس الموجود داخل تصميم الـ HTML
        const completeReadingBtn = iframeDoc.getElementById('completeReadingBtn');
        if (completeReadingBtn && !completeReadingBtn.dataset.listenerAttached) {
          completeReadingBtn.dataset.listenerAttached = 'true';
          completeReadingBtn.addEventListener('click', () => {
            setTimeout(() => markLessonCompletedInStorage(lessonId), 400);
          });
        }

        // 3. مراقبة نتيجة الاختبار النهائي (resultContainer)
        const resultContainer = iframeDoc.getElementById('resultContainer');
        if (resultContainer && resultContainer.classList.contains('active')) {
          markLessonCompletedInStorage(lessonId);
          return;
        }

        // 4. استبدال زر الدرس التالي الموجود بداخل ملف الـ HTML ليظهر الـ Popup الخاص بالمنصة
        const nextBtn = iframeDoc.getElementById('nextLessonBtn');
        if (nextBtn && !nextBtn.dataset.listenerAttached) {
          nextBtn.dataset.listenerAttached = 'true';
          nextBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            markLessonCompletedInStorage(lessonId);
          });
        }
      };

      // تشغيل الفحص الدوري والمراقب (MutationObserver)
      const observer = new MutationObserver(checkCompletion);
      observer.observe(iframeDoc.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style', 'class']
      });

      // استجابة أولية
      checkCompletion();

    } catch (err) {
      console.log('Cross-origin or iframe loading info:', err);
    }
  });
}
