/* ====================================================
   home.js — الصفحة الرئيسية لداشبورد الطالب
   جلب وتحديث بيانات الجلسة واشتراكات الطالب من السيرفر
   ==================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  await loadHomeUserData();
  updateDashboardProgress();
  initDashboardChart();
});

async function loadHomeUserData() {
  try {
    const profileRes = await ApiClient.getProfile().catch(() => null);
    const user = (profileRes && profileRes.user) ? profileRes.user : ApiClient.getUser();

    if (user) {
      const name = `${user.first_name || user.name || 'طالب'}`.trim();
      const greetingEl = document.getElementById('user-welcome-name');
      if (greetingEl) greetingEl.textContent = `مرحباً بك، ${name} 👋`;
    }
  } catch (e) {
    console.error('Home user data load error:', e);
  }
}

function updateDashboardProgress() {
  const completedLessons = getCompletedLessons();
  const completedCount = completedLessons.length;
  const totalCount = 12;
  const progressPercent = Math.round((completedCount / totalCount) * 100);

  const statCompleted = document.getElementById('stat-completed-count');
  if (statCompleted) {
    statCompleted.textContent = completedCount >= 12 ? '1' : '0';
  }

  const percentText = document.getElementById('progress-percent-text');
  if (percentText) {
    percentText.textContent = `${progressPercent}%`;
  }

  const circleBg = document.getElementById('progress-circle-bg');
  if (circleBg) {
    circleBg.style.background = `conic-gradient(#0D9488 ${progressPercent * 3.6}deg, #E5E7EB 0deg)`;
  }
}

function getCompletedLessons() {
  try {
    const saved = localStorage.getItem('cs_completed_lessons');
    return saved ? JSON.parse(saved) : [];
  } catch (e) {
    return [];
  }
}

function initDashboardChart() {
  const ctx = document.getElementById('home-activity-chart');
  if (ctx && typeof Chart !== 'undefined') {
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['الأربعاء', 'الخميس', 'الجمعة', 'السبت', 'الأحد', 'الإثنين', 'الثلاثاء'],
        datasets: [
          {
            label: 'الأسبوع الحالي',
            data: [0, 0, 0, 0, 0, 0, 0],
            borderColor: '#16A34A',
            backgroundColor: 'rgba(22, 163, 74, 0.1)',
            borderWidth: 3,
            tension: 0.3,
            pointRadius: 5,
            pointBackgroundColor: '#16A34A'
          },
          {
            label: 'الأسبوع الماضي',
            data: [0, 0, 0, 0, 0, 0, 0],
            borderColor: '#2563EB',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            borderWidth: 3,
            tension: 0.3,
            pointRadius: 5,
            pointBackgroundColor: '#2563EB'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 1.0,
            ticks: { stepSize: 0.1 }
          }
        }
      }
    });
  }
}
