/* ====================================================
   api.js — محرك الاتصال الآمن بالسيرفر (API Client)
   يتولى الاتصال بقاعدة البيانات والـ Controllers لـ Laravel:
   - حماية مفاتيح التوكن وتشفير طلبات الترويسات (Bearer Auth Token)
   - دعم التبديل التلقائي بين سيرفر الإنتاج وسيرفر التطوير المحلي
   - إدارة كامل الخدمات: التسجيل، الدخول، الكورسات، المستويات، الدروس، المجموعات، اللايفات، ونظام المدرس
   ==================================================== */

const API_CONFIG = {
  // سيرفر الإنتاج وسيرفر التطوير المحلي
  PROD_URL: 'https://code-shell-server-production.up.railway.app/api',
  LOCAL_URL: 'http://localhost:8000/api',
  
  // مفاتيح التخزين الآمن في المتصفح
  TOKEN_KEY: 'cs_token',
  USER_KEY: 'cs_user'
};

// تحديد الرابط الأساسي تلقائياً بناءً على البيئة
let currentBaseUrl = API_CONFIG.PROD_URL;

class ApiClient {
  static getBaseUrl() {
    return currentBaseUrl;
  }

  static setBaseUrl(url) {
    currentBaseUrl = url;
  }

  static getToken() {
    return localStorage.getItem(API_CONFIG.TOKEN_KEY);
  }

  static getUser() {
    const raw = localStorage.getItem(API_CONFIG.USER_KEY);
    try {
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  static setSession(token, user) {
    if (token) localStorage.setItem(API_CONFIG.TOKEN_KEY, token);
    if (user) localStorage.setItem(API_CONFIG.USER_KEY, JSON.stringify(user));
  }

  static clearSession() {
    localStorage.removeItem(API_CONFIG.TOKEN_KEY);
    localStorage.removeItem(API_CONFIG.USER_KEY);
  }

  static getHeaders(customHeaders = {}) {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...customHeaders
    };

    const token = this.getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    return headers;
  }

  static async request(endpoint, options = {}) {
    const url = `${currentBaseUrl}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;
    const headers = this.getHeaders(options.headers || {});

    const config = {
      method: options.method || 'GET',
      headers,
      ...options
    };

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      config.body = JSON.stringify(options.body);
    }

    try {
      let response;
      try {
        response = await fetch(url, config);
      } catch (fetchErr) {
        // إذا حدث خطأ في الاتصال أو CORS، حاول استخدام السيرفر المحلي تلقائياً
        if (currentBaseUrl === API_CONFIG.PROD_URL) {
          console.warn('Network exception on PROD URL. Trying LOCAL URL fallback...');
          const localUrl = `${API_CONFIG.LOCAL_URL}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;
          try {
            response = await fetch(localUrl, config);
            if (response.ok) {
              currentBaseUrl = API_CONFIG.LOCAL_URL;
            }
          } catch (localErr) {
            throw new Error('تعذر الاتصال بالسيرفر. يرجى التأكد من اتصال النت أو إعادة المحاولة.');
          }
        } else {
          throw new Error('تعذر الاتصال بالسيرفر المحلي.');
        }
      }

      // إذا رجع السيرفر استجابة غير ناجحة في 500 وكان إنتاج، جرب السيرفر المحلي
      if (!response.ok && currentBaseUrl === API_CONFIG.PROD_URL && response.status >= 500) {
        console.warn('Attempting local fallback API endpoint...');
        const localUrl = `${API_CONFIG.LOCAL_URL}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;
        try {
          const fallbackRes = await fetch(localUrl, config);
          if (fallbackRes.ok) {
            response = fallbackRes;
            currentBaseUrl = API_CONFIG.LOCAL_URL;
          }
        } catch (e) {}
      }

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        const errorMsg = data.message || data.error || `خطأ في الاتصال (${response.status})`;
        throw new Error(errorMsg);
      }

      return data;
    } catch (error) {
      console.error('[API Error]:', error.message);
      throw error;
    }
  }

  // ====================================================
  // 1. المصادقة والـ Auth (تسجيل الدخول وإنشاء الحساب)
  // ====================================================
  static async login(email, password) {
    const res = await this.request('/login', {
      method: 'POST',
      body: { email, password }
    });
    if (res.token && res.user) {
      this.setSession(res.token, res.user);
    }
    return res;
  }

  static async register(userData) {
    // userData يحتوي على الحقول: first_name, middle_name, last_name, birth_date, email, phone, country, password, password_confirmation
    const res = await this.request('/register', {
      method: 'POST',
      body: userData
    });
    if (res.token && res.user) {
      this.setSession(res.token, res.user);
    }
    return res;
  }

  // طلب إرسال رابط استعادة كلمة المرور عبر البريد الإلكتروني (POST /forgot-password)
  static async forgotPassword(email) {
    return await this.request('/forgot-password', {
      method: 'POST',
      body: { email }
    });
  }

  // تغيير كلمة المرور الحالية بكلمة مرور جديدة من داخل الإعدادات (POST /change-password)
  static async changePassword(currentPassword, newPassword, newPasswordConfirmation) {
    return await this.request('/change-password', {
      method: 'POST',
      body: {
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: newPasswordConfirmation
      }
    });
  }

  // إعادة إرسال رسالة/رابط تأكيد البريد الإلكتروني للمستخدم الحقيقي (POST /resend-verification)
  static async resendVerification() {
    return await this.request('/resend-verification', {
      method: 'POST'
    });
  }

  static async getProfile() {
    return await this.request('/profile');
  }

  static async logout() {
    try {
      await this.request('/logout', { method: 'POST' });
    } catch (e) {
      // Ignore logout request error
    } finally {
      this.clearSession();
    }
  }

  // ====================================================
  // 2. الكورسات والمستويات والدروس (Courses, Levels, Lessons)
  // ====================================================
  static async getCourses() {
    return await this.request('/courses');
  }

  static async getCourseDetails(courseId) {
    return await this.request(`/courses/${courseId}`);
  }

  static async getCourseLevels(courseId) {
    return await this.request(`/levels/${courseId}`);
  }

  static async getLevelLessons(levelId) {
    return await this.request(`/levels/${levelId}/lessons`);
  }

  // ====================================================
  // 3. المجموعات والاشتراكات (Groups & Subscriptions)
  // ====================================================
  static async getCourseGroups(courseId) {
    return await this.request(`/courses/${courseId}/groups`);
  }

  static async getSubscriptionStatus(courseId) {
    return await this.request(`/courses/${courseId}/subscription-status`);
  }

  static async subscribeCourse(courseId, groupId = null) {
    return await this.request(`/courses/${courseId}/subscribe`, {
      method: 'POST',
      body: { group_id: groupId }
    });
  }

  // حجز الكورس قبل الانطلاق
  static async reserveCourse(courseId, groupId = null) {
    return await this.request(`/courses/${courseId}/reserve`, {
      method: 'POST',
      body: { group_id: groupId }
    });
  }

  // إلغاء الاشتراك في الكورس مع تقديم كلمة مرور الحساب للأمان والتحقق
  static async cancelSubscription(courseId, password = null) {
    const body = {};
    if (password) body.password = password;
    return await this.request(`/courses/${courseId}/cancel`, {
      method: 'POST',
      body
    });
  }

  static async initiatePayment(courseId) {
    return await this.request(`/courses/${courseId}/pay`, {
      method: 'POST'
    });
  }

  // ====================================================
  // 4. المحاضرات المباشرة والـ Zoom (Online Lectures)
  // ====================================================
  static async joinOnlineLecture(lectureId) {
    return await this.request(`/online-lectures/${lectureId}/join`);
  }

  static async getMyLectures() {
    return await this.request('/my-lectures');
  }

  static async getCourseOnlineLectures(courseId) {
    return await this.request(`/courses/${courseId}/online-lectures`);
  }

  // ====================================================
  // 5. نظام المدرس (Teacher System)
  // ====================================================
  static async getTeacherGroups() {
    return await this.request('/teacher/my-groups');
  }

  static async getTeacherGroupStudents(groupId) {
    return await this.request(`/teacher/groups/${groupId}/students`);
  }

  static async getTeacherGroupLectures(groupId) {
    return await this.request(`/teacher/groups/${groupId}/lectures`);
  }

  static async getTeacherSessions() {
    return await this.request('/teacher/my-sessions');
  }

  static async teacherStartBreak(groupId, durationMinutes) {
    return await this.request('/teacher/break', {
      method: 'POST',
      body: { group_id: groupId, duration_minutes: durationMinutes }
    });
  }

  static async teacherRequestPostponement(lectureId, reason, newDate) {
    return await this.request('/teacher/postpone', {
      method: 'POST',
      body: { lecture_id: lectureId, reason, new_date: newDate }
    });
  }

  // ====================================================
  // 6. التقدم الدراسي وإكتمال الدروس (Progress)
  // ====================================================
  static async getProgress() {
    return await this.request('/progress');
  }

  static async markLessonComplete(lessonId) {
    return await this.request(`/progress/lesson/${lessonId}/complete`, {
      method: 'POST'
    });
  }
}

window.ApiClient = ApiClient;
