<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();
$adminUser = requireAdmin();
requireAdminPermission('marks');
$currentUser = $adminUser;

// -------------------------------------------------------------
// 1. Fetch Academic Years & Published Quizzes (For Filters)
// -------------------------------------------------------------
$years = $db->query("SELECT id, name FROM Year ORDER BY `order` ASC")->fetchAll();
$allQuizzes = $db->query("SELECT q.id, q.title, c.title AS courseTitle, y.name AS yearName FROM Quiz q JOIN Course c ON q.courseId = c.id LEFT JOIN Section s ON c.sectionId = s.id LEFT JOIN Year y ON s.yearId = y.id ORDER BY q.createdAt DESC")->fetchAll();

// -------------------------------------------------------------
// 2. Read Request Filters
// -------------------------------------------------------------
$q = trim($_GET['q'] ?? '');
$yearFilter = trim($_GET['yearId'] ?? '');
$typeFilter = trim($_GET['studentType'] ?? '');
$levelFilter = trim($_GET['level'] ?? '');
$quizFilter = trim($_GET['quizId'] ?? '');
$sortBy = trim($_GET['sortBy'] ?? 'highest_score');
$isExport = (isset($_GET['export']) && $_GET['export'] === 'csv');

// -------------------------------------------------------------
// 3. Base Query for Students
// -------------------------------------------------------------
$sql = "
    SELECT u.id, u.fullName, u.firstName, u.phone, u.parentPhone, u.role, u.status,
           u.studentType, u.centerCode, u.attendanceID, u.joinedAt, u.yearId,
           y.name AS yearName
    FROM User u
    LEFT JOIN Year y ON u.yearId = y.id
    WHERE u.role = 'STUDENT'
";
$params = [];

if ($q !== '') {
    $sql .= " AND (u.fullName LIKE ? OR u.phone LIKE ? OR u.parentPhone LIKE ? OR u.attendanceID LIKE ? OR u.centerCode LIKE ?)";
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($yearFilter !== '') {
    $sql .= " AND u.yearId = ?";
    $params[] = $yearFilter;
}

if ($typeFilter === 'CENTER') {
    $sql .= " AND u.studentType = 'CENTER'";
} elseif ($typeFilter === 'ONLINE') {
    $sql .= " AND (u.studentType = 'ONLINE' OR u.studentType IS NULL)";
}

$sql .= " ORDER BY u.joinedAt DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rawStudents = $stmt->fetchAll();

// -------------------------------------------------------------
// 4. Batch Fetch Quiz Attempts & Video Views for Performance
// -------------------------------------------------------------
// Fetch all attempts grouped by user
$allAttemptsStmt = $db->query("
    SELECT qa.id, qa.userId, qa.quizId, qa.submittedAt, qa.solvedCorrectly, qa.numberOfQuestion,
           q.title AS quizTitle, c.title AS courseTitle
    FROM QuizAttempt qa
    JOIN Quiz q ON qa.quizId = q.id
    LEFT JOIN Course c ON q.courseId = c.id
    ORDER BY qa.submittedAt DESC
");
$allAttempts = $allAttemptsStmt->fetchAll();

$attemptsByUser = [];
foreach ($allAttempts as $att) {
    $attemptsByUser[$att['userId']][] = $att;
}

// Fetch video views count per user
$allViewsStmt = $db->query("
    SELECT userId, COUNT(*) AS videosWatched, SUM(viewCount) AS totalViews
    FROM VideoView
    GROUP BY userId
");
$viewsByUser = [];
foreach ($allViewsStmt->fetchAll() as $vv) {
    $viewsByUser[$vv['userId']] = [
        'videosWatched' => (int)$vv['videosWatched'],
        'totalViews' => (int)$vv['totalViews']
    ];
}

// -------------------------------------------------------------
// 5. Compute Metrics & Academic Evaluation for each Student
// -------------------------------------------------------------
$processedStudents = [];
$totalScoreAccumulator = 0;
$studentsWithScoresCount = 0;
$countExcellent = 0;
$countVeryGood = 0;
$countGood = 0;
$countNeedsFollowup = 0;
$countUntested = 0;
$totalCenterStudents = 0;
$totalOnlineStudents = 0;

foreach ($rawStudents as $st) {
    $uid = $st['id'];
    $uAttempts = $attemptsByUser[$uid] ?? [];
    $uViews = $viewsByUser[$uid] ?? ['videosWatched' => 0, 'totalViews' => 0];

    // If a specific quiz filter is set, check if student took it
    $targetQuizAttempt = null;
    if ($quizFilter !== '') {
        foreach ($uAttempts as $att) {
            if ($att['quizId'] === $quizFilter) {
                $targetQuizAttempt = $att;
                break;
            }
        }
    }

    // Group attempts by quiz (take the latest or best attempt per distinct quiz)
    $attemptsByQuiz = [];
    foreach ($uAttempts as $att) {
        $qId = $att['quizId'];
        if (!isset($attemptsByQuiz[$qId])) {
            $attemptsByQuiz[$qId] = $att;
        }
    }

    $quizzesTaken = count($attemptsByQuiz);
    $totalScorePercent = 0;
    $scoresList = [];

    foreach ($attemptsByQuiz as $att) {
        $solved = json_decode($att['solvedCorrectly'], true);
        $solvedCount = is_array($solved) ? count($solved) : (int)$att['solvedCorrectly'];
        $numQ = (int)$att['numberOfQuestion'];
        $pct = ($numQ > 0) ? round(($solvedCount / $numQ) * 100) : 0;
        $scoresList[] = $pct;
        $totalScorePercent += $pct;
    }

    $avgScore = ($quizzesTaken > 0) ? round($totalScorePercent / $quizzesTaken) : 0;
    $highestScore = !empty($scoresList) ? max($scoresList) : 0;
    $lowestScore = !empty($scoresList) ? min($scoresList) : 0;

    // Evaluation level
    if ($quizzesTaken === 0) {
        $levelKey = 'UNTESTED';
        $levelLabel = 'لم يؤدِ اختبارات بعد';
        $levelBadgeClass = 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
        $levelIcon = 'fa-clock';
        $countUntested++;
    } elseif ($avgScore >= 90) {
        $levelKey = 'EXCELLENT';
        $levelLabel = '🌟 ممتاز (متفوق)';
        $levelBadgeClass = 'bg-emerald-100 text-emerald-900 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-700';
        $levelIcon = 'fa-star';
        $countExcellent++;
        $totalScoreAccumulator += $avgScore;
        $studentsWithScoresCount++;
    } elseif ($avgScore >= 75) {
        $levelKey = 'VERY_GOOD';
        $levelLabel = '🟢 جيد جداً (مستقر)';
        $levelBadgeClass = 'bg-teal-100 text-teal-900 border-teal-300 dark:bg-teal-950/60 dark:text-teal-300 dark:border-teal-700';
        $levelIcon = 'fa-check-circle';
        $countVeryGood++;
        $totalScoreAccumulator += $avgScore;
        $studentsWithScoresCount++;
    } elseif ($avgScore >= 60) {
        $levelKey = 'GOOD';
        $levelLabel = '🟡 جيد (متوسط)';
        $levelBadgeClass = 'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-700';
        $levelIcon = 'fa-triangle-exclamation';
        $countGood++;
        $totalScoreAccumulator += $avgScore;
        $studentsWithScoresCount++;
    } else {
        $levelKey = 'NEEDS_FOLLOWUP';
        $levelLabel = '🔴 يحتاج متابعة عاجلة';
        $levelBadgeClass = 'bg-rose-100 text-rose-900 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-700';
        $levelIcon = 'fa-circle-exclamation';
        $countNeedsFollowup++;
        $totalScoreAccumulator += $avgScore;
        $studentsWithScoresCount++;
    }

    $isCenter = (($st['studentType'] ?? 'ONLINE') === 'CENTER');
    if ($isCenter) $totalCenterStudents++;
    else $totalOnlineStudents++;

    // Apply level filter
    if ($levelFilter !== '' && $levelFilter !== $levelKey) {
        continue;
    }

    // Apply specific quiz filter (if set and student didn't take this quiz, skip)
    if ($quizFilter !== '' && !$targetQuizAttempt) {
        continue;
    }

    $processedStudents[] = [
        'id' => $st['id'],
        'fullName' => $st['fullName'] ?: $st['firstName'],
        'phone' => $st['phone'],
        'parentPhone' => $st['parentPhone'] ?: '',
        'yearName' => $st['yearName'] ?: 'غير محدد',
        'studentType' => $st['studentType'] ?? 'ONLINE',
        'centerCode' => $st['centerCode'] ?? '',
        'attendanceID' => $st['attendanceID'] ?? '',
        'joinedAt' => $st['joinedAt'],
        'quizzesTaken' => $quizzesTaken,
        'avgScore' => $avgScore,
        'highestScore' => $highestScore,
        'lowestScore' => $lowestScore,
        'videosWatched' => $uViews['videosWatched'],
        'totalViews' => $uViews['totalViews'],
        'levelKey' => $levelKey,
        'levelLabel' => $levelLabel,
        'levelBadgeClass' => $levelBadgeClass,
        'levelIcon' => $levelIcon,
        'allAttempts' => $uAttempts,
        'targetQuizAttempt' => $targetQuizAttempt
    ];
}

// -------------------------------------------------------------
// 6. Sorting
// -------------------------------------------------------------
usort($processedStudents, function($a, $b) use ($sortBy) {
    if ($sortBy === 'highest_score') {
        return $b['avgScore'] <=> $a['avgScore'];
    } elseif ($sortBy === 'lowest_score') {
        return $a['avgScore'] <=> $b['avgScore'];
    } elseif ($sortBy === 'most_quizzes') {
        return $b['quizzesTaken'] <=> $a['quizzesTaken'];
    } elseif ($sortBy === 'name') {
        return strcmp($a['fullName'], $b['fullName']);
    } elseif ($sortBy === 'joined') {
        return strcmp($b['joinedAt'], $a['joinedAt']);
    }
    return $b['avgScore'] <=> $a['avgScore'];
});

$platformAverage = ($studentsWithScoresCount > 0) ? round($totalScoreAccumulator / $studentsWithScoresCount) : 0;

// -------------------------------------------------------------
// 7. Handle CSV / Excel Export
// -------------------------------------------------------------
if ($isExport) {
    $filename = "kashf_daragat_al-tulab_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Output UTF-8 BOM so Excel opens Arabic correctly
    echo "\xEF\xBB\xBF";
    
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'م',
        'اسم الطالب',
        'نوع الطالب',
        'كود السنتر',
        'كود الحضور',
        'رقم هاتف الطالب',
        'رقم ولي الأمر',
        'الصف الدراسي',
        'عدد الامتحانات المنجزة',
        'متوسط الدرجات %',
        'التقييم والمستوى العام',
        'عدد الحصص المشاهدة',
        'تاريخ التسجيل'
    ]);

    $i = 1;
    foreach ($processedStudents as $row) {
        $typeStr = ($row['studentType'] === 'CENTER') ? 'طالب سنتر' : 'طالب أونلاين';
        fputcsv($out, [
            $i++,
            $row['fullName'],
            $typeStr,
            $row['centerCode'] ?: '---',
            $row['attendanceID'] ?: '---',
            $row['phone'],
            $row['parentPhone'] ?: '---',
            $row['yearName'],
            $row['quizzesTaken'],
            $row['avgScore'] . '%',
            $row['levelLabel'],
            $row['videosWatched'],
            substr($row['joinedAt'], 0, 10)
        ]);
    }
    fclose($out);
    exit;
}

$adminTitle = "كشف تقدم وتقييم درجات الطلاب - منصة أ. محمد يوسف";
$activeAdminNav = "marks";
require_once __DIR__ . '/header.php';
?>

<!-- Print Specific Styling -->
<style>
@media print {
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 11pt;
    }
    header, aside, .no-print, button, form, .filter-box, .btn-action {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    #printable-sheet {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    th, td {
        border: 1px solid #333333 !important;
        padding: 6px 8px !important;
        color: #000000 !important;
    }
    thead {
        background-color: #f1f5f9 !important;
    }
}
.print-only {
    display: none;
}
</style>

<div class="space-y-6">

    <!-- Top Action Bar & Page Title -->
    <div class="no-print flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-[#0d3753] tracking-tight">كشف متابعة تقدم وتقييم درجات الطلاب</h2>
            <p class="text-xs text-slate-500 font-medium mt-1">متابعة نتائج الامتحانات الإلكترونية، تقييم مستوى طلاب السنتر والأونلاين، والتواصل المباشر مع أولياء الأمور</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-black text-white font-bold text-xs rounded-xl shadow transition cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                <span>طباعة الكشف (A4 PDF)</span>
            </button>
            <?php
                $csvParams = $_GET;
                $csvParams['export'] = 'csv';
                $csvUrl = 'studentsmarks.php?' . http_build_query($csvParams);
            ?>
            <a href="<?= htmlspecialchars($csvUrl) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs rounded-xl shadow transition">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                <span>تصدير إلى Excel (CSV)</span>
            </a>
            <a href="users.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <span>إدارة حسابات الطلاب</span>
            </a>
        </div>
    </div>

    <!-- KPI Summary Overview Cards -->
    <div class="no-print grid grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Total Students -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-teal-50 border border-teal-200 text-[#0f766e] flex items-center justify-center shrink-0">
                <i class="fa-solid fa-users text-lg"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-500 font-bold">إجمالي الطلاب بالكشف</p>
                <h3 class="text-xl font-black text-slate-900"><?= count($processedStudents) ?> <span class="text-xs text-slate-400 font-normal">طالب</span></h3>
            </div>
        </div>

        <!-- Center vs Online -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-building-columns text-lg"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-500 font-bold">طلاب السنتر</p>
                <h3 class="text-xl font-black text-amber-900"><?= $totalCenterStudents ?> <span class="text-[10px] text-slate-400 font-bold">(أونلاين: <?= $totalOnlineStudents ?>)</span></h3>
            </div>
        </div>

        <!-- Platform Average Score -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-chart-line text-lg"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-500 font-bold">متوسط الدرجات العام</p>
                <h3 class="text-xl font-black text-indigo-700"><?= $platformAverage ?>%</h3>
            </div>
        </div>

        <!-- Excellent Students -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-award text-lg"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-500 font-bold">المتفوقون (🌟 ممتاز)</p>
                <h3 class="text-xl font-black text-emerald-700"><?= $countExcellent ?> <span class="text-xs text-slate-400 font-normal">طالب</span></h3>
            </div>
        </div>

        <!-- Needs Followup -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-500 font-bold">يحتاجون متابعة (&lt;60%)</p>
                <h3 class="text-xl font-black text-rose-700"><?= $countNeedsFollowup ?> <span class="text-xs text-slate-400 font-normal">طالب</span></h3>
            </div>
        </div>
    </div>

    <!-- Search & Advanced Filter Bar -->
    <div class="no-print bg-white p-5 rounded-2xl border border-slate-200 shadow-sm filter-box">
        <form method="GET" action="studentsmarks.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            
            <!-- Search Text -->
            <div class="sm:col-span-3 relative">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="بحث بالاسم، كود السنتر، الهاتف..."
                       class="w-full pl-3 pr-9 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-[#0f766e] outline-none transition"/>
                <svg class="h-4 w-4 text-slate-400 absolute right-3 top-3 pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>

            <!-- Academic Year Filter -->
            <div class="sm:col-span-2">
                <select name="yearId" onchange="this.form.submit()" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-[#0f766e] outline-none transition">
                    <option value="">جميع الصفوف الدراسية</option>
                    <?php foreach ($years as $yr): ?>
                        <option value="<?= htmlspecialchars($yr['id']) ?>" <?= $yearFilter === $yr['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($yr['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Student Type Filter -->
            <div class="sm:col-span-2">
                <select name="studentType" onchange="this.form.submit()" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-[#0f766e] outline-none transition">
                    <option value="">جميع الطلاب (سنتر وأونلاين)</option>
                    <option value="CENTER" <?= $typeFilter === 'CENTER' ? 'selected' : '' ?>>🏢 طلاب السنتر فقط</option>
                    <option value="ONLINE" <?= $typeFilter === 'ONLINE' ? 'selected' : '' ?>>🌐 طلاب أونلاين فقط</option>
                </select>
            </div>

            <!-- Evaluation Level Filter -->
            <div class="sm:col-span-2">
                <select name="level" onchange="this.form.submit()" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-[#0f766e] outline-none transition">
                    <option value="">جميع المستويات والتقييمات</option>
                    <option value="EXCELLENT" <?= $levelFilter === 'EXCELLENT' ? 'selected' : '' ?>>🌟 ممتاز (90% فما فوق)</option>
                    <option value="VERY_GOOD" <?= $levelFilter === 'VERY_GOOD' ? 'selected' : '' ?>>🟢 جيد جداً (75% - 89%)</option>
                    <option value="GOOD" <?= $levelFilter === 'GOOD' ? 'selected' : '' ?>>🟡 جيد (60% - 74%)</option>
                    <option value="NEEDS_FOLLOWUP" <?= $levelFilter === 'NEEDS_FOLLOWUP' ? 'selected' : '' ?>>🔴 يحتاج متابعة (أقل من 60%)</option>
                    <option value="UNTESTED" <?= $levelFilter === 'UNTESTED' ? 'selected' : '' ?>>⚠️ لم يؤدِ اختبارات بعد</option>
                </select>
            </div>

            <!-- Sort By -->
            <div class="sm:col-span-2">
                <select name="sortBy" onchange="this.form.submit()" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-[#0f766e] outline-none transition">
                    <option value="highest_score" <?= $sortBy === 'highest_score' ? 'selected' : '' ?>>الأعلى تقييماً ودرجات</option>
                    <option value="lowest_score" <?= $sortBy === 'lowest_score' ? 'selected' : '' ?>>الأقل تقييماً</option>
                    <option value="most_quizzes" <?= $sortBy === 'most_quizzes' ? 'selected' : '' ?>>الأكثر امتحاناً</option>
                    <option value="joined" <?= $sortBy === 'joined' ? 'selected' : '' ?>>الأحدث تسجيلاً</option>
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>الاسم أبجدياً</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="sm:col-span-1 flex items-center gap-1.5">
                <button type="submit" class="w-full py-2.5 bg-[#0f766e] hover:bg-[#0d3753] text-white text-xs font-bold rounded-xl transition cursor-pointer">
                    فلترة
                </button>
                <?php if ($q !== '' || $yearFilter !== '' || $typeFilter !== '' || $levelFilter !== '' || $quizFilter !== ''): ?>
                    <a href="studentsmarks.php" title="إلغاء الفلترة" class="p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </a>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <!-- PRINT ONLY OFFICIAL HEADER -->
    <div class="print-only mb-6 border-b-2 border-slate-900 pb-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-900">منصة الأستاذ محمد يوسف التعليمية لمادة اللغة العربية</h1>
                <h2 class="text-base font-bold text-slate-700 mt-1">كشف تقييم ومتابعة تقدم درجات الطلاب والامتحانات الإلكترونية</h2>
                <div class="flex items-center gap-4 text-xs text-slate-600 mt-2">
                    <?php if ($yearFilter): 
                        $curYear = array_filter($years, fn($y) => $y['id'] === $yearFilter);
                        $curYearName = !empty($curYear) ? reset($curYear)['name'] : '';
                    ?>
                        <span>الصف: <strong><?= htmlspecialchars($curYearName) ?></strong></span>
                    <?php endif; ?>
                    <?php if ($typeFilter): ?>
                        <span>فئة الطلاب: <strong><?= $typeFilter === 'CENTER' ? 'طلاب السنتر' : 'طلاب أونلاين' ?></strong></span>
                    <?php endif; ?>
                    <span>عدد الطلاب بالكشف: <strong><?= count($processedStudents) ?></strong> طالب</span>
                </div>
            </div>
            <div class="text-left text-xs text-slate-500 font-mono">
                <p>تاريخ الاستخراج: <?= date('Y/m/d H:i') ?></p>
                <p>إشراف: أ. محمد يوسف</p>
            </div>
        </div>
    </div>

    <!-- MAIN STUDENT MARKSHEET TABLE -->
    <div id="printable-sheet" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        
        <div class="no-print p-4 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
            <span class="text-xs font-bold text-slate-600">
                نتائج التقييم: <strong class="text-slate-900 font-black"><?= count($processedStudents) ?></strong> طالب 
                <?php if ($typeFilter === 'CENTER'): ?>
                    (🏢 طلاب السنتر فقط)
                <?php elseif ($typeFilter === 'ONLINE'): ?>
                    (🌐 طلاب أونلاين فقط)
                <?php endif; ?>
            </span>
            <span class="text-xs text-slate-400 font-medium">اضغط على "سجل الاختبارات" لعرض درجات امتحانات الطالب بالتفصيل</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50/80 text-slate-700 font-bold border-b border-slate-200">
                    <tr>
                        <th class="p-3.5 text-center w-10">م</th>
                        <th class="p-3.5">اسم الطالب</th>
                        <th class="p-3.5">نوع الطالب</th>
                        <th class="p-3.5">كود السنتر / الحضور</th>
                        <th class="p-3.5">رقم الهاتف</th>
                        <th class="p-3.5">الصف الدراسي</th>
                        <th class="p-3.5 text-center">الامتحانات المنجزة</th>
                        <th class="p-3.5 text-center">متوسط الدرجات</th>
                        <th class="p-3.5 text-center">المستوى والتقييم الأكاديمي</th>
                        <th class="p-3.5 text-center no-print">الإجراءات والمتابعة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php 
                    $index = 1;
                    foreach ($processedStudents as $st): 
                        $isCenter = ($st['studentType'] === 'CENTER');
                    ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Index -->
                            <td class="p-3.5 text-center font-mono font-bold text-slate-400">
                                <?= $index++ ?>
                            </td>

                            <!-- Student Info -->
                            <td class="p-3.5 font-bold text-slate-900">
                                <a href="/admin/student_profile.php?id=<?= urlencode($st['id']) ?>" class="group flex items-center gap-2 hover:text-[#0f766e] transition">
                                    <div class="w-8 h-8 rounded-full <?= $isCenter ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-teal-50 border border-teal-200 text-[#0f766e]' ?> flex items-center justify-center font-bold text-xs shrink-0">
                                        <?= mb_substr($st['fullName'], 0, 1) ?>
                                    </div>
                                    <div>
                                        <span class="block group-hover:underline"><?= htmlspecialchars($st['fullName']) ?></span>
                                        <span class="text-[10px] text-slate-400 font-mono font-normal">مشاهدات: <?= $st['videosWatched'] ?> حصة</span>
                                    </div>
                                </a>
                            </td>

                            <!-- Student Type -->
                            <td class="p-3.5">
                                <?php if ($isCenter): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-black bg-amber-100 text-amber-950 border border-amber-300">
                                        <i class="fa-solid fa-building-columns text-amber-700 text-[10px]"></i>
                                        <span>طالب سنتر</span>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                        <i class="fa-solid fa-globe text-sky-600 text-[10px]"></i>
                                        <span>أونلاين</span>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Center Code / Attendance ID -->
                            <td class="p-3.5 font-mono font-bold">
                                <?php if ($isCenter && !empty($st['centerCode'])): ?>
                                    <div class="inline-block bg-amber-50 border border-amber-300 text-amber-900 px-2 py-0.5 rounded text-xs font-black">
                                        <?= htmlspecialchars($st['centerCode']) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-[#0f766e]"><?= htmlspecialchars($st['attendanceID'] ?: '---') ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Phone -->
                            <td class="p-3.5 font-mono text-slate-700" dir="ltr">
                                <div><?= htmlspecialchars($st['phone']) ?></div>
                                <?php if (!empty($st['parentPhone'])): ?>
                                    <div class="text-[10px] text-slate-400 font-normal">ولي الأمر: <?= htmlspecialchars($st['parentPhone']) ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Year -->
                            <td class="p-3.5 font-semibold text-slate-600">
                                <?= htmlspecialchars($st['yearName']) ?>
                            </td>

                            <!-- Quizzes Taken Count -->
                            <td class="p-3.5 text-center font-mono font-bold">
                                <span class="px-2.5 py-1 rounded-full text-xs <?= $st['quizzesTaken'] > 0 ? 'bg-teal-50 text-teal-800 border border-teal-200' : 'bg-slate-100 text-slate-500' ?>">
                                    <?= $st['quizzesTaken'] ?> اختبارات
                                </span>
                            </td>

                            <!-- Average Score -->
                            <td class="p-3.5 text-center font-mono">
                                <?php if ($st['quizzesTaken'] > 0): ?>
                                    <div class="flex flex-col items-center">
                                        <span class="text-sm font-black <?= $st['avgScore'] >= 85 ? 'text-emerald-700' : ($st['avgScore'] >= 65 ? 'text-teal-700' : 'text-rose-700') ?>">
                                            <?= $st['avgScore'] ?>%
                                        </span>
                                        <div class="w-16 bg-slate-100 rounded-full h-1.5 mt-1 overflow-hidden border border-slate-200">
                                            <div class="h-full rounded-full <?= $st['avgScore'] >= 85 ? 'bg-emerald-500' : ($st['avgScore'] >= 65 ? 'bg-teal-500' : 'bg-rose-500') ?>" style="width: <?= min(100, $st['avgScore']) ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-300 font-bold">---</span>
                                <?php endif; ?>
                            </td>

                            <!-- Academic Level Evaluation -->
                            <td class="p-3.5 text-center">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black border <?= $st['levelBadgeClass'] ?>">
                                    <i class="fa-solid <?= $st['levelIcon'] ?> text-[10px]"></i>
                                    <span><?= $st['levelLabel'] ?></span>
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="p-3.5 text-center no-print">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- View Quizzes History Modal Button -->
                                    <button type="button" onclick="showQuizHistory('modal-st-<?= $st['id'] ?>')" title="عرض سجل اختبارات الطالب" class="px-2 py-1 rounded-lg bg-teal-50 text-[#0f766e] hover:bg-[#0f766e] hover:text-white border border-teal-200 transition font-bold text-[11px] flex items-center gap-1 cursor-pointer">
                                        <i class="fa-solid fa-list-check"></i>
                                        <span>السجل</span>
                                    </button>

                                    <!-- WhatsApp Contact Button -->
                                    <?php 
                                        $targetPhone = !empty($st['parentPhone']) ? $st['parentPhone'] : $st['phone'];
                                        $cleanPhone = preg_replace('/[^0-9]/', '', $targetPhone);
                                        if (str_starts_with($cleanPhone, '0')) {
                                            $cleanPhone = '2' . $cleanPhone;
                                        }
                                        $waMsg = "السلام عليكم ورحمة الله، مع حضراتكم منصة الأستاذ محمد يوسف لمادة اللغة العربية.\nنحيطكم علماً بتقرير أداء الطالب: " . $st['fullName'] . "\n- نوع القيد: " . ($isCenter ? 'طالب سنتر' : 'طالب أونلاين') . "\n- عدد الاختبارات المنجزة: " . $st['quizzesTaken'] . "\n- متوسط الدرجات: " . $st['avgScore'] . "%\n- التقييم العام: " . $st['levelLabel'] . "\nمع أطيب تمنياتنا بدوام التفوق والنجاح.";
                                        $waUrl = "https://wa.me/" . $cleanPhone . "?text=" . urlencode($waMsg);
                                    ?>
                                    <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" title="إرسال تقرير الأداء لولي الأمر عبر واتساب" class="p-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition">
                                        <i class="fa-brands fa-whatsapp text-sm"></i>
                                    </a>

                                    <!-- Student Profile Link -->
                                    <a href="/admin/student_profile.php?id=<?= urlencode($st['id']) ?>" title="الملف الشامل للطالب" class="p-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                                        <i class="fa-solid fa-arrow-left text-xs"></i>
                                    </a>
                                </div>

                                <!-- Student Quiz Attempts Modal (Hidden by default) -->
                                <div id="modal-st-<?= $st['id'] ?>" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" onclick="if(event.target === this) hideQuizHistory('modal-st-<?= $st['id'] ?>')">
                                    <div class="bg-white rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl relative text-right max-h-[85vh] overflow-y-auto">
                                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                            <div>
                                                <h3 class="font-black text-sm text-[#0d3753]">سجل امتحانات الطالب: <?= htmlspecialchars($st['fullName']) ?></h3>
                                                <p class="text-[11px] text-slate-500 font-medium">متوسط الدرجات: <?= $st['avgScore'] ?>% | التقييم: <?= $st['levelLabel'] ?></p>
                                            </div>
                                            <button type="button" onclick="hideQuizHistory('modal-st-<?= $st['id'] ?>')" class="text-slate-400 hover:text-slate-600 text-sm">
                                                <i class="fa-solid fa-xmark text-lg"></i>
                                            </button>
                                        </div>

                                        <?php if (!empty($st['allAttempts'])): ?>
                                            <div class="divide-y divide-slate-100 border border-slate-100 rounded-2xl overflow-hidden">
                                                <?php foreach ($st['allAttempts'] as $att): 
                                                    $solved = json_decode($att['solvedCorrectly'], true);
                                                    $solvedCount = is_array($solved) ? count($solved) : (int)$att['solvedCorrectly'];
                                                    $numQ = (int)$att['numberOfQuestion'];
                                                    $pct = ($numQ > 0) ? round(($solvedCount / $numQ) * 100) : 0;
                                                ?>
                                                    <div class="p-3.5 flex items-center justify-between gap-3 hover:bg-slate-50 transition">
                                                        <div>
                                                            <h4 class="font-bold text-xs text-slate-900"><?= htmlspecialchars($att['quizTitle']) ?></h4>
                                                            <p class="text-[10px] text-slate-400 mt-0.5">
                                                                <?= htmlspecialchars($att['courseTitle'] ?: 'كورس عام') ?> • 
                                                                <span class="font-mono"><?= substr($att['submittedAt'], 0, 16) ?></span>
                                                            </p>
                                                        </div>
                                                        <div class="text-left shrink-0">
                                                            <div class="font-mono font-black text-xs <?= $pct >= 85 ? 'text-emerald-700' : ($pct >= 65 ? 'text-teal-700' : 'text-rose-700') ?>">
                                                                <?= $solvedCount ?> / <?= $numQ ?> (<?= $pct ?>%)
                                                            </div>
                                                            <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full mt-0.5 <?= $pct >= 65 ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' ?>">
                                                                <?= $pct >= 65 ? 'اجتاز بنجاح' : 'يحتاج إعادة' ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-8 text-slate-400 font-bold text-xs">
                                                <i class="fa-solid fa-clipboard-question text-3xl mb-2 text-slate-300"></i>
                                                <p>لم يقم الطالب بأداء أي امتحان إلكتروني حتى الآن.</p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="pt-2 flex justify-end gap-2">
                                            <a href="/admin/student_profile.php?id=<?= urlencode($st['id']) ?>" class="px-4 py-2 bg-[#0f766e] text-white rounded-xl text-xs font-bold hover:bg-[#0d3753] transition">
                                                الملف الكامل للطالب
                                            </a>
                                            <button type="button" onclick="hideQuizHistory('modal-st-<?= $st['id'] ?>')" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200 transition">
                                                إغلاق
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($processedStudents)): ?>
                        <tr>
                            <td colspan="10" class="text-center p-10 text-slate-400 font-bold">
                                <i class="fa-solid fa-folder-open text-3xl mb-2 text-slate-300 block"></i>
                                لا توجد نتائج مطابقة لخيارات الفلترة المحددة.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- PRINT ONLY SIGNATURE BLOCK -->
    <div class="print-only mt-12 pt-6 border-t-2 border-slate-900 flex justify-between items-end text-xs font-bold text-slate-800">
        <div>
            <p>إدارة منصة الأستاذ محمد يوسف التعليمية</p>
            <p class="text-[10px] text-slate-500 mt-1">كشف معتمد رسمياً لأغراض المتابعة الدورية ومجالس الآباء</p>
        </div>
        <div class="text-center">
            <p class="mb-8">توقيع المدرس المشرف:</p>
            <p class="border-t border-slate-900 pt-1 px-8">أ. محمد يوسف</p>
        </div>
    </div>

</div>

<script>
function showQuizHistory(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('hidden');
}
function hideQuizHistory(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
