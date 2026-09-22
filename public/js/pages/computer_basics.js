/* ====================================================
   computer_basics.js — صفحة دروس ومستويات كورس Computer Basics
   ==================================================== */

const COMPUTER_BASICS_DATA = {
  id: 1,
  title: 'Computer Basics',
  subtitle: 'أساسيات الحاسوب',
  icon: '💻',
  description: 'رحلة شاملة لفهم أساسيات الحاسوب، من التعريف إلى المكونات المادية والبرمجية ونظام التشغيل، بأسلوب شيق ومبسط.',
  items: [
    { id: 'lesson_1', order: 1, title: 'ما هو الحاسوب؟', subtitle: 'تعريف الحاسوب ودورة عمله', htmlFile: 'learning_content/lesson1.html', type: 'lesson' },
    { id: 'lesson_2', order: 2, title: 'أنواع الحواسيب', subtitle: 'الأنواع والمميزات والأحجام', htmlFile: 'learning_content/lesson2.html', type: 'lesson' },
    { id: 'lesson_3', order: 3, title: 'المكونات الأساسية للحاسوب', subtitle: 'دورة عمل الحاسوب (IPO)', htmlFile: 'learning_content/lesson3.html', type: 'lesson' },
    { id: 'lesson_4', order: 4, title: 'المكونات المادية والبرمجيات', subtitle: 'Hardware و Software بالتفصيل', htmlFile: 'learning_content/lesson4.html', type: 'lesson' },
    { id: 'lesson_5', order: 5, title: 'البيانات داخل الحاسوب', subtitle: 'أنواع البيانات ووحدات القياس (Bit & Byte)', htmlFile: 'learning_content/lesson5.html', type: 'lesson' },
    { id: 'lesson_6', order: 6, title: 'الذاكرة والتخزين', subtitle: 'RAM و SSD و HDD والفرق بينهم', htmlFile: 'learning_content/lesson6.html', type: 'lesson' },
    { id: 'exam_final', order: 7, title: 'الاختبار التراكمي الأول 📝', subtitle: '25 سؤالاً - مراجعة الدروس من 1 إلى 6', htmlFile: 'learning_content/final-exam.html', type: 'exam' },
    { id: 'lesson_7', order: 8, title: 'كيف يعمل الحاسوب؟', subtitle: 'الأجهزة والبرمجيات والتكامل التشغيلي', htmlFile: 'learning_content/lesson7.html', type: 'lesson' },
    { id: 'lesson_8', order: 9, title: 'نظام التشغيل (Operating System)', subtitle: 'ما هو نظام التشغيل ولماذا نحتاجه؟', htmlFile: 'learning_content/lesson8.html', type: 'lesson' },
    { id: 'lesson_9', order: 10, title: 'الوظائف الأساسية لنظام التشغيل', subtitle: 'إدارة العمليات والذاكرة والملفات والأمان', htmlFile: 'learning_content/lesson9.html', type: 'lesson' },
    { id: 'lesson_10', order: 11, title: 'كيف تعمل المكونات معًا؟', subtitle: 'مراجعة عامة وتطبيق عملي شامل', htmlFile: 'learning_content/lesson10.html', type: 'lesson' },
    { id: 'exam_2', order: 12, title: 'الاختبار التراكمي الثاني 📝', subtitle: '8 أسئلة - مراجعة الدروس 8 و 9 و 10', htmlFile: 'learning_content/exam2.html', type: 'exam' }
  ]
};

document.addEventListener('DOMContentLoaded', async () => {
  await checkComputerBasicsSubscription();
  const container = document.getElementById('cb-lessons-list-container');
  if (container) {
    container.innerHTML = renderBasicsLessonsList(COMPUTER_BASICS_DATA.items);
    updateComputerBasicsHeaderStats();
  }
});

async function checkComputerBasicsSubscription() {
  const container = document.getElementById('main-page-container');
  if (!container) return;

  let targetBox = document.getElementById('cb-subscription-box');
  if (!targetBox) {
    targetBox = document.createElement('div');
    targetBox.id = 'cb-subscription-box';
    container.insertBefore(targetBox, container.firstChild);
  }

  let isSubscribed = false;
  try {
    const subRes = await ApiClient.getSubscriptionStatus(1).catch(() => null);
    if (subRes && (subRes.is_subscribed || subRes.status === 'subscribed' || subRes.subscribed)) {
      isSubscribed = true;
    }
  } catch (e) {}

  if (!isSubscribed) {
    const localSubs = localStorage.getItem('cs_subscribed_computer_basics');
    if (localSubs === 'true') isSubscribed = true;
  }

  if (isSubscribed) {
    targetBox.innerHTML = `
      <div style="background: rgba(34, 197, 94, 0.12); border: 1.5px solid rgba(34, 197, 94, 0.4); border-radius: 20px; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px;" class="animate-fadeIn">
        <div style="display: flex; align-items: center; gap: 14px;">
          <div style="font-size: 28px;">✅</div>
          <div>
            <h4 style="font-size: 16px; font-weight: 800; color: #16A34A; margin-bottom: 2px;">أنت مشترك في كورس أساسيات الحاسوب</h4>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0;">الكورس مسجل الآن في صفحة "كورساتي الحالية" ومتاح للدراسة الكاملة.</p>
          </div>
        </div>
        <a href="my-courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 10px 20px; font-size: 13px; text-decoration: none; background: #16A34A;">الذهاب لكورساتي 🚀</a>
      </div>
    `;
  } else {
    targetBox.innerHTML = `
      <div style="background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(59,130,246,0.18)); border: 1.5px solid rgba(37, 99, 235, 0.35); border-radius: 20px; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px;" class="animate-fadeIn">
        <div style="display: flex; align-items: center; gap: 14px;">
          <div style="font-size: 28px;">💡</div>
          <div>
            <h4 style="font-size: 16px; font-weight: 800; color: var(--primary); margin-bottom: 2px;">ابدأ الآن وأضف الكورس لكورساتي الحالية</h4>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0;">اضغط على زر الاشتراك لتسجيل الكورس في قائمتك ومتابعة تقدمك الدراسي.</p>
          </div>
        </div>
        <button id="cb-subscribe-action-btn" onclick="subscribeToComputerBasics()" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 24px; font-size: 14px; font-weight: 800; cursor: pointer;">
          اشترك في الكورس الآن 🚀
        </button>
      </div>
    `;
  }
}

async function subscribeToComputerBasics() {
  const btn = document.getElementById('cb-subscribe-action-btn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'جاري الاشتراك... ⏳';
  }

  try {
    await ApiClient.subscribeCourse(1).catch(() => {});
    localStorage.setItem('cs_subscribed_computer_basics', 'true');
    alert('🎉 تهانينا! تم اشتراكك في كورس أساسيات الحاسوب بنجاح وتم إضافته لصفحة كورساتي الحالية.');
    await checkComputerBasicsSubscription();
  } catch (err) {
    localStorage.setItem('cs_subscribed_computer_basics', 'true');
    alert('🎉 تهانينا! تم اشتراكك في كورس أساسيات الحاسوب بنجاح.');
    await checkComputerBasicsSubscription();
  }
}

function getCompletedLessons() {
  try {
    const saved = localStorage.getItem('cs_completed_lessons');
    return saved ? JSON.parse(saved) : [];
  } catch (_) {
    return [];
  }
}

function isLessonUnlocked(item) {
  if (item.order === 1) return true;
  const completed = getCompletedLessons();
  const prevItem = COMPUTER_BASICS_DATA.items.find(i => i.order === item.order - 1);
  return prevItem ? completed.includes(prevItem.id) : false;
}

function markLessonCompletedInStorage(lessonId) {
  const completed = getCompletedLessons();
  if (!completed.includes(lessonId)) {
    completed.push(lessonId);
    localStorage.setItem('cs_completed_lessons', JSON.stringify(completed));
  }

  try {
    ApiClient.markLessonComplete(lessonId).catch(() => {});
  } catch (e) {}

  // التحديث الفوري للقائمة وفتح الدرس التالي مباشرة
  const container = document.getElementById('cb-lessons-list-container');
  if (container) {
    container.innerHTML = renderBasicsLessonsList(COMPUTER_BASICS_DATA.items);
    updateComputerBasicsHeaderStats();
  }

  showToast('🎉 تم إكمال الدرس وحفظ التقدم وفتح الدرس التالي تلقائياً!', 'success');
}

function updateComputerBasicsHeaderStats() {
  const completed = getCompletedLessons();
  const completedCount = completed.length;
  const totalCount = COMPUTER_BASICS_DATA.items.length;
  const countElem = document.getElementById('cb-lessons-progress-count');
  if (countElem) {
    countElem.textContent = `${completedCount} / ${totalCount}`;
  }
}

function renderBasicsLessonsList(items) {
  return items.map(item => {
    const isExam = item.type === 'exam';
    const isCompleted = getCompletedLessons().includes(item.id);
    const isUnlocked = isLessonUnlocked(item);
    
    let statusBadgeHtml = '';
    if (isCompleted) {
      statusBadgeHtml = `
        <span style="background: rgba(34, 197, 94, 0.12); color: #16A34A; border: 1px solid rgba(34, 197, 94, 0.3); font-size: 12px; font-weight: 800; padding: 6px 14px; border-radius: 12px; display: inline-flex; align-items: center; gap: 6px;">
          ✓ مكتمل
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

    let iconSvg = isExam ? '📝' : '💻';
    if (!isUnlocked) iconSvg = '🔒';

    const cardCursor = isUnlocked ? 'pointer' : 'not-allowed';

    return `
      <div class="cb-lesson-item animate-fadeIn"
           style="background: var(--bg-card); border: 1.5px solid var(--border); border-radius: 20px; padding: 18px 22px; display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px; cursor: ${cardCursor}; transition: all 0.25s ease; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04); ${!isUnlocked ? 'opacity: 0.68;' : ''}"
           onclick="handleBasicsLessonClick('${item.id}')">
        
        <div style="display: flex; align-items: center; gap: 18px; flex: 1;">
          <div style="width: 52px; height: 52px; border-radius: 16px; background: ${isUnlocked ? 'linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%)' : 'rgba(148, 163, 184, 0.2)'}; display: flex; align-items: center; justify-content: center; color: #FFFFFF; font-size: 22px; flex-shrink: 0;">
            ${iconSvg}
          </div>

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
        </div>

      </div>
    `;
  }).join('');
}

function handleBasicsLessonClick(lessonId) {
  const item = COMPUTER_BASICS_DATA.items.find(i => i.id === lessonId);
  if (!item) return;

  if (!isLessonUnlocked(item)) {
    showToast('🔒 أكمل الدرس السابق أولاً لفتح هذا الدرس!', 'warning');
    return;
  }

  const pageContainer = document.getElementById('main-page-container') || document.getElementById('main-content');
  renderLessonViewPage(pageContainer, item);
}

function renderLessonViewPage(container, item) {
  container.innerHTML = `
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

    <div style="width: 100%; height: calc(100vh - 180px); min-height: 650px; border-radius: 24px; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);" class="animate-fadeIn delay-1">
      <iframe id="lesson-iframe" src="${item.htmlFile}" style="width: 100%; height: 100%; border: none;" title="${item.title}"></iframe>
    </div>
  `;

  window.scrollTo({ top: 0, behavior: 'smooth' });
  initAutomaticCompletionDetector(item.id);
}

function initAutomaticCompletionDetector(lessonId) {
  const iframe = document.getElementById('lesson-iframe');
  if (!iframe) return;

  iframe.addEventListener('load', () => {
    try {
      const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
      if (!iframeDoc) return;

      const checkCompletion = () => {
        const quizResult = iframeDoc.getElementById('quizResult');
        if (quizResult && (quizResult.style.display === 'block' || window.getComputedStyle(quizResult).display !== 'none')) {
          markLessonCompletedInStorage(lessonId);
          return;
        }

        const completeReadingBtn = iframeDoc.getElementById('completeReadingBtn');
        if (completeReadingBtn && !completeReadingBtn.dataset.listenerAttached) {
          completeReadingBtn.dataset.listenerAttached = 'true';
          completeReadingBtn.addEventListener('click', () => {
            setTimeout(() => markLessonCompletedInStorage(lessonId), 300);
          });
        }

        const resultContainer = iframeDoc.getElementById('resultContainer');
        if (resultContainer && resultContainer.classList.contains('active')) {
          markLessonCompletedInStorage(lessonId);
          return;
        }

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

      const observer = new MutationObserver(checkCompletion);
      observer.observe(iframeDoc.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style', 'class']
      });

      checkCompletion();

    } catch (err) {
      console.log('Cross-origin info:', err);
    }
  });
}
