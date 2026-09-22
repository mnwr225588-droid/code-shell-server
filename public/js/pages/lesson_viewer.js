// js/pages/lesson_viewer.js

let currentLessonData = null;
let quizQuestions = [];
let quizUserAnswers = {}; // { questionIndex: optionId }
let currentQuestionIndex = 0;

document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const courseId = urlParams.get('courseId');
  const levelIdx = urlParams.get('levelIdx');
  const lessonIdx = urlParams.get('lessonIdx');
  const container = document.getElementById('course-viewer-container');

  if (!courseId || levelIdx === null || lessonIdx === null) {
    container.innerHTML = `<div style="text-align:center; padding: 40px; color: red;">بيانات الدرس غير مكتملة.</div>`;
    return;
  }

  try {
    const levelsRes = await ApiClient.getCourseLevels(courseId);
    const levels = Array.isArray(levelsRes) ? levelsRes : (levelsRes.levels || levelsRes.data || []);
    let level = levels[parseInt(levelIdx, 10)];
    
    if (!level && levels.length > 0) {
      level = levels.find(l => String(l.id) === String(levelIdx) || String(l.order_num) === String(levelIdx)) || levels[0];
    }

    if (!level) {
      container.innerHTML = `<div style="text-align:center; padding: 40px;">المستوى غير موجود.</div>`;
      return;
    }

    let lessons = level.lessons || [];
    if (level.id && (!lessons || lessons.length === 0)) {
      const directRes = await ApiClient.getLevelLessons(level.id).catch(() => []);
      lessons = Array.isArray(directRes) ? directRes : (directRes.data || []);
    }

    let lesson = lessons[parseInt(lessonIdx, 10)];
    if (!lesson && lessons.length > 0) {
      lesson = lessons.find(l => String(l.id) === String(lessonIdx) || String(l.order_num) === String(lessonIdx)) || lessons[0];
    }

    if (!lesson) {
      container.innerHTML = `<div style="text-align:center; padding: 40px;">الدرس غير موجود.</div>`;
      return;
    }

    currentLessonData = lesson;
    quizQuestions = lesson.questions || [];

    renderLessonPlayer(lesson, level, courseId, levelIdx);

  } catch (err) {
    console.error('Error loading lesson:', err);
    container.innerHTML = `<div style="text-align:center; padding: 40px; color: red;">حدث خطأ أثناء تحميل الدرس.</div>`;
  }
});

function renderLessonPlayer(lesson, level, courseId, levelIdx) {
  const container = document.getElementById('course-viewer-container');
  const lessonTitle = lesson.title || lesson.name || `الدرس ${parseInt(lessonIdx, 10) + 1}`;
  const levelNumStr = `المستوى ${(parseInt(levelIdx, 10) + 1).toString().padStart(2, '0')}`;
  
  const videoUrl = lesson.video_url_full || lesson.video_url || 'https://www.w3schools.com/html/mov_bbb.mp4';
  const posterUrl = lesson.thumbnail_url || lesson.thumbnail || 'assets/images/placeholder.jpg';
  const duration = lesson.duration || '10 دقائق';

  container.innerHTML = `
    <!-- رأس الصفحة مع زر العودة -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
      <div>
        <h1 style="font-size: 24px; font-weight: 900; color: var(--text-primary); margin-bottom: 8px;">${lessonTitle}</h1>
        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(59,130,246,0.1); color: #3B82F6; padding: 4px 12px; border-radius: 8px; font-size: 13px; font-weight: 700;">
          ${levelNumStr}
        </div>
      </div>
      <button onclick="window.history.back()" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 20px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 8px;" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='var(--bg-card)'">
        <i class="fas fa-arrow-right"></i>
        <span>رجوع للدروس</span>
      </button>
    </div>

    <!-- حاوية مشغل الفيديو الاحترافي — الحجم متناسق ومصغر قبل ملء الشاشة -->
    <div style="max-width: 880px; margin: 0 auto 28px; background: #000; border-radius: 24px; overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.15); position: relative;" id="video-player-main-box">
      
      <!-- حاوية أبعاد 16:9 شاشات العرض المزدوجة -->
      <div style="position: relative; width: 100%; aspect-ratio: 16/9; max-height: 70vh;">
        <video id="plyr-video" playsinline controls poster="${posterUrl}" data-poster="${posterUrl}" style="width: 100%; height: 100%; object-fit: contain;">
          <source src="${videoUrl}" type="video/mp4" size="1080" />
          <source src="${videoUrl}" type="video/mp4" size="720" />
          <source src="${videoUrl}" type="video/mp4" size="480" />
          <source src="${videoUrl}" type="video/mp4" size="360" />
        </video>
      </div>
      <!-- العلامة المائية المتحركة بإيميل الطالب -->
      <div id="lesson-video-watermark" style="position: absolute; top: 10%; left: 10%; color: rgba(255, 40, 40, 0.35); font-size: 15px; font-weight: 800; font-family: 'Cairo', monospace; pointer-events: none; z-index: 10; user-select: none; text-shadow: 0 0 4px rgba(0,0,0,0.3); transition: all 2.8s cubic-bezier(0.4, 0, 0.2, 1); white-space: nowrap; letter-spacing: 0.5px;"></div>
    </div>

    <!-- تفاصيل الدرس السفلية مع زر الاختبار المتوقف لحين إكمال الفيديو -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); max-width: 880px; margin: 0 auto;">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
        <div style="flex: 1; min-width: 280px;">
          <h2 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 12px;">عن هذا الدرس</h2>
          <p style="font-size: 15px; color: var(--text-secondary); line-height: 1.6; max-width: 800px; margin-bottom: 20px;">
            ${lesson.description || 'لا يوجد وصف مضاف لهذا الدرس حالياً. يمكنك مشاهدة الفيديو مباشرة ثم حل الاختبار لإنهاء الدرس.'}
          </p>
          
          <!-- زر الاختبار المغلق لحين مشاهدة الفيديو بالكامل -->
          <button id="quiz-btn" disabled onclick="startLessonQuiz()" style="background: #64748B; opacity: 0.65; color: #FFF; border: none; padding: 14px 28px; border-radius: 14px; font-weight: 800; font-size: 15px; cursor: not-allowed; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s;" onmouseover="if(!this.disabled) this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <i class="fas fa-lock" id="quiz-btn-icon"></i>
            <span id="quiz-btn-text">بدء الاختبار (شاهد الفيديو كاملاً لتفعيله)</span>
          </button>
        </div>
        
        <div style="display: flex; gap: 12px;">
          <div style="display: flex; flex-direction: column; align-items: center; background: var(--bg-body); padding: 12px 20px; border-radius: 14px; border: 1px solid var(--border-color);">
            <i class="far fa-clock" style="font-size: 20px; color: #3B82F6; margin-bottom: 6px;"></i>
            <span style="font-size: 13px; font-weight: 700; color: var(--text-primary);" id="lesson-real-duration-label">${duration}</span>
          </div>
        </div>
      </div>
    </div>
  `;

  // تهيئة مشغل Plyr بدقة وحسب خيارات الإعدادات (الجودة + السرعة)
  setTimeout(() => {
    const playerEl = document.getElementById('plyr-video');
    
    // حساب مدة الفيديو الفعلية فور تحميل البيانات
    if (playerEl) {
      playerEl.addEventListener('loadedmetadata', () => {
        if (playerEl.duration && !isNaN(playerEl.duration)) {
          const m = Math.floor(playerEl.duration / 60);
          const s = Math.floor(playerEl.duration % 60);
          const formatted = `${m}:${s < 10 ? '0' : ''}${s}`;
          const label = document.getElementById('lesson-real-duration-label');
          if (label) label.textContent = formatted;
        }
      });
    }

    if (window.mainPlyrPlayer && typeof window.mainPlyrPlayer.destroy === 'function') {
      window.mainPlyrPlayer.destroy();
    }

    window.mainPlyrPlayer = new Plyr('#plyr-video', {
      controls: [
        'play-large', 'play', 'progress', 'current-time', 'duration',
        'mute', 'volume', 'captions', 'settings', 'airplay', 'fullscreen'
      ],
      settings: ['quality', 'speed', 'loop'],
      quality: {
        default: 1080,
        options: [1080, 720, 480, 360],
        forced: true
      },
      speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2] },
      i18n: {
        restart: 'إعادة',
        rewind: 'ترجيع {seektime}ث',
        play: 'تشغيل',
        pause: 'إيقاف',
        fastForward: 'تقديم {seektime}ث',
        seek: 'بحث',
        seekLabel: '{currentTime} من {duration}',
        played: 'مشغل',
        buffered: 'محمل',
        currentTime: 'الوقت الحالي',
        duration: 'المدة',
        volume: 'الصوت',
        mute: 'كتم الصوت',
        unmute: 'إلغاء الكتم',
        enableCaptions: 'تفعيل الترجمة',
        disableCaptions: 'إلغاء الترجمة',
        download: 'تحميل',
        enterFullscreen: 'ملء الشاشة',
        exitFullscreen: 'الخروج من ملء الشاشة',
        frameTitle: 'مشغل لـ {title}',
        captions: 'الترجمة',
        settings: 'الإعدادات ⚙️',
        speed: 'السرعة ⚡',
        normal: 'طبيعي (1x)',
        quality: 'الجودة 🎬',
        loop: 'تكرار 🔁'
      }
    });

    const unlockQuizBtn = () => {
      const quizBtn = document.getElementById('quiz-btn');
      const quizBtnIcon = document.getElementById('quiz-btn-icon');
      const quizBtnText = document.getElementById('quiz-btn-text');
      if (quizBtn) {
        quizBtn.disabled = false;
        quizBtn.style.background = 'linear-gradient(135deg, #2563EB, #1D4ED8)';
        quizBtn.style.opacity = '1';
        quizBtn.style.cursor = 'pointer';
        quizBtn.style.boxShadow = '0 6px 20px rgba(37,99,235,0.35)';
        if (quizBtnIcon) quizBtnIcon.className = 'fas fa-file-signature';
        if (quizBtnText) quizBtnText.textContent = 'بدء الاختبار الآن 📝';
      }
    };

    // إتاحة الزر فورا إذا كان الطالب قد أتم الدرس سابقاً
    if (lesson.is_completed) {
      unlockQuizBtn();
    }

    // تفعيل زر الاختبار عند انتهاء مشاهدة الفيديو بالكامل
    if (window.mainPlyrPlayer) {
      window.mainPlyrPlayer.on('ended', unlockQuizBtn);
      window.mainPlyrPlayer.on('timeupdate', () => {
        if (window.mainPlyrPlayer.duration > 0 && window.mainPlyrPlayer.currentTime >= window.mainPlyrPlayer.duration - 1) {
          unlockQuizBtn();
        }
      });
    }

    if (!document.getElementById('plyr-custom-style')) {
      const style = document.createElement('style');
      style.id = 'plyr-custom-style';
      style.innerHTML = `
        :root {
          --plyr-color-main: #2563EB;
          --plyr-video-background: #000;
          --plyr-video-control-background-hover: rgba(37,99,235,1);
        }
        .plyr--video {
          border-radius: 0px;
        }
        .plyr__video-wrapper {
          background-color: #000;
        }
        .plyr__video-wrapper video {
          object-fit: contain !important;
        }
      `;
      document.head.appendChild(style);
    }

    // تفعيل العلامة المائية المتحركة بإيميل الطالب
    initLessonWatermark();

  }, 100);
}

// بدء الاختبار عند الضغط على زر الاختبار بعد إكمال الفيديو
function startLessonQuiz() {
  if (!currentLessonData) return;

  // إذا لم تتواجد أسئلة مضافة لهذا الدرس من الأدمن، يتم إكمال الدرس فوراً
  if (!quizQuestions || quizQuestions.length === 0) {
    markCurrentLessonCompleted(currentLessonData.id);
    return;
  }

  // إعادة تهيئة الإجابات السابقة للمستخدم
  quizUserAnswers = {};
  currentQuestionIndex = 0;

  // إنشاء النافذة المنبثقة للاختبار
  openQuizModal();
}

// فتح نافذة الاختبار التفاعلية
function openQuizModal() {
  let modalOverlay = document.getElementById('quiz-modal-overlay');
  if (!modalOverlay) {
    modalOverlay = document.createElement('div');
    modalOverlay.id = 'quiz-modal-overlay';
    modalOverlay.style.cssText = `
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px);
      z-index: 99999; display: flex; align-items: center; justify-content: center;
      padding: 20px; animation: fadeIn 0.3s ease;
    `;
    document.body.appendChild(modalOverlay);
  }

  renderQuizQuestionStep();
}

// عرض السؤال الحالي دون إظهار أي تلميح بالإجابة الصحيحة أو الخاطئة حتى يتم التسليم
function renderQuizQuestionStep() {
  const modalOverlay = document.getElementById('quiz-modal-overlay');
  if (!modalOverlay) return;

  const total = quizQuestions.length;
  const question = quizQuestions[currentQuestionIndex];
  const options = question.options || [];

  const progressPercent = Math.round(((currentQuestionIndex + 1) / total) * 100);
  const selectedOptId = quizUserAnswers[currentQuestionIndex];

  modalOverlay.innerHTML = `
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 24px; width: 100%; max-width: 680px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden; display: flex; flex-direction: column;">
      
      <!-- رأس نافذة الاختبار -->
      <div style="padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(59,130,246,0.03);">
        <div>
          <span style="font-size: 13px; font-weight: 700; color: #3B82F6; text-transform: uppercase;">اختبار الدرس</span>
          <h3 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin-top: 2px;">${currentLessonData.title || 'الاختبار التقييمي'}</h3>
        </div>
        <button onclick="closeQuizModal()" style="background: transparent; border: none; color: var(--text-secondary); font-size: 20px; cursor: pointer; padding: 4px 8px;" title="إغلاق">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- شريط التقدم بالسؤال -->
      <div style="width: 100%; height: 6px; background: var(--bg-body);">
        <div style="width: ${progressPercent}%; height: 100%; background: linear-gradient(90deg, #3B82F6, #1D4ED8); transition: width 0.3s ease;"></div>
      </div>

      <!-- جسم نافذة الاختبار (نص السؤال والخيارات) -->
      <div style="padding: 28px 24px; flex: 1;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <span style="font-size: 13px; font-weight: 800; color: var(--text-secondary); background: var(--bg-body); padding: 4px 12px; border-radius: 8px;">
            السؤال ${currentQuestionIndex + 1} من ${total}
          </span>
        </div>

        <h4 style="font-size: 18px; font-weight: 800; color: var(--text-primary); line-height: 1.5; margin-bottom: 24px;">
          ${question.question_text}
        </h4>

        <!-- خيارات الإجابة دون أي إشارة للصحة أو الخطأ -->
        <div style="display: flex; flex-direction: column; gap: 12px;">
          ${options.map((opt, idx) => {
            const isSelected = selectedOptId === opt.id;
            const letter = String.fromCharCode(65 + idx); // A, B, C, D
            return `
              <div onclick="selectQuizOption(${opt.id})" style="padding: 14px 18px; border-radius: 14px; border: 2px solid ${isSelected ? '#3B82F6' : 'var(--border-color)'}; background: ${isSelected ? 'rgba(59,130,246,0.1)' : 'var(--bg-body)'}; color: var(--text-primary); font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s;" onmouseover="if(!${isSelected}) this.style.borderColor='#3B82F6'" onmouseout="if(!${isSelected}) this.style.borderColor='var(--border-color)'">
                <div style="display: flex; align-items: center; gap: 12px;">
                  <span style="width: 28px; height: 28px; border-radius: 8px; background: ${isSelected ? '#3B82F6' : 'var(--border-color)'}; color: ${isSelected ? '#FFF' : 'var(--text-secondary)'}; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 900;">${letter}</span>
                  <span>${opt.option_text}</span>
                </div>
                <div style="width: 20px; height: 20px; border-radius: 50%; border: 2px solid ${isSelected ? '#3B82F6' : 'var(--border-color)'}; display: flex; align-items: center; justify-content: center; background: ${isSelected ? '#3B82F6' : 'transparent'};">
                  ${isSelected ? '<i class="fas fa-check" style="font-size: 10px; color: #FFF;"></i>' : ''}
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>

      <!-- أزرار الانتقال بين الأسئلة -->
      <div style="padding: 20px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.02);">
        <button onclick="prevQuizQuestion()" ${currentQuestionIndex === 0 ? 'disabled' : ''} style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 20px; border-radius: 12px; font-weight: 700; cursor: ${currentQuestionIndex === 0 ? 'not-allowed' : 'pointer'}; opacity: ${currentQuestionIndex === 0 ? '0.5' : '1'};">
          السابق
        </button>

        ${currentQuestionIndex === total - 1 ? `
          <button onclick="submitQuiz()" style="background: linear-gradient(135deg, #16A34A, #15803D); color: #FFF; border: none; padding: 12px 28px; border-radius: 12px; font-weight: 800; font-size: 15px; cursor: pointer; box-shadow: 0 4px 15px rgba(22,163,74,0.3);">
            تسليم الاختبار 🎯
          </button>
        ` : `
          <button onclick="nextQuizQuestion()" style="background: #3B82F6; color: #FFF; border: none; padding: 10px 24px; border-radius: 12px; font-weight: 700; cursor: pointer;">
            التالي <i class="fas fa-arrow-left" style="margin-right: 6px;"></i>
          </button>
        `}
      </div>

    </div>
  `;
}

// اختيار الإجابة وحفظها بالسياق المحلي مؤقتا دون كشف النتيجة
function selectQuizOption(optionId) {
  quizUserAnswers[currentQuestionIndex] = optionId;
  renderQuizQuestionStep();
}

// السؤال التالي
function nextQuizQuestion() {
  if (currentQuestionIndex < quizQuestions.length - 1) {
    currentQuestionIndex++;
    renderQuizQuestionStep();
  }
}

// السؤال السابق
function prevQuizQuestion() {
  if (currentQuestionIndex > 0) {
    currentQuestionIndex--;
    renderQuizQuestionStep();
  }
}

// تسليم الاختبار وحساب النتيجة وحفظ التقدم بالسيرفر
async function submitQuiz() {
  let score = 0;
  const total = quizQuestions.length;

  quizQuestions.forEach((q, idx) => {
    const userOptId = quizUserAnswers[idx];
    const correctOpt = (q.options || []).find(opt => opt.is_correct == 1 || opt.is_correct === true || opt.is_correct === '1');
    if (correctOpt && userOptId === correctOpt.id) {
      score++;
    }
  });

  const percentage = Math.round((score / total) * 100);

  // حفظ إكمال الدرس بالسيرفر
  try {
    if (currentLessonData && currentLessonData.id) {
      await ApiClient.markLessonComplete(currentLessonData.id).catch(() => null);
    }
  } catch (e) {}

  // إظهار واجهة النتيجة النهائية المكتملة
  showQuizResults(score, total, percentage);
}

// عرض النتيجة التقييمية ومراجعة الإجابات الصحيحة والخاطئة
function showQuizResults(score, total, percentage) {
  const modalOverlay = document.getElementById('quiz-modal-overlay');
  if (!modalOverlay) return;

  const passed = percentage >= 50;

  modalOverlay.innerHTML = `
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 24px; width: 100%; max-width: 680px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;">
      
      <!-- رأس النتيجة -->
      <div style="padding: 24px; text-align: center; background: ${passed ? 'rgba(34,197,94,0.1)' : 'rgba(239,68,68,0.1)'}; border-bottom: 1px solid var(--border-color);">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: ${passed ? '#22C55E' : '#EF4444'}; color: #FFF; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 12px; box-shadow: 0 10px 25px ${passed ? 'rgba(34,197,94,0.4)' : 'rgba(239,68,68,0.4)'};">
          <i class="fas ${passed ? 'fa-trophy' : 'fa-redo'}"></i>
        </div>
        <h3 style="font-size: 22px; font-weight: 900; color: var(--text-primary); margin-bottom: 4px;">
          ${passed ? 'تهانينا! لقد اجتزت الاختبار بنجاح 🎉' : 'حاول مرة أخرى لتحسين درجتك 💪'}
        </h3>
        <p style="font-size: 15px; color: var(--text-secondary);">
          حصلت على <strong>${score}</strong> من أصل <strong>${total}</strong> أشكال درجات (${percentage}%)
        </p>
      </div>

      <!-- تفاصيل الإجابات ومراجعتها -->
      <div style="padding: 24px; flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 16px;">
        <h4 style="font-size: 16px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">مراجعة الأسئلة والإجابات الصحيحة:</h4>

        ${quizQuestions.map((q, idx) => {
          const userOptId = quizUserAnswers[idx];
          const correctOpt = (q.options || []).find(opt => opt.is_correct == 1 || opt.is_correct === true || opt.is_correct === '1');
          const userOpt = (q.options || []).find(opt => opt.id === userOptId);
          const isUserCorrect = correctOpt && userOptId === correctOpt.id;

          return `
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 16px;">
              <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
                <span style="background: ${isUserCorrect ? '#22C55E' : '#EF4444'}; color: #FFF; width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; flex-shrink: 0; margin-top: 2px;">
                  <i class="fas ${isUserCorrect ? 'fa-check' : 'fa-times'}"></i>
                </span>
                <span style="font-size: 15px; font-weight: 800; color: var(--text-primary);">${idx + 1}. ${q.question_text}</span>
              </div>

              <div style="font-size: 13.5px; line-height: 1.5; color: var(--text-secondary); margin-right: 32px;">
                <div>إجابتك: <strong style="color: ${isUserCorrect ? '#22C55E' : '#EF4444'}">${userOpt ? userOpt.option_text : 'لم يتم الاختيار'}</strong></div>
                ${!isUserCorrect && correctOpt ? `<div style="color: #22C55E; margin-top: 4px;">الإجابة الصحيحة: <strong>${correctOpt.option_text}</strong></div>` : ''}
              </div>
            </div>
          `;
        }).join('')}
      </div>

      <!-- أزرار الإجراءات السفلية -->
      <div style="padding: 20px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.02);">
        <button onclick="startLessonQuiz()" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 20px; border-radius: 12px; font-weight: 700; cursor: pointer;">
          إعادة الاختبار 🔄
        </button>

        <button onclick="closeQuizModal()" style="background: #2563EB; color: #FFF; border: none; padding: 12px 28px; border-radius: 12px; font-weight: 800; font-size: 15px; cursor: pointer; box-shadow: 0 4px 15px rgba(37,99,235,0.3);">
          إغلاق ومتابعة الدروس ✨
        </button>
      </div>

    </div>
  `;
}

// إغلاق النافذة المنبثقة للاختبار
function closeQuizModal() {
  const modalOverlay = document.getElementById('quiz-modal-overlay');
  if (modalOverlay) {
    modalOverlay.remove();
  }
}

// دالة تسجيل إكمال الدرس وحفظ التقدم بالسيرفر
async function markCurrentLessonCompleted(lessonId) {
  try {
    if (lessonId) {
      await ApiClient.markLessonComplete(lessonId).catch(() => null);
    }
    alert('🎉 أمتياز! تم إكمال مشاهدة الدرس وحفظ تقدمك في السيرفر بنجاح!');
  } catch (err) {
    alert(err.message || 'تعذر حفظ التقدم حالياً');
  }
}

// ====================================================
// العلامة المائية المتحركة (Moving Email Watermark)
// ====================================================
let lessonWatermarkInterval = null;

function initLessonWatermark() {
  // إيقاف أي علامة مائية سابقة
  if (lessonWatermarkInterval) {
    clearInterval(lessonWatermarkInterval);
    lessonWatermarkInterval = null;
  }

  const watermarkEl = document.getElementById('lesson-video-watermark');
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

  // جلب إيميل الطالب من بيانات الجلسة
  const user = ApiClient.getUser();
  const email = user ? (user.email || user.name || 'student') : 'student';
  watermarkEl.textContent = email;

  // تحريك العلامة المائية بشكل عشوائي كل 3 ثوانٍ
  function moveWatermark() {
    const wrapper = document.getElementById('video-player-main-box');
    if (!wrapper || !watermarkEl) return;

    const maxTop = 75;
    const maxLeft = 65;
    const randomTop = Math.floor(Math.random() * maxTop) + 5;
    const randomLeft = Math.floor(Math.random() * maxLeft) + 5;

    watermarkEl.style.top = randomTop + '%';
    watermarkEl.style.left = randomLeft + '%';
  }

  moveWatermark();
  lessonWatermarkInterval = setInterval(moveWatermark, 3000);

  // إظهار العلامة المائية فقط عند تشغيل الفيديو (play)، وإخفاؤها عند الإيقاف (pause/ended)، ودعم وضع ملء الشاشة (fullscreen)
  setTimeout(() => {
    const plyrContainer = document.querySelector('.plyr');
    if (plyrContainer && watermarkEl.parentElement !== plyrContainer) {
      plyrContainer.appendChild(watermarkEl);
    }

    if (window.mainPlyrPlayer) {
      window.mainPlyrPlayer.on('play', () => {
        watermarkEl.style.display = 'block';
      });
      window.mainPlyrPlayer.on('pause', () => {
        watermarkEl.style.display = 'none';
      });
      window.mainPlyrPlayer.on('ended', () => {
        watermarkEl.style.display = 'none';
      });
    }

    const videoEl = document.getElementById('plyr-video');
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

window.startLessonQuiz = startLessonQuiz;
window.selectQuizOption = selectQuizOption;
window.nextQuizQuestion = nextQuizQuestion;
window.prevQuizQuestion = prevQuizQuestion;
window.submitQuiz = submitQuiz;
window.closeQuizModal = closeQuizModal;
window.markCurrentLessonCompleted = markCurrentLessonCompleted;
window.initLessonWatermark = initLessonWatermark;
