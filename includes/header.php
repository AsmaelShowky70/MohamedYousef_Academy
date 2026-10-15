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
        }
        html.dark .bg-background:hover, html.dark button.bg-background:hover {
            background-color: #162c44 !important;
            color: #ffffff !important;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">

<!-- Top Announcement Bar -->
<div class="bg-navy-custom text-white text-xs py-2 px-4 text-center font-medium flex items-center justify-center gap-3">
    <span><i class="fa-solid fa-graduation-cap text-gold-custom mr-1"></i> مرحباً بكم في منصة الأستاذ محمد يوسف التعليمية للغة العربية</span>
    <span class="hidden md:inline text-slate-300">|</span>
    <span class="hidden md:inline"><i class="fa-solid fa-phone text-gold-custom mr-1"></i> الدعم الفني: 01092037257</span>
</div>

<!-- Main Navigation Header -->
<header class="sticky top-0 z-50 glass-nav border-b border-slate-200/80 shadow-sm transition-all duration-300">
    <div class="container mx-auto px-4 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <!-- Brand / Logo -->
            <a href="/index.php" class="flex items-center gap-3 group">
                <img src="/public/identity/logo2.svg" alt="شعار المنصة" class="h-12 w-auto object-contain transition-transform group-hover:scale-105" onerror="this.src='/public/identity/logo.png'">
                <div class="flex flex-col">
                    <span class="text-xl font-extrabold text-navy-custom leading-tight">منصة أ. محمد يوسف</span>
                    <span class="text-xs font-semibold text-primary-custom">اللغة العربية للثانوية العامة والأزهرية</span>
                </div>
            </a>

            <!-- Navigation Links -->
            <nav class="hidden md:flex items-center gap-7 font-bold text-sm text-slate-700">
                <a href="/index.php" class="hover:text-primary-custom transition-colors <?= empty($activeNav) || $activeNav === 'home' ? 'text-primary-custom font-extrabold' : '' ?>">
                    <i class="fa-solid fa-house ml-1"></i> الرئيسية
                </a>
                <a href="/index.php#years" class="hover:text-primary-custom transition-colors">
                    <i class="fa-solid fa-layer-group ml-1"></i> الصفوف الدراسية
                </a>
                <a href="/index.php#courses" class="hover:text-primary-custom transition-colors">
                    <i class="fa-solid fa-book-open ml-1"></i> الكورسات المتاحة
                </a>
                <a href="/parent.php" class="hover:text-primary-custom transition-colors <?= ($activeNav ?? '') === 'parent' ? 'text-primary-custom font-extrabold' : '' ?>">
                    <i class="fa-solid fa-user-shield ml-1"></i> بوابة ولي الأمر
                </a>
            </nav>

            <!-- User Auth & Theme Controls -->
            <div class="flex items-center gap-3">
                <!-- Theme Toggle Button -->
                <button type="button" onclick="toggleTheme()" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 shadow-sm transition" title="تبديل الوضع الليلي / الفاتح">
                    <svg id="themeIconSun" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="block dark:hidden">
                        <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
                    </svg>
                    <svg id="themeIconMoon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden dark:block text-yellow-400">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                    </svg>
                </button>

                <?php if ($currentUser): ?>
                    <?php if (in_array($currentUser['role'], ['ADMIN', 'SUPERADMIN'])): ?>
                        <a href="/admin/index.php" class="px-4 py-2 bg-navy-custom text-white text-sm font-bold rounded-lg shadow-sm hover:bg-slate-800 transition flex items-center gap-2">
                            <i class="fa-solid fa-gauge-high text-gold-custom"></i> لوحة التحكم
                        </a>
                    <?php endif; ?>
                    <a href="/profile.php" class="px-4 py-2 bg-primary-custom text-white text-sm font-bold rounded-lg shadow-sm hover:bg-teal-800 transition flex items-center gap-2">
                        <i class="fa-solid fa-user-graduate"></i> <?= htmlspecialchars($currentUser['firstName']) ?>
                    </a>
                    <button onclick="handleLogout()" class="p-2 text-slate-500 hover:text-red-600 transition" title="تسجيل الخروج">
                        <i class="fa-solid fa-right-from-bracket text-lg"></i>
                    </button>
                <?php else: ?>
                    <a href="/login.php" class="px-4 py-2 text-sm font-bold text-navy-custom dark:text-slate-200 hover:text-primary-custom transition">
                        تسجيل الدخول
                    </a>
                    <a href="/register.php" class="px-5 py-2.5 bg-primary-custom text-white text-sm font-bold rounded-lg shadow-md hover:bg-teal-800 hover:shadow-lg transition-all flex items-center gap-2">
                        <i class="fa-solid fa-user-plus text-xs"></i> طالب جديد
                    </a>
                <?php endif; ?>
            </div>
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

function handleLogout() {
    if (confirm('هل تريد تسجيل الخروج؟')) {
        window.location.href = '/logout.php';
    }
}
</script>
