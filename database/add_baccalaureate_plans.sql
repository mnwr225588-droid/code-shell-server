-- ============================================
-- إضافة باقات كورس منهج البرمجة ثانية بكالوريا
-- ============================================

-- باقة اشتراك شهري (30 يوم)
INSERT INTO course_plans (course_id, name, name_en, slug, description, price, currency, duration_days, duration_type, is_active, sort_order, created_at, updated_at)
VALUES (
    10,
    'اشتراك شهري (30 يوماً)',
    'Monthly Subscription (30 days)',
    'monthly',
    'اشتراك شهري مرن ومناسب للمتابعة الشهرية. يبدأ احتساب مدة الاشتراك من تاريخ نزول أول محاضرة أونلاين.',
    1.00,
    'EGP',
    30,
    'days',
    true,
    1,
    NOW(),
    NOW()
);

-- باقة اشتراك الترم الأول (ثلاثة أشهر)
INSERT INTO course_plans (course_id, name, name_en, slug, description, price, currency, duration_days, duration_type, is_active, sort_order, created_at, updated_at)
VALUES (
    10,
    'اشتراك ثلاث شهور (الترم الأول)',
    '3-Month Subscription (First Term)',
    'term_3months',
    'اشتراك متكامل يغطي الترم الأول. يبدأ احتساب مدة الاشتراك (90 يوماً) من تاريخ نزول أول محاضرة أونلاين.',
    1.00,
    'EGP',
    90,
    'days',
    true,
    2,
    NOW(),
    NOW()
);
