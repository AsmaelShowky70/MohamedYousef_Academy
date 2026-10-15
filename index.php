<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
$currentUser = getCurrentUser();
$db = getDB();

// Fetch Educational Years
$yearsStmt = $db->query("SELECT * FROM Year ORDER BY `order` ASC");
$years = $yearsStmt->fetchAll();

// Fetch Published Courses
$coursesStmt = $db->query("
    SELECT c.*, s.title as sectionTitle, y.name as yearName
    FROM Course c
    LEFT JOIN Section s ON c.sectionId = s.id
    LEFT JOIN Year y ON s.yearId = y.id
    WHERE c.published = 1
    ORDER BY c.`order` ASC, c.createdAt DESC
");
$courses = $coursesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes"/>
    <!-- Anti-flicker script to apply saved theme instantly -->
    <script>
    (function() {
        try {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        } catch(e) {}
    })();
    </script>
    <title>منصة الأستاذ محمد يوسف - مادة اللغة العربية للثانوية العامة والأزهرية</title>
    <link rel="icon" type="image/png" href="public/identity/logo.png"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/app.css"/>
    
    <style>
      :root {
        --PPBackgroundColor: #ffffff;
        --PPrimaryColor: #0f766e;
        --PButtonColor: #c79a32;
        --PTextColor: #111a14;
        --PCardColor: #d6e2f1;
        --PThirdColor: #0d3753;
      }
      html.dark, .dark {
        --PPBackgroundColor: #0a0f1d;
        --PPrimaryColor: #0f766e;
        --PButtonColor: #c79a32;
        --PTextColor: #f3f6f4;
        --PCardColor: #12283a;
        --PThirdColor: #0d3753;
      }
      body {
        font-family: 'Tajawal', 'Cairo', sans-serif;
        background-color: var(--PPBackgroundColor);
        color: var(--PTextColor);
        transition: background-color 0.2s ease, color 0.2s ease;
      }
      html.dark body {
        background-color: #0a0f1d !important;
        color: #f1f5f9 !important;
      }
      .bg-PPrimaryColor { background-color: #0f766e !important; }
      .bg-PButtonColor { background-color: #c79a32 !important; }
      .bg-PCardColor { background-color: var(--PCardColor) !important; }
      .bg-PThirdColor { background-color: #0d3753 !important; }
      .text-PPrimaryColor { color: #0f766e !important; }
      .text-PButtonColor { color: #c79a32 !important; }
      .text-PThirdColor { color: #0d3753 !important; }
      .border-PPrimaryColor { border-color: #0f766e !important; }
      .border-PButtonColor { border-color: #c79a32 !important; }
      [data-sal] {
        opacity: 1 !important;
        transform: none !important;
        visibility: visible !important;
      }

      /* ========================================================
         HERO SECTION: LIGHT & DARK MODE CONTRAST PERFECTION
         ======================================================== */
      #hero {
        background-image: url('public/heroPhotos/ebg.png');
        background-size: cover;
        background-position: center top;
        background-repeat: no-repeat;
        background-color: #f4f7fa;
        padding-top: 175px !important;
      }
      @media (min-width: 1024px) {
        #hero {
          padding-top: 200px !important;
        }
      }
      html.dark #hero, .dark #hero {
        background-image: linear-gradient(rgba(10, 15, 29, 0.90), rgba(10, 15, 29, 0.95)), url('public/heroPhotos/ebg.png') !important;
        background-color: #0a0f1d !important;
      }

      /* Hero Text: Light Mode */
      .hero-subtitle { color: #334155 !important; }
      .hero-title { color: #0d3753 !important; }
      .hero-desc { color: #0f172a !important; }
      .hero-badge-item { color: #0f172a !important; }
      .hero-badge-check { background-color: #ccfbf1 !important; color: #0f766e !important; }

      /* Hero Text: Dark Mode */
      html.dark .hero-subtitle, .dark .hero-subtitle { color: #94a3b8 !important; }
      html.dark .hero-title, .dark .hero-title { color: #ffffff !important; }
      html.dark .hero-desc, .dark .hero-desc { color: #f1f5f9 !important; }
      html.dark .hero-badge-item, .dark .hero-badge-item { color: #e2e8f0 !important; }
      html.dark .hero-badge-check, .dark .hero-badge-check { background-color: rgba(15, 118, 110, 0.35) !important; color: #2dd4bf !important; }

      /* Teacher Floating Badge */
      #teacher-hero-badge {
        background-color: #ffffff !important;
        border: 1px solid rgba(15, 118, 110, 0.2) !important;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15) !important;
      }
      #teacher-hero-badge .teacher-name { color: #0d3753 !important; }
      #teacher-hero-badge .teacher-title { color: #c79a32 !important; }

      html.dark #teacher-hero-badge, .dark #teacher-hero-badge {
        background-color: #101e2e !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.6) !important;
      }
      html.dark #teacher-hero-badge .teacher-name, .dark #teacher-hero-badge .teacher-name { color: #ffffff !important; }
      html.dark #teacher-hero-badge .teacher-title, .dark #teacher-hero-badge .teacher-title { color: #fbbf24 !important; }

      /* Subject Branch Cards (#hero-branches) */
      .hero-branch-card {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.05);
        transition: all 0.25s ease;
      }
      .hero-branch-desc {
        color: #475569 !important;
        font-weight: 600;
      }
      html.dark .hero-branch-card, .dark .hero-branch-card {
        background-color: #101e2e !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        box-shadow: 0 4px 20px -3px rgba(0, 0, 0, 0.4) !important;
      }
      html.dark .hero-branch-desc, .dark .hero-branch-desc {
        color: #94a3b8 !important;
        font-weight: 500;
      }
      html.dark .hero-branch-title-nahw { color: #2dd4bf !important; }
      html.dark .hero-branch-btn-nahw { border-color: #2dd4bf !important; color: #2dd4bf !important; }
      html.dark .hero-branch-btn-nahw:hover { background-color: #0f766e !important; color: #ffffff !important; }

      html.dark .hero-branch-title-sarf { color: #fbbf24 !important; }
      html.dark .hero-branch-btn-sarf { border-color: #fbbf24 !important; color: #fbbf24 !important; }
      html.dark .hero-branch-btn-sarf:hover { background-color: #c79a32 !important; color: #ffffff !important; }

      html.dark .hero-branch-title-balagha { color: #60a5fa !important; }
      html.dark .hero-branch-btn-balagha { border-color: #60a5fa !important; color: #60a5fa !important; }
      html.dark .hero-branch-btn-balagha:hover { background-color: #2563eb !important; color: #ffffff !important; }

      html.dark .hero-branch-title-adab { color: #2dd4bf !important; }
      html.dark .hero-branch-btn-adab { border-color: #2dd4bf !important; color: #2dd4bf !important; }
      html.dark .hero-branch-btn-adab:hover { background-color: #0f766e !important; color: #ffffff !important; }

      /* Course Cards */
      html.dark .bg-PCardColor, .dark .bg-PCardColor {
        background-color: #101e2e !important;
        color: #f8fafc !important;
      }
      html.dark .bg-PCardColor h2, .dark .bg-PCardColor h2 { color: #ffffff !important; }
      html.dark .bg-PCardColor p, .dark .bg-PCardColor p { color: #94a3b8 !important; }
      .course-btn-enter {
        background-color: #ffffff;
        border-color: #cbd5e1;
        color: #1e293b;
      }
      html.dark .course-btn-enter, .dark .course-btn-enter {
        background-color: #162c44 !important;
        border-color: #244365 !important;
        color: #ffffff !important;
      }
      html.dark .course-btn-enter:hover, .dark .course-btn-enter:hover {
        background-color: #1e3a5f !important;
        border-color: #c79a32 !important;
      }

      /* "Why Choose Us" 8 Cards Section */
      html.dark section.bg-\[\#f4fafa\], .dark section.bg-\[\#f4fafa\] {
        background-color: #070d18 !important;
      }
      html.dark section.bg-\[\#f4fafa\] h2, .dark section.bg-\[\#f4fafa\] h2 { color: #ffffff !important; }
      html.dark section.bg-\[\#f4fafa\] > div > div > p, .dark section.bg-\[\#f4fafa\] > div > div > p { color: #94a3b8 !important; }
      html.dark .why-card, .dark .why-card {
        background-color: #101e2e !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
        color: #ffffff !important;
      }
      html.dark .why-card p, .dark .why-card p { color: #ffffff !important; }
      html.dark .why-card-icon, .dark .why-card-icon { background-color: rgba(15, 118, 110, 0.25) !important; }

      /* Top Navbar in Dark Mode */
      html.dark nav > div, .dark nav > div {
        background-color: #0e1b29 !important;
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
      }

      /* Torn Paper Separator Dark Mode Switch */
      html.dark .dark\:hidden, .dark .dark\:hidden { display: none !important; }
      html.dark .dark\:block, .dark .dark\:block { display: block !important; }
    </style>
</head>
<body dir="rtl" class="flex min-h-screen flex-col bg-PBackgroundColor text-PTextColor transition duration-300">
<nav class="fixed z-30 w-full top-0 left-0 right-0 flex flex-col items-center pointer-events-none"><div class="relative pointer-events-auto mx-4 mt-3 w-[calc(100%-2rem)] sm:mx-6 sm:w-[calc(100%-3rem)] lg:mx-10 lg:w-[calc(100%-5rem)] flex flex-col justify-around rounded-2xl bg-PBackgroundColor transition-all duration-300 shadow-md"><div class="w-full px-5 lg:px-10 flex h-[80px] items-center justify-between flex-row-reverse"><a class="flex items-center" href="index.php"><img alt="logo" loading="lazy" width="95" height="50" decoding="async" data-nimg="1" class="floting" style="color:transparent"  src="public/identity/logo.png"/></a><div class="flex items-center gap-4 flex-row-reverse"><button type="button" onclick="toggleTheme()" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-800 p-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 shadow-sm transition" title="تبديل الوضع الليلي / الفاتح"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="block dark:hidden"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden dark:block text-yellow-400"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg></button><div class="flex items-center gap-4 max-sm:hidden">
    <?php if ($currentUser): ?>
      <a href="profile.php">
        <button class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-bold border-2 border-PPrimaryColor bg-PPrimaryColor text-white transition-all duration-300 hover:-translate-y-[2px] hover:bg-PThirdColor hover:border-PThirdColor shadow-md hover:shadow-lg h-10 px-4 py-2">الملف الشخصي</button>
      </a>
      <?php if (($currentUser['role'] ?? '') === 'ADMIN'): ?>
        <a href="admin/index.php">
          <button class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-bold border-2 border-indigo-600 bg-indigo-600 text-white transition-all duration-300 hover:-translate-y-[2px] hover:bg-indigo-700 shadow-md h-10 px-4 py-2">لوحة التحكم</button>
        </a>
      <?php endif; ?>
    <?php else: ?>
      <a href="login.php">
        <button class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-bold border-2 border-PPrimaryColor bg-PPrimaryColor text-white transition-all duration-300 hover:-translate-y-[2px] hover:bg-PThirdColor hover:border-PThirdColor shadow-md hover:shadow-lg h-10 px-4 py-2">تسجيل الدخول</button>
      </a>
      <a href="register.php">
        <button class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-bold border-2 border-PPrimaryColor bg-transparent text-PPrimaryColor transition-all duration-300 hover:-translate-y-[2px] hover:bg-PPrimaryColor hover:text-white shadow-sm hover:shadow-md h-10 px-4 py-2">حساب جديد</button>
      </a>
    <?php endif; ?>
  </div><button class="duration-200 sm:hidden"><div class="rounded-full border-2 border-PPrimaryColor p-2"><svg width="20" height="20" style="fill:var(--PButtonColor)" class="transition-transform duration-200 " xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M502.6 278.6l-128 128c-12.51 12.51-32.76 12.49-45.25 0c-12.5-12.5-12.5-32.75 0-45.25L402.8 288H32C14.31 288 0 273.7 0 255.1S14.31 224 32 224h370.8l-73.38-73.38c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0l128 128C515.1 245.9 515.1 266.1 502.6 278.6z"></path></svg></div></button></div></div><div class="flex w-full flex-col items-center space-y-4 bg-background rounded-b-2xl p-5 text-center shadow-lg drop-shadow-md transition duration-200 dark:border-t dark:border-border sm:hidden hidden"><a class="w-full" href="profile.php"><button class="inline-flex items-center justify-center whitespace-nowrap rounded-md font-medium ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-10 px-4 py-2 text-md w-full border-2 border-PPrimaryColor bg-PPrimaryColor text-white transition-all duration-300 hover:bg-PThirdColor hover:border-PThirdColor dark:hover:bg-white dark:hover:text-black dark:hover:border-white shadow-md">الملف الشخصي</button></a><a class="w-full" href="logout.php"><button class="inline-flex items-center justify-center whitespace-nowrap rounded-md font-medium ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-10 px-4 py-2 text-md w-full border-2 border-red-500 bg-transparent text-red-500 hover:bg-red-500 hover:text-white transition-all duration-300 shadow-sm">تسجيل الخروج</button></a></div><div class="absolute bottom-0 left-2 right-0 h-[4px] rounded-b-2xl overflow-hidden "><div style="position:absolute;bottom:0;left:0;width:0%;height:4px;background-color:#2f4256;z-index:99;transition:width 0.0s ease-out"></div></div></div></nav>
<main class="flex flex-grow flex-col">
<div class="relative">
<section class="relative mt-[-80px] overflow-hidden pb-5 pt-[95px] sm:pb-8 sm:pt-[130px] lg:pb-10 lg:pt-[120px]" id="hero" dir="rtl">
<div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.02]" style="background-image:radial-gradient(circle at 1px 1px, var(--PPrimaryColor) 1px, transparent 0);background-size:40px 40px"></div>
<div class="relative z-10">
    <div class="flex lg:hidden"><div class="flex w-full gap-3 sm:gap-5"><div class="relative z-10 flex flex-1 max-[390px]:text-wrap -mr-8 flex-col justify-center items-center text-nowrap font-tajawal" style="opacity:1;transform:none"><p class="mb-0.5 text-[0.7rem] font-semibold hero-subtitle sm:text-[1rem]">أهلاً بك في منصة</p><h1 class="mb-1 text-[1.4rem] font-[900] leading-[1.3] sm:mb-2 sm:text-[2.2rem] py-2 hero-title">أ. محمد يوسف</h1><div class="mb-2 text-[0.65rem]  min-[350px]:hidden text-center font-semibold leading-[1.4] hero-desc sm:mb-3 sm:text-[1rem]"><div> رحلتك نحو إتقان </div><div> اللغة العربية تبدأ من هنا </div></div><p class="mb-2 text-[0.65rem] max-[350px]:hidden font-semibold leading-[1.4] hero-desc sm:mb-3 sm:text-[1rem]">في اللغة العربية للثانوية الأزهرية</p><a class="group flex w-fit items-center gap-1.5 rounded-full border-2 border-transparent px-4 py-2 text-[0.7rem] font-bold text-white shadow-md shadow-PPrimaryColor/20 transition-all duration-300 hover:shadow-lg hover:brightness-110 sm:gap-2 sm:px-6 sm:py-2.5 sm:text-[0.95rem]" style="background: linear-gradient(135deg, #0f766e 0%, #0d5f58 65%, #c79a32 100%);" href="#hero-branches"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left h-3.5 w-3.5 text-white transition-transform duration-300 group-hover:-translate-x-1 sm:h-4 sm:w-4"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg><span>ابدأ رحلتك الآن</span></a></div><div class="relative flex-shrink-0" style="opacity:1"><div class="relative max-[390px]:right-[0.95rem] max-[350px]:right-[1.1rem] max-[350px]:-mr-[6.5rem] h-[325px] w-[220px] -mr-[5.95rem]"><img alt="أ. محمد يوسف - خبير اللغة العربية بالأزهر الشريف" decoding="async" data-nimg="fill" style="position:absolute;height:100%;width:100%;left:0;top:0;right:0;bottom:0;color:transparent;object-fit:contain;object-position:bottom center;transform:scale(0.88) translateY(22px);transform-origin:bottom center;" sizes="(max-width: 640px) 150px, 240px"  src="public/heroPhotos/teacher-photo.png"/></div><div class="absolute bottom-0 -right-[4.5rem] left-0 z-20 mx-auto w-fit rounded-lg border border-PPrimaryColor/20 bg-white/90 px-2.5 py-3 shadow-md backdrop-blur-sm dark:bg-PBackgroundColor/90 sm:rounded-xl sm:px-4 sm:py-2"><p class="text-center font-cairo text-[0.75rem] font-extrabold text-PThirdColor sm:text-[0.95rem]">أ. محمد يوسف</p><p class="text-center font-cairo text-[0.75rem] font-semibold text-PButtonColor sm:text-[0.65rem]">خبير اللغة العربية بالأزهر الشريف</p></div></div></div></div>

    <!-- Desktop Exact Layout matching Original -->
    <div class="hidden lg:flex justify-between items-center px-8 lg:px-16 max-w-7xl mx-auto min-h-[480px]">
        <!-- Right side: Text & Badges -->
        <div class="flex-1 text-right space-y-4 pr-6">
            <p class="text-xl font-bold hero-subtitle">أهلاً بك في منصة</p>
            <h1 class="font-black tracking-tight pb-2 hero-title" style="font-family: Tajawal, Cairo, sans-serif; font-size: 3.5rem; font-weight: 900; line-height: 1.2;">أ. محمد يوسف</h1>
            <p class="text-2xl font-bold hero-desc">رحلتك نحو إتقان اللغة العربية تبدأ من هنا</p>
            
            <!-- 4 Badges -->
            <div class="flex items-center gap-5 pt-3">
                <span class="inline-flex items-center gap-1.5 text-base font-bold hero-badge-item">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full hero-badge-check text-xs">✔</span> النحو
                </span>
                <span class="inline-flex items-center gap-1.5 text-base font-bold hero-badge-item">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full hero-badge-check text-xs">✔</span> الصرف
                </span>
                <span class="inline-flex items-center gap-1.5 text-base font-bold hero-badge-item">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full hero-badge-check text-xs">✔</span> البلاغة
                </span>
                <span class="inline-flex items-center gap-1.5 text-base font-bold hero-badge-item">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full hero-badge-check text-xs">✔</span> الأدب والنصوص
                </span>
            </div>

            <!-- CTA Button -->
            <div class="pt-5">
                <a href="#courses-section" class="inline-flex items-center gap-3 rounded-full px-10 py-3.5 text-lg font-bold text-white shadow-xl hover:shadow-2xl hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #0f766e 0%, #0d5f58 65%, #c79a32 100%);">
                    <span>ابدأ رحلتك الآن</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                </a>
            </div>
        </div>

        <!-- Left side: Teacher Photo & Badge -->
        <div class="flex-shrink-0 flex items-end justify-center pl-6 relative" style="width: 460px; height: 460px;">
            <img src="public/heroPhotos/teacher-photo.png" alt="أ. محمد يوسف" style="max-width: 100%; max-height: 420px; width: auto; height: auto; object-fit: contain; object-position: bottom center;" class="drop-shadow-2xl transition-transform duration-300" />
            <div id="teacher-hero-badge" class="absolute text-center z-20 rounded-2xl px-6 py-2.5 shadow-xl whitespace-nowrap transition-colors" style="bottom: 15px; left: 20px;">
                <p class="teacher-name font-cairo text-xl font-black">أ. محمد يوسف</p>
                <p class="teacher-title font-cairo text-xs font-bold">خبير اللغة العربية بالأزهر الشريف</p>
            </div>
        </div>
    </div>
</div>

<div id="hero-branches" class="mt-8 grid grid-cols-2 gap-3 sm:mt-10 sm:gap-4 lg:grid-cols-4 lg:gap-6 mb-16 py-2 px-4 sm:px-8 max-w-7xl mx-auto">
    <!-- Branch 1: Nahw -->
    <div class="hero-branch-card group relative flex items-center justify-between rounded-2xl p-4 sm:p-5 transition hover:scale-[1.02]">
        <div class="flex flex-col items-start gap-1 flex-1">
            <h3 class="hero-branch-title-nahw font-cairo text-base sm:text-lg font-bold text-[#0f766e]">النحو</h3>
            <p class="hero-branch-desc text-xs">القواعد الأساسية والتطبيقات</p>
            <a href="courses.php?search=<?= urlencode('نحو') ?><?= !empty($currentUser['yearId']) ? '&year=' . urlencode($currentUser['yearId']) : '' ?>" class="hero-branch-btn-nahw mt-2 inline-flex items-center gap-1 rounded-full border border-[#0f766e] px-3.5 py-1 text-xs font-bold text-[#0f766e] hover:bg-[#0f766e] hover:text-white transition">ابدأ الآن &larr;</a>
        </div>
        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full border-2 border-teal-100 overflow-hidden flex-shrink-0 mr-3">
            <img src="public/designs/النحو.jpeg" alt="النحو" class="w-full h-full object-cover">
        </div>
    </div>

    <!-- Branch 2: Sarf -->
    <div class="hero-branch-card group relative flex items-center justify-between rounded-2xl p-4 sm:p-5 transition hover:scale-[1.02]">
        <div class="flex flex-col items-start gap-1 flex-1">
            <h3 class="hero-branch-title-sarf font-cairo text-base sm:text-lg font-bold text-[#c79a32]">الصرف</h3>
            <p class="hero-branch-desc text-xs">بناء الكلمات وأوزانها</p>
            <a href="courses.php?search=<?= urlencode('صرف') ?><?= !empty($currentUser['yearId']) ? '&year=' . urlencode($currentUser['yearId']) : '' ?>" class="hero-branch-btn-sarf mt-2 inline-flex items-center gap-1 rounded-full border border-[#c79a32] px-3.5 py-1 text-xs font-bold text-[#c79a32] hover:bg-[#c79a32] hover:text-white transition">ابدأ الآن &larr;</a>
        </div>
        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full border-2 border-amber-100 overflow-hidden flex-shrink-0 mr-3">
            <img src="public/designs/صرف.jpeg" alt="الصرف" class="w-full h-full object-cover">
        </div>
    </div>

    <!-- Branch 3: Balagha -->
    <div class="hero-branch-card group relative flex items-center justify-between rounded-2xl p-4 sm:p-5 transition hover:scale-[1.02]">
        <div class="flex flex-col items-start gap-1 flex-1">
            <h3 class="hero-branch-title-balagha font-cairo text-base sm:text-lg font-bold text-[#0d3753]">البلاغة</h3>
            <p class="hero-branch-desc text-xs">الفن والجمال في التعبير</p>
            <a href="courses.php?search=<?= urlencode('بلاغة') ?><?= !empty($currentUser['yearId']) ? '&year=' . urlencode($currentUser['yearId']) : '' ?>" class="hero-branch-btn-balagha mt-2 inline-flex items-center gap-1 rounded-full border border-[#0d3753] px-3.5 py-1 text-xs font-bold text-[#0d3753] hover:bg-[#0d3753] hover:text-white transition">ابدأ الآن &larr;</a>
        </div>
        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full border-2 border-blue-100 overflow-hidden flex-shrink-0 mr-3">
            <img src="public/designs/بلاغة.jpeg" alt="البلاغة" class="w-full h-full object-cover">
        </div>
    </div>

    <!-- Branch 4: Adab -->
    <div class="hero-branch-card group relative flex items-center justify-between rounded-2xl p-4 sm:p-5 transition hover:scale-[1.02]">
        <div class="flex flex-col items-start gap-1 flex-1">
            <h3 class="hero-branch-title-adab font-cairo text-base sm:text-lg font-bold text-[#0f766e]">الأدب والنصوص</h3>
            <p class="hero-branch-desc text-xs">الفهم والتحليل والتذوق</p>
            <a href="courses.php?search=<?= urlencode('أدب') ?><?= !empty($currentUser['yearId']) ? '&year=' . urlencode($currentUser['yearId']) : '' ?>" class="hero-branch-btn-adab mt-2 inline-flex items-center gap-1 rounded-full border border-[#0f766e] px-3.5 py-1 text-xs font-bold text-[#0f766e] hover:bg-[#0f766e] hover:text-white transition">ابدأ الآن &larr;</a>
        </div>
        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full border-2 border-teal-100 overflow-hidden flex-shrink-0 mr-3">
            <img src="public/designs/نصوص.jpeg" alt="الأدب والنصوص" class="w-full h-full object-cover">
        </div>
    </div>
</div>
</section><div class="absolute left-0 right-0 bottom-[-2px] h-[60px] w-full z-10 pointer-events-none"><img alt="separator" loading="lazy" decoding="async" data-nimg="fill" class="object-fill dark:hidden" style="position:absolute;height:100%;width:100%;left:0;top:0;right:0;bottom:0;color:transparent" sizes="100vw"  src="public/designs/paper-torn/paper-torn-up-white.png"/><img alt="separator" loading="lazy" decoding="async" data-nimg="fill" class="object-fill hidden dark:block" style="position:absolute;height:100%;width:100%;left:0;top:0;right:0;bottom:0;color:transparent" sizes="100vw"  src="public/designs/paper-torn/paper-torn-up-dark.png"/></div></div><section id="courses-section" class="relative"><div class="mb-16 w-full"><div class="flex flex-row items-center px-6 justify-between mb-8 max-md:flex-col max-md:gap-4 w-full"><div class="text-right text-sm text-slate-500 max-md:text-center md:w-1/3"><div><p class="font-semibold text-slate-700 dark:text-slate-300">تقدر تختار من افضل الكورسات</p></div></div><div class="flex flex-col items-center justify-center md:w-1/3"><div class="relative inline-block py-6"><div class="absolute inset-0 z-0" style="background-image:url('public/designs/brushes/title.svg');background-size:100% 100%;background-position:center;background-repeat:no-repeat"></div><h2 class="relative z-10 text-4xl max-sm:text-3xl font-bold text-foreground text-center" style="font-family:BackToSchool">الكورسات <span class="text-PPrimaryColor">المقترحة</span></h2></div></div><div class="flex items-center justify-end max-md:justify-center md:w-1/3"><a class="rounded-lg bg-PButtonColor px-6 py-2.5 text-center text-sm font-semibold text-white shadow-md transition-all hover:opacity-90" href="/courses">الكل</a></div></div><div class="relative w-full overflow-visible"><div class="absolute left-0 top-0 bottom-0 w-28 z-20 flex items-center justify-start pointer-events-none" style="background:linear-gradient(to right, var(--PPBackgroundColor) 30%, transparent 100%)"><button class="pointer-events-auto flex items-center justify-center text-PButtonColor transition-transform hover:scale-110 active:scale-95 z-30 -translate-x-4 md:-translate-x-8"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left h-20 w-20 stroke-[1.5]"><path d="m15 18-6-6 6-6"></path></svg></button></div><div class="flex flex-row overflow-x-auto gap-8 scroll-smooth pt-12 pb-10 w-full no-scrollbar relative z-10 px-6 lg:px-14">
    <?php foreach ($courses as $c): ?>
    <div class="w-[420px] max-sm:w-[320px] flex-shrink-0">
        <div class="group relative max-w-[540px] max-lg:max-w-full">
            <div class="w-full h-56 overflow-hidden rounded-md bg-slate-100 relative">
                <?php if (!empty($c['thumbnail'])): ?>
                    <img src="<?= htmlspecialchars($c['thumbnail']) ?>" alt="<?= htmlspecialchars($c['title']) ?>" class="w-full h-full object-cover transition duration-300 group-hover:scale-105" />
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center bg-PThirdColor text-PButtonColor text-2xl font-bold">
                        <?= htmlspecialchars($c['title']) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="px-3">
                <div class="duration-400 min-h-[200px] -translate-y-10 space-y-3 rounded-[10px] bg-PCardColor px-4 py-6 shadow-[0px_0px_25px_1px_rgba(0,0,0,0.3)] transition-all group-hover:scale-[102%] group-hover:shadow-[0px_0px_30px_2px_var(--PPrimaryColor)]">
                    <div class="flex items-start justify-between border-b-2 border-PPrimaryColor pb-2">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($c['title']) ?></h2>
                        <div class="max-h-fit rounded-md bg-PButtonColor px-3 py-[0.3rem] text-center text-xs font-extrabold text-white flex items-center gap-1">
                            <span class="rounded-full bg-white px-2 py-0.5 text-black font-bold"><?= number_format((float)$c['price'], 2) ?></span>
                            <span>جنيهاً</span>
                        </div>
                    </div>
                    <div class="w-full space-y-2">
                        <p class="font-medium text-xs text-slate-600 dark:text-slate-300 line-clamp-2"><?= htmlspecialchars($c['description'] ?? '') ?></p>
                    </div>
                    <div class="flex flex-col justify-center gap-2 pt-2">
                        <a class="w-full" href="course.php?id=<?= urlencode($c['id']) ?>">
                            <button class="course-btn-enter w-full rounded-md text-xs font-bold h-9 px-4 border-2 transition">الدخول إلي الكورس</button>
                        </a>
                        <a href="purchase.php?id=<?= urlencode($c['id']) ?>" class="w-full">
                            <button class="w-full rounded-md text-xs font-bold h-9 px-4 border-2 border-PButtonColor bg-PButtonColor hover:bg-[#b08728] text-white transition">اشترك الان !</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div></div></div><div class="absolute left-0 right-0 bottom-[-2px] h-[60px] w-full z-10 pointer-events-none"><img alt="separator" loading="lazy" decoding="async" data-nimg="fill" class="object-fill dark:hidden" style="position:absolute;height:100%;width:100%;left:0;top:0;right:0;bottom:0;color:transparent" sizes="100vw"  src="public/designs/paper-torn/paper-torn-up-white.png"/><img alt="separator" loading="lazy" decoding="async" data-nimg="fill" class="object-fill hidden dark:block" style="position:absolute;height:100%;width:100%;left:0;top:0;right:0;bottom:0;color:transparent" sizes="100vw"  src="public/designs/paper-torn/paper-torn-up-dark.png"/></div></section><section class="flex flex-col relative"><div class="min-h-[150px]"></div><div class="px-4 max-sm:px-2 "><div class="mb-20"><div class="flex justify-start my-10"><div class="relative inline-block py-6"><div class="absolute inset-0 z-0" style="background-image:url('public/designs/brushes/title.svg');background-size:100% 100%;background-position:center;background-repeat:no-repeat"></div><h2 class="relative z-10 text-4xl max-sm:text-3xl font-bold text-foreground text-center" style="font-family:Amin-">السنوات الدراسية</h2></div></div><div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
    <?php foreach ($years as $yr): ?>
        <?php 
            $yrImg = $yr['image'] ?: 'public/heroPhotos/teacher-photo.png';
            if (!str_starts_with($yrImg, 'http') && !str_starts_with($yrImg, '/') && !str_starts_with($yrImg, 'public/')) {
                $yrImg = 'public/uploads/' . $yrImg;
            }
        ?>
        <a class="group relative block aspect-[16/10] overflow-hidden rounded-2xl bg-slate-900 shadow-lg transition-all duration-500 hover:scale-[1.03] hover:shadow-[0px_0px_30px_2px_var(--PPrimaryColor)]" href="year.php?id=<?= urlencode($yr['id']) ?>">
            <img alt="<?= htmlspecialchars($yr['name']) ?>" loading="lazy" decoding="async" class="object-cover w-full h-full transition-transform duration-700 ease-out group-hover:scale-110 group-hover:brightness-90" src="<?= htmlspecialchars($yrImg) ?>"/>
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/35 to-transparent transition-opacity duration-500"></div>
            <div class="absolute inset-0 p-6 flex flex-col justify-end">
                <h3 class="text-2xl font-bold text-white tracking-wide transition-transform duration-500 group-hover:-translate-y-1"><?= htmlspecialchars($yr['name']) ?></h3>
                <div class="mt-2 h-[3px] w-10 rounded-full bg-PPrimaryColor transition-all duration-500 group-hover:w-20"></div>
            </div>
        </a>
    <?php endforeach; ?>
</div></div></div></section><section class="w-full pt-20 pb-[10rem] bg-[#f4fafa] dark:bg-background relative"><div class="absolute left-0 right-0 top-[-58px] h-[60px] w-full z-10 pointer-events-none"><img alt="separator" loading="lazy" decoding="async" data-nimg="fill" class="object-fill dark:hidden" style="position:absolute;height:100%;width:100%;left:0;top:0;right:0;bottom:0;color:transparent" sizes="100vw"  src="public/designs/paper-torn/paper-torn-up-card-color.png"/><img alt="separator" loading="lazy" decoding="async" data-nimg="fill" class="object-fill hidden dark:block" style="position:absolute;height:100%;width:100%;left:0;top:0;right:0;bottom:0;color:transparent" sizes="100vw"  src="public/designs/paper-torn/paper-torn-up-dark-card-color.png"/></div><div class="max-w-[1300px] mx-auto px-4 sm:px-6 lg:px-8"><div class="text-center mb-14" dir="rtl"><h2 class="text-4xl md:text-5xl font-extrabold text-gray-800 dark:text-foreground mb-4">لماذا تختار منصتنا التعليمية؟</h2><p class="text-gray-500 dark:text-muted-foreground text-lg font-medium">نقدم لك تجربة تعليمية متكاملة تساعدك على التعلم والتطور بسهولة وفاعلية</p></div><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6" dir="rtl"><div class="why-card group relative bg-white dark:bg-card rounded-2xl border border-gray-100 dark:border-border shadow-sm hover:shadow-[0px_0px_20px_2px_var(--PPrimaryColor)] hover:scale-[1.05] transition-all duration-300 p-6 flex flex-col items-center text-center z-10 hover:z-20"><span class="absolute top-4 left-4 text-sm font-bold text-PPrimaryColor opacity-70">01</span><div class="w-16 h-16 rounded-full bg-[#e8f5f5] dark:bg-[#1a3333] flex items-center justify-center mb-5 mt-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text w-7 h-7 text-PPrimaryColor"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" x2="8" y1="13" y2="13"></line><line x1="16" x2="8" y1="17" y2="17"></line><line x1="10" x2="8" y1="9" y2="9"></line></svg></div><p class="text-gray-800 dark:text-foreground font-bold text-lg leading-snug mb-3">محتوى مبسط ومتطور</p><span class="block w-10 h-[3px] rounded-full bg-PPrimaryColor"></span></div><div class="why-card group relative bg-white dark:bg-card rounded-2xl border border-gray-100 dark:border-border shadow-sm hover:shadow-[0px_0px_20px_2px_var(--PPrimaryColor)] hover:scale-[1.05] transition-all duration-300 p-6 flex flex-col items-center text-center z-10 hover:z-20"><span class="absolute top-4 left-4 text-sm font-bold text-PPrimaryColor opacity-70">02</span><div class="w-16 h-16 rounded-full bg-[#e8f5f5] dark:bg-[#1a3333] flex items-center justify-center mb-5 mt-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play w-7 h-7 text-PPrimaryColor"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg></div><p class="text-gray-800 dark:text-foreground font-bold text-lg leading-snug mb-3">دروس بصرية تفاعلية</p><span class="block w-10 h-[3px] rounded-full bg-PPrimaryColor"></span></div><div class="why-card group relative bg-white dark:bg-card rounded-2xl border border-gray-100 dark:border-border shadow-sm hover:shadow-[0px_0px_20px_2px_var(--PPrimaryColor)] hover:scale-[1.05] transition-all duration-300 p-6 flex flex-col items-center text-center z-10 hover:z-20"><span class="absolute top-4 left-4 text-sm font-bold text-PPrimaryColor opacity-70">03</span><div class="w-16 h-16 rounded-full bg-[#e8f5f5] dark:bg-[#1a3333] flex items-center justify-center mb-5 mt-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-brain w-7 h-7 text-PPrimaryColor"><path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.98-3A2.5 2.5 0 0 1 9.5 2Z"></path><path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.98-3A2.5 2.5 0 0 0 14.5 2Z"></path></svg></div><p class="text-gray-800 dark:text-foreground font-bold text-lg leading-snug mb-3">تدريبات ذكية وممتعة</p><span class="block w-10 h-[3px] rounded-full bg-PPrimaryColor"></span></div><div class="why-card group relative bg-white dark:bg-card rounded-2xl border border-gray-100 dark:border-border shadow-sm hover:shadow-[0px_0px_20px_2px_var(--PPrimaryColor)] hover:scale-[1.05] transition-all duration-300 p-6 flex flex-col items-center text-center z-10 hover:z-20"><span class="absolute top-4 left-4 text-sm font-bold text-PPrimaryColor opacity-70">04</span><div class="w-16 h-16 rounded-full bg-[#e8f5f5] dark:bg-[#1a3333] flex items-center justify-center mb-5 mt-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-grid w-7 h-7 text-PPrimaryColor"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg></div><p class="text-gray-800 dark:text-foreground font-bold text-lg leading-snug mb-3">جدول مرن للمذاكرة</p><span class="block w-10 h-[3px] rounded-full bg-PPrimaryColor"></span></div><div class="why-card group relative bg-white dark:bg-card rounded-2xl border border-gray-100 dark:border-border shadow-sm hover:shadow-[0px_0px_20px_2px_var(--PPrimaryColor)] hover:scale-[1.05] transition-all duration-300 p-6 flex flex-col items-center text-center z-10 hover:z-20"><span class="absolute top-4 left-4 text-sm font-bold text-PPrimaryColor opacity-70">05</span><div class="w-16 h-16 rounded-full bg-[#e8f5f5] dark:bg-[#1a3333] flex items-center justify-center mb-5 mt-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart2 w-7 h-7 text-PPrimaryColor"><line x1="18" x2="18" y1="20" y2="10"></line><line x1="12" x2="12" y1="20" y2="4"></line><line x1="6" x2="6" y1="20" y2="14"></line></svg></div><p class="text-gray-800 dark:text-foreground font-bold text-lg leading-snug mb-3">تقييم مستمر ودقيق</p><span class="block w-10 h-[3px] rounded-full bg-PPrimaryColor"></span></div><div class="why-card group relative bg-white dark:bg-card rounded-2xl border border-gray-100 dark:border-border shadow-sm hover:shadow-[0px_0px_20px_2px_var(--PPrimaryColor)] hover:scale-[1.05] transition-all duration-300 p-6 flex flex-col items-center text-center z-10 hover:z-20"><span class="absolute top-4 left-4 text-sm font-bold text-PPrimaryColor opacity-70">06</span><div class="w-16 h-16 rounded-full bg-[#e8f5f5] dark:bg-[#1a3333] flex items-center justify-center mb-5 mt-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open w-7 h-7 text-PPrimaryColor"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg></div><p class="text-gray-800 dark:text-foreground font-bold text-lg leading-snug mb-3">منهج شامل ومنظم</p><span class="block w-10 h-[3px] rounded-full bg-PPrimaryColor"></span></div><div class="why-card group relative bg-white dark:bg-card rounded-2xl border border-gray-100 dark:border-border shadow-sm hover:shadow-[0px_0px_20px_2px_var(--PPrimaryColor)] hover:scale-[1.05] transition-all duration-300 p-6 flex flex-col items-center text-center z-10 hover:z-20"><span class="absolute top-4 left-4 text-sm font-bold text-PPrimaryColor opacity-70">07</span><div class="w-16 h-16 rounded-full bg-[#e8f5f5] dark:bg-[#1a3333] flex items-center justify-center mb-5 mt-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-refresh-cw w-7 h-7 text-PPrimaryColor"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path></svg></div><p class="text-gray-800 dark:text-foreground font-bold text-lg leading-snug mb-3">تحديثات دورية للمحتوى</p><span class="block w-10 h-[3px] rounded-full bg-PPrimaryColor"></span></div><div class="why-card group relative bg-white dark:bg-card rounded-2xl border border-gray-100 dark:border-border shadow-sm hover:shadow-[0px_0px_20px_2px_var(--PPrimaryColor)] hover:scale-[1.05] transition-all duration-300 p-6 flex flex-col items-center text-center z-10 hover:z-20"><span class="absolute top-4 left-4 text-sm font-bold text-PPrimaryColor opacity-70">08</span><div class="w-16 h-16 rounded-full bg-[#e8f5f5] dark:bg-[#1a3333] flex items-center justify-center mb-5 mt-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users w-7 h-7 text-PPrimaryColor"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div><p class="text-gray-800 dark:text-foreground font-bold text-lg leading-snug mb-3">تواصل مع مجتمع تعليمي</p><span class="block w-10 h-[3px] rounded-full bg-PPrimaryColor"></span></div></div></div></section><!--$--><!--/$--></div><link rel="preload" as="image" imageSrcSet="/_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=32&amp;q=75 32w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=48&amp;q=75 48w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=64&amp;q=75 64w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=96&amp;q=75 96w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=128&amp;q=75 128w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=256&amp;q=75 256w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=384&amp;q=75 384w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=640&amp;q=75 640w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=750&amp;q=75 750w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=828&amp;q=75 828w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=1080&amp;q=75 1080w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=1200&amp;q=75 1200w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=1920&amp;q=75 1920w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=2048&amp;q=75 2048w, /_next/image?url=%2FheroPhotos%2Fteacher-photo.png&amp;w=3840&amp;q=75 3840w" imageSizes="800px"/>
<footer class="relative z-10 -mt-20 md:-mt-28 w-full bg-[#f4fafa] pb-12 pt-20 md:pt-28 text-PTextColor dark:border-slate-800 bg-cover bg-center bg-no-repeat bg-[url('public/designs/footer/footer-mobile-bg.svg')] md:bg-[url('public/designs/footer/footer-bg.svg')] dark:bg-[url('public/designs/footer/footer-bg-dark.svg')]"><div class="w-full px-6 lg:px-14"><div class="grid gap-10 text-right grid-cols-2 md:grid-cols-6"><div class="flex flex-col items-center space-y-4 max-md:text-center md:items-start col-span-3 max-sm:col-span-2"><img alt="logo" width="120" height="70" decoding="async" data-nimg="1" class="object-contain" style="color:transparent"  src="public/identity/logo.png"/><div><p class="text-sm text-slate-800 dark:text-slate-200">جميع الحقوق محفوظة © 2026 - منصة الأستاذ محمد يوسف</p></div></div><div class="flex flex-col space-y-3 max-md:items-center"><h3 class="text-lg  text-[#d2155a]">الصفحات</h3><a class="text-sm font-medium text-slate-600 transition-colors hover:text-[#d2155a] dark:text-slate-300" href="index.php">الرئيسية</a><a class="text-sm font-medium text-slate-600 transition-colors hover:text-[#d2155a] dark:text-slate-300" href="about.php">من نحن</a><a class="text-sm font-medium text-slate-600 transition-colors hover:text-[#d2155a] dark:text-slate-300" href="privacy.php">سياسة الخصوصية</a><a class="text-sm font-medium text-slate-600 transition-colors hover:text-[#d2155a] dark:text-slate-300" href="terms.php">الشروط والأحكام</a><a class="text-sm font-medium text-slate-600 transition-colors hover:text-[#d2155a] dark:text-slate-300" href="exchange.php">سياسة الاستبدال والاسترجاع</a></div><div class="flex flex-col space-y-3 max-md:items-center"><h3 class="text-lg  text-[#d2155a]">السوشيال ميديا</h3><a href="https://youtube.com/@mohamedyoussef-arabic?si=wCtbJvUBEZCWiIy7" target="_blank" rel="noopener noreferrer" class="flex items-center space-x-2 space-x-reverse text-sm font-medium text-slate-600 transition-colors hover:text-[#d2155a] dark:text-slate-300"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-youtube "><path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"></path><path d="m10 15 5-3-5-3z"></path></svg><span>يوتيوب</span></a><a href="https://www.facebook.com/share/1JmfgFeh8d/" target="_blank" rel="noopener noreferrer" class="flex items-center space-x-2 space-x-reverse text-sm font-medium text-slate-600 transition-colors hover:text-[#d2155a] dark:text-slate-300"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-facebook fill-[#1877F2] text-[#1877F2]"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg><span>فيسبوك</span></a></div><div class="flex flex-col space-y-3 max-md:items-center"><h3 class="text-lg  text-[#d2155a]">تواصل معنا</h3><a class="inline-block w-fit rounded-lg bg-[#5e6e85] px-8 py-2.5 text-center text-sm font-semibold text-white shadow-md transition-all hover:bg-[#4f5d72]" href="contact-us.php">الدعم الفني</a><a class="inline-flex items-center gap-2 rounded-lg bg-[#25D366] hover:bg-[#128C7E] px-4 py-2 text-center text-xs font-bold text-white shadow-md transition-all mt-1" href="https://wa.me/201092037257" target="_blank"><svg class="w-4 h-4 fill-white" viewBox="0 0 448 512"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg><span>01092037257</span></a></div></div></div></footer>
<script>
// Floating mobile nav toggle
document.querySelectorAll('button[class*="sm:hidden"]').forEach(btn => {
  btn.addEventListener('click', () => {
    const mobileMenu = btn.closest('nav').querySelector('div[class*="sm:hidden hidden"]');
    if (mobileMenu) {
      mobileMenu.classList.toggle('hidden');
    }
  });
});

function toggleTheme() {
  const isDark = document.documentElement.classList.toggle('dark');
  try {
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
  } catch(e) {}
}
</script>
</body>
</html>