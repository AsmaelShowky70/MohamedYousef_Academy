<?php
require_once __DIR__ . '/../config/helpers.php';
$adminUser = requireAdmin();
requireAdminPermission('students');
$db = getDB();

$studentId = $_GET['id'] ?? ($_POST['student_id'] ?? '');

if (!$studentId) {
    $adminTitle = "ملف الطالب والمتابعة الشاملة - لوحة التحكم";
    $activeAdminNav = "users";
    require_once __DIR__ . '/header.php';
    echo "<div class='p-6 text-center text-red-600 font-bold'>لم يتم تحديد معرّف الطالب! <a href='users.php' class='underline'>العودة للطلاب</a></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

$stmtUser = $db->prepare("SELECT u.*, y.name AS yearName FROM User u LEFT JOIN Year y ON u.yearId = y.id WHERE u.id = ? AND u.role = 'STUDENT'");
$stmtUser->execute([$studentId]);
$student = $stmtUser->fetch();

if (!$student) {
    $adminTitle = "ملف الطالب والمتابعة الشاملة - لوحة التحكم";
    $activeAdminNav = "users";
    require_once __DIR__ . '/header.php';
    echo "<div class='p-6 text-center text-red-600 font-bold'>الطالب غير موجود أو تم حذفه! <a href='users.php' class='underline'>العودة للطلاب</a></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// Flash Message from session
$msg = $_SESSION['student_msg'] ?? '';
$msgType = $_SESSION['student_msg_type'] ?? '';
unset($_SESSION['student_msg'], $_SESSION['student_msg_type']);

// Handle POST actions with PRG (Post-Redirect-Get) BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['fullName'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $parentPhone = trim($_POST['parentPhone'] ?? '');
        $yearId = !empty($_POST['yearId']) ? $_POST['yearId'] : null;
        $attendanceID = !empty($_POST['attendanceID']) ? (int)$_POST['attendanceID'] : null;
        $status = $_POST['status'] ?? 'ACTIVE';
        $maxDevices = max(1, (int)($_POST['maxDevices'] ?? 1));
        $newPassword = trim($_POST['new_password'] ?? '');

        if (!$fullName || !$phone) {
            $_SESSION['student_msg'] = 'الاسم ورقم الهاتف حقول إجبارية!';
            $_SESSION['student_msg_type'] = 'error';
        } else {
            // Check phone uniqueness
            $chk = $db->prepare("SELECT id FROM User WHERE phone = ? AND id != ?");
            $chk->execute([$phone, $studentId]);
            if ($chk->fetch()) {
                $_SESSION['student_msg'] = 'رقم الهاتف مسجل لطالب آخر!';
                $_SESSION['student_msg_type'] = 'error';
            } else {
                $parts = explode(' ', $fullName);
                $firstName = $parts[0] ?? $fullName;
                $studentType = ($_POST['studentType'] ?? 'ONLINE') === 'CENTER' ? 'CENTER' : 'ONLINE';
                $centerCode = ($studentType === 'CENTER') ? trim($_POST['centerCode'] ?? '') : null;
                if ($centerCode === '') $centerCode = null;

                if ($newPassword !== '') {
                    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                    $up = $db->prepare("UPDATE User SET fullName = ?, firstName = ?, phone = ?, parentPhone = ?, yearId = ?, attendanceID = ?, status = ?, maxDevices = ?, studentType = ?, centerCode = ?, password = ? WHERE id = ?");
                    $up->execute([$fullName, $firstName, $phone, $parentPhone, $yearId, $attendanceID, $status, $maxDevices, $studentType, $centerCode, $hashed, $studentId]);
                } else {
                    $up = $db->prepare("UPDATE User SET fullName = ?, firstName = ?, phone = ?, parentPhone = ?, yearId = ?, attendanceID = ?, status = ?, maxDevices = ?, studentType = ?, centerCode = ? WHERE id = ?");
                    $up->execute([$fullName, $firstName, $phone, $parentPhone, $yearId, $attendanceID, $status, $maxDevices, $studentType, $centerCode, $studentId]);
                }
                $_SESSION['student_msg'] = 'تم تحديث بيانات الطالب بنجاح!';
                $_SESSION['student_msg_type'] = 'success';
            }
        }
        header("Location: student_profile.php?id=" . urlencode($studentId));
        exit;
    }

    if ($action === 'toggle_ban') {
        $newStatus = ($student['status'] === 'BANNED') ? 'ACTIVE' : 'BANNED';
        $db->prepare("UPDATE User SET status = ? WHERE id = ?")->execute([$newStatus, $studentId]);
        $_SESSION['student_msg'] = ($newStatus === 'BANNED') ? 'تم حظر الطالب بنجاح' : 'تم فك الحظر عن الطالب بنجاح';
        $_SESSION['student_msg_type'] = 'success';
        header("Location: student_profile.php?id=" . urlencode($studentId));
        exit;
    }

    if ($action === 'unlink_device') {
        $devId = $_POST['deviceId'] ?? '';
        if ($devId) {
            $db->prepare("DELETE FROM Device WHERE id = ? AND userId = ?")->execute([$devId, $studentId]);
            $_SESSION['student_msg'] = 'تم إزالة الجهاز بنجاح من حساب الطالب!';
            $_SESSION['student_msg_type'] = 'success';
        }
        header("Location: student_profile.php?id=" . urlencode($studentId));
        exit;
    }

    if ($action === 'reset_all_devices') {
        $db->prepare("DELETE FROM Device WHERE userId = ?")->execute([$studentId]);
        $_SESSION['student_msg'] = 'تم فك ربط جميع أجهزة الطالب بنجاح! يمكنه الآن تسجيل الدخول من جهاز جديد.';
        $_SESSION['student_msg_type'] = 'success';
        header("Location: student_profile.php?id=" . urlencode($studentId));
        exit;
    }

    if ($action === 'enroll_course') {
        $courseId = $_POST['courseId'] ?? '';
        if ($courseId) {
            $chk = $db->prepare("SELECT 1 FROM _CourseToUser WHERE A = ? AND B = ?");
            $chk->execute([$courseId, $studentId]);
            if (!$chk->fetch()) {
                $db->prepare("INSERT INTO _CourseToUser (A, B) VALUES (?, ?)")->execute([$courseId, $studentId]);
                $_SESSION['student_msg'] = 'تم إضافة الكورس للطالب مباشرة بنجاح!';
                $_SESSION['student_msg_type'] = 'success';
            } else {
                $_SESSION['student_msg'] = 'الطالب مشترك بالفعل في هذا الكورس!';
                $_SESSION['student_msg_type'] = 'error';
            }
        }
        header("Location: student_profile.php?id=" . urlencode($studentId));
        exit;
    }

    if ($action === 'unenroll_course') {
        $courseId = $_POST['courseId'] ?? '';
        if ($courseId) {
            $db->prepare("DELETE FROM _CourseToUser WHERE A = ? AND B = ?")->execute([$courseId, $studentId]);
            $_SESSION['student_msg'] = 'تم إلغاء اشتراك الطالب من الكورس بنجاح!';
            $_SESSION['student_msg_type'] = 'success';
        }
        header("Location: student_profile.php?id=" . urlencode($studentId));
        exit;
    }

    if ($action === 'enroll_collection') {
        $collectionId = $_POST['collectionId'] ?? '';
        if ($collectionId) {
            $chk = $db->prepare("SELECT 1 FROM _CollectionToUser WHERE A = ? AND B = ?");
            $chk->execute([$collectionId, $studentId]);
            if (!$chk->fetch()) {
                $db->prepare("INSERT INTO _CollectionToUser (A, B) VALUES (?, ?)")->execute([$collectionId, $studentId]);
                $_SESSION['student_msg'] = 'تم إضافة الحصة / الدرس للطالب مباشرة بنجاح!';
                $_SESSION['student_msg_type'] = 'success';
            } else {
                $_SESSION['student_msg'] = 'الطالب مشترك بالفعل في هذه الحصة!';
                $_SESSION['student_msg_type'] = 'error';
            }
        }
        header("Location: student_profile.php?id=" . urlencode($studentId));
        exit;
    }

    if ($action === 'unenroll_collection') {
        $collectionId = $_POST['collectionId'] ?? '';
        if ($collectionId) {
            $db->prepare("DELETE FROM _CollectionToUser WHERE A = ? AND B = ?")->execute([$collectionId, $studentId]);
            $_SESSION['student_msg'] = 'تم إلغاء اشتراك الطالب من الحصة بنجاح!';
            $_SESSION['student_msg_type'] = 'success';
        }
        header("Location: student_profile.php?id=" . urlencode($studentId));
        exit;
    }
}

$adminTitle = "ملف الطالب والمتابعة الشاملة - لوحة التحكم";
$activeAdminNav = "users";
require_once __DIR__ . '/header.php';

// 1. Fetch Student Devices
$stmtDev = $db->prepare("SELECT * FROM Device WHERE userId = ? ORDER BY lastActiveAt DESC");
$stmtDev->execute([$studentId]);
$devices = $stmtDev->fetchAll();

// 2. Fetch Enrolled Courses
$stmtEnrolledCourses = $db->prepare("
    SELECT c.*, s.title AS sectionTitle, y.name AS yearName 
    FROM _CourseToUser cu 
    JOIN Course c ON cu.A = c.id 
    LEFT JOIN Section s ON c.sectionId = s.id 
    LEFT JOIN Year y ON s.yearId = y.id 
    WHERE cu.B = ? 
    ORDER BY c.title ASC
");
$stmtEnrolledCourses->execute([$studentId]);
$enrolledCourses = $stmtEnrolledCourses->fetchAll();
$enrolledCourseIds = array_column($enrolledCourses, 'id');

// All Courses (for enrollment dropdown)
$allCourses = $db->query("SELECT c.id, c.title, y.name AS yearName FROM Course c LEFT JOIN Section s ON c.sectionId = s.id LEFT JOIN Year y ON s.yearId = y.id ORDER BY y.name ASC, c.title ASC")->fetchAll();

// 3. Fetch Enrolled Collections (Lessons)
$stmtEnrolledColls = $db->prepare("
    SELECT cl.*, c.title AS courseTitle 
    FROM _CollectionToUser cu 
    JOIN Collection cl ON cu.A = cl.id 
    LEFT JOIN Course c ON cl.courseId = c.id 
    WHERE cu.B = ? 
    ORDER BY cl.title ASC
");
$stmtEnrolledColls->execute([$studentId]);
$enrolledCollections = $stmtEnrolledColls->fetchAll();
$enrolledCollectionIds = array_column($enrolledCollections, 'id');

// All Collections (for lesson dropdown)
$allCollections = $db->query("SELECT cl.id, cl.title, cl.price, c.title AS courseTitle FROM Collection cl LEFT JOIN Course c ON cl.courseId = c.id ORDER BY c.title ASC, cl.order ASC")->fetchAll();

// 4. Video Watch Analytics (#15)
// Find all accessible videos (from enrolled courses OR enrolled collections)
$targetVideos = [];
if (!empty($enrolledCourseIds) || !empty($enrolledCollectionIds)) {
    $whereParts = [];
    $vParams = [];
    if (!empty($enrolledCourseIds)) {
        $inCourses = implode(',', array_fill(0, count($enrolledCourseIds), '?'));
        $whereParts[] = "v.courseId IN ($inCourses)";
        $vParams = array_merge($vParams, $enrolledCourseIds);
    }
    if (!empty($enrolledCollectionIds)) {
        $inColls = implode(',', array_fill(0, count($enrolledCollectionIds), '?'));
        $whereParts[] = "v.collectionId IN ($inColls)";
        $vParams = array_merge($vParams, $enrolledCollectionIds);
    }
    $whereSql = implode(' OR ', $whereParts);
    $stmtV = $db->prepare("
        SELECT v.*, c.title AS courseTitle, cl.title AS collectionTitle,
               vv.viewCount, vv.updatedAt AS lastViewedAt
        FROM Video v
        LEFT JOIN Course c ON v.courseId = c.id
        LEFT JOIN Collection cl ON v.collectionId = cl.id
        LEFT JOIN VideoView vv ON (vv.videoId = v.id AND vv.userId = ?)
        WHERE ($whereSql)
        ORDER BY c.title ASC, v.order ASC
    ");
    $stmtV->execute(array_merge([$studentId], $vParams));
    $targetVideos = $stmtV->fetchAll();
}

$watchedVideosCount = 0;
foreach ($targetVideos as $tv) {
    if (!empty($tv['viewCount']) && $tv['viewCount'] > 0) {
        $watchedVideosCount++;
    }
}
$unwatchedVideosCount = count($targetVideos) - $watchedVideosCount;

// 5. Quiz Performance Analytics (#15)
// Find all quizzes in enrolled courses/collections
$targetQuizzes = [];
if (!empty($enrolledCourseIds) || !empty($enrolledCollectionIds)) {
    $qWhereParts = [];
    $qParams = [];
    if (!empty($enrolledCourseIds)) {
        $inCourses = implode(',', array_fill(0, count($enrolledCourseIds), '?'));
        $qWhereParts[] = "q.courseId IN ($inCourses)";
        $qParams = array_merge($qParams, $enrolledCourseIds);
    }
    if (!empty($enrolledCollectionIds)) {
        $inColls = implode(',', array_fill(0, count($enrolledCollectionIds), '?'));
        $qWhereParts[] = "q.collectionId IN ($inColls)";
        $qParams = array_merge($qParams, $enrolledCollectionIds);
    }
    $qWhereSql = implode(' OR ', $qWhereParts);
    $stmtQ = $db->prepare("
        SELECT q.*, c.title AS courseTitle, cl.title AS collectionTitle
        FROM Quiz q
        LEFT JOIN Course c ON q.courseId = c.id
        LEFT JOIN Collection cl ON q.collectionId = cl.id
        WHERE ($qWhereSql)
        ORDER BY c.title ASC, q.order ASC
    ");
    $stmtQ->execute($qParams);
    $targetQuizzes = $stmtQ->fetchAll();
}

// Student Attempts
$stmtAttempts = $db->prepare("
    SELECT qa.*, q.title AS quizTitle, c.title AS courseTitle
    FROM QuizAttempt qa
    JOIN Quiz q ON qa.quizId = q.id
    LEFT JOIN Course c ON q.courseId = c.id
    WHERE qa.userId = ?
    ORDER BY qa.submittedAt DESC
");
$stmtAttempts->execute([$studentId]);
$attempts = $stmtAttempts->fetchAll();

$attemptsByQuiz = [];
foreach ($attempts as $at) {
    $attemptsByQuiz[$at['quizId']][] = $at;
}

$quizzesTakenCount = count($attemptsByQuiz);
$quizzesUntakenCount = max(0, count($targetQuizzes) - $quizzesTakenCount);

// Calculate Average Score
$totalScorePercent = 0;
$scoresList = [];
foreach ($attemptsByQuiz as $qId => $atts) {
    $latest = $atts[0];
    $solved = json_decode($latest['solvedCorrectly'], true);
    $solvedCount = is_array($solved) ? count($solved) : (int)$latest['solvedCorrectly'];
    $numQ = (int)$latest['numberOfQuestion'];
    $pct = ($numQ > 0) ? round(($solvedCount / $numQ) * 100) : 0;
    $scoresList[] = $pct;
    $totalScorePercent += $pct;
}
$avgScore = count($scoresList) > 0 ? round($totalScorePercent / count($scoresList)) : 0;

$years = $db->query("SELECT id, name FROM Year ORDER BY `order` ASC")->fetchAll();
?>

<!-- Print Style Customization -->
<style>
@media print {
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 12pt;
    }
    header, nav, aside, .no-print, button, .btn-action, form select, form input {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    #printable-report {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .print-break-inside-avoid {
        page-break-inside: avoid;
    }
}
.print-only {
    display: none;
}
</style>

<!-- Top Breadcrumbs & Actions -->
<div class="no-print flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <a href="users.php" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition flex items-center justify-center" title="رجوع لقائمة الطلاب">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
        </a>
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="text-2xl font-black text-[#0d3753] tracking-tight">ملف الطالب: <?= htmlspecialchars($student['fullName'] ?: $student['firstName']) ?></h2>
                <?php if (($student['studentType'] ?? 'ONLINE') === 'CENTER'): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-amber-100 text-amber-900 border border-amber-300">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 9h1"/><path d="M9 13h1"/><path d="M9 17h1"/><path d="M14 9h1"/><path d="M14 13h1"/><path d="M14 17h1"/></svg>
                        <span>طالب سنتر</span>
                        <?php if (!empty($student['centerCode'])): ?>
                            <span class="font-mono bg-white px-2 py-0.5 rounded border border-amber-300 text-amber-950 font-black">كود: <?= htmlspecialchars($student['centerCode']) ?></span>
                        <?php endif; ?>
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-sky-100 text-sky-800 border border-sky-300">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                        <span>طالب أونلاين</span>
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-500 font-medium mt-1">متابعة دقيقة للأداء، المشاهدات، الامتحانات، وإدارة الأجهزة والاشتراكات المباشرة</p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="window.print()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-800 hover:bg-black text-white font-bold text-xs rounded-xl shadow transition cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
            <span>طباعة التقرير / حفظ PDF</span>
        </button>
        <form method="POST" onsubmit="return confirm('هل تريد تغيير حالة حظر هذا الطالب؟')">
            <input type="hidden" name="action" value="toggle_ban">
            <?php if ($student['status'] === 'BANNED'): ?>
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span>فك الحظر</span>
                </button>
            <?php else: ?>
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                    <span>حظر الحساب</span>
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($msg): ?>
    <div class="no-print mb-6 p-4 rounded-xl text-xs font-bold <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- PRINT ONLY HEADER (visible only when printing or saving as PDF) -->
<div class="print-only mb-6 border-b-2 border-slate-900 pb-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900">منصة الأستاذ محمد يوسف التعليمية</h1>
            <p class="text-sm text-slate-600 mt-1">تقرير أداء ومتابعة الطالب الشامل</p>
            <p class="text-xs text-slate-500 mt-1 font-bold">
                <?= ($student['studentType'] ?? 'ONLINE') === 'CENTER' ? '🏢 طالب سنتر - كود: ' . htmlspecialchars($student['centerCode'] ?: '---') : '🌐 طالب أونلاين' ?>
            </p>
        </div>
        <div class="text-left text-xs text-slate-500 font-mono">
            <p>تاريخ التقرير: <?= date('Y/m/d H:i') ?></p>
            <p>كود الحضور: <?= htmlspecialchars($student['attendanceID'] ?: '---') ?></p>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- TOP OVERVIEW SUMMARY CARDS -->
<!-- ======================================================== -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-teal-50 border border-teal-200 text-[#0f766e] flex items-center justify-center shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10"/><path d="M6 10h10"/></svg>
        </div>
        <div>
            <p class="text-xs text-slate-500 font-medium">الكورسات المشترك بها</p>
            <h3 class="text-xl font-black text-slate-900"><?= count($enrolledCourses) ?></h3>
        </div>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        </div>
        <div>
            <p class="text-xs text-slate-500 font-medium">الحصص المستمعة</p>
            <h3 class="text-xl font-black text-slate-900"><?= $watchedVideosCount ?> <span class="text-xs text-slate-400 font-normal">/ <?= count($targetVideos) ?></span></h3>
        </div>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-600 flex items-center justify-center shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
        <div>
            <p class="text-xs text-slate-500 font-medium">الامتحانات المنجزة</p>
            <h3 class="text-xl font-black text-slate-900"><?= $quizzesTakenCount ?> <span class="text-xs text-slate-400 font-normal">/ <?= count($targetQuizzes) ?></span></h3>
        </div>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div>
            <p class="text-xs text-slate-500 font-medium">متوسط الدرجات</p>
            <h3 class="text-xl font-black text-emerald-700"><?= $avgScore ?>%</h3>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- ======================================================== -->
    <!-- COLUMN 1: STUDENT PROFILE & DEVICE MANAGEMENT -->
    <!-- ======================================================== -->
    <div class="space-y-6">
        <!-- Edit Profile Form (Requirement #3) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h3 class="text-base font-black text-slate-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-[#0f766e]"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                تعديل بيانات الطالب
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_profile">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الاسم بالكامل</label>
                    <input type="text" name="fullName" value="<?= htmlspecialchars($student['fullName'] ?: $student['firstName']) ?>" required class="w-full px-3 py-2 text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-[#0f766e] outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رقم الهاتف</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($student['phone']) ?>" required dir="ltr" class="w-full px-3 py-2 text-xs font-mono font-bold bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-[#0f766e] outline-none text-right">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رقم ولي الأمر</label>
                        <input type="text" name="parentPhone" value="<?= htmlspecialchars($student['parentPhone'] ?? '') ?>" dir="ltr" class="w-full px-3 py-2 text-xs font-mono font-bold bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-[#0f766e] outline-none text-right">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الصف الدراسي</label>
                        <select name="yearId" class="w-full px-3 py-2 text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-[#0f766e] outline-none">
                            <option value="">غير محدد</option>
                            <?php foreach ($years as $yr): ?>
                                <option value="<?= htmlspecialchars($yr['id']) ?>" <?= $student['yearId'] === $yr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($yr['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">كود الحضور</label>
                        <input type="number" name="attendanceID" value="<?= htmlspecialchars($student['attendanceID'] ?? '') ?>" class="w-full px-3 py-2 text-xs font-mono font-bold bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-[#0f766e] outline-none">
                    </div>
                </div>

                <!-- Student Type & Center Code -->
                <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl space-y-2">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-amber-950 mb-1">نوع الطالب</label>
                            <select name="studentType" id="editStudentType" onchange="toggleEditCenterCode(this.value)" class="w-full px-3 py-2 text-xs font-bold bg-white border border-amber-300 rounded-xl focus:border-amber-600 outline-none">
                                <option value="ONLINE" <?= ($student['studentType'] ?? 'ONLINE') !== 'CENTER' ? 'selected' : '' ?>>🌐 طالب أونلاين</option>
                                <option value="CENTER" <?= ($student['studentType'] ?? 'ONLINE') === 'CENTER' ? 'selected' : '' ?>>🏢 طالب سنتر</option>
                            </select>
                        </div>
                        <div id="editCenterCodeWrapper" class="<?= ($student['studentType'] ?? 'ONLINE') === 'CENTER' ? '' : 'hidden' ?>">
                            <label class="block text-xs font-bold text-amber-950 mb-1">كود طالب السنتر</label>
                            <input type="text" name="centerCode" id="editCenterCode" value="<?= htmlspecialchars($student['centerCode'] ?? '') ?>" placeholder="مثال: C-105" class="w-full px-3 py-2 text-xs font-mono font-bold bg-white border border-amber-300 rounded-xl focus:border-amber-600 outline-none">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">حالة الحساب</label>
                        <select name="status" class="w-full px-3 py-2 text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-[#0f766e] outline-none">
                            <option value="ACTIVE" <?= $student['status'] === 'ACTIVE' ? 'selected' : '' ?>>نشط</option>
                            <option value="PENDING" <?= $student['status'] === 'PENDING' ? 'selected' : '' ?>>انتظار</option>
                            <option value="BANNED" <?= $student['status'] === 'BANNED' ? 'selected' : '' ?>>محظور</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الحد الأقصى للأجهزة (افتراضي 1)</label>
                        <input type="number" min="1" max="5" name="maxDevices" value="<?= (int)($student['maxDevices'] ?? 1) ?>" required class="w-full px-3 py-2 text-xs font-mono font-bold bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-[#0f766e] outline-none">
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <label class="block text-xs font-bold text-slate-700 mb-1">تعيين كلمة مرور جديدة (اتركها فارغة إذا لم ترد التغيير)</label>
                    <input type="text" name="new_password" placeholder="أدخل كلمة مرور جديدة..." class="w-full px-3 py-2 text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-[#0f766e] outline-none">
                </div>

                <button type="submit" class="w-full py-2.5 bg-[#0f766e] hover:bg-[#0d3753] text-white font-bold text-xs rounded-xl shadow transition cursor-pointer">
                    حفظ التعديلات
                </button>
            </form>
        </div>

        <!-- Connected Devices Card (Requirements #3, #4) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-blue-600"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                        الأجهزة المرتبطة (<?= count($devices) ?> / <?= (int)($student['maxDevices'] ?? 1) ?>)
                    </h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">الحساب مقيّد بجهاز واحد افتراضياً ولا يغيره إلا الأدمن</p>
                </div>
                <?php if (!empty($devices)): ?>
                    <form method="POST" onsubmit="return confirm('هل تريد فك ربط جميع أجهزة هذا الطالب؟')" class="no-print">
                        <input type="hidden" name="action" value="reset_all_devices">
                        <button type="submit" class="text-[11px] text-red-600 hover:text-red-800 font-bold underline cursor-pointer">
                            فك ربط الكل
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (!empty($devices)): ?>
                <div class="space-y-3">
                    <?php foreach ($devices as $dev): 
                        $dType = strtolower($dev['deviceType'] ?? 'pc');
                        $dTypeArabic = ($dType === 'phone') ? 'هاتف محمول' : (($dType === 'tablet') ? 'تابلت' : (($dType === 'laptop') ? 'لابتوب' : 'كمبيوتر'));
                    ?>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-white rounded-lg shadow-sm border border-slate-200 text-blue-600">
                                    <?php if ($dType === 'phone'): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                                    <?php else: ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-800"><?= htmlspecialchars($dev['os']) ?> - <?= htmlspecialchars($dev['browser']) ?> (<?= $dTypeArabic ?>)</h4>
                                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">آخر نشاط: <?= substr($dev['lastActiveAt'], 0, 16) ?></p>
                                </div>
                            </div>
                            <form method="POST" onsubmit="return confirm('هل تريد فك ربط هذا الجهاز؟')" class="no-print">
                                <input type="hidden" name="action" value="unlink_device">
                                <input type="hidden" name="deviceId" value="<?= htmlspecialchars($dev['id']) ?>">
                                <button type="submit" title="فك ربط الجهاز" class="p-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/></svg>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-xs text-slate-400 text-center py-4 bg-slate-50 rounded-xl">لا توجد أجهزة مسجلة حالياً. سيتم ربط أول جهاز يدخل منه الطالب تلقائياً.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- COLUMN 2 & 3: DIRECT ENROLLMENTS & PERFORMANCE REPORT -->
    <!-- ======================================================== -->
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Direct Enrollment Controls (Requirement #3) -->
        <div class="no-print bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h3 class="text-base font-black text-slate-900 mb-2 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-teal-600"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                الاشتراكات المباشرة في الكورسات والحصص (بدون أكواد)
            </h3>
            <p class="text-xs text-slate-500 mb-4">يمكن للمدرس أو الأدمن إضافة الكورس أو الحصة للطالب مباشرة ليظهر له فوراً في حسابه</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Add Course -->
                <form method="POST" action="student_profile.php?id=<?= urlencode($studentId) ?>" class="p-4 bg-teal-50/50 border border-teal-100 rounded-xl flex flex-col justify-between">
                    <input type="hidden" name="action" value="enroll_course">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">
                    <div>
                        <label class="block text-xs font-bold text-teal-900 mb-1.5">إضافة كورس كامل مباشرة:</label>
                        <select name="courseId" required class="w-full px-3 py-2 text-xs font-bold bg-white border border-teal-200 rounded-xl focus:border-[#0f766e] outline-none mb-3">
                            <option value="">-- اختر الكورس المطلوب --</option>
                            <?php foreach ($allCourses as $ac): ?>
                                <option value="<?= htmlspecialchars($ac['id']) ?>"><?= htmlspecialchars($ac['title']) ?> (<?= htmlspecialchars($ac['yearName'] ?: 'عام') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="w-full py-2 bg-[#0f766e] hover:bg-[#0d3753] text-white font-bold text-xs rounded-xl shadow transition cursor-pointer">
                        + تفعيل الكورس للطالب
                    </button>
                </form>

                <!-- Add Lesson/Collection -->
                <form method="POST" action="student_profile.php?id=<?= urlencode($studentId) ?>" class="p-4 bg-amber-50/50 border border-amber-100 rounded-xl flex flex-col justify-between">
                    <input type="hidden" name="action" value="enroll_collection">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">
                    <div>
                        <label class="block text-xs font-bold text-amber-900 mb-1.5">إضافة حصة / درس منفرد مباشرة:</label>
                        <select name="collectionId" required class="w-full px-3 py-2 text-xs font-bold bg-white border border-amber-200 rounded-xl focus:border-amber-600 outline-none mb-3">
                            <option value="">-- اختر الحصة المطلوبة --</option>
                            <?php foreach ($allCollections as $acl): ?>
                                <option value="<?= htmlspecialchars($acl['id']) ?>"><?= htmlspecialchars($acl['title']) ?> (<?= htmlspecialchars($acl['courseTitle'] ?: 'كورس') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="w-full py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow transition cursor-pointer">
                        + تفعيل الحصة للطالب
                    </button>
                </form>
            </div>

            <!-- List Enrolled Courses & Collections -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="text-xs font-bold text-slate-700 mb-2">الكورسات المفعلة حالياً (<?= count($enrolledCourses) ?>):</h4>
                    <?php if (!empty($enrolledCourses)): ?>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            <?php foreach ($enrolledCourses as $ec): ?>
                                <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between text-xs font-bold">
                                    <span class="text-slate-800"><?= htmlspecialchars($ec['title']) ?></span>
                                    <form method="POST" action="student_profile.php?id=<?= urlencode($studentId) ?>" onsubmit="return confirm('إلغاء اشتراك الطالب في هذا الكورس؟')">
                                        <input type="hidden" name="action" value="unenroll_course">
                                        <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">
                                        <input type="hidden" name="courseId" value="<?= htmlspecialchars($ec['id']) ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-[11px] font-bold underline cursor-pointer">إلغاء</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-slate-400 bg-slate-50 p-3 rounded-xl text-center">لا توجد كورسات مفعلة للطالب</p>
                    <?php endif; ?>
                </div>

                <div>
                    <h4 class="text-xs font-bold text-slate-700 mb-2">الحصص المفعلة حالياً (<?= count($enrolledCollections) ?>):</h4>
                    <?php if (!empty($enrolledCollections)): ?>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            <?php foreach ($enrolledCollections as $ecl): ?>
                                <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between text-xs font-bold">
                                    <div>
                                        <span class="text-slate-800"><?= htmlspecialchars($ecl['title']) ?></span>
                                        <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($ecl['courseTitle'] ?? '') ?></span>
                                    </div>
                                    <form method="POST" action="student_profile.php?id=<?= urlencode($studentId) ?>" onsubmit="return confirm('إلغاء اشتراك الطالب في هذه الحصة؟')">
                                        <input type="hidden" name="action" value="unenroll_collection">
                                        <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">
                                        <input type="hidden" name="collectionId" value="<?= htmlspecialchars($ecl['id']) ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-[11px] font-bold underline cursor-pointer">إلغاء</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-slate-400 bg-slate-50 p-3 rounded-xl text-center">لا توجد حصص منفردة مفعلة للطالب</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- LECTURES / VIDEO WATCH LOG (Requirement #15) -->
        <!-- ======================================================== -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm print-break-inside-avoid">
            <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-amber-600"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        سجل مشاهدة الفيديوهات والحصص (المسموع وغير المسموع)
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">تفاصيل الحصص المشترك بها الطالب، ما تم مشاهدته وعدد المشاهدات وما لم يُسمع بعد</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-bold">
                    <span class="px-2.5 py-1 rounded-lg bg-green-50 text-green-700 border border-green-200">مشاهدة: <?= $watchedVideosCount ?></span>
                    <span class="px-2.5 py-1 rounded-lg bg-red-50 text-red-700 border border-red-200">لم يشاهد: <?= $unwatchedVideosCount ?></span>
                </div>
            </div>

            <?php if (!empty($targetVideos)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                            <tr>
                                <th class="p-3">عنوان الفيديو / الحصة</th>
                                <th class="p-3">الكورس / القسم</th>
                                <th class="p-3 text-center">حالة المشاهدة</th>
                                <th class="p-3 text-center">مرات المشاهدة</th>
                                <th class="p-3">تاريخ آخر مشاهدة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($targetVideos as $tv): 
                                $isViewed = !empty($tv['viewCount']) && $tv['viewCount'] > 0;
                            ?>
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="p-3 font-bold text-slate-800"><?= htmlspecialchars($tv['title']) ?></td>
                                    <td class="p-3 text-slate-500 font-medium"><?= htmlspecialchars($tv['courseTitle'] ?: ($tv['collectionTitle'] ?: '---')) ?></td>
                                    <td class="p-3 text-center">
                                        <?php if ($isViewed): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                                تم الاستماع
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                لم يستمع بعد
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-center font-mono font-bold text-slate-700"><?= (int)($tv['viewCount'] ?? 0) ?></td>
                                    <td class="p-3 font-mono text-[11px] text-slate-400"><?= !empty($tv['lastViewedAt']) ? substr($tv['lastViewedAt'], 0, 16) : '---' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-xs text-slate-400 text-center py-6">لا توجد فيديوهات ضمن اشتراكات الطالب حتى الآن.</p>
            <?php endif; ?>
        </div>

        <!-- ======================================================== -->
        <!-- QUIZZES / EXAMS PERFORMANCE (Requirement #15) -->
        <!-- ======================================================== -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm print-break-inside-avoid">
            <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-indigo-600"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        سجل الاختبارات والواجبات (المُمتحن والدرجات)
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">تفاصيل الامتحانات المنجزة، الدرجة والنسبة المئوية، والامتحانات التي لم يؤدها الطالب بعد</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-bold">
                    <span class="px-2.5 py-1 rounded-lg bg-green-50 text-green-700 border border-green-200">تم الامتحان: <?= $quizzesTakenCount ?></span>
                    <span class="px-2.5 py-1 rounded-lg bg-red-50 text-red-700 border border-red-200">لم يمتحن: <?= $quizzesUntakenCount ?></span>
                </div>
            </div>

            <?php if (!empty($targetQuizzes) || !empty($attempts)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                            <tr>
                                <th class="p-3">عنوان الامتحان</th>
                                <th class="p-3">الكورس</th>
                                <th class="p-3 text-center">حالة الامتحان</th>
                                <th class="p-3 text-center">الدرجة</th>
                                <th class="p-3 text-center">النسبة المئوية</th>
                                <th class="p-3">تاريخ التسليم</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($targetQuizzes as $tq): 
                                $hasAttempt = !empty($attemptsByQuiz[$tq['id']]);
                                $attRow = $hasAttempt ? $attemptsByQuiz[$tq['id']][0] : null;
                                $solvedCount = 0;
                                $numQ = 0;
                                $pct = 0;
                                if ($attRow) {
                                    $solved = json_decode($attRow['solvedCorrectly'], true);
                                    $solvedCount = is_array($solved) ? count($solved) : (int)$attRow['solvedCorrectly'];
                                    $numQ = (int)$attRow['numberOfQuestion'];
                                    $pct = ($numQ > 0) ? round(($solvedCount / $numQ) * 100) : 0;
                                }
                            ?>
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="p-3 font-bold text-slate-800"><?= htmlspecialchars($tq['title']) ?></td>
                                    <td class="p-3 text-slate-500 font-medium"><?= htmlspecialchars($tq['courseTitle'] ?: '---') ?></td>
                                    <td class="p-3 text-center">
                                        <?php if ($hasAttempt): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">
                                                تم الاختبار
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">
                                                لم يؤدِ الاختبار
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-center font-mono font-bold <?= $hasAttempt ? 'text-slate-800' : 'text-slate-400' ?>">
                                        <?= $hasAttempt ? "$solvedCount / $numQ" : '---' ?>
                                    </td>
                                    <td class="p-3 text-center font-mono font-black">
                                        <?php if ($hasAttempt): ?>
                                            <span class="<?= $pct >= 85 ? 'text-emerald-700' : ($pct >= 65 ? 'text-blue-700' : 'text-amber-700') ?>"><?= $pct ?>%</span>
                                        <?php else: ?>
                                            <span class="text-slate-300">---</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 font-mono text-[11px] text-slate-400">
                                        <?= $hasAttempt ? substr($attRow['submittedAt'], 0, 16) : '---' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-xs text-slate-400 text-center py-6">لا توجد امتحانات مسجلة ضمن اشتراكات الطالب حتى الآن.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function toggleEditCenterCode(val) {
    const wrap = document.getElementById('editCenterCodeWrapper');
    if (val === 'CENTER') {
        wrap.classList.remove('hidden');
    } else {
        wrap.classList.add('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
