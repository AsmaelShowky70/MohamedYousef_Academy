<?php
require_once __DIR__ . '/config/helpers.php';
$user = requireAuth();
$db = getDB();

$msg = '';
$msgType = '';

// Active Tab
$tab = $_GET['tab'] ?? 'info';
if ($tab === '' || $tab === 'profile') {
    $tab = 'info';
}

// Handle Profile Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['fullName'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $parentPhone = trim($_POST['parentPhone'] ?? '');
    $yearId = !empty($_POST['yearId']) ? $_POST['yearId'] : null;
    $password = trim($_POST['password'] ?? '');
    $repassword = trim($_POST['repassword'] ?? '');

    $nameParts = array_filter(explode(' ', $fullName));
    if (count($nameParts) < 2) {
        $msg = 'الاسم غير صالح (يرجى إدخال اسمك ثلاثي باللغة العربية)';
        $msgType = 'error';
    } elseif (!preg_match('/^(010|011|012|015)\d{8}$/', $phone)) {
        $msg = 'رقم الهاتف يجب أن يبدأ بـ 010, 011, 012 أو 015 وأن يتكون من 11 رقم';
        $msgType = 'error';
    } elseif ($parentPhone && !preg_match('/^(010|011|012|015)\d{8}$/', $parentPhone)) {
        $msg = 'رقم ولي الأمر يجب أن يبدأ بـ 010, 011, 012 أو 015 وأن يتكون من 11 رقم';
        $msgType = 'error';
    } elseif ($password !== '' && strlen($password) < 5) {
        $msg = 'كلمة المرور يجب ألا تقل عن 5 أحرف';
        $msgType = 'error';
    } elseif ($password !== '' && $password !== $repassword) {
        $msg = 'كلمة المرور لا تطابق التأكيد';
        $msgType = 'error';
    } else {
        if ($phone !== $user['phone']) {
            $chk = $db->prepare("SELECT id FROM User WHERE phone = ? AND id != ?");
            $chk->execute([$phone, $user['id']]);
            if ($chk->fetch()) {
                $msg = 'رقم الهاتف هذا مسجل بالفعل لحساب آخر';
                $msgType = 'error';
            }
        }

        if (!$msg) {
            $firstName = $nameParts[0] ?? $fullName;
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE User SET fullName = ?, firstName = ?, phone = ?, parentPhone = ?, yearId = ?, password = ? WHERE id = ?");
                $stmt->execute([$fullName, $firstName, $phone, $parentPhone, $yearId, $hashed, $user['id']]);
            } else {
                $stmt = $db->prepare("UPDATE User SET fullName = ?, firstName = ?, phone = ?, parentPhone = ?, yearId = ? WHERE id = ?");
                $stmt->execute([$fullName, $firstName, $phone, $parentPhone, $yearId, $user['id']]);
            }

            $_SESSION['user_id'] = $user['id'];
            $user = getCurrentUser();
            $msg = 'تم تحديث البيانات بنجاح!';
            $msgType = 'success';
        }
    }
}

// Fetch user year name
$userYearName = 'لم يتم التحديد';
if (!empty($user['yearId'])) {
    $yStmt = $db->prepare("SELECT name FROM Year WHERE id = ?");
    $yStmt->execute([$user['yearId']]);
    $userYearName = $yStmt->fetchColumn() ?: 'لم يتم التحديد';
}

$years = getCachedYears($db);

// Fetch Connected Devices
$stmtDev = $db->prepare("SELECT * FROM Device WHERE userId = ? ORDER BY lastActiveAt DESC");
$stmtDev->execute([$user['id']]);
$userDevices = $stmtDev->fetchAll();

// Data for Tabs
$enrolledCourses = [];
$enrolledCollections = [];
$quizAttempts = [];
$activeCodes = [];

if ($tab === 'my-courses') {
    $stmtCourses = $db->prepare("
        SELECT DISTINCT c.*, s.title AS sectionTitle, y.name AS yearName 
        FROM (
            SELECT A AS courseId FROM _CourseToUser WHERE B = ?
            UNION
            SELECT courseId FROM CourseCode WHERE userId = ? AND (collectionId IS NULL OR collectionId = '') AND expires > NOW()
        ) enrolled
        JOIN Course c ON enrolled.courseId = c.id 
        LEFT JOIN Section s ON c.sectionId = s.id 
        LEFT JOIN Year y ON s.yearId = y.id 
        ORDER BY c.id DESC
    ");
    $stmtCourses->execute([$user['id'], $user['id']]);
    $enrolledCourses = $stmtCourses->fetchAll();

    $stmtColls = $db->prepare("
        SELECT DISTINCT cl.*, c.title AS courseTitle 
        FROM (
            SELECT A AS colId FROM _CollectionToUser WHERE B = ?
            UNION
            SELECT collectionId AS colId FROM CourseCode WHERE userId = ? AND collectionId IS NOT NULL AND expires > NOW()
        ) enrolled
        JOIN Collection cl ON enrolled.colId = cl.id
        LEFT JOIN Course c ON cl.courseId = c.id
        ORDER BY cl.id DESC
    ");
    $stmtColls->execute([$user['id'], $user['id']]);
    $enrolledCollections = $stmtColls->fetchAll();
} elseif ($tab === 'my-quizzes') {
    $stmtQA = $db->prepare("
        SELECT qa.*, q.title AS quiz_title, c.title AS course_title
        FROM QuizAttempt qa
        JOIN Quiz q ON qa.quizId = q.id
        LEFT JOIN Course c ON q.courseId = c.id
        WHERE qa.userId = ?
        ORDER BY qa.submittedAt DESC
    ");
    $stmtQA->execute([$user['id']]);
    $quizAttempts = $stmtQA->fetchAll();
} elseif ($tab === 'code' || $tab === 'collection-code') {
    $stmtCodes = $db->prepare("
        SELECT cc.*, c.title AS courseTitle, col.title AS collectionTitle, cg.maxUses 
        FROM CourseCode cc
        JOIN Course c ON cc.courseId = c.id
        LEFT JOIN Collection col ON cc.collectionId = col.id
        LEFT JOIN CodeGroup cg ON cc.codeGroupId = cg.id
        WHERE cc.userId = ? AND cc.accessTimes > 0 AND cc.expires > NOW()
        ORDER BY cc.id DESC
    ");
    $stmtCodes->execute([$user['id']]);
    $activeCodes = $stmtCodes->fetchAll();
}

// Tab Titles
$tabTitles = [
    'info' => 'البيانات الشخصية',
    'my-quizzes' => 'سجل الامتحانات',
    'my-courses' => 'كورساتي',
    'code' => 'ادخال كود',
    'collection-code' => 'ادخال كود مجموعة'
];
$currentTitle = $tabTitles[$tab] ?? 'البيانات الشخصية';
$pageTitle = $currentTitle . ' | منصة الأستاذ محمد يوسف';

// Include global standard layout header
require_once __DIR__ . '/includes/header.php';
?>

<style>
.moving-profile-bg {
    animation: 4s ease-in-out infinite alternate floatBg;
    background-size: cover;
    background-position: center;
}
@keyframes floatBg {
    0% { transform: translateY(0); }
    100% { transform: translateY(-8px); }
}
</style>

<!-- Outer Profile Layout Matching Next.js src/app/(main)/profile/layout.tsx -->
<main class="flex-grow">
    <div class="px-3 py-5 max-md:px-4 pb-24 md:pb-32">
        <!-- Banner Floating Background -->
        <div style="background-image: url('/public/profile-bg.png');" class="moving-profile-bg min-h-[300px] w-full translate-y-4 rounded-lg bg-green-200 max-md:hidden"></div>
        
        <!-- Main Profile Box Container -->
        <div class="md:px-8">
            <div class="mx-auto my-10 min-h-[500px] max-w-[1800px] rounded-lg bg-PCardColor dark:bg-[#12283a] px-5 py-10 shadow-[0px_0px_25px_1px_rgba(0,0,0,0.3)] border border-transparent dark:border-slate-700 md:-translate-y-[120px]">
                
                <div class="space-y-2">
                    <!-- Top Pill Header Matching Next.js profileUI.tsx -->
                    <div class="flex w-full items-center justify-center pb-4 text-[1.2rem] font-bold">
                        <div class="rounded-full border border-slate-200 dark:border-slate-700 bg-PCardColor dark:bg-[#0e1b29] text-slate-800 dark:text-white px-8 py-2 shadow-lg">
                            <?= htmlspecialchars($currentTitle) ?>
                        </div>
                    </div>

                    <?php if ($msg): ?>
                        <div class="mb-4 p-4 rounded-lg text-sm font-bold <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
                            <?= htmlspecialchars($msg) ?>
                        </div>
                    <?php endif; ?>

                    <!-- 2-Column Layout: Sidebar on Right (RTL) + Main Content on Left -->
                    <div class="flex gap-10 max-md:flex-col">
                        
                        <!-- Sidebar Navigation Menu Matching Next.js profileUI.tsx -->
                        <div class="max-md:min-h-[30px] max-md:w-full max-md:border-b-2 max-md:border-b-slate-300 dark:border-b-slate-700 max-md:pb-10 md:w-1/5 md:-translate-y-2">
                            <ul class="flex w-full flex-col space-y-7 text-center">
                                <?php
                                $navItems = [
                                    'info' => ['name' => 'البيانات الشخصية', 'link' => '/profile.php?tab=info', 'icon' => 'fa-solid fa-id-card'],
                                    'my-courses' => ['name' => 'كورساتي', 'link' => '/profile.php?tab=my-courses', 'icon' => 'fa-solid fa-graduation-cap'],
                                    'my-quizzes' => ['name' => 'سجل الامتحانات', 'link' => '/profile.php?tab=my-quizzes', 'icon' => 'fa-solid fa-clock-rotate-left'],
                                    'code' => ['name' => 'ادخال كود كورس', 'link' => '/profile.php?tab=code', 'icon' => 'fa-solid fa-key'],
                                    'collection-code' => ['name' => 'ادخال كود حصة', 'link' => '/profile.php?tab=collection-code', 'icon' => 'fa-solid fa-ticket'],
                                ];
                                foreach ($navItems as $key => $item):
                                    $isActive = ($tab === $key);
                                ?>
                                    <li class="w-full">
                                        <a href="<?= $item['link'] ?>" class="w-full justify-start inline-flex items-center gap-3 rounded-xl font-bold h-12 px-5 transition duration-200 cursor-pointer <?= $isActive ? 'bg-PButtonColor text-white shadow-lg pointer-events-none' : 'text-slate-700 dark:text-slate-200 shadow-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0e1b29] hover:bg-slate-100 dark:hover:bg-[#162c44]' ?>">
                                            <i class="<?= $item['icon'] ?> text-base"></i>
                                            <span><?= $item['name'] ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- Right / Main Content Area Based on Active Tab -->
                        <?php if ($tab === 'info'): ?>
                            <!-- ======================================================== -->
                            <!-- TAB 1: Personal Information Matching profileUI.tsx -->
                            <!-- ======================================================== -->
                            <div class="mt-10 flex min-h-[300px] flex-col justify-around space-y-5 rounded-[25px] border border-transparent dark:border-slate-700 md:flex-grow md:p-5 md:shadow-[0px_0px_25px_1px_rgba(10,0,0,0.1)]">
                                
                                <?php if ($user['status'] === 'PENDING'): ?>
                                    <!-- Pending Review Notice -->
                                    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
                                        <div class="flex items-start gap-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" x2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                                            <div>
                                                <h3 class="font-bold text-lg mb-1">حسابك قيد المراجعة</h3>
                                                <p class="opacity-90">حسابك حالياً قيد المراجعة من قبل الإدارة. يرجى الانتظار حتى يتم الموافقة عليه لتتمكن من تصفح وشراء الكورسات.</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Student Name & Details Block -->
                                <div class="text-center text-4xl max-md:text-3xl font-bold text-slate-900 dark:text-white">
                                    <span><?= htmlspecialchars($user['fullName'] ?: $user['firstName']) ?></span>
                                    
                                    <div class="mt-10 flex justify-between max-md:flex-col max-md:space-y-4 font-normal text-base">
                                        <!-- User Details Column -->
                                        <div class="space-y-2 text-[1.2rem] max-md:space-y-4 max-md:text-[1.1rem]">
                                            <div class="flex gap-2">
                                                <div class="flex justify-start gap-2 font-bold text-slate-900 dark:text-white">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone inline translate-y-2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                                    <span>رقم الهاتف:</span>
                                                </div>
                                                <span class="text-slate-700 dark:text-slate-300"><?= htmlspecialchars($user['phone']) ?></span>
                                            </div>

                                            <div class="flex gap-2">
                                                <div class="flex justify-start gap-2 font-bold text-slate-900 dark:text-white">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box mt-2"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                                                    <span>رقم ولي الامر:</span>
                                                </div>
                                                <span class="text-slate-700 dark:text-slate-300"><?= htmlspecialchars($user['parentPhone'] ?: 'لا يوجد') ?></span>
                                            </div>

                                            <div class="flex gap-2">
                                                <div class="flex justify-start gap-2 font-bold text-slate-900 dark:text-white">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box mt-2"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                                                    <span>الصف الدراسي:</span>
                                                </div>
                                                <span class="font-bold text-teal-600 dark:text-teal-400"><?= htmlspecialchars($userYearName) ?></span>
                                            </div>

                                            <div class="flex gap-2 items-center">
                                                <div class="flex justify-start gap-2 font-bold text-slate-900 dark:text-white">
                                                    <i class="fa-solid fa-id-badge mt-1 text-[#0f766e]"></i>
                                                    <span>نوع الحساب:</span>
                                                </div>
                                                <?php if (($user['studentType'] ?? 'ONLINE') === 'CENTER'): ?>
                                                    <span class="inline-flex items-center gap-1.5 font-black text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 px-3 py-1 rounded-xl border border-amber-300 dark:border-amber-700 text-xs">
                                                        <i class="fa-solid fa-building-columns text-amber-600"></i>
                                                        <span>طالب سنتر</span>
                                                        <?php if (!empty($user['centerCode'])): ?>
                                                            <span class="font-mono bg-white dark:bg-slate-800 px-2 py-0.5 rounded border border-amber-300 font-bold">كود: <?= htmlspecialchars($user['centerCode']) ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1.5 font-bold text-sky-800 dark:text-sky-300 bg-sky-50 dark:bg-sky-900/30 px-3 py-1 rounded-xl border border-sky-200 dark:border-sky-700 text-xs">
                                                        <i class="fa-solid fa-globe text-sky-600"></i>
                                                        <span>طالب أونلاين</span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Action Buttons Column -->
                                        <div class="flex flex-col gap-4 md:self-end text-sm">
                                            <button type="button" onclick="openModal('editProfileModal')" class="text-sm font-medium border-2 border-PButtonColor bg-PButtonColor text-white transition duration-200 hover:-translate-y-[2px] hover:border-PPrimaryColor hover:bg-white hover:text-black inline-flex items-center justify-center rounded-md h-10 px-4 py-2 <?= $user['status'] === 'PENDING' ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                                تعديل البيانات الشخصية
                                            </button>
                                            <button type="button" onclick="openModal('logoutModal')" class="text-sm font-medium border border-slate-300 dark:border-slate-600 bg-white dark:bg-[#0e1b29] text-slate-700 dark:text-slate-200 hover:text-red-600 dark:hover:text-red-400 hover:border-red-600 dark:hover:border-red-400 transition duration-200 w-full inline-flex items-center justify-center rounded-md h-10 px-4 py-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out ml-2 h-4 w-4"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                                                <span>تسجيل الخروج</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- DevicesList Matching Next.js DevicesList.tsx -->
                                <!-- DevicesList -->
                                <div class="mt-8 border-t border-slate-200 dark:border-slate-700 pt-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-PPrimaryColor"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                                            الأجهزة المرتبطة بحسابك
                                        </h3>
                                        <span class="text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold px-3 py-1 rounded-full border border-slate-200 dark:border-slate-700">
                                            الحد المسموح: <?= (int)($user['maxDevices'] ?? 1) ?> جهاز
                                        </span>
                                    </div>
                                    <div class="p-3 mb-4 rounded-xl bg-blue-50/80 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900/40 text-xs text-blue-800 dark:text-blue-300 flex items-start gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0 text-blue-600 dark:text-blue-400 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                        <div>
                                            حسابك مقيّد للعمل على جهازك المعتمد فقط لضمان أمان حسابك. في حال قمت بتغيير هاتفك أو جهازك وترغب بنقل الحساب، يرجى التواصل مع إدارة المنصة أو الدعم الفني: <strong class="underline">01092037257</strong>.
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($userDevices)): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <?php foreach ($userDevices as $dev): 
                                                $devType = strtolower($dev['deviceType'] ?? 'pc');
                                                $devTypeArabic = ($devType === 'phone') ? 'هاتف محمول' : (($devType === 'tablet') ? 'تابلت / لوحي' : (($devType === 'laptop') ? 'لابتوب' : 'كمبيوتر مكتبي (PC)'));
                                                $lastActive = !empty($dev['lastActiveAt']) ? date('Y/m/d H:i', strtotime($dev['lastActiveAt'])) : 'غير محدد';
                                            ?>
                                                <div class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-[#0b1623]" id="device-card-<?= $dev['id'] ?>">
                                                    <div class="flex items-center gap-4">
                                                        <div class="p-3 bg-white dark:bg-[#12283a] rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                                                            <?php if ($devType === 'phone'): ?>
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-500"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                                                            <?php elseif ($devType === 'tablet'): ?>
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-500"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><line x1="12" x2="12.01" y1="18" y2="18"/></svg>
                                                            <?php elseif ($devType === 'laptop'): ?>
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-500"><path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/></svg>
                                                            <?php else: ?>
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-500"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="flex items-center gap-2">
                                                                <p class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($dev['os']) ?> - <?= htmlspecialchars($dev['browser']) ?></p>
                                                                <span class="text-[11px] bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 font-medium px-2 py-0.5 rounded-full">معتمد</span>
                                                            </div>
                                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">النوع: <strong class="text-slate-700 dark:text-slate-300"><?= $devTypeArabic ?></strong></p>
                                                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">آخر نشاط: <?= $lastActive ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-slate-500 dark:text-slate-400 text-sm">سيتم ربط جهازك تلقائياً عند أول عملية دخول وتصفح للحصص.</p>
                                    <?php endif; ?>
                                </div>

                            </div>

                        <?php elseif ($tab === 'my-courses'): ?>
                            <!-- ======================================================== -->
                            <!-- TAB 2: Enrolled Courses & Collections Matching Next.js -->
                            <!-- ======================================================== -->
                            <div class="mt-10 space-y-8 rounded-[25px] p-5 md:flex-grow">
                                <div class="space-y-5">
                                    <h2 class="text-xl font-bold border-b pb-2">الكورسات المشترك بها</h2>
                                    <?php if (!empty($enrolledCourses)): ?>
                                        <div class="grid min-h-[50%] grid-cols-1 lg:grid-cols-2 gap-6">
                                            <?php foreach ($enrolledCourses as $course): 
                                                $thumb = !empty($course['thumbnail']) ? $course['thumbnail'] : (!empty($course['image']) ? '/public/uploads/' . $course['image'] : '/heroPhotos/heroBg.png');
                                            ?>
                                                <div data-sal="zoom-in" data-sal-duration="400" data-sal-delay="100" class="group relative max-w-[540px] max-lg:max-w-full">
                                                    <div class="w-full overflow-hidden rounded-md">
                                                        <img src="<?= htmlspecialchars($thumb) ?>" alt="courseImage" width="300" height="300" class="duration-400 w-full transform transition ease-in group-hover:scale-110 group-hover:brightness-110 group-hover:saturate-150 h-56 object-cover"/>
                                                    </div>
                                                    <div class="px-3">
                                                        <div class="duration-400 min-h-[200px] -translate-y-10 space-y-3 rounded-[10px] bg-PCardColor px-4 py-6 shadow-[0px_0px_25px_1px_rgba(0,0,0,0.7)] transition-all group-hover:scale-[103%] group-hover:shadow-[0px_0px_30px_2px_var(--PPrimaryColor)]">
                                                            <div class="flex items-start justify-between border-b-2 border-PPrimaryColor pb-2">
                                                                <h2 class="text-lg font-bold"><?= htmlspecialchars($course['title']) ?></h2>
                                                                <div class="max-h-fit rounded-md bg-PButtonColor px-3 py-[0.3rem] text-center text-[0.8rem] text-white transition duration-500 hover:rotate-6">
                                                                    <span class="ml-1 rounded-full bg-white px-[0.4rem] py-[0.2rem] text-black">تم الاشتراك</span>
                                                                </div>
                                                            </div>
                                                            <div class="w-3/4 space-y-5">
                                                                <p class="font-light opacity-70"><?= htmlspecialchars($course['description'] ?? '') ?></p>
                                                            </div>
                                                            <div class="flex flex-col justify-center gap-3">
                                                                <a href="/course.php?id=<?= urlencode($course['id']) ?>" class="w-full text-center border-2 border-PButtonColor bg-PButtonColor text-white transition duration-200 hover:-translate-y-[2px] hover:border-PPrimaryColor hover:bg-white hover:text-black py-2.5 rounded-xl font-bold cursor-pointer block">
                                                                    الدخول إلي الكورس
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted-foreground text-sm">لا توجد كورسات مدفوعة حالياً.</p>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($enrolledCollections)): ?>
                                    <div class="space-y-5 pt-6 border-t">
                                        <h2 class="text-xl font-bold border-b pb-2">الدروس المشترك بها</h2>
                                        <div class="grid min-h-[50%] grid-cols-1 lg:grid-cols-2 gap-6">
                                            <?php foreach ($enrolledCollections as $coll): 
                                                $collThumb = !empty($coll['thumbnail']) ? $coll['thumbnail'] : '';
                                                $cTitle = $coll['courseTitle'] ?? '';
                                            ?>
                                                <div data-sal="zoom-in" data-sal-duration="400" data-sal-delay="100" class="group relative max-w-[540px] max-lg:max-w-full">
                                                    <div class="w-full overflow-hidden rounded-md bg-PPrimaryColor/10 min-h-[160px] flex items-center justify-center">
                                                        <?php if ($collThumb): ?>
                                                            <img src="<?= htmlspecialchars($collThumb) ?>" alt="collectionImage" width="300" height="300" class="duration-400 w-full transform transition ease-in group-hover:scale-110 group-hover:brightness-110 group-hover:saturate-150 object-cover h-44"/>
                                                        <?php else: ?>
                                                            <div class="text-4xl font-bold text-PPrimaryColor p-8 text-center"><?= htmlspecialchars($coll['title']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="px-3">
                                                        <div class="duration-400 min-h-[180px] -translate-y-10 space-y-3 rounded-[10px] bg-PCardColor px-4 py-6 shadow-[0px_0px_25px_1px_rgba(0,0,0,0.7)] transition-all group-hover:scale-[103%] group-hover:shadow-[0px_0px_30px_2px_var(--PPrimaryColor)]">
                                                            <div class="flex items-start justify-between border-b-2 border-PPrimaryColor pb-2">
                                                                <div>
                                                                    <h2 class="text-lg font-bold"><?= htmlspecialchars($coll['title']) ?></h2>
                                                                    <?php if ($cTitle): ?>
                                                                        <span class="text-xs text-muted-foreground block mt-1">من كورس: <?= htmlspecialchars($cTitle) ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="max-h-fit rounded-md bg-amber-500 px-3 py-[0.3rem] text-center text-[0.8rem] text-white transition duration-500 hover:rotate-6">
                                                                    <span class="ml-1 rounded-full bg-white px-[0.4rem] py-[0.2rem] text-black font-semibold">درس</span>
                                                                </div>
                                                            </div>
                                                            <div class="w-3/4 space-y-5">
                                                                <p class="font-light opacity-70 text-sm">درس تم الاشتراك به بنجاح.</p>
                                                            </div>
                                                            <div class="flex flex-col justify-center gap-3">
                                                                <a href="/collection.php?id=<?= urlencode($coll['id']) ?>" class="w-full text-center border-2 border-PButtonColor bg-PButtonColor text-white transition duration-200 hover:-translate-y-[2px] hover:border-PPrimaryColor hover:bg-white hover:text-black font-bold py-2.5 rounded-xl cursor-pointer block">
                                                                    الدخول إلي الدرس
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php elseif ($tab === 'my-quizzes'): ?>
                            <!-- ======================================================== -->
                            <!-- TAB 3: Quiz Attempts Matching Next.js my-quizzes -->
                            <!-- ======================================================== -->
                            <div class="mt-10 px-10 py-8 max-md:px-5 md:flex-grow">
                                <div class="w-full space-y-5">
                                    <div dir="ltr" class="relative w-full overflow-auto">
                                        <table class="w-full caption-bottom text-sm border-collapse">
                                            <thead class="[&_tr]:border-b">
                                                <tr class="border-b transition-colors hover:bg-muted/50">
                                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Quiz</th>
                                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Course</th>
                                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Score</th>
                                                    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Date</th>
                                                </tr>
                                            </thead>
                                            <tbody class="[&_tr:last-child]:border-0">
                                                <?php if (empty($quizAttempts)): ?>
                                                    <tr>
                                                        <td colspan="4" class="p-4 align-middle text-center text-muted-foreground">No quiz attempts yet.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($quizAttempts as $qa): 
                                                        $solved = !empty($qa['solvedCorrectly']) ? json_decode($qa['solvedCorrectly'], true) : [];
                                                        $solvedCount = is_array($solved) ? count($solved) : (int)($qa['score'] ?? 0);
                                                        $numQ = (int)($qa['numberOfQuestion'] ?? 0);
                                                        $scoreStr = "{$solvedCount} / {$numQ}";
                                                        $dateStr = !empty($qa['submittedAt']) ? date('m/d/Y, h:i A', strtotime($qa['submittedAt'])) : '---';
                                                    ?>
                                                        <tr class="border-b transition-colors hover:bg-muted/50">
                                                            <td class="p-4 align-middle font-medium"><?= htmlspecialchars($qa['quiz_title'] ?? '---') ?></td>
                                                            <td class="p-4 align-middle"><?= htmlspecialchars($qa['course_title'] ?? '---') ?></td>
                                                            <td class="p-4 align-middle font-bold text-primary"><?= htmlspecialchars($scoreStr) ?></td>
                                                            <td class="p-4 align-middle font-mono text-xs"><?= htmlspecialchars($dateStr) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        <?php elseif ($tab === 'code' || $tab === 'collection-code'): ?>
                            <!-- ======================================================== -->
                            <!-- TAB 4 & 5: Code / Collection Code Matching Next.js -->
                            <!-- ======================================================== -->
                            <div class="mt-10 space-y-5 rounded-[25px] md:flex-grow">
                                
                                <!-- CodeForm -->
                                <form id="codeForm" onsubmit="promptCodeSubmit(event)" class="container mx-auto space-y-5">
                                    <input type="text" id="courseCodeInput" placeholder="ادخل الكود" class="placeholder:text-PTextColor/60 w-full rounded-md border border-slate-400 bg-transparent px-4 py-4 outline-none transition duration-300 ease-in-out focus:ring-2 focus:ring-PPrimaryColor focus:ring-opacity-50 disabled:opacity-50 disabled:cursor-not-allowed" required <?= $user['status'] === 'PENDING' ? 'disabled' : '' ?>>
                                    <button type="submit" id="codeSubmitBtn" <?= $user['status'] === 'PENDING' ? 'disabled' : '' ?> class="rounded-md border border-PButtonColor bg-PButtonColor px-5 py-2 font-bold text-white transition duration-300 ease-in-out hover:cursor-pointer hover:bg-white hover:text-black max-sm:w-full <?= $user['status'] === 'PENDING' ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                        استخدام الكود
                                    </button>
                                </form>

                                <!-- Active Codes (UsedCodes) -->
                                <?php if (!empty($activeCodes)): ?>
                                    <div class="mt-8 space-y-4 w-full px-2 md:px-6">
                                        <h3 class="text-xl font-bold border-b pb-2 text-PPrimaryColor">أكوادك الفعالة</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <?php foreach ($activeCodes as $ac): ?>
                                                <div class="border border-slate-300 dark:border-slate-700 rounded-xl p-4 bg-white dark:bg-slate-800 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                                                    <div class="space-y-2 mb-4">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <h4 class="font-bold text-base text-black dark:text-white">
                                                                <?= !empty($ac['collectionTitle']) ? 'حصة: ' . htmlspecialchars($ac['collectionTitle']) : htmlspecialchars($ac['courseTitle']) ?>
                                                            </h4>
                                                            <?php if (!empty($ac['collectionTitle'])): ?>
                                                                <span class="text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 px-2 py-0.5 rounded-full shrink-0">
                                                                    🎯 كود حصة
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-[10px] font-bold bg-teal-100 text-teal-800 dark:bg-teal-950/60 dark:text-teal-300 px-2 py-0.5 rounded-full shrink-0">
                                                                    كورس كامل
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($ac['collectionTitle'])): ?>
                                                            <span class="text-xs text-muted-foreground block font-medium">كورس: <?= htmlspecialchars($ac['courseTitle']) ?></span>
                                                        <?php endif; ?>
                                                        <div class="flex items-center justify-between bg-slate-100 dark:bg-slate-900 px-3 py-1.5 rounded-lg border dark:border-slate-700">
                                                            <span class="font-mono text-sm tracking-widest text-slate-800 dark:text-slate-200"><?= htmlspecialchars($ac['code']) ?></span>
                                                            <button type="button" onclick="copyCode('<?= htmlspecialchars($ac['code']) ?>', this)" class="text-slate-500 hover:text-PPrimaryColor transition cursor-pointer" title="نسخ الكود">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                                            </button>
                                                        </div>
                                                        <div class="flex justify-between items-center text-sm mt-3">
                                                            <span class="text-slate-600 dark:text-slate-400">تاريخ الانتهاء:</span>
                                                            <span class="font-semibold text-red-500" dir="ltr"><?= date('Y/m/d h:i A', strtotime($ac['expires'])) ?></span>
                                                        </div>
                                                        <div class="flex justify-between items-center text-sm">
                                                            <span class="text-slate-600 dark:text-slate-400">الاستخدامات المتبقية:</span>
                                                            <span class="font-bold text-PPrimaryColor px-2 py-0.5 bg-PPrimaryColor/10 rounded-full"><?= $ac['accessTimes'] ?> <?= !empty($ac['maxUses']) ? "من {$ac['maxUses']}" : "" ?></span>
                                                        </div>
                                                    </div>
                                                    <a href="<?= !empty($ac['collectionId']) ? '/collection.php?id=' . urlencode($ac['collectionId']) : '/course.php?id=' . urlencode($ac['courseId']) ?>" class="w-full text-center font-bold bg-PPrimaryColor hover:bg-teal-700 text-white shadow-sm py-2.5 rounded-xl cursor-pointer transition block">
                                                        <?= !empty($ac['collectionTitle']) ? 'مشاهدة الحصة الآن' : 'استخدام الآن' ?>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<!-- ======================================================== -->
<!-- Modal 1: Edit Profile Modal (Exact Next.js Sheet Content) -->
<!-- ======================================================== -->
<div id="editProfileModal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4 overflow-y-auto" onclick="if(event.target === this) closeModal('editProfileModal')">
    <div class="bg-PBackgroundColor border dark:border-slate-700 text-right rounded-2xl max-w-lg w-full p-6 shadow-2xl relative my-8">
        <button type="button" onclick="closeModal('editProfileModal')" class="absolute left-4 top-4 text-slate-400 hover:text-slate-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>

        <div class="my-2 flex items-center border-b border-slate-400 pb-3">
            <h3 class="text-lg font-bold">تعديل البيانات الشخصية</h3>
        </div>

        <form method="POST" action="/profile.php?tab=info" class="mt-4 flex flex-col gap-6 text-right">
            <input type="hidden" name="update_profile" value="1"/>

            <div class="space-y-2">
                <label for="fullName" class="text-sm font-medium">الاسم الكامل (ثلاثي باللغة العربية)</label>
                <input type="text" name="fullName" id="fullName" value="<?= htmlspecialchars($user['fullName'] ?: $user['firstName']) ?>" placeholder="أدخل اسمك الثلاثي باللغة العربية" required class="w-full rounded-md border border-slate-400 bg-transparent px-4 py-2 outline-none transition duration-300 ease-in-out focus:ring-2 focus:ring-PPrimaryColor focus:ring-opacity-50"/>
            </div>

            <div class="space-y-2">
                <label for="phone" class="text-sm font-medium">رقم الهاتف</label>
                <input type="text" name="phone" id="phone" value="<?= htmlspecialchars($user['phone']) ?>" placeholder="أدخل رقم الهاتف" required dir="ltr" class="w-full rounded-md border border-slate-400 bg-transparent px-4 py-2 outline-none transition duration-300 ease-in-out focus:ring-2 focus:ring-PPrimaryColor focus:ring-opacity-50"/>
            </div>

            <div class="space-y-2">
                <label for="parentPhone" class="text-sm font-medium">رقم ولي الأمر</label>
                <input type="text" name="parentPhone" id="parentPhone" value="<?= htmlspecialchars($user['parentPhone'] ?? '') ?>" placeholder="أدخل رقم ولي الأمر" dir="ltr" class="w-full rounded-md border border-slate-400 bg-transparent px-4 py-2 outline-none transition duration-300 ease-in-out focus:ring-2 focus:ring-PPrimaryColor focus:ring-opacity-50"/>
            </div>

            <div class="space-y-2">
                <label for="password" class="text-sm font-medium">كلمة المرور الجديدة (اختياري)</label>
                <input type="password" name="password" id="password" placeholder="اتركها فارغة لإبقائها كما هي" class="w-full rounded-md border border-slate-400 bg-transparent px-4 py-2 outline-none transition duration-300 ease-in-out focus:ring-2 focus:ring-PPrimaryColor focus:ring-opacity-50"/>
            </div>

            <div class="space-y-2">
                <label for="repassword" class="text-sm font-medium">تأكيد كلمة المرور الجديدة</label>
                <input type="password" name="repassword" id="repassword" placeholder="تأكيد كلمة المرور الجديدة" class="w-full rounded-md border border-slate-400 bg-transparent px-4 py-2 outline-none transition duration-300 ease-in-out focus:ring-2 focus:ring-PPrimaryColor focus:ring-opacity-50"/>
            </div>

            <div class="space-y-2">
                <label for="yearId" class="text-sm font-medium">الصف الدراسي</label>
                <select name="yearId" id="yearId" class="w-full rounded-md border border-slate-400 bg-transparent px-4 py-2 outline-none transition duration-300 ease-in-out focus:ring-2 focus:ring-PPrimaryColor focus:ring-opacity-50">
                    <option value="">اختر الصف الدراسي</option>
                    <?php foreach ($years as $yr): ?>
                        <option value="<?= htmlspecialchars($yr['id']) ?>" <?= ($user['yearId'] === $yr['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($yr['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="mt-4 border-2 border-PButtonColor bg-PButtonColor text-white transition duration-200 hover:-translate-y-[2px] hover:border-PPrimaryColor hover:bg-white hover:text-black rounded-md font-medium text-sm h-10 px-4 py-2">
                حفظ التغييرات
            </button>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- Modal 2: Logout Confirmation Dialog (Exact Next.js z Component) -->
<!-- ======================================================== -->
<div id="logoutModal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4" onclick="if(event.target === this) closeModal('logoutModal')">
    <div class="bg-PBackgroundColor border dark:border-slate-700 text-right rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-center">
        <h3 class="text-center font-bold text-xl text-black dark:text-white">هل انت متأكد ؟</h3>
        <p class="text-center text-muted-foreground mt-2 text-sm">هل تريد تسجيل الخروج من الحساب ؟</p>
        
        <div class="flex justify-center gap-4 mt-6">
            <button type="button" onclick="closeModal('logoutModal')" class="border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 rounded-md font-medium h-10 px-6 transition text-sm">
                إلغاء
            </button>
            <a href="/logout.php" class="bg-destructive text-destructive-foreground hover:bg-destructive/90 rounded-md font-medium h-10 px-8 transition text-sm inline-flex items-center justify-center">
                تسجيل خروج
            </a>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- Modal 3: Code Caution Dialog (Exact Next.js Dialog in CodeForm) -->
<!-- ======================================================== -->
<div id="codeConfirmModal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4" onclick="if(event.target === this) closeModal('codeConfirmModal')">
    <div class="border-none bg-PBackgroundColor rounded-2xl max-w-lg w-full p-6 shadow-2xl relative">
        <button type="button" onclick="closeModal('codeConfirmModal')" class="absolute left-4 top-4 text-slate-400 hover:text-slate-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>

        <div class="mr-8 text-right">
            <h3 class="font-bold text-lg text-slate-900">تنبيه</h3>
            <p class="text-right text-red-600 text-sm mt-2 font-medium">
                تنبيه: يمكنك استخدام هذا الكود ومشاهدة الكورس عدد مرات محددة, احرص علي عدم غلق المتصفح حتي لا تفقد الكورس
            </p>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" onclick="closeModal('codeConfirmModal')" class="border border-slate-300 rounded-md px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                إلغاء
            </button>
            <button type="button" id="confirmRedeemBtn" onclick="executeCodeRedeem()" class="text-sm rounded-md border border-PButtonColor bg-PButtonColor px-5 py-2 font-bold text-white transition duration-300 ease-in-out hover:cursor-pointer hover:bg-white hover:text-black">
                تأكيد
            </button>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('hidden');
        el.style.display = 'flex';
    }
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('hidden');
        el.style.display = 'none';
    }
}


let pendingCode = '';
function promptCodeSubmit(e) {
    e.preventDefault();
    const code = document.getElementById('courseCodeInput').value.trim();
    if (!code) return;
    pendingCode = code;
    openModal('codeConfirmModal');
}

function executeCodeRedeem() {
    if (!pendingCode) return;
    const btn = document.getElementById('confirmRedeemBtn');
    btn.disabled = true;
    btn.innerText = 'جاري التفعيل...';

    fetch('/api/custom.php?action=activate-code', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: pendingCode })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerText = 'تأكيد';
        closeModal('codeConfirmModal');
        if (data.success) {
            alert(data.message || 'تمت العملية بنجاح');
            if (data.courseId) {
                window.location.href = '/course.php?id=' + encodeURIComponent(data.courseId);
            } else {
                window.location.reload();
            }
        } else {
            alert(data.error || 'الكود خاطئ, رجاء التاكد من الكود مجدداً');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerText = 'تأكيد';
        alert('حدث خطأ أثناء معالجة الكود');
    });
}

function copyCode(code, btn) {
    navigator.clipboard.writeText(code).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-green-500"><polyline points="20 6 9 17 4 12"/></svg>';
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    });
}
</script>

<?php
// Include global standard layout footer
require_once __DIR__ . '/includes/footer.php';
?>
