/* ====================================================
   community.js — المنطق التفاعلي لمنتدى مجتمع Code Shell
   يتيح:
   - فلترة الأسئلة حسب المجال والتصنيف
   - التبديل بين التبويبات (الأحدث، الأنشط، لم يُجب، تم الحل)
   - إضافة منشور وسؤال جديد مع الكود والتسجيل
   - التفاعل بالإعجابات (Likes) واختيار الإجابة المقبولة (Accepted Solution)
   - التصفح ونسخ الكود
   ==================================================== */

// --- البيانات التجريبية الغنية للمنتدى (Mock Forum Data) ---
const ForumData = {
  categories: [
    { id: 'all', name: 'الكل', icon: '🌐', count: 124 },
    { id: 'python', name: 'Python', icon: '🐍', count: 32 },
    { id: 'web', name: 'تطوير المواقع', icon: '💻', count: 45 },
    { id: 'flutter', name: 'Flutter & Dart', icon: '📱', count: 28 },
    { id: 'java', name: 'Java', icon: '☕', count: 15 },
    { id: 'javascript', name: 'JavaScript', icon: '⚡', count: 38 },
    { id: 'basics', name: 'أساسيات البرمجة', icon: '🎓', count: 50 },
    { id: 'help', name: 'مساعدة برمجية', icon: '🆘', count: 62 },
  ],
  discussions: [
    {
      id: 1,
      title: 'كيف يمكن التعامل مع APIs في Flutter باستخدام مكتبة Dio والتعامل مع الأخطاء؟',
      excerpt: 'أواجه مشكلة في التعامل مع الاستجابات المتأخرة والـ Interceptors عند إجراء طلبات POST، هل هناك مثال عملي موصى به؟',
      content: `مرحباً بمجتمع Code Shell 👋\n\nأحاول ربط تطبيق الموبايل بسيرفر الباك إند باستخدام Dio في Flutter، ولدي كود يشبه التالي ولكن يحصل استثناء عند تأخر الشبكة:\n\n\`\`\`dart\nfinal dio = Dio();\nvoid fetchData() async {\n  final response = await dio.get('https://api.codeshell.com/courses');\n  print(response.data);\n}\n\`\`\`\n\nما هي أفضل الممارسات لإضافة Timeout وإظهار رسالة خطأ مناسبة للمستخدم؟`,
      author: { name: 'أحمد محمود', avatar: 'assets/images/logo.png', role: 'student', badge: 'طالب' },
      category: 'flutter',
      categoryName: 'Flutter & Dart',
      tags: ['Flutter', 'Dart', 'API', 'Dio'],
      createdAt: 'منذ ساعتين',
      repliesCount: 8,
      viewsCount: 186,
      likesCount: 24,
      isLiked: false,
      isPinned: true,
      isSolved: true,
      solvedReplyId: 101,
      replies: [
        {
          id: 101,
          author: { name: 'م. محمد علي', avatar: 'assets/images/logo.png', role: 'instructor', badge: 'محاضر' },
          createdAt: 'منذ ساعة واحدة',
          content: `أهلاً بك يا أحمد! أفضل طريقة هي استخدام \`BaseOptions\` لتحديد المهلة الزمانية واستخدام \`Interceptors\` للتعامل مع الأخطاء بشكل مركزي:\n\n\`\`\`dart\nfinal dio = Dio(BaseOptions(\n  connectTimeout: Duration(seconds: 5),\n  receiveTimeout: Duration(seconds: 5),\n));\n\ndio.interceptors.add(InterceptorsWrapper(\n  onError: (DioException e, handler) {\n    print('حدث خطأ في الشبكة: \${e.message}');\n    return handler.next(e);\n  }\n));\n\`\`\`\n\nبهذه الطريقة يمكنك التحكم في كافة أخطاء الشبكة بسهولة!`,
          likes: 12,
          isLiked: false,
          isSolution: true
        },
        {
          id: 102,
          author: { name: 'سارة خالد', avatar: 'assets/images/logo.png', role: 'student', badge: 'طالبة' },
          createdAt: 'منذ 45 دقيقة',
          content: 'شكراً م. محمد! الحل يعمل بشكل ممتاز وقمت بتجربته في مشروعي الآن.',
          likes: 3,
          isLiked: false,
          isSolution: false
        }
      ]
    },
    {
      id: 2,
      title: 'ما الفرق الحقيقي بين List و Tuple في لغة Python ومتى أستخدم كلاً منهما؟',
      excerpt: 'أعلم أن القوائم قابلة للتعديل والـ Tuples غير قابلة للتعديل، ولكن هل هناك فرق في الأداء أو الذاكرة؟',
      content: `السلام عليكم جميعاً،\nأنا في بداية تعلم لغة بايثون، وأتساءل دائماً لماذا نستخدم Tuples طالما أن الـ Lists توفر كل شيء تقريباً؟ هل هناك فائدة فعلية للذاكرة؟`,
      author: { name: 'عمر شريف', avatar: 'assets/images/logo.png', role: 'student', badge: 'طالب' },
      category: 'python',
      categoryName: 'Python',
      tags: ['Python', 'Data Structures', 'Basics'],
      createdAt: 'منذ 5 ساعات',
      repliesCount: 14,
      viewsCount: 310,
      likesCount: 38,
      isLiked: true,
      isPinned: false,
      isSolved: true,
      solvedReplyId: 201,
      replies: [
        {
          id: 201,
          author: { name: 'م. إبراهيم كمال', avatar: 'assets/images/logo.png', role: 'moderator', badge: 'مشرف' },
          createdAt: 'منذ 3 ساعات',
          content: `وعليكم السلام يا عمر.\nنعم هناك فرق جوهري:\n1. **الذاكرة**: الـ Tuple تستهلك حجم ذاكرة أقل لأن حجمها ثابت.\n2. **الأداء**: عمليات التكرار والقراءة من الـ Tuple أسرع قليلاً.\n3. **الأمان**: نستخدم الـ Tuple للبيانات التي لا ينبغي لأحد تعديلها أثناء تشغيل البرنامج مثل الإحداثيات أو مفاتيح الإعدادات.`,
          likes: 19,
          isLiked: true,
          isSolution: true
        }
      ]
    },
    {
      id: 3,
      title: 'كيف أقوم بضبط محاذاة العناصر في CSS Grid لجعل الموقع responsive تماماً؟',
      excerpt: 'عند تكبير وتصغير الشاشة تتداخل الكروت بشكل غير متناسق، ما هو كود repeat(auto-fit, minmax) الصحيح؟',
      content: 'أواجه مشكلة في جعل شبكة الكروت تتجاوب بسلاسة في الشاشات الصغرى دون أن تخرج العناصر خارج الإطار.',
      author: { name: 'نور الدين', avatar: 'assets/images/logo.png', role: 'student', badge: 'طالب' },
      category: 'web',
      categoryName: 'تطوير المواقع',
      tags: ['CSS', 'Grid', 'Responsive', 'Web'],
      createdAt: 'منذ يوم واحد',
      repliesCount: 5,
      viewsCount: 142,
      likesCount: 15,
      isLiked: false,
      isPinned: false,
      isSolved: false,
      replies: []
    }
  ]
};

// --- الحالة الحالية لصفحة المنتدى ---
let currentCategory = 'all';
let currentTab = 'latest';
let activeDiscussionId = null;

// ====================================================
// تهيئة صفحة المنتدى التفاعلية
// ====================================================
function initCommunityPage() {
  if (typeof AppState !== 'undefined') {
    AppState.currentPage = 'community';
  }
  renderForumApp();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCommunityPage);
}
// تشغيل الدالة فوراً دائماً كضمان
setTimeout(initCommunityPage, 50);
setTimeout(initCommunityPage, 300);

function renderForumApp() {
  const container = document.getElementById('forum-root-container') || document.getElementById('main-page-container');
  if (!container) return;

  container.innerHTML = `
    <div style="background: var(--bg-card); border-radius: 28px; padding: 60px 32px; text-align: center; border: 1px solid var(--border-color); max-width: 680px; margin: 40px auto; box-shadow: 0 20px 60px rgba(0,0,0,0.06);" class="animate-fadeIn">
      <div style="width: 90px; height: 90px; border-radius: 30px; background: rgba(239, 68, 68, 0.15); color: #EF4444; display: flex; align-items: center; justify-content: center; font-size: 44px; margin: 0 auto 24px; box-shadow: 0 10px 30px rgba(239, 68, 68, 0.2);">
        🔒
      </div>
      <h2 style="font-size: 24px; font-weight: 900; color: var(--text-primary); margin-bottom: 12px;">منتدى المجتمع مغلق حالياً</h2>
      <p style="font-size: 15px; color: var(--text-secondary); line-height: 1.7; margin-bottom: 32px; max-width: 520px; margin-left: auto; margin-right: auto;">
        تم إيقاف قسم المنتدى والمناقشات البرمجية مؤقتاً من قبل إدارة المنصة. يمكنك الاستمرار في متابعة دروسك والكورسات المتاحة.
      </p>
      <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
        <a href="index.html" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 28px; text-decoration: none; border-radius: 14px; background: linear-gradient(135deg, #2563EB, #1D4ED8);">الرئيسية</a>
        <a href="courses.html" class="auth-btn" style="display: inline-block; width: auto; padding: 12px 28px; text-decoration: none; border-radius: 14px; background: var(--bg-body); color: var(--text-primary); border: 1px solid var(--border-color);">الكورسات</a>
      </div>
    </div>
  `;
}
        <div class="forum-sidebar-card">
          <div class="forum-sidebar-title">تصفح المنتدى</div>
          <div class="forum-menu-list">
            <a class="forum-menu-item active" onclick="switchMainView('discussions')">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
              <span>جميع المناقشات</span>
            </a>
            <a class="forum-menu-item" onclick="switchMainView('my-questions')">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
              <span>أسئلتي ومشاركاتي</span>
            </a>
            <a class="forum-menu-item" onclick="switchMainView('saved')">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
              </svg>
              <span>المواضيع المحفوظة</span>
            </a>
          </div>
        </div>

        <!-- كارت إحصائيات المجتمع -->
        <div class="forum-sidebar-card">
          <div class="forum-sidebar-title">إحصائيات المجتمع</div>
          <div class="community-stats-grid">
            <div class="stat-box">
              <div class="stat-box-val">1,240</div>
              <div class="stat-box-lbl">عضو</div>
            </div>
            <div class="stat-box">
              <div class="stat-box-val">450</div>
              <div class="stat-box-lbl">سؤال</div>
            </div>
            <div class="stat-box">
              <div class="stat-box-val">380</div>
              <div class="stat-box-lbl">إجابة</div>
            </div>
          </div>
        </div>
      </aside>

      <!-- الجزء الأوسط: قائمة المناقشات والتفاصيل -->
      <main id="forum-content-body">
        ${renderDiscussionsView()}
      </main>

    </div>
  `;
}

// --- عرض قائمة المناقشات والتبويبات ---
function renderDiscussionsView() {
  let list = ForumData.discussions;

  // الفلترة بالقسم
  if (currentCategory !== 'all') {
    list = list.filter(d => d.category === currentCategory);
  }

  // الفلترة حسب التبويب (الأحدث / الأكثر تفاعلاً / تم الحل)
  if (currentTab === 'solved') {
    list = list.filter(d => d.isSolved);
  } else if (currentTab === 'unanswered') {
    list = list.filter(d => d.repliesCount === 0);
  } else if (currentTab === 'active') {
    list = [...list].sort((a, b) => b.repliesCount - a.repliesCount);
  }

  return `
    <!-- تبويبات الفلترة -->
    <div class="forum-tabs-header">
      <div class="forum-tabs">
        <button class="forum-tab-btn ${currentTab === 'latest' ? 'active' : ''}" onclick="selectTab('latest')">الأحدث</button>
        <button class="forum-tab-btn ${currentTab === 'active' ? 'active' : ''}" onclick="selectTab('active')">الأنشط</button>
        <button class="forum-tab-btn ${currentTab === 'unanswered' ? 'active' : ''}" onclick="selectTab('unanswered')">لم يُجب عليه</button>
        <button class="forum-tab-btn ${currentTab === 'solved' ? 'active' : ''}" onclick="selectTab('solved')">تم الحل ✅</button>
      </div>
      <div style="font-size: 13px; color: var(--text-muted); font-weight: 700;">
        عرض ${list.length} موضوع
      </div>
    </div>

    <!-- قائمة كروت الأسئلة -->
    <div class="discussions-list">
      ${list.length > 0 ? list.map(item => `
        <div class="discussion-card ${item.isPinned ? 'pinned' : ''} ${item.isSolved ? 'solved' : ''}">
          <div class="discussion-header">
            <div class="author-meta">
              <img src="${item.author.avatar}" alt="${item.author.name}" class="author-avatar" />
              <div class="author-info">
                <div class="author-name-row">
                  <span class="author-name">${item.author.name}</span>
                  <span class="user-badge badge-${item.author.role}">${item.author.badge}</span>
                </div>
                <span class="discussion-time">${item.createdAt} • في ${item.categoryName}</span>
              </div>
            </div>
            ${item.isSolved ? `<div class="solved-indicator">✓ تم الحل</div>` : ''}
          </div>

          <a class="discussion-title" onclick="showDiscussionDetail(${item.id})">${item.title}</a>
          <p class="discussion-excerpt">${item.excerpt}</p>

          <div class="discussion-footer">
            <div class="discussion-tags">
              ${item.tags.map(t => `<span class="tag-pill">#${t}</span>`).join('')}
            </div>
            <div class="discussion-stats">
              <span class="stat-item">💬 ${item.repliesCount} ردود</span>
              <span class="stat-item">👁️ ${item.viewsCount} مشاهدة</span>
              <span class="stat-item ${item.isLiked ? 'liked' : ''}" onclick="toggleLikeDiscussion(${item.id})">❤️ ${item.likesCount}</span>
            </div>
          </div>
        </div>
      `).join('') : `
        <div style="text-align: center; padding: 48px; background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border);">
          <div style="font-size: 40px; margin-bottom: 12px;">🔍</div>
          <h3 style="font-size: 18px; color: var(--text-primary); margin-bottom: 6px;">لا توجد مناقشات في هذا القسم حالياً</h3>
          <p style="font-size: 13px; color: var(--text-muted);">كن أول من يطرح سؤالاً أو يفتح نقاشاً جديداً!</p>
        </div>
      `}
    </div>
  `;
}

// --- تغيير القسم المختاري ---
function selectCategory(catId) {
  currentCategory = catId;
  renderForumApp();
}

// --- تغيير التبويب ---
function selectTab(tab) {
  currentTab = tab;
  const contentBody = document.getElementById('forum-content-body');
  if (contentBody) {
    contentBody.innerHTML = renderDiscussionsView();
  }
}

// --- البحث الفوري في المنتدى ---
function handleForumSearch(query) {
  const q = query.trim().toLowerCase();
  const cards = document.querySelectorAll('.discussion-card');
  cards.forEach(card => {
    const text = card.textContent.toLowerCase();
    card.style.display = text.includes(q) ? 'block' : 'none';
  });
}

// --- الإعجاب بموضوع ---
function toggleLikeDiscussion(id) {
  const item = ForumData.discussions.find(d => d.id === id);
  if (item) {
    item.isLiked = !item.isLiked;
    item.likesCount += item.isLiked ? 1 : -1;
    const contentBody = document.getElementById('forum-content-body');
    if (contentBody) {
      contentBody.innerHTML = renderDiscussionsView();
    }
  }
}

// --- عرض تفاصيل موضوع معين والسؤال ---
function showDiscussionDetail(id) {
  const item = ForumData.discussions.find(d => d.id === id);
  if (!item) return;

  activeDiscussionId = id;
  const contentBody = document.getElementById('forum-content-body');
  if (!contentBody) return;

  contentBody.innerHTML = `
    <div style="margin-bottom: 16px;">
      <button onclick="renderForumApp()" class="btn btn-outline" style="padding: 8px 16px; border-radius: 12px; font-size: 13px;">
        ← العودة لجميع الأسئلة
      </button>
    </div>

    <!-- كارت التفاصيل الرئيسية -->
    <div class="discussion-detail-card">
      <div class="discussion-header">
        <div class="author-meta">
          <img src="${item.author.avatar}" alt="${item.author.name}" class="author-avatar" />
          <div class="author-info">
            <div class="author-name-row">
              <span class="author-name">${item.author.name}</span>
              <span class="user-badge badge-${item.author.role}">${item.author.badge}</span>
            </div>
            <span class="discussion-time">${item.createdAt} • قسم ${item.categoryName}</span>
          </div>
        </div>
        ${item.isSolved ? `<div class="solved-indicator">✓ تم الحل</div>` : ''}
      </div>

      <h1 style="font-size: 22px; font-weight: 800; color: var(--text-primary); margin: 16px 0 12px; line-height: 1.4;">${item.title}</h1>
      
      <div style="font-size: 14.5px; color: var(--text-primary); line-height: 1.7; white-space: pre-line; margin-bottom: 20px;">
        ${formatCodeBlocks(item.content)}
      </div>

      <div class="discussion-footer">
        <div class="discussion-tags">
          ${item.tags.map(t => `<span class="tag-pill">#${t}</span>`).join('')}
        </div>
        <div class="discussion-stats">
          <button onclick="toggleLikeDiscussion(${item.id})" class="btn" style="background: var(--primary-light); color: var(--primary); padding: 6px 14px; border-radius: 10px; font-size: 13px;">
            ❤️ إعجاب (${item.likesCount})
          </button>
        </div>
      </div>
    </div>

    <!-- قسم الردود -->
    <div style="margin-bottom: 20px;">
      <h3 style="font-size: 18px; font-weight: 800; color: var(--text-primary);">الردود والإجابات (${item.replies.length})</h3>
    </div>

    ${item.replies.map(reply => `
      <div class="reply-card ${reply.isSolution ? 'is-solution' : ''}">
        ${reply.isSolution ? `<div class="solution-banner">⭐ إجابة معتمدة حلت المشكلة</div>` : ''}
        <div class="discussion-header">
          <div class="author-meta">
            <img src="${reply.author.avatar}" alt="${reply.author.name}" class="author-avatar" />
            <div class="author-info">
              <div class="author-name-row">
                <span class="author-name">${reply.author.name}</span>
                <span class="user-badge badge-${reply.author.role}">${reply.author.badge}</span>
              </div>
              <span class="discussion-time">${reply.createdAt}</span>
            </div>
          </div>
        </div>

        <div style="font-size: 14px; color: var(--text-primary); line-height: 1.6; margin: 12px 0;">
          ${formatCodeBlocks(reply.content)}
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12.5px; color: var(--text-muted);">
          <span>❤️ ${reply.likes} إعجاب</span>
          ${!reply.isSolution ? `<button onclick="markAsSolution(${item.id}, ${reply.id})" style="background: none; border: none; color: #10B981; font-weight: 800; cursor: pointer;">تحديد كإجابة معتمدة ✓</button>` : ''}
        </div>
      </div>
    `).join('')}

    <!-- نموذج إضافة رد جديد -->
    <div class="create-post-card" style="margin-top: 24px;">
      <h4 style="font-size: 16px; font-weight: 800; color: var(--text-primary); margin-bottom: 12px;">أضف إجابتك أو ردك</h4>
      <textarea id="reply-text-input" class="form-textarea" rows="4" placeholder="اكتب إجابتك هنا بوضوح ويمكنك إضافة أكواد برمجية..."></textarea>
      <div style="text-align: left; margin-top: 12px;">
        <button onclick="submitReply(${item.id})" class="btn btn-primary" style="padding: 10px 24px; border-radius: 12px;">
          نشر الرد
        </button>
      </div>
    </div>
  `;
}

// --- تنسيق الأكواد البرمجية مع زر النسخ (Syntax Highlighting UI) ---
function formatCodeBlocks(text) {
  return text.replace(/```(\w+)?\n([\s\S]*?)```/g, (match, lang, code) => {
    const languageName = lang || 'code';
    return `
      <div class="code-block-container">
        <div class="code-header">
          <span>${languageName.toUpperCase()}</span>
          <button class="btn-copy-code" onclick="navigator.clipboard.writeText(\`${code.replace(/`/g, '\\`')}\`); alert('تم نسخ الكود!');">نسخ الكود 📋</button>
        </div>
        <pre><code>${escapeHtml(code)}</code></pre>
      </div>
    `;
  });
}

function escapeHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// --- تحديد الرد كإجابة حل مقبولة ---
function markAsSolution(discId, replyId) {
  const item = ForumData.discussions.find(d => d.id === discId);
  if (item) {
    item.isSolved = true;
    item.replies.forEach(r => r.isSolution = (r.id === replyId));
    showDiscussionDetail(discId);
  }
}

// --- إضافة رد جديد ---
function submitReply(discId) {
  const textarea = document.getElementById('reply-text-input');
  if (!textarea || !textarea.value.trim()) return;

  const item = ForumData.discussions.find(d => d.id === discId);
  if (item) {
    item.replies.push({
      id: Date.now(),
      author: { name: 'أنت (التلميذ)', avatar: 'assets/images/logo.png', role: 'student', badge: 'طالب' },
      createdAt: 'الآن',
      content: textarea.value.trim(),
      likes: 0,
      isLiked: false,
      isSolution: false
    });
    item.repliesCount++;
    showDiscussionDetail(discId);
  }
}

// --- عرض واجهة إضافة منشور جديد ---
function showCreatePostView() {
  const contentBody = document.getElementById('forum-content-body');
  if (!contentBody) return;

  contentBody.innerHTML = `
    <div style="margin-bottom: 16px;">
      <button onclick="renderForumApp()" class="btn btn-outline" style="padding: 8px 16px; border-radius: 12px; font-size: 13px;">
        ← العودة للمنتدى
      </button>
    </div>

    <div class="create-post-card">
      <h2 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 20px;">إطرح سؤالاً أو افتح موضوعاً جديداً ✍️</h2>

      <div class="form-group">
        <label class="form-label">عنوان السؤال أو المناقشة</label>
        <input type="text" id="post-title-input" class="form-input" placeholder="اكتب عنواناً واضحاً ومختصراً لسؤالك..." />
      </div>

      <div class="form-group">
        <label class="form-label">اختر القسم</label>
        <select id="post-category-select" class="form-select">
          ${ForumData.categories.filter(c => c.id !== 'all').map(c => `<option value="${c.id}">${c.name}</option>`).join('')}
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">تفاصيل الموضوع أو السؤال (مع الأكواد إن وجدت)</label>
        <textarea id="post-content-input" class="form-textarea" rows="8" placeholder="اشرح مشكلتك بالتفصيل. لإضافة كود استخدم: &#10;\`\`\`python &#10;print('Hello World') &#10;\`\`\`"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">التاجات (فواصل بمسافة)</label>
        <input type="text" id="post-tags-input" class="form-input" placeholder="مثال: Python Flutter API" />
      </div>

      <div style="text-align: left; margin-top: 24px;">
        <button onclick="publishNewPost()" class="btn btn-primary" style="padding: 12px 32px; border-radius: 14px; font-weight: 800;">
          نشر السؤال في المنتدى
        </button>
      </div>
    </div>
  `;
}

// --- نشر سؤال جديد ---
function publishNewPost() {
  const title = document.getElementById('post-title-input').value.trim();
  const category = document.getElementById('post-category-select').value;
  const content = document.getElementById('post-content-input').value.trim();
  const tagsStr = document.getElementById('post-tags-input').value.trim();

  if (!title || !content) {
    alert('يرجى ملء عنوان السؤال والتفاصيل كاملاً!');
    return;
  }

  const catObj = ForumData.categories.find(c => c.id === category);

  const newPost = {
    id: Date.now(),
    title: title,
    excerpt: content.substring(0, 100) + '...',
    content: content,
    author: { name: 'أنت (التلميذ)', avatar: 'assets/images/logo.png', role: 'student', badge: 'طالب' },
    category: category,
    categoryName: catObj ? catObj.name : 'عام',
    tags: tagsStr ? tagsStr.split(' ') : ['عام'],
    createdAt: 'الآن',
    repliesCount: 0,
    viewsCount: 1,
    likesCount: 0,
    isLiked: false,
    isPinned: false,
    isSolved: false,
    replies: []
  };

  ForumData.discussions.unshift(newPost);
  alert('تم نشر سؤالك في المنتدى بنجاح!');
  renderForumApp();
}

function switchMainView(view) {
  if (view === 'discussions') {
    renderForumApp();
  } else {
    alert('هذه الميزة متاحة في حسابك!');
  }
}
