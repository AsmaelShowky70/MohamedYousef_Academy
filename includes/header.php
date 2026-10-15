<?php
require_once __DIR__ . '/../config/helpers.php';
$currentUser = getCurrentUser();
$whatsappNumber = getSetting('whatsappFloatingNumber', '201092037257');
$whatsappEnabled = getSetting('whatsappFloatingEnabled', 'true') === 'true';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'منصة الأستاذ محمد يوسف - مادة اللغة العربية') ?></title>
    <meta name="description" content="منصة الأستاذ محمد يوسف التعليمية المتخصصة في تدريس مادة اللغة العربية للثانوية العامة والأزهرية">
    <link rel="icon" href="/public/favicon.svg" type="image/svg+xml">
    
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

    <!-- Google Fonts: Cairo & Almarai -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Original Tailored CSS with Design System Tokens -->
    <link rel="stylesheet" href="/public/css/app.css">
    
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --font-family: 'Almarai', 'Cairo', sans-serif;
            --PPBackgroundColor: #ffffff;
            --PPrimaryColor: #0f766e;
            --PButtonColor: #c79a32;
            --PTextColor: #111a14;
            --PCardColor: #f4f8fa;
            --PThirdColor: #0d3753;
        }
        html.dark, .dark {
            --PPBackgroundColor: #0a0f1d;
            --PPrimaryColor: #0f766e;
            --PButtonColor: #c79a32;
            --PTextColor: #f3f6f4;
            --PCardColor: #12283a;
            --PThirdColor: #0d3753;
            --background: 220 43% 5.5%;
            --foreground: 210 40% 98%;
            --card: 204 73% 19%;
            --card-foreground: 210 40% 98%;
            --muted: 217.2 32.6% 17.5%;
            --muted-foreground: 215 20.2% 65.1%;
            --border: 204 50% 25%;
            --input: 204 50% 25%;
        }
        body {
            font-family: 'Almarai', 'Cairo', system-ui, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            direction: rtl;
            overflow-x: hidden;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .bg-primary-custom { background-color: #0f766e !important; }
        .text-primary-custom { color: #0f766e !important; }
        .bg-gold-custom { background-color: #c79a32 !important; }
        .text-gold-custom { color: #c79a32 !important; }
        .bg-navy-custom { background-color: #0d3753 !important; }
        .text-navy-custom { color: #0d3753 !important; }
        .glass-nav {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        /* Dark Mode Frontend Overrides */
        html.dark body {
            background-color: #0a0f1d !important;
            color: #f1f5f9 !important;
        }
        html.dark .glass-nav {
            background: rgba(10, 15, 29, 0.92) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        html.dark .text-navy-custom {
            color: #f1f5f9 !important;
        }
        html.dark nav a {
            color: #cbd5e1 !important;
        }
        html.dark nav a:hover {
            color: #2dd4bf !important;
        }
        html.dark .bg-white {
            background-color: #101e2e !important;
            color: #f8fafc !important;
            border-color: #1e3347 !important;
        }
        html.dark .bg-slate-50 {
            background-color: #0b1623 !important;
            color: #f8fafc !important;
            border-color: #1e3347 !important;
        }
        html.dark .border-slate-200, html.dark .border-slate-200\/80, html.dark .border-slate-300, html.dark [class*="border-slate-"] {
            border-color: #1e3347 !important;
        }
        html.dark .bg-slate-100, html.dark [class*="bg-slate-100"] {
            background-color: #132337 !important;
            color: #f8fafc !important;
        }
        html.dark [class*="bg-slate-50"] {
            background-color: #0b1623 !important;
            color: #f8fafc !important;
        }
        html.dark .text-slate-900, html.dark .text-slate-800 {
            color: #f8fafc !important;
        }
        html.dark .text-slate-700, html.dark .text-slate-600 {
            color: #cbd5e1 !important;
        }
        html.dark input, html.dark select, html.dark textarea {
            background-color: #0d1825 !important;
            color: #f8fafc !important;
            border-color: #1e3347 !important;
        }
        html.dark input:focus, html.dark select:focus, html.dark textarea:focus {
            background-color: #132337 !important;
            border-color: #0f766e !important;
            color: #ffffff !important;
        }
        html.dark select option {
            background-color: #0d1825 !important;
            color: #f8fafc !important;
        }

        /* Profile, Course, & Wallet Cards in Dark Mode */
        html.dark .bg-PCardColor {
            background-color: #12283a !important;
            color: #f8fafc !important;
        }
        html.dark .bg-PBackgroundColor {
            background-color: #0a0f1d !important;
            color: #f8fafc !important;
        }
        html.dark .text-PTextColor {
            color: #f8fafc !important;
        }
        html.dark .bg-background {
            background-color: #0e1b29 !important;
            color: #f8fafc !important;
            border-color: #1e3347 !important;
        html.dark .bg-background:hover, html.dark button.bg-background:hover {
            background-color: #162c44 !important;
            color: #ffffff !important;
        }

        /* Mobile Header Responsiveness & Animation */
        .brand-title {
            font-size: 0.95rem;
            line-height: 1.25;
            font-weight: 800;
            white-space: nowrap;
        }
        @media (min-width: 640px) {
            .brand-title {
                font-size: 1.15rem;
            }
        }
        @media (min-width: 1024px) {
            .brand-title {
                font-size: 1.25rem;
            }
        }

        .brand-subtitle {
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
            display: none;
        }
        @media (min-width: 640px) {
            .brand-subtitle {
                display: block;
            }
        }

        .header-desktop-only {
            display: none !important;
        }
        @media (min-width: 768px) {
            .header-desktop-only {
                display: inline-flex !important;
            }
        }

        .header-mobile-only {
            display: inline-flex !important;
        }
        @media (min-width: 768px) {
            .header-mobile-only {
                display: none !important;
            }
        }

        @keyframes menuSlideDown {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-menu-slide {
            animation: menuSlideDown 0.22s ease-out forwards;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">

<!-- Top Announcement Bar -->
<div class="bg-navy-custom text-white text-[11px] sm:text-xs py-1.5 sm:py-2 px-3 sm:px-4 text-center font-medium flex items-center justify-center gap-2 sm:gap-3">
    <span class="truncate"><i class="fa-solid fa-graduation-cap text-gold-custom ml-1"></i> مرحباً بكم في منصة الأستاذ محمد يوسف التعليمية للغة العربية</span>
    <span class="hidden md:inline text-slate-300">|</span>
    <span class="hidden md:inline"><i class="fa-solid fa-phone text-gold-custom ml-1"></i> الدعم الفني: <?= htmlspecialchars($whatsappNumber) ?></span>
</div>

<!-- Main Navigation Header -->
<header class="sticky top-0 z-50 glass-nav border-b border-slate-200/80 shadow-sm transition-all duration-300">
    <div class="container mx-auto px-3 sm:px-4 lg:px-8">
        <div class="flex items-center justify-between h-16 sm:h-20 gap-2">
            <!-- Brand / Logo -->
            <a href="/index.php" class="flex items-center gap-2 sm:gap-3 group flex-shrink-0 min-w-0">
                <img src="/public/identity/logo2.svg" alt="شعار المنصة" class="h-9 sm:h-11 md:h-12 w-auto object-contain flex-shrink-0 transition-transform group-hover:scale-105" onerror="this.src='/public/identity/logo.png'">
                <div class="flex flex-col justify-center min-w-0">
                    <span class="brand-title text-navy-custom">منصة أ. محمد يوسف</span>
                    <span class="brand-subtitle text-primary-custom">اللغة العربية للثانوية العامة والأزهرية</span>
                </div>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="header-desktop-only items-center gap-6 lg:gap-8 font-bold text-sm text-slate-700">
                <a href="/index.php" class="hover:text-primary-custom transition-colors <?= empty($activeNav) || $activeNav === 'home' ? 'text-primary-custom font-extrabold' : '' ?>">
                    <i class="fa-solid fa-house ml-1"></i> الرئيسية
                </a>
                <a href="/index.php#years" class="hover:text-primary-custom transition-colors">
                    <i class="fa-solid fa-layer-group ml-1"></i> الصفوف الدراسية
                </a>
                <a href="/courses.php" class="hover:text-primary-custom transition-colors <?= ($activeNav ?? '') === 'courses' ? 'text-primary-custom font-extrabold' : '' ?>">
                    <i class="fa-solid fa-book-open ml-1"></i> الكورسات المتاحة
                </a>
                <a href="/parent.php" class="hover:text-primary-custom transition-colors <?= ($activeNav ?? '') === 'parent' ? 'text-primary-custom font-extrabold' : '' ?>">
                    <i class="fa-solid fa-user-shield ml-1"></i> بوابة ولي الأمر
                </a>
            </nav>

            <!-- User Auth & Controls -->
            <div class="flex items-center gap-1.5 sm:gap-3 flex-shrink-0">
                <!-- Theme Toggle Button -->
                <button type="button" onclick="toggleTheme()" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-800/90 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 shadow-sm transition flex-shrink-0" title="تبديل الوضع الليلي / الفاتح" aria-label="تبديل الوضع">
                    <svg id="themeIconSun" xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="block dark:hidden">
                        <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
                    </svg>
                    <svg id="themeIconMoon" xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden dark:block text-yellow-400">
                        <path d="M12 3a6 6 0 0 0 9 9 9 0 1 1-9-9Z"/>
                    </svg>
                </button>

                <?php if ($currentUser): ?>
                    <?php if (in_array($currentUser['role'], ['ADMIN', 'SUPERADMIN'])): ?>
                        <a href="/admin/index.php" class="header-desktop-only px-3.5 py-2 bg-navy-custom text-white text-xs sm:text-sm font-bold rounded-xl shadow-sm hover:bg-slate-800 transition items-center gap-1.5 whitespace-nowrap">
                            <i class="fa-solid fa-gauge-high text-gold-custom"></i> لوحة التحكم
                        </a>
                    <?php endif; ?>
                    <a href="/profile.php" class="px-2.5 sm:px-3.5 py-1.5 sm:py-2 bg-primary-custom text-white text-xs sm:text-sm font-bold rounded-xl shadow-sm hover:bg-teal-800 transition flex items-center gap-1.5 whitespace-nowrap max-w-[125px] sm:max-w-none">
                        <i class="fa-solid fa-user-graduate flex-shrink-0 text-xs"></i> <span class="truncate"><?= htmlspecialchars($currentUser['firstName']) ?></span>
                    </a>
                    <button onclick="handleLogout()" class="header-desktop-only p-2 text-slate-500 hover:text-red-600 transition" title="تسجيل الخروج">
                        <i class="fa-solid fa-right-from-bracket text-lg"></i>
                    </button>
                <?php else: ?>
                    <!-- Desktop Auth Buttons -->
                    <a href="/login.php" class="header-desktop-only px-3.5 py-2 text-sm font-bold text-navy-custom dark:text-slate-200 hover:text-primary-custom whitespace-nowrap transition">
                        تسجيل الدخول
                    </a>
                    <a href="/register.php" class="header-desktop-only px-4 py-2 bg-primary-custom text-white text-sm font-bold rounded-xl shadow-md hover:bg-teal-800 whitespace-nowrap transition items-center gap-2">
                        <i class="fa-solid fa-user-plus text-xs"></i> طالب جديد
                    </a>
                    <!-- Mobile Compact Login Button -->
                    <a href="/login.php" class="header-mobile-only px-2.5 py-1.5 bg-primary-custom text-white text-xs font-bold rounded-lg shadow-sm hover:bg-teal-800 transition whitespace-nowrap items-center gap-1">
                        <i class="fa-solid fa-arrow-right-to-bracket text-[11px]"></i> <span>دخول</span>
                    </a>
                <?php endif; ?>

                <!-- Mobile Hamburger Menu Button -->
                <button type="button" onclick="toggleMobileNav()" id="mobileMenuBtn" class="header-mobile-only items-center justify-center w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-800/90 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 shadow-sm transition flex-shrink-0" aria-label="القائمة الرئيسية" title="القائمة">
                    <i class="fa-solid fa-bars text-sm transition-transform duration-200" id="mobileMenuIcon"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation Menu Dropdown -->
    <div id="mobileNavMenu" class="hidden border-t border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-[#0a0f1d]/95 backdrop-blur-xl px-4 py-4 space-y-3 shadow-xl">
        <!-- Quick Student Greeting Banner -->
        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-full bg-teal-50 dark:bg-teal-900/40 text-primary-custom flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-navy-custom dark:text-slate-100"><?= $currentUser ? 'مرحباً، ' . htmlspecialchars($currentUser['firstName']) : 'أهلاً بك في منصتنا' ?></p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">اللغة العربية للثانوية العامة والأزهرية</p>
                </div>
            </div>
            <?php if ($currentUser): ?>
                <a href="/profile.php" class="text-xs font-bold text-primary-custom hover:underline">حسابي</a>
            <?php else: ?>
                <a href="/register.php" class="text-xs font-bold text-primary-custom hover:underline">تسجيل جديد</a>
            <?php endif; ?>
        </div>

        <!-- Navigation Links -->
        <nav class="flex flex-col space-y-1 font-bold text-sm text-slate-700 dark:text-slate-200">
            <a href="/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800/80 transition <?= empty($activeNav) || $activeNav === 'home' ? 'text-primary-custom bg-teal-50/60 dark:bg-teal-950/40' : '' ?>">
                <i class="fa-solid fa-house w-5 text-center text-primary-custom"></i>
                <span>الرئيسية</span>
            </a>
            <a href="/index.php#years" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800/80 transition">
                <i class="fa-solid fa-layer-group w-5 text-center text-gold-custom"></i>
                <span>الصفوف الدراسية</span>
            </a>
            <a href="/courses.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800/80 transition <?= ($activeNav ?? '') === 'courses' ? 'text-primary-custom bg-teal-50/60 dark:bg-teal-950/40' : '' ?>">
                <i class="fa-solid fa-book-open w-5 text-center text-primary-custom"></i>
                <span>الكورسات المتاحة</span>
            </a>
            <a href="/parent.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800/80 transition <?= ($activeNav ?? '') === 'parent' ? 'text-primary-custom bg-teal-50/60 dark:bg-teal-950/40' : '' ?>">
                <i class="fa-solid fa-user-shield w-5 text-center text-navy-custom"></i>
                <span>بوابة ولي الأمر</span>
            </a>
            <?php if ($whatsappEnabled && !empty($whatsappNumber)): ?>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsappNumber) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800/80 text-emerald-600 dark:text-emerald-400 transition">
                <i class="fa-brands fa-whatsapp w-5 text-center text-emerald-500 text-base"></i>
                <span>الدعم الفني والواتساب</span>
            </a>
            <?php endif; ?>
        </nav>

        <!-- Auth Actions inside Drawer -->
        <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80">
            <?php if ($currentUser): ?>
                <div class="flex items-center gap-2">
                    <?php if (in_array($currentUser['role'], ['ADMIN', 'SUPERADMIN'])): ?>
                        <a href="/admin/index.php" class="flex-1 py-2.5 px-3 text-center bg-navy-custom text-white text-xs font-bold rounded-xl shadow-sm">
                            <i class="fa-solid fa-gauge-high text-gold-custom ml-1"></i> لوحة التحكم
                        </a>
                    <?php endif; ?>
                    <button onclick="handleLogout()" class="flex-1 py-2.5 px-3 text-center border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 text-xs font-bold rounded-xl hover:bg-red-50 dark:hover:bg-red-950/30 transition">
                        <i class="fa-solid fa-right-from-bracket ml-1"></i> تسجيل الخروج
                    </button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-2">
                    <a href="/login.php" class="py-2.5 px-3 text-center bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs font-bold rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                        <i class="fa-solid fa-arrow-right-to-bracket ml-1"></i> تسجيل الدخول
                    </a>
                    <a href="/register.php" class="py-2.5 px-3 text-center bg-primary-custom text-white text-xs font-bold rounded-xl shadow-sm hover:bg-teal-800 transition">
                        <i class="fa-solid fa-user-plus ml-1"></i> طالب جديد
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
function toggleTheme() {
    const isDark = document.documentElement.classList.toggle('dark');
    try {
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    } catch(e) {}
}

function toggleMobileNav() {
    const menu = document.getElementById('mobileNavMenu');
    const icon = document.getElementById('mobileMenuIcon');
    if (!menu) return;
    
    const isOpening = menu.classList.contains('hidden');
    if (isOpening) {
        menu.classList.remove('hidden');
        menu.classList.add('animate-menu-slide');
        if (icon) {
            icon.className = 'fa-solid fa-xmark text-sm text-red-500 transition-transform duration-200';
        }
    } else {
        menu.classList.add('hidden');
        menu.classList.remove('animate-menu-slide');
        if (icon) {
            icon.className = 'fa-solid fa-bars text-sm transition-transform duration-200';
        }
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(e) {
    const menu = document.getElementById('mobileNavMenu');
    const btn = document.getElementById('mobileMenuBtn');
    if (!menu || !btn || menu.classList.contains('hidden')) return;
    if (!menu.contains(e.target) && !btn.contains(e.target)) {
        toggleMobileNav();
    }
});

// Close mobile menu on resize to desktop
window.addEventListener('resize', function() {
    if (window.innerWidth >= 768) {
        const menu = document.getElementById('mobileNavMenu');
        const icon = document.getElementById('mobileMenuIcon');
        if (menu && !menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
            menu.classList.remove('animate-menu-slide');
            if (icon) icon.className = 'fa-solid fa-bars text-sm transition-transform duration-200';
        }
    }
});

function handleLogout() {
    if (confirm('هل تريد تسجيل الخروج؟')) {
        window.location.href = '/logout.php';
    }
}
</script>
