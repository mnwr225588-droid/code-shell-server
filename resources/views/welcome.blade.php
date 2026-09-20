<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <!-- إعدادات التجاوب مع شاشات الهواتف الذكية والأجهزة المحمولة -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Code Shell — الرئيسية" />
  <meta name="theme-color" content="#2563EB" />
  <title>Code Shell — الرئيسية</title>
  <link rel="icon" type="image/png" href="assets/images/logo.png" />
  
  <!-- استدعاء ملفات التنسيقات (CSS): المتغيرات، التنسيقات الأساسية، العناصر المشتركة، وتنسيقات الصفحات -->
  <link rel="stylesheet" href="css/variables.css?v=2" />
  <link rel="stylesheet" href="css/base.css?v=2" />
  <link rel="stylesheet" href="css/components.css?v=2" />
  <link rel="stylesheet" href="css/pages.css?v=2" />
  <link rel="stylesheet" href="css/course-modal.css" />
</head>
<body>

  <!-- خلفية مظلمة شفافة تظهر عند فتح القائمة الجانبية في الهاتف (إغلاق القائمة عند النقر عليها) -->
  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <!-- الهيكل العام للتطبيق: يشمل القائمة الجانبية (Sidebar) والمحتوى الرئيسي (Main Content) -->
  <div class="app-layout">
    
    <!-- ====================================================
         القائمة الجانبية (Sidebar Navigation)
         تتيح للتلميذ التنقل بين أقسام المنصة ومتابعة دراسته
         ==================================================== -->
    <aside class="sidebar" id="sidebar">
      
      <!-- شعار المنصة واسم التطبيق في أعلى القائمة -->
      <div class="sidebar-header">
        <img src="assets/images/logo.png" alt="Code Shell Logo" class="sidebar-logo" />
        <span class="sidebar-brand">Code Shell</span>
      </div>

      <!-- زر تصغير / طي النافذة الجانبية (لأجهزة الكمبيوتر والتابلت) -->
      <div class="sidebar-toggle" id="sidebar-toggle">
        <div class="sidebar-toggle-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
        </div>
        <span>تصغير النافذة</span>
      </div>

      <!-- روابط التصفح الأساسية في القائمة الجانبية -->
      <nav class="sidebar-nav">
        <a class="nav-item active" href="index.html">
          <span class="nav-item-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <rect x="3" y="3" width="7" height="7" rx="1.5"/>
              <rect x="14" y="3" width="7" height="7" rx="1.5"/>
              <rect x="3" y="14" width="7" height="7" rx="1.5"/>
              <rect x="14" y="14" width="7" height="7" rx="1.5"/>
            </svg>
          </span>
          <span class="nav-item-text">الرئيسية</span>
        </a>

        <a class="nav-item" href="courses.html">
          <span class="nav-item-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>
          </span>
          <span class="nav-item-text">الكورسات</span>
        </a>

        <a class="nav-item" href="my-courses.html">
          <span class="nav-item-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
          </span>
          <span class="nav-item-text">كورساتي الحالية</span>
        </a>

        <div class="sidebar-divider"></div>

        <a class="nav-item" href="community.html">
          <span class="nav-item-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
            </svg>
          </span>
          <span class="nav-item-text">المنتدى</span>
        </a>

        <a class="nav-item" href="live.html">
          <span class="nav-item-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="2"/>
              <path d="M16.24 7.76a6 6 0 0 1 0 8.49"/>
              <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
              <path d="M7.76 16.24a6 6 0 0 1 0-8.49"/>
              <path d="M4.93 19.07a10 10 0 0 1 0-14.14"/>
            </svg>
          </span>
          <span class="nav-item-text">اللايفات المباشرة</span>
        </a>

        <div class="sidebar-divider"></div>

        <a class="nav-item" href="profile.html">
          <span class="nav-item-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </span>
          <span class="nav-item-text">حسابي</span>
        </a>

        <a class="nav-item" href="settings.html">
          <span class="nav-item-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="3"/>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
          </span>
          <span class="nav-item-text">الإعدادات</span>
        </a>

        <a class="nav-item nav-item-logout" href="javascript:void(0)" onclick="logout()">
          <span class="nav-item-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </span>
          <span class="nav-item-text">تسجيل الخروج</span>
        </a>
      </nav>
    </aside>

    <!-- ====================================================
         المحتوى الرئيسي للمنصة (Main Content Container)
         ==================================================== -->
    <main class="main-content" id="main-content">
      
      <!-- ====================================================
           الهيدر العلوي الثابت (Sticky Top Navbar)
           يحتوي على زر الهامبرغر للهاتف، العنوان، البحث، الإشعارات، زر المظهر، وصورة الحساب
           ==================================================== -->
      <header class="top-navbar">
        
        <!-- الجزء الأيمن: زر فتح القائمة على للهواتف + عنوان الصفحة الحالي -->
        <div class="top-nav-right">
          <!-- زر الهامبرغر الجانبي لفتح القائمة الجانبية في شاشات الهاتف -->
          <button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="فتح القائمة">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="3" y1="6" x2="21" y2="6"/>
              <line x1="3" y1="12" x2="21" y2="12"/>
              <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
          </button>
          <!-- عنوان الصفحة الحالي -->
          <h2 class="top-nav-title">الرئيسية</h2>
        </div>

        <!-- الجزء الأوسط: حقل البحث العام في محتوى المنصة والكورسات -->
        <div class="top-nav-center">
          <div class="top-nav-search">
            <!-- أيقونة العدسة للبحث -->
            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"/>
              <path d="M21 21l-4.35-4.35"/>
            </svg>
            <!-- مربع الكتابة والبحث العام -->
            <input type="text" id="top-global-search" placeholder="إبحث هنا" />
          </div>
        </div>

        <!-- الجزء الأيسر: أزرار التفاعل (الإشعارات + المظهر + الملف الشخصي) -->
        <div class="top-nav-left">
          
          <!-- زر الإشعارات والتنبيهات المستلمة (يحتوي على نقطة تنبيه حية) -->
          <button class="top-nav-btn" id="top-notif-btn" title="الإشعارات">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <!-- الشارة الملونة لتنبيه وجود إشعارات جديدة -->
            <span class="top-notif-badge"></span>
          </button>

          <!-- زر التبديل بين الوضع الليلي (Dark Mode) والوضع النهاري (Light Mode) -->
          <button class="top-nav-btn theme-single-btn" id="top-theme-btn" onclick="toggleTheme()" title="تغيير المظهر">
            <!-- أيقونة الشمس للوضع المضيء -->
            <svg class="theme-icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="5"/>
              <line x1="12" y1="1" x2="12" y2="3"/>
              <line x1="12" y1="21" x2="12" y2="23"/>
            </svg>
            <!-- أيقونة القمر للوضع الداكن -->
            <svg class="theme-icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
          </button>

          <!-- زر الصورة الشخصية (الأفاتار) الذي ينقل التلميذ لصفحة حسابه الشخصي -->
          <a href="profile.html" class="top-nav-avatar" title="حسابي">
            <div class="avatar-circle">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
            </div>
          </a>
        </div>
      </header>

      <!-- ====================================================
           محتوى الداشبورد والصفحة الرئيسية (Main Dashboard Layout)
           يتكون من 3 كروت إحصائيات علوية + سطر سفلي يشمل التقدم والرسم البياني
           ==================================================== -->
      <div id="main-page-container">
        
        <!-- السطر العلوي: 3 كروت إحصائيات للمتعلم متجاوبة تماماً مع الهاتف -->
        <div class="dashboard-stats-grid">
          
          <!-- 1. كارت الفيديوهات المحفوظة (باللون الأزرق السماوي) -->
          <div class="dashboard-stat-card card-blue">
            <!-- خلفية مائية مخصصة لأيقونة الكارت بالخلفية -->
            <div class="stat-card-watermark">
              <svg width="110" height="110" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
              </svg>
            </div>

            <!-- النصوص والأرقام المخصصة لكارت الفيديوهات المحفوظة -->
            <div class="stat-card-content">
              <div class="stat-card-val">0</div>
              <h3 class="stat-card-title">فيديوهات محفوظة</h3>
              <p class="stat-card-desc">المحاضرات اللي حفظتها علشان ترجع لها بسرعة.</p>
            </div>

            <!-- أيقونة إشارة الحفظ الخاصة بالكارت -->
            <div class="stat-card-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2z"/>
                <path d="M19 5v14l2 1V7c0-1.1-.9-2-2-2h-1v0z" opacity="0.6"/>
              </svg>
            </div>
          </div>

          <!-- 2. كارت الكورسات الحالية (باللون الأخضر) -->
          <div class="dashboard-stat-card card-green">
            <!-- خلفية مائية خفيفة لأيقونة اللمبة في الخلفية -->
            <div class="stat-card-watermark">
              <svg width="120" height="120" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2a7 7 0 0 0-7 7c0 2.38 1.19 4.47 3 5.74V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 0 0-7-7zM9 21a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-1H9v1z"/>
              </svg>
            </div>

            <!-- النصوص والأرقام المخصصة لكارت الكورسات الجارية -->
            <div class="stat-card-content">
              <div class="stat-card-val">1</div>
              <h3 class="stat-card-title">كورسات حالية</h3>
              <p class="stat-card-desc">الكورسات اللي بدأت فيها ولسه مكملتش محتواها.</p>
            </div>

            <!-- أيقونة اللمبة المضيئة المخصصة لتعني الفهم والاستمرار -->
            <div class="stat-card-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 18h6"/>
                <path d="M10 22h4"/>
                <path d="M15 14c.8-1 2-1.8 2-3a5 5 0 0 0-10 0c0 1.2 1.2 2 2 3l1 1h4l1-1z"/>
              </svg>
            </div>
          </div>

          <!-- 3. كارت الكورسات المكتملة (باللون الأزرق الملكي) -->
          <div class="dashboard-stat-card card-brand-blue">
            <!-- أيقونة علامة الصح المائية الشفافة في الخلفية -->
            <div class="stat-card-watermark">
              <svg width="110" height="110" viewBox="0 0 24 24" fill="currentColor">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
              </svg>
            </div>

            <!-- النصوص والأرقام المخصصة لكارت الإنجاز المكتمل -->
            <div class="stat-card-content">
              <div id="stat-completed-count" class="stat-card-val">0</div>
              <h3 class="stat-card-title">كورسات مكتملة</h3>
              <p class="stat-card-desc">الكورسات اللي خلصت كل محتواها الدراسي.</p>
            </div>

            <!-- أيقونة المربع الأزرق ذو علامة الصح للإنجاز -->
            <div class="stat-card-icon">
              <div class="check-box-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </div>
            </div>
          </div>

        </div>

        <!-- السطر السكندري: كارت دائرة التقدم الدراسي (يمين) + الرسم البياني لنشاط التلميذ (يسار) -->
        <div class="dashboard-bottom-grid">
          
          <!-- 1. كارت مقياس ومؤشر التقدم الدراسي -->
          <div class="dashboard-progress-card">
            
            <!-- الدائرة التفاعلية المخصصة لنسبة الإنجاز المئوية -->
            <div class="progress-circle-outer" id="progress-circle-bg">
              <div class="progress-circle-inner" id="progress-percent-text">
                0%
              </div>
            </div>

            <!-- عنوان ووصف كارت التقدم الدراسي -->
            <h4 class="progress-title">تقدمك</h4>
            <p class="progress-desc">
              مقياس لكمية الدروس السابقة والمتبقية في كورساتك الحالية.
            </p>

            <!-- زر التوجيه لمتابعة الدراسة "أكمل مذاكرة" (يأخذ عرض الشاشة بالكامل في الهاتف) -->
            <a href="computer-basics.html" class="btn btn-primary btn-block progress-btn">
              أكمل مذاكرة
            </a>

            <!-- رابط الكورس الأخير النشط -->
            <a href="computer-basics.html" class="progress-link">
              Computer Basics - المستوى الأول
            </a>
          </div>

          <!-- 2. كارت الرسم البياني لنشاط التلميذ الأسبوعي (Chart.js) -->
          <div class="dashboard-chart-card">
            
            <!-- هيدر كارت الرسم البياني: العنوان والرموز التوضيحية (الأسبوع الحالي والماضي) -->
            <div class="chart-card-header">
              <h2 class="chart-title">نشاطك التعليمي</h2>
              
              <!-- الرموز والألوان للتفريق بين الأسبوع الحالي والماضي -->
              <div class="chart-legend">
                <span class="legend-item current-week">
                  <span class="dot"></span>
                  الأسبوع الحالي
                </span>
                <span class="legend-item last-week">
                  <span class="dot"></span>
                  الأسبوع الماضي
                </span>
              </div>
            </div>

            <!-- حاوية الرسم البياني التفاعلي من خلال مكتبة Chart.js -->
            <div class="chart-container">
              <canvas id="home-activity-chart"></canvas>
            </div>

            <!-- ملاحظة توضيحية أسفل الرسم البياني -->
            <p class="chart-note">
              *ابداً اول كورس علشان نقدر نعرضلك بيانات نشاطك التعليمية بشكل دقيق!
            </p>
          </div>

        </div>

      </div>
    </main>
  </div>

  <!-- استدعاء مكتبة Chart.js للرسومات البيانية التفاعلية + ملفات الـ JavaScript للمنصة -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="js/app.js"></script>
  <script src="js/api.js"></script>
  <script src="js/pages/home.js"></script>
</body>
</html>
