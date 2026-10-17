<?php
require_once __DIR__ . '/config/helpers.php';
$db = getDB();

// Handle Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['parent_student_id']);
    setcookie('parent_student_id', '', time() - 3600, '/');
    header('Location: /parent.php');
    exit;
}

// Handle Student Selection (when parent phone has multiple students)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_student') {
    $selectedId = trim($_POST['student_id'] ?? '');
    if ($selectedId) {
        $stmt = $db->prepare("SELECT id, status FROM User WHERE id = ? AND role = 'STUDENT'");
        $stmt->execute([$selectedId]);
        $st = $stmt->fetch();
        if ($st && $st['status'] !== 'BANNED') {
            $_SESSION['parent_student_id'] = $st['id'];
            setcookie('parent_student_id', $st['id'], time() + 86400, '/');
            header('Location: /parent.php');
            exit;
        }
    }
}

// Handle Login Submission
$error = '';
$matchingStudents = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'parent_login') {
    $loginType = trim($_POST['login_type'] ?? 'phone');
    $query = trim($_POST['query'] ?? '');

    if ($loginType === 'phone') {
        if ($query === '') {
            $error = 'برجاء إدخال رقم الهاتف';
        } else {
            $stmt = $db->prepare("
                SELECT id, firstName, fullName, phone 
                FROM User 
                WHERE parentPhone = ? AND role = 'STUDENT' AND status != 'BANNED'
            ");
            $stmt->execute([$query]);
            $students = $stmt->fetchAll();

            if (empty($students)) {
                $error = 'هذا الرقم غير مرتبط بأي طالب. تأكد من أن ابنك قام بتسجيل رقمك بشكل صحيح في حسابه، أو يمكنك استخدام الكود الشخصي للطالب إذا كنت تعرفه.';
            } elseif (count($students) === 1) {
                $_SESSION['parent_student_id'] = $students[0]['id'];
                setcookie('parent_student_id', $students[0]['id'], time() + 86400, '/');
                header('Location: /parent.php');
                exit;
            } else {
                $matchingStudents = $students;
            }
        }
    } else { // Code
        if ($query === '') {
            $error = 'برجاء إدخال الكود الشخصي للطالب';
        } elseif (!ctype_digit($query)) {
            $error = 'الكود يجب أن يكون أرقام فقط';
        } else {
            $stmt = $db->prepare("
                SELECT id, firstName, fullName, phone, status 
                FROM User 
                WHERE attendanceID = ? AND role = 'STUDENT'
            ");
            $stmt->execute([(int)$query]);
            $student = $stmt->fetch();

            if (!$student) {
                $error = 'هذا الكود غير مرتبط بأي طالب. تأكد من صحة الكود الشخصي أو جرب تسجيل الدخول برقم هاتفك.';
            } elseif ($student['status'] === 'BANNED') {
                $error = 'هذا الحساب محظور. تواصل مع الدعم الفني.';
            } else {
                $_SESSION['parent_student_id'] = $student['id'];
                setcookie('parent_student_id', $student['id'], time() + 86400, '/');
                header('Location: /parent.php');
                exit;
            }
        }
    }
}

// Check logged in parent session
$parentId = $_SESSION['parent_student_id'] ?? $_COOKIE['parent_student_id'] ?? null;
$studentData = null;

if ($parentId) {
    $stmt = $db->prepare("
        SELECT u.*, y.name AS yearName 
        FROM User u 
        LEFT JOIN Year y ON u.yearId = y.id 
        WHERE u.id = ? AND u.role = 'STUDENT'
    ");
    $stmt->execute([$parentId]);
    $studentData = $stmt->fetch();

    if (!$studentData || $studentData['status'] === 'BANNED') {
        unset($_SESSION['parent_student_id']);
        setcookie('parent_student_id', '', time() - 3600, '/');
        $studentData = null;
    }
}

// If student is logged in, fetch all performance details
if ($studentData) {
    // 1. Enrolled courses + videos
    $stmtCourses = $db->prepare("
        SELECT c.id, c.title, c.price 
        FROM _CourseToUser cu 
        JOIN Course c ON cu.A = c.id 
        WHERE cu.B = ?
    ");
    $stmtCourses->execute([$studentData['id']]);
    $enrolledCourses = $stmtCourses->fetchAll();

    foreach ($enrolledCourses as &$course) {
        $stmtV = $db->prepare("SELECT id, title FROM Video WHERE courseId = ? ORDER BY `order` ASC, id ASC");
        $stmtV->execute([$course['id']]);
        $course['videos'] = $stmtV->fetchAll();
    }
    unset($course);

    // 2. Enrolled collections
    $stmtColl = $db->prepare("
        SELECT cl.id, cl.title 
        FROM _CollectionToUser clu 
        JOIN Collection cl ON clu.A = cl.id 
        WHERE clu.B = ?
    ");
    $stmtColl->execute([$studentData['id']]);
    $enrolledCollections = $stmtColl->fetchAll();

    // 3. Video views
    $stmtViews = $db->prepare("SELECT * FROM VideoView WHERE userId = ?");
    $stmtViews->execute([$studentData['id']]);
    $videoViews = $stmtViews->fetchAll();

    // 4. Quiz Attempts
    $stmtAttempts = $db->prepare("
        SELECT qa.*, q.id AS quiz_id, q.title AS quiz_title, c.title AS course_title
        FROM QuizAttempt qa
        JOIN Quiz q ON qa.quizId = q.id
        LEFT JOIN Course c ON q.courseId = c.id
        WHERE qa.userId = ?
        ORDER BY qa.submittedAt DESC
    ");
    $stmtAttempts->execute([$studentData['id']]);
    $quizAttempts = $stmtAttempts->fetchAll();

    // Group attempts by quiz to determine first attempt
    $attemptsByQuiz = [];
    foreach ($quizAttempts as $att) {
        $qId = $att['quizId'];
        if (!isset($attemptsByQuiz[$qId])) {
            $attemptsByQuiz[$qId] = [];
        }
        $attemptsByQuiz[$qId][] = $att;
    }

    $firstAttemptIds = [];
    $firstAttemptsList = [];
    foreach ($attemptsByQuiz as $qId => $atts) {
        // Sort asc by submittedAt
        usort($atts, function($a, $b) {
            return strtotime($a['submittedAt']) - strtotime($b['submittedAt']);
        });
        if (!empty($atts[0])) {
            $firstAttemptIds[] = $atts[0]['id'];
            $firstAttemptsList[] = $atts[0];
        }
    }

    // Calculate first attempt average
    $totalFirstScores = 0;
    foreach ($firstAttemptsList as $fAtt) {
        $solved = json_decode($fAtt['solvedCorrectly'], true);
        $solvedCount = is_array($solved) ? count($solved) : (int)$fAtt['solvedCorrectly'];
        $numQuestions = (int)$fAtt['numberOfQuestion'];
        $scorePercent = ($numQuestions > 0) ? ($solvedCount / $numQuestions * 100) : 0;
        $totalFirstScores += $scorePercent;
    }

    $avgFirstAttempt = count($firstAttemptsList) > 0 ? (int)round($totalFirstScores / count($firstAttemptsList)) : 0;

    // Grade Level Badge
    $gradeName = 'غير محدد';
    $gradeBadgeClass = 'bg-slate-500/10 text-slate-500 border border-slate-500/20';
    if (count($firstAttemptsList) > 0) {
        if ($avgFirstAttempt >= 95) {
            $gradeName = 'ممتاز مرتفع (A+)';
            $gradeBadgeClass = 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20';
        } elseif ($avgFirstAttempt >= 85) {
            $gradeName = 'ممتاز (A)';
            $gradeBadgeClass = 'bg-green-500/10 text-green-600 border border-green-500/20';
        } elseif ($avgFirstAttempt >= 75) {
            $gradeName = 'جيد جداً (B)';
            $gradeBadgeClass = 'bg-blue-500/10 text-blue-600 border border-blue-500/20';
        } elseif ($avgFirstAttempt >= 65) {
            $gradeName = 'جيد (C)';
            $gradeBadgeClass = 'bg-yellow-500/10 text-yellow-600 border border-yellow-500/20';
        } elseif ($avgFirstAttempt >= 50) {
            $gradeName = 'مقبول (D)';
            $gradeBadgeClass = 'bg-orange-500/10 text-orange-600 border border-orange-500/20';
        } else {
            $gradeName = 'ضعيف (F)';
            $gradeBadgeClass = 'bg-red-500/10 text-red-500 border border-red-500/20';
        }
    }

    // Video Stats
    $totalVideosInCourses = 0;
    foreach ($enrolledCourses as $c) {
        $totalVideosInCourses += count($c['videos']);
    }
    $watchedVideosCount = count($videoViews);
    $totalVideoViewsCount = 0;
    foreach ($videoViews as $vv) {
        $totalVideoViewsCount += (int)($vv['viewCount'] ?? 1);
    }
    $totalAvailableVideos = max($watchedVideosCount, $totalVideosInCourses);

    // Formatted Dates
    $lastLoginFormatted = !empty($studentData['lastLoginAt']) ? date('Y/m/d - H:i', strtotime($studentData['lastLoginAt'])) : 'لم يسجل دخول بعد';
    $joinedAtFormatted = !empty($studentData['createdAt']) ? date('Y/m/d', strtotime($studentData['createdAt'])) : 'غير معروف';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes"/>
    <title>مساحة ولي الأمر | منصة الأستاذ محمد يوسف</title>
    <meta name="description" content="مساحة ولي الأمر - متابعة أداء الطالب"/>
    <link rel="icon" href="/public/identity/logo.png" sizes="48x48"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
      (function() {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          document.documentElement.classList.add('dark');
        } else {
          document.documentElement.classList.remove('dark');
        }
      })();
    </script>
    <style>
        :root {
            --PPrimaryColor: #0f766e;
            --PButtonColor: #c79a32;
            --PTextColor: #0f172a;
            --PBackgroundColor: #f8fafc;
        }
        .dark {
            --PBackgroundColor: #0f172a;
            --PTextColor: #f8fafc;
        }
        body {
            font-family: 'Tajawal', 'Cairo', sans-serif;
            background-color: var(--PBackgroundColor);
            color: var(--PTextColor);
        }
    </style>
</head>
<body class="flex min-h-screen flex-col bg-PBackgroundColor text-PTextColor transition duration-300">

    <!-- Navbar -->
    <nav class="w-full border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a class="flex items-center gap-3" href="/parent.php">
                <img alt="logo" src="/public/identity/logo.png" class="h-10 object-contain"/>
                <span class="text-lg font-bold text-[#0f766e]">مساحة ولي الأمر</span>
            </a>
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleTheme()" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 shadow-sm transition" title="تبديل الوضع الليلي / الفاتح">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="block dark:hidden">
                        <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden dark:block text-yellow-400">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                    </svg>
                </button>
                <?php if ($studentData): ?>
                    <a href="/parent.php?logout=1" class="flex items-center gap-1.5 px-3.5 py-1.5 border border-red-200 dark:border-red-900/40 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 font-bold text-xs rounded-xl transition">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span>خروج</span>
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="text-xs font-bold text-slate-500 hover:text-[#0f766e] transition">
                        الدخول كطالب &larr;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex flex-grow flex-col">
        <?php if (!$studentData): ?>
            <!-- Login Form / Student Picker View -->
            <div class="flex min-h-[calc(100vh-8rem)] items-center justify-center p-4">
                <div class="w-full max-w-md">
                    <div class="rounded-2xl border-2 border-slate-200 bg-white shadow-[0px_0px_25px_1px_rgba(0,0,0,0.1)] overflow-hidden">
                        
                        <!-- Header -->
                        <div class="flex items-center gap-3 border-b-2 border-slate-100 px-6 py-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-[#0f766e]"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <h3 class="text-xl font-bold text-slate-900">مساحة ولي الأمر</h3>
                        </div>

                        <div class="p-6 space-y-6">
                            
                            <?php if ($matchingStudents): ?>
                                <!-- Multiple Students Matching Parent Phone -->
                                <div class="space-y-4">
                                    <div class="text-center">
                                        <h4 class="text-lg font-bold mb-1 text-slate-900">اختر الطالب</h4>
                                        <p class="text-sm text-slate-500">تم العثور على أكثر من طالب مرتبط بهذا الرقم</p>
                                    </div>

                                    <div class="space-y-2">
                                        <?php foreach ($matchingStudents as $mst): ?>
                                            <form method="POST" action="/parent.php">
                                                <input type="hidden" name="action" value="select_student"/>
                                                <input type="hidden" name="student_id" value="<?= htmlspecialchars($mst['id']) ?>"/>
                                                <button type="submit" class="w-full flex items-center justify-between rounded-xl border-2 border-slate-200 p-4 transition-all hover:border-[#0f766e] hover:bg-[#0f766e]/5 text-right">
                                                    <div>
                                                        <span class="block font-bold text-slate-900"><?= htmlspecialchars($mst['fullName'] ?: $mst['firstName']) ?></span>
                                                        <span class="block text-sm text-slate-500 font-mono" dir="ltr"><?= htmlspecialchars($mst['phone']) ?></span>
                                                    </div>
                                                    <i class="fa-solid fa-arrow-left text-slate-400"></i>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>

                                    <a href="/parent.php" class="block w-full text-center text-sm text-[#0f766e] hover:underline font-semibold pt-2">
                                        رجوع
                                    </a>
                                </div>

                            <?php else: ?>
                                <!-- Normal Login Tabs & Form -->
                                <div class="text-center">
                                    <img alt="logo" src="/public/identity/logo.png" class="h-16 object-contain mx-auto mb-4"/>
                                    <p class="text-sm font-medium text-slate-500 leading-relaxed">
                                        تابع أداء ابنك الدراسي ونتائجه في الامتحانات ومدى تقدمه في المشاهدة
                                    </p>
                                </div>

                                <div class="space-y-5">
                                    <!-- Tabs Switcher -->
                                    <div class="flex rounded-xl border-2 border-slate-200 overflow-hidden bg-slate-50 p-1">
                                        <button type="button" id="tabPhone" onclick="switchLoginTab('phone')" class="flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-bold rounded-lg transition-all bg-[#0f766e] text-white shadow-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            <span>رقم الهاتف</span>
                                        </button>
                                        <button type="button" id="tabCode" onclick="switchLoginTab('code')" class="flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-bold rounded-lg transition-all text-slate-600 hover:text-slate-900">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="9" y2="9"></line><line x1="4" x2="20" y1="15" y2="15"></line><line x1="10" x2="8" y1="3" y2="21"></line><line x1="16" x2="14" y1="3" y2="21"></line></svg>
                                            <span>كود الطالب</span>
                                        </button>
                                    </div>

                                    <!-- Form -->
                                    <form method="POST" action="/parent.php" class="space-y-4">
                                        <input type="hidden" name="action" value="parent_login"/>
                                        <input type="hidden" id="loginTypeInput" name="login_type" value="phone"/>

                                        <div>
                                            <input type="text" id="queryInput" name="query" required dir="ltr"
                                                   placeholder="أدخل رقم هاتف ولي الأمر"
                                                   class="placeholder:text-slate-400 w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 outline-none transition duration-300 ease-in-out focus:border-[#0f766e] focus:ring-2 focus:ring-[#0f766e]/20 text-center font-bold text-slate-800"/>
                                        </div>

                                        <?php if ($error): ?>
                                            <div class="rounded-xl bg-red-50 border border-red-200 p-3.5 text-xs font-bold text-red-700 leading-relaxed text-right">
                                                <i class="fa-solid fa-circle-exclamation ml-1"></i> <?= htmlspecialchars($error) ?>
                                            </div>
                                        <?php endif; ?>

                                        <button type="submit" id="submitBtn" class="w-full rounded-xl border border-[#0f766e] bg-[#0f766e] px-5 py-3 font-bold text-white transition duration-300 ease-in-out hover:bg-white hover:text-[#0f766e] shadow-sm">
                                            تسجيل الدخول
                                        </button>
                                    </form>

                                    <div class="pt-2 border-t border-slate-200 text-center">
                                        <a class="text-sm font-semibold text-[#0f766e] hover:underline" href="/login.php">الدخول كطالب</a>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- ======================================================== -->
            <!-- Logged-in Parent Dashboard Matching Next.js UI -->
            <!-- ======================================================== -->
            <div class="min-h-screen bg-slate-50 p-4 md:p-8">
                <div class="mx-auto max-w-6xl space-y-6">
                    
                    <!-- Top Welcome & Logout Bar -->
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">
                                أهلاً بك في متابعة أداء: <?= htmlspecialchars($studentData['fullName'] ?: $studentData['firstName']) ?>
                            </h1>
                            <?php if (!empty($studentData['yearName'])): ?>
                                <p class="text-slate-500 mt-1 flex items-center gap-1.5 text-sm font-bold">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                    <?= htmlspecialchars($studentData['yearName']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <a href="/parent.php?logout=1" class="flex items-center gap-2 px-4 py-2 rounded-xl text-red-600 border border-red-200 hover:bg-red-50 font-bold text-sm transition">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            <span>خروج</span>
                        </a>
                    </div>

                    <!-- 5 Performance KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        
                        <!-- Card 1: المستوى الدراسي -->
                        <div class="border border-slate-200 rounded-2xl bg-white shadow-sm p-5 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-xs font-semibold text-slate-500 block">المستوى الدراسي</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?= $gradeBadgeClass ?>">
                                    <?= $gradeName ?>
                                </span>
                                <span class="text-xs text-slate-500 block">
                                    متوسط المحاولة الأولى: <strong class="text-slate-900"><?= $avgFirstAttempt ?>%</strong>
                                </span>
                            </div>
                            <div class="p-3 bg-teal-50 rounded-xl text-[#0f766e]">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                            </div>
                        </div>

                        <!-- Card 2: الكورسات المشترك بها -->
                        <div class="border border-slate-200 rounded-2xl bg-white shadow-sm p-5 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-xs font-semibold text-slate-500 block">الكورسات المشترك بها</span>
                                <span class="text-2xl font-black text-slate-900 block"><?= count($enrolledCourses) ?></span>
                            </div>
                            <div class="p-3 bg-blue-50 rounded-xl text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            </div>
                        </div>

                        <!-- Card 3: الدروس المشترك بها -->
                        <div class="border border-slate-200 rounded-2xl bg-white shadow-sm p-5 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-xs font-semibold text-slate-500 block">الدروس المشترك بها</span>
                                <span class="text-2xl font-black text-slate-900 block"><?= count($enrolledCollections) ?></span>
                            </div>
                            <div class="p-3 bg-amber-50 rounded-xl text-amber-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                            </div>
                        </div>

                        <!-- Card 4: مشاهدة الفيديوهات -->
                        <div class="border border-slate-200 rounded-2xl bg-white shadow-sm p-5 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-xs font-semibold text-slate-500 block">مشاهدة الفيديوهات</span>
                                <span class="text-2xl font-black text-slate-900 block">
                                    <?= $watchedVideosCount ?> <span class="text-sm font-normal text-slate-500">/ <?= $totalAvailableVideos ?></span>
                                </span>
                                <span class="text-xs text-slate-500 block">
                                    إجمالي المشاهدات: <strong class="text-slate-900"><?= $totalVideoViewsCount ?> مرات</strong>
                                </span>
                            </div>
                            <div class="p-3 bg-emerald-50 rounded-xl text-emerald-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect width="15" height="14" x="1" y="5" rx="2" ry="2"/></svg>
                            </div>
                        </div>

                        <!-- Card 5: آخر تسجيل دخول -->
                        <div class="border border-slate-200 rounded-2xl bg-white shadow-sm p-5 flex items-center justify-between">
                            <div class="space-y-1">
                                <span class="text-xs font-semibold text-slate-500 block">آخر تسجيل دخول</span>
                                <span class="text-sm font-bold text-slate-900 block truncate max-w-[160px]" title="<?= $lastLoginFormatted ?>">
                                    <?= $lastLoginFormatted ?>
                                </span>
                                <span class="text-xs text-slate-500 block">
                                    تاريخ الانضمام: <strong class="text-slate-900"><?= $joinedAtFormatted ?></strong>
                                </span>
                            </div>
                            <div class="p-3 bg-purple-50 rounded-xl text-purple-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                        </div>

                    </div>

                    <!-- 2 Main Cards: Courses Progress & Exam Results -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                        
                        <!-- Card: الكورسات وتقدم المشاهدة -->
                        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
                            <div class="border-b border-slate-100 px-6 py-4">
                                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-[#0f766e]"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                    <span>الكورسات وتقدم المشاهدة</span>
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="space-y-3 max-h-[600px] overflow-y-auto pl-1">
                                    <?php if (empty($enrolledCourses)): ?>
                                        <p class="text-sm text-slate-400 text-center py-8">لم يشترك في أي كورس بعد</p>
                                    <?php else: ?>
                                        <?php foreach ($enrolledCourses as $course): 
                                            $courseViews = array_filter($videoViews, function($vv) use ($course) {
                                                return $vv['courseId'] === $course['id'];
                                            });
                                            $watchedCount = count($courseViews);
                                            $videosCount = count($course['videos']);
                                            $progressPercent = $videosCount > 0 ? (int)round($watchedCount / $videosCount * 100) : 0;
                                            $totalCourseViews = array_sum(array_column($courseViews, 'viewCount'));
                                        ?>
                                            <div class="flex flex-col gap-2.5 p-3.5 rounded-xl border border-slate-200 bg-slate-50/50">
                                                <span class="font-bold text-sm text-slate-900"><?= htmlspecialchars($course['title']) ?></span>
                                                <div class="space-y-1.5">
                                                    <div class="flex justify-between text-xs text-slate-500">
                                                        <span>فيديوهات مشاهدة: <strong class="text-slate-800"><?= $watchedCount ?> / <?= $videosCount ?></strong></span>
                                                        <span class="font-bold text-[#0f766e]"><?= $progressPercent ?>%</span>
                                                    </div>
                                                    <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                                        <div class="bg-[#0f766e] h-2 rounded-full transition-all duration-300" style="width: <?= $progressPercent ?>%"></div>
                                                    </div>
                                                    <div class="flex justify-between items-center text-[11px] text-slate-500 pt-1">
                                                        <span>إجمالي المشاهدات: <?= $totalCourseViews ?></span>
                                                        <?php if ($videosCount > 0): ?>
                                                            <button type="button" onclick="toggleDetails('details-<?= $course['id'] ?>', this)" class="text-[#0f766e] hover:underline font-bold flex items-center gap-1">
                                                                <span class="btn-label">تفاصيل</span>
                                                                <i class="fa-solid fa-chevron-down text-[10px] icon-arrow"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Expandable Videos List -->
                                                <?php if ($videosCount > 0): ?>
                                                    <div id="details-<?= $course['id'] ?>" class="hidden mt-2 pt-2 border-t border-slate-200 space-y-1.5 max-h-[200px] overflow-y-auto pl-1">
                                                        <?php foreach ($course['videos'] as $vid): 
                                                            $vMatch = array_filter($courseViews, function($vv) use ($vid) {
                                                                return $vv['videoId'] === $vid['id'];
                                                            });
                                                            $vMatch = reset($vMatch);
                                                            $vViews = $vMatch ? (int)($vMatch['viewCount'] ?? 1) : 0;
                                                        ?>
                                                            <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-100 text-xs">
                                                                <span class="truncate text-slate-800 font-medium max-w-[70%]" title="<?= htmlspecialchars($vid['title']) ?>">
                                                                    <?= htmlspecialchars($vid['title']) ?>
                                                                </span>
                                                                <?php if ($vViews > 0): ?>
                                                                    <span class="inline-flex items-center gap-1 rounded bg-emerald-50 text-emerald-700 px-2 py-0.5 font-bold text-[10px] border border-emerald-200">
                                                                        <i class="fa-solid fa-eye text-[9px]"></i> <?= $vViews ?> مشاهدات
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="text-[10px] text-slate-400 italic">غير مشاهد</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>

                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card: نتائج الامتحانات -->
                        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
                            <div class="border-b border-slate-100 px-6 py-4">
                                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-[#0f766e]"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                                    <span>نتائج الامتحانات</span>
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-xs">
                                    <table class="w-full text-right text-xs">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                                <th class="p-3">الامتحان</th>
                                                <th class="p-3">الكورس</th>
                                                <th class="p-3">التاريخ</th>
                                                <th class="p-3 text-left">الدرجة</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <?php if (empty($quizAttempts)): ?>
                                                <tr>
                                                    <td colspan="4" class="h-32 text-center text-slate-400 font-medium">
                                                        لا توجد محاولات امتحانات بعد
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($quizAttempts as $att): 
                                                    $isFirst = in_array($att['id'], $firstAttemptIds);
                                                    $solved = json_decode($att['solvedCorrectly'], true);
                                                    $solvedCount = is_array($solved) ? count($solved) : (int)$att['solvedCorrectly'];
                                                    $numQuestions = (int)$att['numberOfQuestion'];
                                                    $scorePercent = ($numQuestions > 0) ? (int)round($solvedCount / $numQuestions * 100) : 0;
                                                    $dateStr = date('Y/m/d - H:i', strtotime($att['submittedAt']));

                                                    $scoreBadge = 'bg-red-50 text-red-700 border-red-200';
                                                    if ($scorePercent >= 80) {
                                                        $scoreBadge = 'bg-green-50 text-green-700 border-green-200';
                                                    } elseif ($scorePercent >= 50) {
                                                        $scoreBadge = 'bg-amber-50 text-amber-700 border-amber-200';
                                                    }
                                                ?>
                                                    <tr class="<?= $isFirst ? 'bg-teal-50/40 border-r-4 border-r-[#0f766e]' : 'hover:bg-slate-50/50' ?>">
                                                        <td class="p-3 font-bold text-slate-900">
                                                            <div class="flex flex-wrap items-center gap-1.5">
                                                                <span><?= htmlspecialchars($att['quiz_title']) ?></span>
                                                                <?php if ($isFirst): ?>
                                                                    <span class="inline-flex items-center gap-1 rounded bg-[#0f766e]/10 text-[#0f766e] text-[9px] font-bold px-1.5 py-0.5 border border-[#0f766e]/20">
                                                                        <i class="fa-solid fa-circle-check text-[9px]"></i> المحاولة الأولى
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td class="p-3 text-slate-500 font-medium"><?= htmlspecialchars($att['course_title'] ?? '-') ?></td>
                                                        <td class="p-3 text-slate-500 text-[11px] font-mono" dir="ltr"><?= $dateStr ?></td>
                                                        <td class="p-3 text-left font-bold">
                                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold border <?= $scoreBadge ?>">
                                                                <?= $solvedCount ?> / <?= $numQuestions ?> (<?= $scorePercent ?>%)
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-200 py-4 text-center text-xs text-slate-500 font-bold bg-white">
        جميع الحقوق محفوظة © <?= date('Y') ?> منصة الأستاذ محمد يوسف
    </footer>

    <script>
    function switchLoginTab(type) {
        const tabPhone = document.getElementById('tabPhone');
        const tabCode = document.getElementById('tabCode');
        const input = document.getElementById('queryInput');
        const hiddenType = document.getElementById('loginTypeInput');

        if (type === 'phone') {
            tabPhone.className = "flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-bold rounded-lg transition-all bg-[#0f766e] text-white shadow-sm";
            tabCode.className = "flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-bold rounded-lg transition-all text-slate-600 hover:text-slate-900";
            input.placeholder = "أدخل رقم هاتف ولي الأمر";
            hiddenType.value = "phone";
        } else {
            tabCode.className = "flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-bold rounded-lg transition-all bg-[#0f766e] text-white shadow-sm";
            tabPhone.className = "flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-bold rounded-lg transition-all text-slate-600 hover:text-slate-900";
            input.placeholder = "أدخل الكود الشخصي للطالب";
            hiddenType.value = "code";
        }
        input.focus();
    }

    function toggleDetails(id, btn) {
        const el = document.getElementById(id);
        const label = btn.querySelector('.btn-label');
        const icon = btn.querySelector('.icon-arrow');
        if (el.classList.contains('hidden')) {
            el.classList.remove('hidden');
            if (label) label.textContent = 'إخفاء التفاصيل';
            if (icon) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        } else {
            el.classList.add('hidden');
            if (label) label.textContent = 'تفاصيل';
            if (icon) {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        }
    }

    function toggleTheme() {
        const isDark = document.documentElement.classList.toggle('dark');
        try {
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        } catch(e) {}
    }
    </script>
</body>
</html>
