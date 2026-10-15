<?php
$adminTitle = "لوحة التحكم للمسؤول - منصة أ. محمد يوسف";
$activeAdminNav = "dashboard";
require_once __DIR__ . '/header.php';

// Stats queries
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM User WHERE role = 'STUDENT'")->fetchColumn();
$activeStudents = (int)$db->query("SELECT COUNT(*) FROM User WHERE role = 'STUDENT' AND status = 'ACTIVE'")->fetchColumn();
$pendingStudents = (int)$db->query("SELECT COUNT(*) FROM User WHERE role = 'STUDENT' AND status = 'PENDING'")->fetchColumn();
$bannedStudents = (int)$db->query("SELECT COUNT(*) FROM User WHERE role = 'STUDENT' AND status = 'BANNED'")->fetchColumn();
$totalAdmins = (int)$db->query("SELECT COUNT(*) FROM User WHERE role IN ('ADMIN', 'SUPERADMIN')")->fetchColumn();

$totalCourses = (int)$db->query("SELECT COUNT(*) FROM Course")->fetchColumn();
$totalCodes = (int)$db->query("SELECT COUNT(*) FROM CourseCode")->fetchColumn();
$usedCodes = (int)$db->query("SELECT COUNT(*) FROM CourseCode WHERE userId IS NOT NULL")->fetchColumn();

$totalIncome = 0.00;
try {
    // 1. Income from redeemed codes
    $codeIncomeStmt = $db->query("
        SELECT COALESCE(SUM(
            CASE 
                WHEN cc.collectionId IS NOT NULL THEN COALESCE(col.price, 0)
                ELSE COALESCE(c.price, 0)
            END
        ), 0) as total
        FROM CourseCode cc
        LEFT JOIN Course c ON cc.courseId = c.id
        LEFT JOIN Collection col ON cc.collectionId = col.id
        WHERE cc.userId IS NOT NULL
    ");
    $codeIncome = (float)($codeIncomeStmt ? $codeIncomeStmt->fetchColumn() : 0);

    // 2. Direct enrolled courses via admin or purchases
    $directCourseIncome = (float)$db->query("
        SELECT COALESCE(SUM(c.price), 0) 
        FROM _CourseToUser cu 
        JOIN Course c ON cu.A = c.id
    ")->fetchColumn();

    // 3. Direct enrolled collections
    $directColIncome = (float)$db->query("
        SELECT COALESCE(SUM(col.price), 0) 
        FROM _CollectionToUser clu 
        JOIN Collection col ON clu.A = col.id
    ")->fetchColumn();

    $totalIncome = $codeIncome + $directCourseIncome + $directColIncome;
} catch (Exception $e) {
    $totalIncome = 0.00;
}

$totalQuizAttempts = 0;
try {
    $totalQuizAttempts = (int)$db->query("SELECT COUNT(*) FROM QuizAttempt")->fetchColumn();
} catch (Exception $e) {}

// Storage calculation
$storageMB = 2.94;
$storageTotalGB = 1.0;
$storagePercent = round(($storageMB / ($storageTotalGB * 1024)) * 100, 1);

// Recent 5 Students
$stmtRecent = $db->query("
    SELECT u.*, y.name as yearName 
    FROM User u 
    LEFT JOIN Year y ON u.yearId = y.id 
    WHERE u.role = 'STUDENT' 
    ORDER BY u.joinedAt DESC 
    LIMIT 5
");
$recentStudents = $stmtRecent->fetchAll();
?>

<!-- Header Title -->
<div class="space-y-1">
    <h2 class="text-3xl font-extrabold text-[#0d3753] tracking-tight">لوحة التحكم</h2>
    <p class="text-sm font-medium text-slate-500">نظرة عامة على أداء المنصة والعمليات.</p>
</div>

<!-- 4 Top Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- Card 1: Total Income -->
    <div class="rounded-xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-[#101e2e] p-6 shadow-sm border-r-4 border-r-emerald-500 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div class="rounded-full bg-emerald-50 dark:bg-emerald-950/50 p-3 text-emerald-600 dark:text-emerald-400">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
            </div>
            <div class="text-left space-y-1">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">إجمالي الدخل</p>
                <div class="flex items-baseline justify-end gap-1">
                    <span class="text-2xl font-black text-slate-900 dark:text-white"><?= number_format($totalIncome, 0) ?></span>
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">ج.م</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Total Students -->
    <div class="rounded-xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-[#101e2e] p-6 shadow-sm border-r-4 border-r-blue-500 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div class="rounded-full bg-blue-50 dark:bg-blue-950/50 p-3 text-blue-600 dark:text-blue-400">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="text-left space-y-1">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">إجمالي الطلاب</p>
                <div class="flex items-baseline justify-end gap-1">
                    <span class="text-2xl font-black text-slate-900 dark:text-white"><?= $totalStudents ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Total Courses -->
    <div class="rounded-xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-[#101e2e] p-6 shadow-sm border-r-4 border-r-indigo-500 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div class="rounded-full bg-indigo-50 dark:bg-indigo-950/50 p-3 text-indigo-600 dark:text-indigo-400">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <div class="text-left space-y-1">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">إجمالي الكورسات</p>
                <div class="flex items-baseline justify-end gap-1">
                    <span class="text-2xl font-black text-slate-900 dark:text-white"><?= $totalCourses ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Storage Usage -->
    <div class="rounded-xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-[#101e2e] p-6 shadow-sm border-r-4 border-r-slate-400 hover:shadow-md transition">
        <div class="flex items-center justify-between mb-3">
            <div class="rounded-full bg-slate-100 dark:bg-slate-800 p-3 text-slate-500 dark:text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
            </div>
            <div class="text-left space-y-1">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">مساحة التخزين المستخدمة</p>
                <div class="flex items-baseline justify-end gap-1">
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-200">MB <?= $storageMB ?></span>
                    <span class="text-xs text-slate-400">/ <?= $storageTotalGB ?> GB</span>
                </div>
            </div>
        </div>
        <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden flex" dir="ltr">
            <div class="h-2 rounded-full bg-slate-400 dark:bg-slate-500 transition-all duration-1000" style="width: <?= max(1, $storagePercent) ?>%"></div>
        </div>
        <p class="text-[10px] text-slate-400 mt-1.5 text-left"><?= $storagePercent ?>% مستخدم</p>
    </div>
</div>

<!-- Main Insights & Registrations Grid -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    <!-- User Insights (Right 8 Cols in RTL) -->
    <div class="lg:col-span-8 rounded-2xl border border-slate-200/90 bg-white shadow-sm p-6 space-y-6">
        <div>
            <h3 class="text-lg font-black text-[#0d3753]">إحصائيات المستخدمين والتفاعل</h3>
            <p class="text-xs text-slate-400 mt-0.5">تفاصيل حالة الحسابات والمشاركة</p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <!-- Box 1: Active -->
            <div class="p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-[#f8fafc] dark:bg-[#0b1623] space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-400">طلاب نشطين</span>
                    <svg class="h-4 w-4 text-emerald-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white"><?= $activeStudents ?></div>
            </div>

            <!-- Box 2: Pending -->
            <div class="p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-[#f8fafc] dark:bg-[#0b1623] space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-400">قيد الانتظار</span>
                    <svg class="h-4 w-4 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white"><?= $pendingStudents ?></div>
            </div>

            <!-- Box 3: Banned -->
            <div class="p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-[#f8fafc] dark:bg-[#0b1623] space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-400">محظورين</span>
                    <svg class="h-4 w-4 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white"><?= $bannedStudents ?></div>
            </div>

            <!-- Box 4: Admins -->
            <div class="p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-[#f8fafc] dark:bg-[#0b1623] space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-400">المشرفين</span>
                    <svg class="h-4 w-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white"><?= $totalAdmins ?></div>
            </div>

            <!-- Box 5: Quiz Attempts -->
            <div class="p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-[#f8fafc] dark:bg-[#0b1623] space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-400">محاولات الامتحانات</span>
                    <svg class="h-4 w-4 text-cyan-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white"><?= $totalQuizAttempts ?></div>
            </div>

            <!-- Box 6: Used Codes -->
            <div class="p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-[#f8fafc] dark:bg-[#0b1623] space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-400">أكواد مستخدمة</span>
                    <svg class="h-4 w-4 text-orange-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 2-2 2m-1.5 1.5L10 13l-4 4 1 1 2-2 1 1-2 2 1 1 2-2 1.5 1.5a2 2 0 0 0 2.83 0l6.17-6.17a2 2 0 0 0 0-2.83Z"/></svg>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white"><?= $usedCodes ?></div>
            </div>
        </div>
    </div>

    <!-- Recent Registrations (Left 4 Cols in RTL) -->
    <div class="lg:col-span-4 rounded-2xl border border-slate-200/90 bg-white shadow-sm p-6 space-y-4">
        <div>
            <h3 class="text-lg font-black text-[#0d3753]">أحدث التسجيلات</h3>
            <p class="text-xs text-slate-400 mt-0.5">آخر 5 طلاب انضموا للمنصة</p>
        </div>

        <div class="space-y-3 pt-2">
            <?php foreach ($recentStudents as $s): ?>
                <div class="flex items-center justify-between p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-[#f8fafc] dark:bg-[#0b1623] hover:bg-slate-100/60 dark:hover:bg-[#132337] transition">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">نشط</span>
                    </div>
                    <div class="text-left min-w-0">
                        <p class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= htmlspecialchars($s['fullName'] ?? $s['firstName']) ?></p>
                        <p class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($s['phone']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
