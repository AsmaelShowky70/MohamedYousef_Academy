<?php
require_once __DIR__ . '/config/helpers.php';
$user = requireAuth();
$db = getDB();

$quizId = trim($_GET['id'] ?? '');
if (!$quizId) {
    header('Location: /courses.php');
    exit;
}

// 1. Fetch Quiz, Course, and Collection Details
$stmt = $db->prepare("
    SELECT q.*, c.title AS courseTitle, c.id AS courseId,
           col.title AS collectionTitle, col.id AS collectionId
    FROM Quiz q
    JOIN Course c ON q.courseId = c.id
    JOIN Collection col ON q.collectionId = col.id
    WHERE q.id = ?
    LIMIT 1
");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: /courses.php');
    exit;
}

$isAdmin = in_array($user['role'], ['ADMIN', 'SUPERADMIN']);

// 2. Check Student Access to the Course or Collection
$hasAccess = false;
if ($isAdmin) {
    $hasAccess = true;
} else {
    // Check Course Access
    $stmtC = $db->prepare("SELECT 1 FROM _CourseToUser WHERE A = ? AND B = ? LIMIT 1");
    $stmtC->execute([$quiz['courseId'], $user['id']]);
    if ($stmtC->fetch()) $hasAccess = true;

    // Check Single Collection Access
    if (!$hasAccess) {
        $stmtCol = $db->prepare("SELECT 1 FROM _CollectionToUser WHERE A = ? AND B = ? LIMIT 1");
        $stmtCol->execute([$quiz['collectionId'], $user['id']]);
        if ($stmtCol->fetch()) $hasAccess = true;
    }

    // Check Active Course Code
    if (!$hasAccess) {
        $stmtCd = $db->prepare("
            SELECT 1 FROM CourseCode 
            WHERE userId = ? AND courseId = ? AND (collectionId IS NULL OR collectionId = ?) AND expires > NOW()
            LIMIT 1
        ");
        $stmtCd->execute([$user['id'], $quiz['courseId'], $quiz['collectionId']]);
        if ($stmtCd->fetch()) $hasAccess = true;
    }
}

if (!$hasAccess) {
    header('Location: /course.php?id=' . urlencode($quiz['courseId']) . '&col=' . urlencode($quiz['collectionId']));
    exit;
}

// 3. Fetch Questions
$stmtQ = $db->prepare("SELECT * FROM Question WHERE quizId = ? ORDER BY `order` ASC, id ASC");
$stmtQ->execute([$quizId]);
$questions = $stmtQ->fetchAll();

// 4. Check if student already submitted this quiz
$stmtPrev = $db->prepare("SELECT * FROM QuizAttempt WHERE userId = ? AND quizId = ? ORDER BY submittedAt DESC LIMIT 1");
$stmtPrev->execute([$user['id'], $quizId]);
$previousAttempt = $stmtPrev->fetch();

$resultData = null;

// 5. Handle Exam Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $answers = $_POST['answers'] ?? [];
    $solvedCorrectly = [];
    $totalQuestions = count($questions);

    foreach ($questions as $q) {
        $qId = $q['id'];
        $studentAns = trim($answers[$qId] ?? '');
        $correctAns = trim($q['rightAnswer']);

        if ($studentAns !== '' && mb_strtolower($studentAns) === mb_strtolower($correctAns)) {
            $solvedCorrectly[] = $qId;
        }
    }

    $score = count($solvedCorrectly);
    $percentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100) : 0;
    $isPassed = ($percentage >= 50);

    // Save Attempt
    $attemptId = generateId(5);
    $stmtIns = $db->prepare("
        INSERT INTO QuizAttempt (id, userId, quizId, submittedAt, solvedCorrectly, numberOfQuestion)
        VALUES (?, ?, ?, NOW(), ?, ?)
    ");
    $stmtIns->execute([$attemptId, $user['id'], $quizId, json_encode($solvedCorrectly, JSON_UNESCAPED_UNICODE), $totalQuestions]);

    // Update Progress Table if passed
    if ($isPassed) {
        $stmtProg = $db->prepare("SELECT * FROM Progress WHERE userId = ? LIMIT 1");
        $stmtProg->execute([$user['id']]);
        $prog = $stmtProg->fetch();

        if ($prog) {
            $passedList = json_decode($prog['passedQuizzes'] ?? '[]', true) ?: [];
            if (!in_array($quizId, $passedList)) {
                $passedList[] = $quizId;
            }
            $grades = json_decode($prog['quizzesGrades'] ?? '{}', true) ?: [];
            $grades[$quizId] = $percentage;

            $upd = $db->prepare("UPDATE Progress SET passedQuizzes = ?, quizzesGrades = ? WHERE id = ?");
            $upd->execute([json_encode($passedList, JSON_UNESCAPED_UNICODE), json_encode($grades, JSON_UNESCAPED_UNICODE), $prog['id']]);
        } else {
            $progId = generateId(5);
            $ins = $db->prepare("INSERT INTO Progress (id, userId, passedQuizzes, quizzesGrades) VALUES (?, ?, ?, ?)");
            $ins->execute([
                $progId,
                $user['id'],
                json_encode([$quizId], JSON_UNESCAPED_UNICODE),
                json_encode([$quizId => $percentage], JSON_UNESCAPED_UNICODE)
            ]);
        }
    }

    $resultData = [
        'score' => $score,
        'total' => $totalQuestions,
        'percentage' => $percentage,
        'isPassed' => $isPassed
    ];
}

$pageTitle = htmlspecialchars($quiz['title']) . " - منصة الأستاذ محمد يوسف";
$activeNav = "courses";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8 max-w-4xl">

    <!-- Top Breadcrumb -->
    <div class="flex items-center gap-2 text-xs text-slate-500 mb-6">
        <a href="/courses.php" class="hover:text-primary">الكورسات</a>
        <i class="fa-solid fa-angle-left text-[10px]"></i>
        <a href="/course.php?id=<?= urlencode($quiz['courseId']) ?>" class="hover:text-primary"><?= htmlspecialchars($quiz['courseTitle']) ?></a>
        <i class="fa-solid fa-angle-left text-[10px]"></i>
        <a href="/course.php?id=<?= urlencode($quiz['courseId']) ?>&col=<?= urlencode($quiz['collectionId']) ?>" class="hover:text-primary"><?= htmlspecialchars($quiz['collectionTitle']) ?></a>
        <i class="fa-solid fa-angle-left text-[10px]"></i>
        <span class="text-slate-800 dark:text-white font-bold"><?= htmlspecialchars($quiz['title']) ?></span>
    </div>

    <?php if ($resultData): ?>
        <!-- RESULT SCREEN -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 sm:p-12 text-center shadow-xl space-y-6">
            <div class="w-24 h-24 mx-auto rounded-3xl flex items-center justify-center text-4xl shadow-inner <?= $resultData['isPassed'] ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-red-100 text-red-600 dark:bg-red-950/60 dark:text-red-400' ?>">
                <i class="fa-solid <?= $resultData['isPassed'] ? 'fa-award' : 'fa-circle-xmark' ?>"></i>
            </div>

            <div class="space-y-2">
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                    <?= $resultData['isPassed'] ? 'ألف مبروك! تم اجتياز الامتحان بنجاح 🎓' : 'للأسف لم تتخطَّ درجة النجاح المطلوبة' ?>
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    <?= $resultData['isPassed'] ? 'تم فتح الحصة التالية تلقائياً بحسابك ويمكنك الآن متابعة رحلتك التعليمية.' : 'درجة النجاح هي 50% على الأقل لفتح الحصة التالية. راجع الدرس وحاول ثانية.' ?>
                </p>
            </div>

            <!-- Score Pill -->
            <div class="inline-flex items-center gap-6 px-8 py-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                <div class="text-center">
                    <div class="text-3xl font-black <?= $resultData['isPassed'] ? 'text-emerald-600' : 'text-red-600' ?>">
                        <?= $resultData['percentage'] ?>%
                    </div>
                    <div class="text-[11px] font-bold text-slate-400 mt-1">النسبة المئوية</div>
                </div>
                <div class="w-px h-10 bg-slate-200 dark:bg-slate-700"></div>
                <div class="text-center">
                    <div class="text-2xl font-black text-slate-800 dark:text-slate-200">
                        <?= $resultData['score'] ?> <span class="text-xs text-slate-400">/ <?= $resultData['total'] ?></span>
                    </div>
                    <div class="text-[11px] font-bold text-slate-400 mt-1">إجابات صحيحة</div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-4">
                <a href="/course.php?id=<?= urlencode($quiz['courseId']) ?>&col=<?= urlencode($quiz['collectionId']) ?>" class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-primary hover:bg-teal-700 text-white font-black text-xs transition shadow-lg flex items-center justify-center gap-2">
                    <i class="fa-solid fa-arrow-right"></i>
                    <span>العودة لصفحة الحصة</span>
                </a>
                <?php if (!$resultData['isPassed']): ?>
                    <a href="/quiz.php?id=<?= urlencode($quizId) ?>" class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-amber-500 hover:bg-amber-600 text-white font-black text-xs transition shadow flex items-center justify-center gap-2">
                        <i class="fa-solid fa-rotate-right"></i>
                        <span>إعادة المحاولة الآن</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($previousAttempt && !isset($_GET['retake'])): ?>
        <?php
            $prevSolved = json_decode($previousAttempt['solvedCorrectly'] ?? '[]', true) ?: [];
            $prevTotal = max(1, (int)$previousAttempt['numberOfQuestion']);
            $prevPercent = round((count($prevSolved) / $prevTotal) * 100);
            $prevPassed = ($prevPercent >= 50);
        ?>
        <!-- ALREADY ATTEMPTED SCREEN -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 sm:p-12 text-center shadow-xl space-y-6">
            <div class="w-20 h-20 mx-auto rounded-3xl flex items-center justify-center text-3xl shadow-inner <?= $prevPassed ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400' ?>">
                <i class="fa-solid <?= $prevPassed ? 'fa-circle-check' : 'fa-clock-rotate-left' ?>"></i>
            </div>

            <div class="space-y-2">
                <h2 class="text-2xl font-black text-slate-900 dark:text-white">
                    لقد قمت بأداء هذا الامتحان مسبقاً
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    تاريخ الأداء: <?= htmlspecialchars($previousAttempt['submittedAt']) ?>
                </p>
            </div>

            <div class="inline-flex items-center gap-6 px-8 py-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                <div class="text-center">
                    <div class="text-3xl font-black <?= $prevPassed ? 'text-emerald-600' : 'text-amber-600' ?>">
                        <?= $prevPercent ?>%
                    </div>
                    <div class="text-[11px] font-bold text-slate-400 mt-1">النتيجة السابقة</div>
                </div>
                <div class="w-px h-10 bg-slate-200 dark:bg-slate-700"></div>
                <div class="text-center">
                    <div class="text-2xl font-black text-slate-800 dark:text-slate-200">
                        <?= count($prevSolved) ?> <span class="text-xs text-slate-400">/ <?= $prevTotal ?></span>
                    </div>
                    <div class="text-[11px] font-bold text-slate-400 mt-1">إجابة صحيحة</div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-4">
                <a href="/course.php?id=<?= urlencode($quiz['courseId']) ?>&col=<?= urlencode($quiz['collectionId']) ?>" class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-primary hover:bg-teal-700 text-white font-black text-xs transition shadow flex items-center justify-center gap-2">
                    <i class="fa-solid fa-arrow-right"></i>
                    <span>العودة لدروس الكورس</span>
                </a>
                <a href="/quiz.php?id=<?= urlencode($quizId) ?>&retake=1" class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold text-xs transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-rotate-right"></i>
                    <span>إعادة أداء الامتحان للتحسين</span>
                </a>
            </div>
        </div>

    <?php elseif (empty($questions)): ?>
        <!-- NO QUESTIONS YET -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-12 text-center shadow-sm space-y-4">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-2xl">
                <i class="fa-solid fa-file-circle-question"></i>
            </div>
            <h3 class="text-lg font-black text-slate-800 dark:text-white">لم يتم إضافة أسئلة في هذا الامتحان بعد</h3>
            <p class="text-xs text-slate-500">يقوم الأستاذ حالياً بإعداد بنك الأسئلة، يرجى مراجعة الحصة لاحقاً.</p>
            <a href="/course.php?id=<?= urlencode($quiz['courseId']) ?>&col=<?= urlencode($quiz['collectionId']) ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold transition">
                <span>العودة للدرس</span>
            </a>
        </div>

    <?php else: ?>
        <!-- ACTIVE QUIZ FORM -->
        <form id="quizForm" method="POST" action="/quiz.php?id=<?= urlencode($quizId) ?>" class="space-y-6">

            <!-- Quiz Header Card with Timer -->
            <div class="sticky top-20 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-lg bg-teal-50 dark:bg-teal-950/60 text-teal-700 dark:text-teal-300 text-[10px] font-black">
                            امتحان إجباري لتخطي الحصة
                        </span>
                        <span class="text-xs text-slate-400 font-bold"><?= count($questions) ?> سؤال</span>
                    </div>
                    <h1 class="text-lg sm:text-xl font-black text-[#0d3753] dark:text-white">
                        <?= htmlspecialchars($quiz['title']) ?>
                    </h1>
                </div>

                <!-- Countdown Timer -->
                <div class="flex items-center gap-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-300/80 dark:border-amber-700/80 px-4 py-2 rounded-2xl shrink-0">
                    <i class="fa-solid fa-stopwatch text-amber-600 dark:text-amber-400 text-lg animate-pulse"></i>
                    <div>
                        <div class="text-[10px] font-bold text-amber-700 dark:text-amber-300">الوقت المتبقي</div>
                        <div id="quizTimer" class="font-mono text-base font-black text-amber-900 dark:text-amber-200" dir="ltr">
                            <?= sprintf('%02d:00', max(1, (int)$quiz['quizTime'])) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions List -->
            <div class="space-y-6 select-none" oncontextmenu="return false;">
                <?php foreach ($questions as $qIdx => $q): ?>
                    <?php
                        $opts = json_decode($q['options'] ?? '[]', true) ?: [];
                    ?>
                    <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-primary text-white font-black text-xs shrink-0 shadow-sm">
                                <?= $qIdx + 1 ?>
                            </span>
                            <div class="flex-1 text-sm sm:text-base font-bold text-slate-900 dark:text-white leading-relaxed">
                                <?= nl2br(htmlspecialchars($q['questionHead'])) ?>
                            </div>
                        </div>

                        <?php if (!empty($q['image'])): ?>
                            <div class="rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700 max-w-lg mx-auto">
                                <img src="<?= htmlspecialchars($q['image']) ?>" alt="صورة السؤال" class="w-full h-auto object-contain">
                            </div>
                        <?php endif; ?>

                        <!-- Choices List -->
                        <div class="space-y-2.5 pt-2">
                            <?php foreach ($opts as $oIdx => $opt): ?>
                                <label class="flex items-center gap-3 p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-primary dark:hover:border-primary bg-slate-50/50 dark:bg-slate-800/40 cursor-pointer transition group">
                                    <input type="radio" name="answers[<?= htmlspecialchars($q['id']) ?>]" value="<?= htmlspecialchars($opt) ?>" class="w-4 h-4 text-primary accent-primary focus:ring-0">
                                    <span class="text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 group-hover:text-primary transition">
                                        <?= htmlspecialchars($opt) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Submit Button Bar -->
            <div class="p-6 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-md flex items-center justify-between gap-4">
                <div class="text-xs text-slate-500">
                    تأكد من إجابة جميع الأسئلة قبل تسليم الامتحان.
                </div>
                <button type="submit" name="submit_quiz" onclick="return confirmSubmission()" class="px-8 py-3.5 rounded-2xl bg-primary hover:bg-teal-700 text-white font-black text-xs transition shadow-lg flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>تسليم إجابات الامتحان</span>
                </button>
            </div>

        </form>

        <script>
        // Countdown Timer Logic
        (function() {
            let totalSeconds = <?= max(1, (int)$quiz['quizTime']) * 60 ?>;
            const timerEl = document.getElementById('quizTimer');
            const form = document.getElementById('quizForm');

            const interval = setInterval(function() {
                if (totalSeconds <= 0) {
                    clearInterval(interval);
                    alert('انتهى وقت الامتحان! سيتم تسليم إجاباتك تلقائياً.');
                    form.submit();
                    return;
                }
                totalSeconds--;
                const m = Math.floor(totalSeconds / 60);
                const s = totalSeconds % 60;
                timerEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');

                if (totalSeconds <= 60) {
                    timerEl.classList.add('text-red-500', 'animate-pulse');
                }
            }, 1000);
        })();

        function confirmSubmission() {
            return confirm('هل أنت متأكد من رغبتك في تسليم الامتحان الآن؟ لن تتمكن من تعديل الإجابات بعد التسليم.');
        }
        </script>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
