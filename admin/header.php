<?php
require_once __DIR__ . '/../config/helpers.php';
$adminUser = requireAdmin();
$db = getDB();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminTitle ?? 'لوحة التحكم للمسؤول - منصة أ. محمد يوسف') ?></title>
    <link rel="icon" href="/public/identity/logo.png" type="image/png">
    
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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/css/app.css">
    
    <style>
        :root {
            --font-family: 'Cairo', 'Tajawal', sans-serif;
            --background: #f1f5f9;
            --card: #ffffff;
            --PPrimaryColor: #0f766e;
            --PButtonColor: #c79a32;
            --PThirdColor: #0d3753;
        }
        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            margin: 0;
            padding: 0;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        /* Color Utilities & Fallbacks (Guarantees buttons & texts are 100% visible) */
        .bg-primary-custom, .bg-\[\#0f766e\], [class*="bg-[#0f766e]"] {
            background-color: #0f766e !important;
            color: #ffffff !important;
        }
        .hover\:bg-\[\#0d3753\]:hover, .hover\:bg-navy-custom:hover {
            background-color: #0d3753 !important;
            color: #ffffff !important;
        }
        .hover\:bg-\[\#0f766e\]:hover, .hover\:bg-primary-custom:hover {
            background-color: #0f766e !important;
            color: #ffffff !important;
        }
        .bg-navy-custom, .bg-\[\#0d3753\], [class*="bg-[#0d3753]"] {
            background-color: #0d3753 !important;
        }
        .text-navy-custom, .text-\[\#0d3753\] {
            color: #0d3753 !important;
        }
        .bg-gold-custom, .bg-\[\#c79a32\], [class*="bg-[#c79a32]"] {
            background-color: #c79a32 !important;
        }
        .text-gold-custom, .text-\[\#c79a32\] {
            color: #c79a32 !important;
        }
        .text-primary-custom, .text-\[\#0f766e\] {
            color: #0f766e !important;
        }
        .border-\[\#0f766e\] {
            border-color: #0f766e !important;
        }
        .focus\:border-\[\#0f766e\]:focus {
            border-color: #0f766e !important;
        }

        /* Add / Create buttons styling */
        .btn-add-action {
            background-color: #0f766e !important;
            color: #ffffff !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.12) !important;
        }
        .btn-add-action:hover {
            background-color: #0d3753 !important;
            color: #ffffff !important;
        }

        /* ======================================================== */
        /* Admin Dark Mode Styles */
        /* ======================================================== */
        html.dark body {
            background-color: #090e17 !important;
            color: #f1f5f9 !important;
        }
        html.dark header {
            background-color: rgba(14, 25, 38, 0.95) !important;
            border-color: #1e3347 !important;
        }
        html.dark header h1 {
            color: #f8fafc !important;
        }
        html.dark aside {
            background-color: #0b1522 !important;
            border-color: #1e3347 !important;
        }
        html.dark aside a {
            color: #94a3b8 !important;
        }
        html.dark aside a:hover {
            background-color: #132337 !important;
            color: #f8fafc !important;
        }
        html.dark aside a.bg-white {
            background-color: #162c44 !important;
            color: #2dd4bf !important;
            box-shadow: none !important;
        }
        html.dark .bg-white, html.dark [class*="bg-white"] {
            background-color: #101e2e !important;
            color: #f8fafc !important;
            border-color: #1e3347 !important;
        }
        html.dark .bg-slate-50, html.dark [class*="bg-slate-50"] {
            background-color: #0d1825 !important;
            color: #f8fafc !important;
        }
        html.dark .bg-slate-100, html.dark [class*="bg-slate-100"] {
            background-color: #132337 !important;
            color: #f8fafc !important;
        }
        html.dark .bg-slate-100\/70 {
            background-color: #090e17 !important;
        }
        html.dark .border-slate-100, html.dark .border-slate-200, html.dark .border-slate-200\/80, html.dark .border-slate-200\/90, html.dark .border-slate-300, html.dark [class*="border-slate-"] {
            border-color: #1e3347 !important;
        }
        html.dark .text-slate-900, html.dark .text-slate-800, html.dark .text-slate-700, html.dark [class*="text-slate-900"], html.dark [class*="text-slate-800"], html.dark [class*="text-slate-700"] {
            color: #f8fafc !important;
        }
        html.dark .text-slate-600, html.dark .text-slate-500, html.dark .text-slate-400, html.dark [class*="text-slate-600"], html.dark [class*="text-slate-500"] {
            color: #94a3b8 !important;
        }
        html.dark input, html.dark select, html.dark textarea {
            background-color: #0d1825 !important;
            color: #f8fafc !important;
            border-color: #1e3347 !important;
        }
        html.dark input:focus, html.dark select:focus, html.dark textarea:focus, html.dark .focus\:bg-white:focus {
            background-color: #132337 !important;
            border-color: #0f766e !important;
            color: #ffffff !important;
        }
        html.dark select option {
            background-color: #0d1825 !important;
            color: #f8fafc !important;
        }
        html.dark input::placeholder, html.dark textarea::placeholder {
            color: #64748b !important;
        }
        html.dark table thead tr, html.dark table thead {
            background-color: #0e1b29 !important;
            border-color: #1e3347 !important;
        }
        html.dark table thead th {
            color: #94a3b8 !important;
        }
        html.dark table tbody tr {
            border-color: #1e3347 !important;
        }
        html.dark table tbody tr:hover {
            background-color: #132337 !important;
        }
        html.dark .shadow-sm, html.dark .shadow {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.4) !important;
        }
        html.dark .text-navy-custom, html.dark .text-\[\#0d3753\] {
            color: #f8fafc !important;
        }
        html.dark #themeToggleBtn {
            background-color: #101e2e !important;
            border-color: #1e3347 !important;
            color: #f8fafc !important;
        }

        /* Dark Mode Light Surfaces & Cards */
        html.dark .bg-\[\#f8fafc\] {
            background-color: #0b1623 !important;
            border-color: #1e3347 !important;
        }
        html.dark .bg-\[\#f4f7fa\], html.dark .bg-\[\#f4fafa\] {
            background-color: #090e17 !important;
        }
        html.dark .bg-\[\#eef3f8\], html.dark .bg-\[\#eef3f8\]\/70 {
            background-color: #0d1825 !important;
        }
        html.dark .bg-\[\#e8f5f5\] {
            background-color: #1a3333 !important;
        }

        /* Badges & Icons in Dark Mode */
        html.dark .bg-emerald-50, html.dark .bg-emerald-100 {
            background-color: rgba(16, 185, 129, 0.18) !important;
            border-color: rgba(16, 185, 129, 0.3) !important;
        }
        html.dark .text-emerald-600, html.dark .text-emerald-700, html.dark .text-emerald-800 {
            color: #34d399 !important;
        }
        html.dark .bg-blue-50, html.dark .bg-blue-100 {
            background-color: rgba(59, 130, 246, 0.18) !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
        }
        html.dark .text-blue-600, html.dark .text-blue-700, html.dark .text-blue-800 {
            color: #60a5fa !important;
        }
        html.dark .bg-indigo-50, html.dark .bg-indigo-100 {
            background-color: rgba(99, 102, 241, 0.18) !important;
            border-color: rgba(99, 102, 241, 0.3) !important;
        }
        html.dark .text-indigo-600, html.dark .text-indigo-700, html.dark .text-indigo-800 {
            color: #818cf8 !important;
        }
        html.dark .bg-amber-50, html.dark .bg-amber-100, html.dark .bg-yellow-50 {
            background-color: rgba(245, 158, 11, 0.18) !important;
            border-color: rgba(245, 158, 11, 0.3) !important;
        }
        html.dark .text-amber-600, html.dark .text-amber-700, html.dark .text-amber-800, html.dark .text-yellow-700 {
            color: #fbbf24 !important;
        }
        html.dark .bg-red-50, html.dark .bg-red-100 {
            background-color: rgba(239, 68, 68, 0.18) !important;
            border-color: rgba(239, 68, 68, 0.3) !important;
        }
        html.dark .text-red-500, html.dark .text-red-600, html.dark .text-red-700, html.dark .text-red-800 {
            color: #f87171 !important;
        }
        html.dark .bg-teal-50, html.dark .bg-teal-100 {
            background-color: rgba(15, 118, 110, 0.2) !important;
            border-color: rgba(15, 118, 110, 0.35) !important;
        }
        html.dark .bg-green-50 {
            background-color: rgba(34, 197, 94, 0.18) !important;
            border-color: rgba(34, 197, 94, 0.3) !important;
        }
        html.dark .text-green-700, html.dark .text-green-600 {
            color: #4ade80 !important;
        }
        html.dark .bg-slate-200 {
            background-color: #334155 !important;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100/70 text-slate-900 antialiased flex flex-col">

<!-- Top Navigation Bar -->
<header class="h-16 w-full border-b border-slate-200 bg-white/95 px-6 lg:px-8 flex items-center justify-between sticky top-0 z-30 shadow-sm backdrop-blur-md">
    <!-- Right side: Title -->
    <div class="flex items-center gap-3">
        <h1 class="text-xl md:text-2xl font-black text-[#0d3753] tracking-tight">لوح�    <!-- Left side: Controls & User -->
    <div class="flex items-center gap-3">
        <!-- Admin Profile Info -->
        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-slate-100/90 rounded-xl text-xs font-bold border border-slate-200/80">
            <i class="fa-solid <?= isSuperAdmin($adminUser) ? 'fa-crown text-amber-500' : 'fa-user-shield text-teal-600' ?>"></i>
            <span class="text-slate-800"><?= htmlspecialchars($adminUser['firstName'] ?? $adminUser['fullName'] ?? 'المسؤول') ?></span>
            <span class="text-[10px] font-black px-1.5 py-0.5 rounded <?= isSuperAdmin($adminUser) ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-teal-100 text-teal-800 border border-teal-200' ?>">
                <?= isSuperAdmin($adminUser) ? 'المالك' : 'مشرف' ?>
            </span>
        </div>

        <!-- Sun/Moon Theme Toggle Button -->
        <button type="button" id="themeToggleBtn" onclick="toggleTheme()" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-100 shadow-sm transition cursor-pointer" title="تبديل الوضع الليلي / الفاتح">
            <!-- Sun icon (shown in light mode) -->
            <svg id="themeIconSun" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="block dark:hidden">
                <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
            </svg>
            <!-- Moon icon (shown in dark mode) -->
            <svg id="themeIconMoon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden dark:block text-yellow-400">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
            </svg>
        </button>

        <!-- View Website Link -->
        <a href="/index.php" title="زيارة الموقع الرئيسي" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-50 border border-teal-200/80 px-3 py-1.5 text-xs font-bold text-[#0f766e] hover:bg-teal-100 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            <span>الموقع</span>
        </a>

        <!-- Logout Link -->
        <a href="/logout.php" title="تسجيل الخروج" class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 p-2 text-red-600 hover:bg-red-100 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</header>

<!-- Main Page Layout Container (Sidebar on Right, Content on Left) -->
<div class="flex-1 flex w-full">
    <!-- Right Sidebar Navigation -->
    <aside class="w-64 flex-shrink-0 border-l border-slate-200 bg-[#eef3f8]/70 p-4 space-y-1.5 min-h-[calc(100vh-64px)]">
        <a href="/admin/index.php" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold transition <?= ($activeAdminNav ?? '') === 'dashboard' ? 'bg-white text-[#0f766e] shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
            <span>لوحة التحكم</span>
        </a>

        <?php if (hasAdminPermission('students', $adminUser)): ?>
        <a href="/admin/users.php" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold transition <?= ($activeAdminNav ?? '') === 'users' ? 'bg-white text-[#0f766e] shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>المستخدمين والطلاب</span>
        </a>
        <?php endif; ?>

        <?php if (hasAdminPermission('courses', $adminUser)): ?>
        <a href="/admin/courses.php" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold transition <?= ($activeAdminNav ?? '') === 'courses' ? 'bg-white text-[#0f766e] shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            <span>الكورسات</span>
        </a>

        <a href="/admin/years.php" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold transition <?= ($activeAdminNav ?? '') === 'years' ? 'bg-white text-[#0f766e] shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>السنوات الدراسية</span>
        </a>
        <?php endif; ?>

        <?php if (hasAdminPermission('content', $adminUser)): ?>
        <a href="/admin/content.php" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold transition <?= ($activeAdminNav ?? '') === 'content' ? 'bg-white text-[#0f766e] shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>المحتوى</span>
        </a>
        <?php endif; ?>

        <?php if (hasAdminPermission('codes', $adminUser)): ?>
        <a href="/admin/codes.php" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold transition <?= ($activeAdminNav ?? '') === 'codes' ? 'bg-white text-[#0f766e] shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <span>الأكواد</span>
        </a>
        <?php endif; ?>

        <?php if (hasAdminPermission('marks', $adminUser)): ?>
        <a href="/admin/studentsmarks.php" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold transition <?= ($activeAdminNav ?? '') === 'marks' ? 'bg-white text-[#0f766e] shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
            <span>درجات الطلاب والغياب</span>
        </a>
        <?php endif; ?>

        <?php if (hasAdminPermission('settings', $adminUser)): ?>
        <a href="/admin/settings.php" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold transition <?= ($activeAdminNav ?? '') === 'settings' ? 'bg-white text-[#0f766e] shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            <span>الإعدادات</span>
        </a>
        <?php endif; ?>

        <?php if (isSuperAdmin($adminUser)): ?>
        <div class="pt-3 pb-1 border-t border-slate-200/80 my-2">
            <span class="px-3 text-[10px] font-black text-amber-800 tracking-wider uppercase block mb-1">خاص بالمالك</span>
            <a href="/admin/admins.php" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold transition <?= ($activeAdminNav ?? '') === 'admins' ? 'bg-amber-100/80 text-amber-900 shadow-sm border border-amber-300' : 'text-amber-900 bg-amber-50/70 hover:bg-amber-100/90' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-amber-600"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <span>إدارة المشرفين</span>
                <span class="px-1.5 py-0.5 text-[10px] bg-amber-200 text-amber-900 rounded font-black mr-auto">المالك</span>
            </a>
        </div>
        <?php endif; ?>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto">

    <?php if (!empty($_SESSION['admin_flash_msg'])): ?>
        <div class="p-4 rounded-xl text-xs font-bold flex items-center gap-2 <?= ($_SESSION['admin_flash_type'] ?? '') === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' ?>">
            <i class="fa-solid fa-circle-exclamation text-base"></i>
            <span><?= htmlspecialchars($_SESSION['admin_flash_msg']) ?></span>
        </div>
        <?php unset($_SESSION['admin_flash_msg'], $_SESSION['admin_flash_type']); ?>
    <?php endif; ?>

<script>
function toggleTheme() {
    const isDark = document.documentElement.classList.toggle('dark');
    try {
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    } catch(e) {}
}
</script>
