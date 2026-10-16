<?php
require_once __DIR__ . '/config/helpers.php';
$db = getDB();

$courseId = trim($_GET['id'] ?? '');
if (!$courseId) {
    header('Location: /courses.php');
    exit;
}

$user = getCurrentUser();
$isAdmin = $user && in_array($user['role'], ['ADMIN', 'SUPERADMIN']);

// Fetch course details
$stmt = $db->prepare("
    SELECT c.*, s.title as sectionTitle, y.name as yearName, y.id as yearId
    FROM Course c
    LEFT JOIN Section s ON c.sectionId = s.id
    LEFT JOIN Year y ON s.yearId = y.id
    WHERE c.id = ?
");
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) {
    header('Location: /courses.php');
    exit;
}

// Client IP for forensic dynamic watermark
$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (strpos($clientIp, ',') !== false) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}

// Check full course enrollment & access
$isFullCourseEnrolled = false;
if ($user) {
    // 1. Direct Course Enrollment
    $stmtChk = $db->prepare("SELECT 1 FROM _CourseToUser WHERE A = ? AND B = ? LIMIT 1");
    $stmtChk->execute([$courseId, $user['id']]);
    if ($stmtChk->fetch()) {
        $isFullCourseEnrolled = true;
    }

    // 2. Free course
    if (!$isFullCourseEnrolled && (float)$course['price'] == 0.0) {
        $isFullCourseEnrolled = true;
    }

    // 3. Active full CourseCode (where collectionId IS NULL)
    if (!$isFullCourseEnrolled) {
        $stmtCode = $db->prepare("SELECT 1 FROM CourseCode WHERE userId = ? AND courseId = ? AND collectionId IS NULL AND expires > NOW() LIMIT 1");
        $stmtCode->execute([$user['id'], $courseId]);
        if ($stmtCode->fetch()) {
            $isFullCourseEnrolled = true;
        }
    }
}
$isFullCourseAccess = $isFullCourseEnrolled || $isAdmin;

// Check single-lesson (Collection) enrollment for current user
$enrolledCollectionIds = [];
if ($user) {
    // A) Direct Collection Enrollment in _CollectionToUser
    $stmtColEnr = $db->prepare("
        SELECT clu.A 
        FROM _CollectionToUser clu 
        JOIN Collection cl ON clu.A = cl.id 
        WHERE clu.B = ? AND cl.courseId = ?
    ");
    $stmtColEnr->execute([$user['id'], $courseId]);
    $enrolledCollectionIds = $stmtColEnr->fetchAll(PDO::FETCH_COLUMN);

    // B) Active single-lesson CourseCode
    $stmtColCodes = $db->prepare("
        SELECT collectionId 
        FROM CourseCode 
        WHERE userId = ? AND courseId = ? AND collectionId IS NOT NULL AND expires > NOW()
    ");
    $stmtColCodes->execute([$user['id'], $courseId]);
    $codeColIds = $stmtColCodes->fetchAll(PDO::FETCH_COLUMN);
    $enrolledCollectionIds = array_unique(array_merge($enrolledCollectionIds, $codeColIds));
}

// Fetch Collections
$stmtCol = $db->prepare("SELECT * FROM Collection WHERE courseId = ? ORDER BY `order` ASC, id ASC");
$stmtCol->execute([$courseId]);
$collections = $stmtCol->fetchAll();

// 1. Fetch passed quizzes for current student
$passedQuizIds = [];
if ($user) {
    // From QuizAttempt (>= 50%)
    try {
        $stmtQA = $db->prepare("SELECT quizId, solvedCorrectly, numberOfQuestion FROM QuizAttempt WHERE userId = ?");
        $stmtQA->execute([$user['id']]);
        while ($row = $stmtQA->fetch()) {
            $solved = json_decode($row['solvedCorrectly'] ?? '[]', true) ?: [];
            $cCount = count($solved);
            $tCount = max(1, (int)$row['numberOfQuestion']);
            if (($cCount / $tCount) >= 0.5) {
                $passedQuizIds[] = $row['quizId'];
            }
        }
    } catch (Exception $e) {}

    // From Progress table
    try {
        $stmtProg = $db->prepare("SELECT passedQuizzes FROM Progress WHERE userId = ? LIMIT 1");
        $stmtProg->execute([$user['id']]);
        $pQuizzes = $stmtProg->fetchColumn();
        if ($pQuizzes) {
            $dec = json_decode($pQuizzes, true) ?: [];
            $passedQuizIds = array_unique(array_merge($passedQuizIds, $dec));
        }
    } catch (Exception $e) {}
}

// Fetch watched video IDs for current student
$watchedVideoIds = [];
if ($user) {
    try {
        $stmtW = $db->prepare("SELECT videoId FROM VideoView WHERE userId = ? AND viewCount > 0");
        $stmtW->execute([$user['id']]);
        $watchedVideoIds = $stmtW->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {}
}

$allVideos = [];
$totalVideosCount = 0;
$totalFilesCount = 0;
$totalQuizzesCount = 0;

// Load items for each collection
foreach ($collections as $idx => &$col) {
    // Videos
    $stmtV = $db->prepare("SELECT * FROM Video WHERE collectionId = ? ORDER BY `order` ASC, id ASC");
    $stmtV->execute([$col['id']]);
    $col['videos'] = $stmtV->fetchAll();
    $totalVideosCount += count($col['videos']);
    foreach ($col['videos'] as $v) {
        $allVideos[] = $v;
    }

    // Files (PDFs)
    $stmtF = $db->prepare("SELECT * FROM File WHERE collectionId = ? ORDER BY `order` ASC, id ASC");
    $stmtF->execute([$col['id']]);
    $col['files'] = $stmtF->fetchAll();
    $totalFilesCount += count($col['files']);

    // Quizzes (Exams)
    try {
        $stmtQ = $db->prepare("SELECT * FROM Quiz WHERE collectionId = ? ORDER BY `order` ASC, id ASC");
        $stmtQ->execute([$col['id']]);
        $col['quizzes'] = $stmtQ->fetchAll();
        $totalQuizzesCount += count($col['quizzes']);
    } catch (Exception $e) {
        $col['quizzes'] = [];
    }

    // Check purchase status
    $col['isPurchased'] = $isAdmin || $isFullCourseAccess || in_array($col['id'], $enrolledCollectionIds);

    // Check Prerequisite Lock: Student cannot access collection $idx if previous collection ($idx - 1)
    // has unwatched explanation video, unwatched solution video, or unpassed quiz/task!
    $col['isPrerequisiteLocked'] = false;
    $col['prerequisiteInfo'] = null;

    if (!$isAdmin && $idx > 0) {
        $prevCol = $collections[$idx - 1];

        // 1. Explanation Video (First video of previous lesson)
        $prevExplanation = $prevCol['videos'][0] ?? null;
        $watchedExplanation = $prevExplanation ? in_array($prevExplanation['id'], $watchedVideoIds) : true;

        // 2. Solution Video (Second video of previous lesson, if uploaded)
        $prevSolution = $prevCol['videos'][1] ?? null;
        $watchedSolution = $prevSolution ? in_array($prevSolution['id'], $watchedVideoIds) : true;

        // 3. Quiz / Task of previous lesson (if uploaded)
        $hasPrevQuiz = !empty($prevCol['quizzes']);
        $passedPrevQuiz = true;
        $lockingQuizItem = null;
        if ($hasPrevQuiz) {
            $passedPrevQuiz = false;
            foreach ($prevCol['quizzes'] as $pq) {
                if (in_array($pq['id'], $passedQuizIds)) {
                    $passedPrevQuiz = true;
                    break;
                }
            }
            if (!$passedPrevQuiz) {
                $lockingQuizItem = $prevCol['quizzes'][0];
            }
        }

        if (!$watchedExplanation || !$watchedSolution || !$passedPrevQuiz) {
            $col['isPrerequisiteLocked'] = true;
            $col['prerequisiteInfo'] = [
                'prevTitle' => $prevCol['title'],
                'prevId' => $prevCol['id'],
                'hasExplanation' => !empty($prevExplanation),
                'watchedExplanation' => $watchedExplanation,
                'explanationVideo' => $prevExplanation,
                'hasSolution' => !empty($prevSolution),
                'watchedSolution' => $watchedSolution,
                'solutionVideo' => $prevSolution,
                'hasQuiz' => $hasPrevQuiz,
                'passedQuiz' => $passedPrevQuiz,
                'quiz' => $lockingQuizItem
            ];
        }
    }

    // Collection is fully unlocked if purchased and not locked by prerequisites
    $col['isUnlocked'] = $col['isPurchased'] && !$col['isPrerequisiteLocked'];

    foreach ($col['videos'] as &$v) {
        $v['isUnlocked'] = $col['isUnlocked'];
    }
    unset($v);
}
unset($col);

// 2. Active Collection & Video Resolution
$requestedColId = trim($_GET['col'] ?? '');
$activeVideoId = trim($_GET['v'] ?? '');
$activeCollection = null;
$activeVideo = null;
$activeVideoIndex = -1;
$hasSelectedLesson = false;

// Only select a lesson/video if explicitly clicked by the student in the URL!
// This guarantees NO auto-playing on opening the course!
if ($activeVideoId) {
    $hasSelectedLesson = true;
    foreach ($allVideos as $idx => $v) {
        if ($v['id'] === $activeVideoId) {
            $activeVideo = $v;
            $activeVideoIndex = $idx;
            break;
        }
    }
    if ($activeVideo) {
        foreach ($collections as $col) {
            if ($col['id'] === $activeVideo['collectionId']) {
                $activeCollection = $col;
                break;
            }
        }
    }
} elseif ($requestedColId) {
    $hasSelectedLesson = true;
    foreach ($collections as $col) {
        if ($col['id'] === $requestedColId) {
            $activeCollection = $col;
            break;
        }
    }
    if ($activeCollection && !empty($activeCollection['videos'])) {
        $activeVideo = $activeCollection['videos'][0];
        foreach ($allVideos as $idx => $v) {
            if ($v['id'] === $activeVideo['id']) {
                $activeVideoIndex = $idx;
                break;
            }
        }
    }
} else {
    // Neither ?v= nor ?col= was provided!
    // The student will see the Course Overview & Syllabus to choose which lesson to open!
    $hasSelectedLesson = false;
    $activeCollection = null;
    $activeVideo = null;
}

// Can access active video / lesson
$canAccessActiveLesson = $activeCollection && $activeCollection['isUnlocked'];
$canAccessActiveVideo = $canAccessActiveLesson && $activeVideo;
$canAccess = $isFullCourseAccess;

// Next and Previous videos in the active collection or overall
$prevVideo = ($activeVideoIndex > 0) ? $allVideos[$activeVideoIndex - 1] : null;
$nextVideo = ($activeVideoIndex >= 0 && $activeVideoIndex < count($allVideos) - 1) ? $allVideos[$activeVideoIndex + 1] : null;

// Log Video View if user can access
if ($canAccessActiveVideo && $activeVideo && $user) {
    try {
        $stmtView = $db->prepare("SELECT id FROM VideoView WHERE userId = ? AND videoId = ? LIMIT 1");
        $stmtView->execute([$user['id'], $activeVideo['id']]);
        $viewRow = $stmtView->fetch();
        if ($viewRow) {
            $db->prepare("UPDATE VideoView SET viewCount = viewCount + 1, updatedAt = NOW() WHERE id = ?")
               ->execute([$viewRow['id']]);
        } else {
            $db->prepare("INSERT INTO VideoView (id, userId, videoId, courseId, viewCount, createdAt, updatedAt) VALUES (?, ?, ?, ?, 1, NOW(), NOW())")
               ->execute([generateId(5), $user['id'], $activeVideo['id'], $courseId]);
        }
    } catch (Exception $e) {}
}

// Helper: parse video source (NO AUTOPLAY, fs=0 for custom fullscreen)
if (!function_exists('parseVideoSource')) {
    function parseVideoSource($link, $videoId = '') {
        $link = trim((string)$link);

        // 1. YouTube (fs=0 so native fullscreen is replaced by secure container fullscreen)
        if (preg_match('#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|v/|watch\?v=|watch\?.+&v=|shorts/|live/))([\w-]{11})#i', $link, $m) ||
            (preg_match('#^[\w-]{11}$#', $link) && !preg_match('#\.mp4$#i', $link))) {
            $ytId = isset($m[1]) ? $m[1] : $link;
            return [
                'type' => 'youtube',
                'id' => $ytId,
                'embedUrl' => 'https://www.youtube-nocookie.com/embed/' . $ytId . '?autoplay=0&rel=0&modestbranding=1&enablejsapi=1&playsinline=1&controls=1&iv_load_policy=3&fs=0'
            ];
        }

        // 2. Dailymotion
        if (preg_match('#(?:dailymotion\.com/(?:video|embed/video)/|dai\.ly/|geo\.dailymotion\.com/player/xmztx\.html\?video=)([a-zA-Z0-9]+)#i', $link, $m)) {
            return [
                'type' => 'dailymotion',
                'id' => $m[1],
                'embedUrl' => 'https://geo.dailymotion.com/player/xmztx.html?video=' . $m[1] . '&loop=true&autoplay=false&mute=false'
            ];
        }

        if (preg_match('#^[a-zA-Z0-9_-]{12,25}$#', $link) && !preg_match('#\.mp4$#i', $link)) {
            return [
                'type' => 'dailymotion',
                'id' => $link,
                'embedUrl' => 'https://geo.dailymotion.com/player/xmztx.html?video=' . $link . '&loop=true&autoplay=false&mute=false'
            ];
        }

        // 3. MP4
        return [
            'type' => 'mp4',
            'url' => '/api/stream.php?id=' . urlencode($videoId)
        ];
    }
}

// Helper: Securely encrypt video payload to prevent exposing raw URLs in HTML / Page Source
if (!function_exists('obfuscateVideoPayload')) {
    function obfuscateVideoPayload($videoSource) {
        if (!$videoSource) return '';
        $json = json_encode($videoSource);
        $salt = 'MY_SECURE_VIDEO_GUARD_2026';
        $enc = '';
        for ($i = 0; $i < strlen($json); $i++) {
            $enc .= chr(ord($json[$i]) ^ ord($salt[$i % strlen($salt)]));
        }
        return base64_encode($enc);
    }
}

$videoSource = ($activeVideo) ? parseVideoSource($activeVideo['link'], $activeVideo['id']) : null;
$encryptedVideoPayload = ($videoSource) ? obfuscateVideoPayload($videoSource) : '';

$pageTitle = htmlspecialchars($course['title']) . " - منصة الأستاذ محمد يوسف";
$activeNav = "courses";
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ========================================================
   Custom Scoped Theater Player & Course Styles
   Ensures 100% responsiveness & Dark Mode harmony
   ======================================================== */
.course-page-wrapper {
    min-height: calc(100vh - 80px);
    background-color: var(--PPBackgroundColor);
    color: var(--PTextColor);
    transition: background-color 0.3s ease, color 0.3s ease;
}

.course-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 16px 80px 16px;
}

@media (min-width: 768px) {
    .course-container {
        padding: 32px 24px 90px 24px;
    }
}

/* 2-Column Responsive Layout */
.course-layout-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 28px;
    align-items: start;
}

@media (min-width: 1024px) {
    .course-layout-grid {
        grid-template-columns: minmax(0, 1fr) 390px;
        gap: 32px;
    }
}

/* Theater Player Container */
.player-cinema-box {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    min-height: 220px;
    background-color: #000000;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.08);
}

#player-mount-point {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    z-index: 10;
}

#player-mount-point iframe,
#player-mount-point video,
.player-cinema-box iframe,
.player-cinema-box video {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
    object-fit: contain;
    background-color: #000;
}

/* Secure Fullscreen Mode - Keeps watermarks, tamper shield, and custom controls on top */
.player-cinema-box:fullscreen,
.player-cinema-box:-webkit-full-screen,
.player-cinema-box:-moz-full-screen,
.player-cinema-box:-ms-fullscreen {
    width: 100vw !important;
    height: 100vh !important;
    max-width: 100vw !important;
    max-height: 100vh !important;
    aspect-ratio: auto !important;
    border-radius: 0 !important;
    border: none !important;
    background-color: #000000 !important;
    margin: 0 !important;
    padding: 0 !important;
    z-index: 2147483640 !important;
}

.player-cinema-box:fullscreen #player-mount-point,
.player-cinema-box:-webkit-full-screen #player-mount-point {
    position: absolute !important;
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
    z-index: 10 !important;
}

.player-cinema-box:fullscreen .watermark-tiled-grid,
.player-cinema-box:-webkit-full-screen .watermark-tiled-grid {
    z-index: 2147483630 !important;
    position: absolute !important;
    inset: 0 !important;
}

.player-cinema-box:fullscreen .floating-watermark-badge,
.player-cinema-box:-webkit-full-screen .floating-watermark-badge {
    z-index: 2147483640 !important;
    position: absolute !important;
}

.player-cinema-box:fullscreen .security-screen-shield,
.player-cinema-box:-webkit-full-screen .security-screen-shield {
    z-index: 2147483646 !important;
    position: absolute !important;
    inset: 0 !important;
}

.player-cinema-box:fullscreen #secureFullscreenBtn,
.player-cinema-box:-webkit-full-screen #secureFullscreenBtn {
    z-index: 2147483647 !important;
}

/* Anti-Piracy & Anti-Print System Protection */
@media print {
    html, body {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        height: 0 !important;
        overflow: hidden !important;
    }
}

.protected-content,
.player-cinema-box,
.no-copy {
    -webkit-touch-callout: none !important;
    -webkit-user-select: none !important;
    -khtml-user-select: none !important;
    -moz-user-select: none !important;
    -ms-user-select: none !important;
    user-select: none !important;
}

/* Dynamic Floating Watermark */
.floating-watermark-badge {
    position: absolute;
    z-index: 45;
    pointer-events: none;
    user-select: none;
    -webkit-user-select: none;
    transition: top 2.2s cubic-bezier(0.4, 0, 0.2, 1), left 2.2s cubic-bezier(0.4, 0, 0.2, 1), opacity 1.5s ease-in-out;
    opacity: 0.70;
}

.floating-watermark-inner {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 16px;
    border-radius: 9999px;
    background: rgba(10, 15, 29, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.65);
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.95);
    white-space: nowrap;
}

/* Subtle Corner / Tiled Watermark Grid (Anti-Crop & External Camera Defense) */
.watermark-tiled-grid {
    position: absolute;
    inset: 0;
    z-index: 30;
    pointer-events: none;
    user-select: none;
    -webkit-user-select: none;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 16px 20px;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
}

/* Security Screen Protection Shield (Active upon blur, printscreen, or recording trigger) */
.security-screen-shield {
    display: none;
    position: absolute;
    inset: 0;
    z-index: 999;
    background: #020617;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px;
    text-align: center;
    color: #ffffff;
    pointer-events: auto;
    transition: opacity 0.2s ease;
}
.security-screen-shield.show-shield {
    display: flex !important;
}

/* Cards & UI Elements */
.course-card {
    background-color: var(--PPBackgroundColor);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 20px;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
    transition: all 0.25s ease;
}

html.dark .course-card {
    background-color: #12283a;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 6px 25px -4px rgba(0, 0, 0, 0.35);
}

.lesson-active-item {
    background-color: rgba(15, 118, 110, 0.12) !important;
    border: 1px solid rgba(15, 118, 110, 0.4) !important;
    color: var(--PPrimaryColor) !important;
    font-weight: 800 !important;
}

html.dark .lesson-active-item {
    background-color: rgba(15, 118, 110, 0.25) !important;
    border-color: rgba(45, 212, 191, 0.5) !important;
    color: #2dd4bf !important;
}

/* Accordion Styling */
.collection-accordion-header {
    cursor: pointer;
    user-select: none;
}
</style>

<div class="course-page-wrapper w-full max-w-full overflow-x-hidden">
    <div class="course-container space-y-8 w-full max-w-full">

        <!-- Top Navigation / Breadcrumbs -->
        <div class="flex flex-wrap items-center justify-between gap-4 text-xs font-bold text-slate-500 dark:text-slate-400">
            <div class="flex flex-wrap items-center gap-2 min-w-0">
                <a href="/index.php" class="hover:text-primary transition flex items-center gap-1">
                    <i class="fa-solid fa-house"></i>
                    <span>الرئيسية</span>
                </a>
                <span>/</span>
                <a href="/courses.php" class="hover:text-primary transition">الكورسات</a>
                <?php if (!empty($course['yearName'])): ?>
                    <span>/</span>
                    <a href="/year.php?id=<?= urlencode($course['yearId']) ?>" class="hover:text-primary transition">
                        <?= htmlspecialchars($course['yearName']) ?>
                    </a>
                <?php endif; ?>
                <span>/</span>
                <span class="text-slate-900 dark:text-white font-extrabold line-clamp-1 max-w-[220px] sm:max-w-md truncate"><?= htmlspecialchars($course['title']) ?></span>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-3 py-1 bg-navy-custom text-white text-[11px] font-black rounded-full">
                    <?= htmlspecialchars($course['yearName'] ?? 'ثانوية عامة') ?>
                </span>
                <?php if (!empty($course['specialization'])): ?>
                    <span class="px-3 py-1 bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300 text-[11px] font-black rounded-full">
                        <?= $course['specialization'] === 'ELMI' ? 'القسم العلمي' : 'القسم الأدبي' ?>
                    </span>
                <?php endif; ?>
                <?php if ($isFullCourseAccess): ?>
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 text-[11px] font-black rounded-full flex items-center gap-1">
                        <i class="fa-solid fa-circle-check"></i> مشترك في الكورس كاملاً
                    </span>
                <?php elseif (!empty($enrolledCollectionIds)): ?>
                    <span class="px-3 py-1 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-[11px] font-black rounded-full flex items-center gap-1">
                        <i class="fa-solid fa-circle-check"></i> مشترك في <?= count($enrolledCollectionIds) ?> حصة
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Course Header Info Banner -->
        <div class="course-card p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-3 text-center md:text-right w-full md:w-auto">
                <h1 class="text-2xl md:text-3xl font-black text-[#0d3753] dark:text-white">
                    <?= htmlspecialchars($course['title']) ?>
                </h1>
                <?php if (!empty($course['description'])): ?>
                    <p class="text-xs md:text-sm text-slate-500 dark:text-slate-300 max-w-3xl leading-relaxed">
                        <?= nl2br(htmlspecialchars($course['description'])) ?>
                    </p>
                <?php endif; ?>

                <!-- Stats Badges -->
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 pt-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1.5 rounded-xl">
                        <i class="fa-solid fa-play text-primary"></i> <?= $totalVideosCount ?> حصص فيديو
                    </span>
                    <span class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1.5 rounded-xl">
                        <i class="fa-solid fa-file-pdf text-red-500"></i> <?= $totalFilesCount ?> مذكرات وملفات
                    </span>
                    <?php if ($totalQuizzesCount > 0): ?>
                        <span class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 px-3 py-1.5 rounded-xl">
                            <i class="fa-solid fa-clock text-amber-500"></i> <?= $totalQuizzesCount ?> امتحانات وتدريبات
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Enrollment Action / Price Block (If not full course enrolled) -->
            <?php if (!$isFullCourseAccess): ?>
                <div class="text-center bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 p-6 rounded-2xl w-full md:w-auto min-w-[260px] space-y-3 shrink-0">
                    <div>
                        <span class="text-xs text-slate-400 block font-bold">اشتراك الكورس كاملاً</span>
                        <span class="text-2xl font-black text-primary block font-mono">
                            <?= number_format($course['price'], 0) ?> ج.م
                        </span>
                    </div>

                    <?php if ($user): ?>
                        <div class="space-y-2">
                            <a href="/purchase.php?id=<?= urlencode($courseId) ?>" class="block w-full py-2.5 bg-primary hover:bg-teal-700 text-white font-black text-xs rounded-xl shadow-md transition">
                                <i class="fa-solid fa-cart-shopping ml-1"></i> اشترك في الكورس كاملاً
                            </a>
                            <button onclick="openActivationModal()" class="w-full py-2.5 bg-gold-custom hover:bg-yellow-500 text-navy-custom font-black text-xs rounded-xl shadow transition">
                                <i class="fa-solid fa-key ml-1"></i> تفعيل كود الاشتراك
                            </button>
                        </div>
                    <?php else: ?>
                        <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="block w-full py-3 bg-primary hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow transition">
                            <i class="fa-solid fa-right-to-bracket ml-1"></i> سجل دخول للاشتراك
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Theater Layout Grid (Player on Right/Main, Syllabus on Left/Sidebar) -->
        <div class="course-layout-grid">

            <!-- MAIN COLUMN: Video Player & Details -->
            <div class="space-y-6">

                <?php if ($hasSelectedLesson && $canAccessActiveVideo && $activeVideo && $videoSource): ?>
                    <!-- 1. UNLOCKED LESSON CINEMA PLAYER -->
                    <div id="secure-player-wrapper" class="player-cinema-box select-none relative overflow-hidden protected-content" oncontextmenu="return false;">

                        <!-- Secure Fullscreen Toggle Button (Keeps watermarks on top) -->
                        <div class="absolute top-3 left-3 z-40 flex items-center gap-2">
                            <button id="secureFullscreenBtn" type="button" onclick="toggleSecureFullscreen()" class="px-3.5 py-1.5 rounded-xl bg-black/75 hover:bg-black/90 border border-white/20 text-white text-xs font-bold transition flex items-center gap-1.5 backdrop-blur-md shadow-lg" title="شاشة كاملة آمنة">
                                <i class="fa-solid fa-expand ml-1"></i>
                                <span class="hidden sm:inline">شاشة كاملة</span>
                            </button>
                        </div>

                        <!-- Active Anti-Recording & Anti-Screenshot Shield -->
                        <div id="tamper-shield" class="security-screen-shield" style="display: none;">
                            <div class="w-16 h-16 rounded-2xl bg-red-500/20 text-red-500 flex items-center justify-center text-3xl mb-4 border border-red-500/30 animate-pulse">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <h3 class="text-xl sm:text-2xl font-black text-white mb-2 tracking-wide">
                                محتوى محمي بحقوق الملكية الفكرية
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-300 max-w-md leading-relaxed mb-4">
                                تم حجب الفيديو فوراً لرصد محاولة تصوير شاشة أو تبديل النوافذ لحماية حقوق الملكية الفكرية لمنصة <span class="text-amber-400 font-bold">الأستاذ محمد يوسف</span>.
                            </p>
                            <div class="inline-flex flex-wrap items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700/80 text-xs font-mono text-slate-300 shadow-lg">
                                <span class="text-red-400 font-bold"><i class="fa-solid fa-user-lock ml-1"></i> المشاهد المسجل:</span>
                                <span><?= htmlspecialchars($user['fullName'] ?: 'طالب مسجل') ?></span>
                                <span>•</span>
                                <span dir="ltr" class="text-amber-300 font-bold"><?= htmlspecialchars($user['phone'] ?? '') ?></span>
                                <span>•</span>
                                <span>ID: #<?= htmlspecialchars($user['attendanceID'] ?? $user['id']) ?></span>
                                <span>•</span>
                                <span class="text-slate-400"><?= htmlspecialchars($clientIp) ?></span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-4 flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-info text-amber-400"></i>
                                سيتم استئناف العرض تلقائياً بمجرد العودة لشاشة الدرس.
                            </p>
                        </div>

                        <!-- Subtle Diagonal Tiled Watermarks (Anti-Crop & External Camera Defense) -->
                        <div class="watermark-tiled-grid select-none pointer-events-none">
                            <div class="flex justify-between items-center opacity-20 font-mono text-[10px] sm:text-xs">
                                <span><?= htmlspecialchars($user['phone'] ?? '') ?> • ID:#<?= htmlspecialchars($user['attendanceID'] ?? $user['id']) ?></span>
                                <span><?= htmlspecialchars($user['fullName'] ?? '') ?> • <?= htmlspecialchars($clientIp) ?></span>
                            </div>
                            <div class="flex justify-center items-center py-2 pointer-events-none">
                                <span class="transform -rotate-12 tracking-wider text-xs sm:text-sm font-mono font-bold select-none opacity-25 text-center px-4 py-1.5 rounded-lg border border-white/10 bg-black/40 backdrop-blur-[1px]">
                                    <?= htmlspecialchars($user['fullName'] ?? '') ?> • <?= htmlspecialchars($user['phone'] ?? '') ?> • ID:#<?= htmlspecialchars($user['attendanceID'] ?? $user['id']) ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center opacity-20 font-mono text-[10px] sm:text-xs">
                                <span><?= htmlspecialchars($user['fullName'] ?? '') ?> • <?= htmlspecialchars($clientIp) ?></span>
                                <span><?= htmlspecialchars($user['phone'] ?? '') ?> • ID:#<?= htmlspecialchars($user['attendanceID'] ?? $user['id']) ?></span>
                            </div>
                        </div>

                        <!-- Dynamic Floating Anti-Recording Watermark Badge -->
                        <div id="dynamic-watermark" class="floating-watermark-badge">
                            <div class="floating-watermark-inner">
                                <i class="fa-solid fa-shield-halved text-amber-400 text-xs"></i>
                                <span><?= htmlspecialchars($user['fullName'] ?: ($user['firstName'] . ' ' . $user['lastName'])) ?></span>
                                <span>•</span>
                                <span dir="ltr" class="text-amber-300 font-mono"><?= htmlspecialchars($user['phone'] ?? '') ?></span>
                                <span>•</span>
                                <span class="font-mono">#<?= htmlspecialchars($user['attendanceID'] ?? $user['id']) ?></span>
                                <span>•</span>
                                <span id="wm-live-time" class="font-mono text-emerald-300 text-[10px]"></span>
                            </div>
                        </div>

                        <!-- Player Mount Point -->
                        <div id="player-mount-point" class="w-full h-full transition-all duration-300" data-player="<?= htmlspecialchars($encryptedVideoPayload) ?>">
                            <?php if ($videoSource['type'] === 'youtube'): ?>
                                <iframe id="video-frame" src="<?= htmlspecialchars($videoSource['embedUrl']) ?>" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen class="w-full h-full border-0 block bg-black"></iframe>
                            <?php elseif ($videoSource['type'] === 'dailymotion'): ?>
                                <iframe id="video-frame" src="<?= htmlspecialchars($videoSource['embedUrl']) ?>" allow="picture-in-picture" allowfullscreen class="w-full h-full border-0 block bg-black"></iframe>
                            <?php elseif ($videoSource['type'] === 'mp4'): ?>
                                <video id="direct-video-player" controls controlsList="nodownload" class="w-full h-full object-contain bg-black">
                                    <source src="<?= htmlspecialchars($videoSource['url']) ?>" type="video/mp4">
                                </video>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Psychological & Legal Deterrent Notice -->
                    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-amber-500/10 border border-amber-500/25 text-amber-900 dark:text-amber-200 text-xs leading-relaxed shadow-sm">
                        <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 text-lg">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                        <div class="flex-1">
                            <strong class="font-bold">تنبيه حماية الملكية الفكرية:</strong>
                            هذا الدرس محمي بنظام البصمة الرقمية الحية المشفرة ببيانات حسابك (الاسم، الهاتف، كود الطالب، وساعة المشاهدة بالثواني). يُحظر تماماً تصوير الشاشة أو تسجيل الفيديوهات أو تداولها بأي وسيلة. المخالفة تعرض صاحب الحساب للحظر النهائي والملاحقة القانونية فوراً.
                        </div>
                    </div>

                    <!-- Video Controls, Multi-Video Switcher & Metadata Bar -->
                    <div class="course-card p-6 space-y-5">
                        
                        <!-- Top Bar: Lesson Title & Multi-Video Switcher -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="px-2.5 py-0.5 bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300 text-[10px] font-black rounded-md">
                                        الحصة الحالية
                                    </span>
                                    <?php if ($activeCollection): ?>
                                        <span class="text-xs text-slate-400 font-bold">
                                            <?= htmlspecialchars($activeCollection['title']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h2 class="text-xl md:text-2xl font-black text-[#0d3753] dark:text-white">
                                    <?= htmlspecialchars($activeVideo['title']) ?>
                                </h2>
                            </div>

                            <!-- Next / Previous Lesson Buttons -->
                            <div class="flex items-center gap-2 shrink-0">
                                <?php if ($prevVideo): ?>
                                    <a href="/course.php?id=<?= urlencode($courseId) ?>&v=<?= urlencode($prevVideo['id']) ?>" 
                                       class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold hover:border-primary transition flex items-center gap-1.5 shadow-sm"
                                       title="الحصة السابقة: <?= htmlspecialchars($prevVideo['title']) ?>">
                                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                        <span>السابق</span>
                                    </a>
                                <?php endif; ?>

                                <?php if ($nextVideo): ?>
                                    <a href="/course.php?id=<?= urlencode($courseId) ?>&v=<?= urlencode($nextVideo['id']) ?>" 
                                       class="px-4 py-2 rounded-xl bg-primary hover:bg-teal-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm"
                                       title="الحصة التالية: <?= htmlspecialchars($nextVideo['title']) ?>">
                                        <span>التالي</span>
                                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Multi-Video Tabs within the Same Lesson (فيديو الشرح وفيديو الحل) -->
                        <?php if ($activeCollection && count($activeCollection['videos']) > 1): ?>
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/70 rounded-2xl border border-slate-200/80 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="text-xs font-bold text-slate-600 dark:text-slate-300 flex items-center gap-2">
                                    <i class="fa-solid fa-clapperboard text-primary"></i>
                                    <span>فيديوهات هذا الدرس:</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php foreach ($activeCollection['videos'] as $vIdx => $vItem): 
                                        $isThisActive = ($activeVideo['id'] === $vItem['id']);
                                    ?>
                                        <a href="/course.php?id=<?= urlencode($courseId) ?>&v=<?= urlencode($vItem['id']) ?>" 
                                           class="px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 <?= $isThisActive ? 'bg-primary text-white shadow-md' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-primary' ?>">
                                            <i class="fa-solid <?= $vIdx === 0 ? 'fa-play' : 'fa-pen-to-square' ?>"></i>
                                            <span><?= htmlspecialchars($vItem['title'] ?: ($vIdx === 0 ? '🎬 فيديو الشرح' : '📝 فيديو الحل والتدريبات')) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Video Description -->
                        <?php if (!empty($activeVideo['description'])): ?>
                            <div class="text-xs md:text-sm text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">
                                <?= htmlspecialchars($activeVideo['description']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Lesson Attachments (PDF الملخص) -->
                        <?php if ($activeCollection && !empty($activeCollection['files'])): ?>
                            <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                                <h4 class="text-xs font-black text-slate-800 dark:text-slate-200 mb-3 flex items-center gap-1.5">
                                    <i class="fa-solid fa-file-pdf text-red-500"></i> مذكرات وملخصات الشرح الخاصة بهذا الدرس:
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <?php foreach ($activeCollection['files'] as $f): ?>
                                        <a href="<?= htmlspecialchars($f['link']) ?>" target="_blank" download class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-700/80 bg-slate-50 dark:bg-slate-800/60 hover:border-primary transition flex items-center justify-between text-xs group shadow-sm">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <div class="w-9 h-9 rounded-xl bg-red-100 dark:bg-red-900/30 text-red-600 flex items-center justify-center shrink-0">
                                                    <i class="fa-solid fa-file-pdf text-base"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate group-hover:text-primary transition">
                                                        <?= htmlspecialchars($f['title']) ?>
                                                    </div>
                                                    <div class="text-[10px] text-slate-400">ملف ملخص الدرس PDF</div>
                                                </div>
                                            </div>
                                            <span class="text-[11px] text-primary font-bold flex items-center gap-1 shrink-0 px-3 py-1.5 rounded-lg bg-teal-50 dark:bg-teal-950/60">
                                                <i class="fa-solid fa-download"></i> تحميل
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Lesson Quizzes / Exams (امتحان الحصة التدريبي) -->
                        <?php if ($activeCollection && !empty($activeCollection['quizzes'])): ?>
                            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-3">
                                <h4 class="text-xs font-black text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                    <i class="fa-solid fa-award text-amber-500"></i> امتحانات وتدريبات هذا الدرس:
                                </h4>
                                <?php foreach ($activeCollection['quizzes'] as $qz): 
                                    $isQuizPassed = in_array($qz['id'], $passedQuizIds);
                                ?>
                                    <div class="p-4 rounded-2xl border <?= $isQuizPassed ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/50 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/40 dark:border-amber-900/50 dark:bg-amber-950/20' ?> flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-2xl <?= $isQuizPassed ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50' : 'bg-amber-100 text-amber-600 dark:bg-amber-900/50' ?> flex items-center justify-center text-lg shrink-0">
                                                <i class="fa-solid <?= $isQuizPassed ? 'fa-circle-check' : 'fa-clipboard-question' ?>"></i>
                                            </div>
                                            <div>
                                                <div class="text-xs sm:text-sm font-black text-slate-900 dark:text-white">
                                                    <?= htmlspecialchars($qz['title']) ?>
                                                </div>
                                                <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2 mt-0.5">
                                                    <span><i class="fa-solid fa-clock ml-0.5"></i> <?= (int)$qz['quizTime'] ?> دقيقة</span>
                                                    <span>•</span>
                                                    <span class="<?= $isQuizPassed ? 'text-emerald-600 font-bold' : 'text-amber-600 font-bold' ?>">
                                                        <?= $isQuizPassed ? 'تم اجتياز الامتحان بنجاح ✓' : 'يلزم اجتياز الامتحان لفتح الحصة التالية' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="/quiz.php?id=<?= urlencode($qz['id']) ?>" class="px-5 py-2.5 rounded-xl <?= $isQuizPassed ? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200' : 'bg-primary hover:bg-teal-700 text-white shadow-md' ?> font-black text-xs transition flex items-center justify-center gap-1.5 shrink-0">
                                            <i class="fa-solid <?= $isQuizPassed ? 'fa-eye' : 'fa-pen-to-square' ?>"></i>
                                            <span><?= $isQuizPassed ? 'مراجعة نتيجة الامتحان' : 'ابدأ امتحان الحصة الآن' ?></span>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </div>

                <?php elseif ($hasSelectedLesson && $activeCollection && !empty($activeCollection['isPrerequisiteLocked'])): ?>
                    <!-- 2. PREREQUISITE LOCKED SCREEN (الشرح + الحل + التاسك) -->
                    <?php 
                        $pInfo = $activeCollection['prerequisiteInfo'] ?? [];
                        $prevTitle = $pInfo['prevTitle'] ?? 'الحصة السابقة';
                        $prevId = $pInfo['prevId'] ?? '';
                        $watchedExp = !empty($pInfo['watchedExplanation']);
                        $expVid = $pInfo['explanationVideo'] ?? null;
                        $hasSol = !empty($pInfo['hasSolution']);
                        $watchedSol = !empty($pInfo['watchedSolution']);
                        $solVid = $pInfo['solutionVideo'] ?? null;
                        $hasQz = !empty($pInfo['hasQuiz']);
                        $passedQz = !empty($pInfo['passedQuiz']);
                        $quizItem = $pInfo['quiz'] ?? null;
                    ?>
                    <div class="course-card p-6 md:p-12 space-y-6">
                        <div class="text-center space-y-3 max-w-xl mx-auto">
                            <div class="w-20 h-20 rounded-3xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 flex items-center justify-center text-3xl mx-auto shadow-inner border border-amber-200 dark:border-amber-800">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <span class="inline-block px-3.5 py-1 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-xs font-black rounded-full">
                                ⚠️ شروط فتح الحصة التالية
                            </span>
                            <h2 class="text-xl md:text-2xl font-black text-[#0d3753] dark:text-white">
                                حصة: <?= htmlspecialchars($activeCollection['title']) ?>
                            </h2>
                            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                                هذه الحصة مقفلة حتى تستكمل متطلبات الحصة السابقة (<strong class="text-slate-800 dark:text-slate-200">"<?= htmlspecialchars($prevTitle) ?>"</strong>). تفقد الشروط أدناه لإكمال ما تبقى منها:
                            </p>
                        </div>

                        <!-- 3-Item Prerequisite Checklist -->
                        <div class="max-w-xl mx-auto space-y-3 pt-2">
                            
                            <!-- 1. فيديو الشرح -->
                            <div class="p-4 rounded-2xl border <?= $watchedExp ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/50 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' ?> flex items-center justify-between gap-3 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl <?= $watchedExp ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40' : 'bg-amber-100 text-amber-600 dark:bg-amber-900/40' ?> flex items-center justify-center text-base shrink-0">
                                        <i class="fa-solid <?= $watchedExp ? 'fa-check' : 'fa-play' ?>"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs sm:text-sm font-black text-slate-900 dark:text-white">
                                            1. سماع فيديو الشرح
                                        </div>
                                        <div class="text-[11px] <?= $watchedExp ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400' ?>">
                                            <?= $watchedExp ? 'تمت المشاهدة بنجاح ✓' : 'يلزم سماع فيديو الشرح للحصة السابقة' ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!$watchedExp && $expVid): ?>
                                    <a href="/course.php?id=<?= urlencode($courseId) ?>&v=<?= urlencode($expVid['id']) ?>" class="px-4 py-2 bg-primary hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow transition shrink-0 flex items-center gap-1.5">
                                        <i class="fa-solid fa-play text-[10px]"></i> مشاهدة الشرح
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- 2. فيديو الحل والتدريبات -->
                            <?php if ($hasSol): ?>
                                <div class="p-4 rounded-2xl border <?= $watchedSol ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/50 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' ?> flex items-center justify-between gap-3 shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl <?= $watchedSol ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40' : 'bg-amber-100 text-amber-600 dark:bg-amber-900/40' ?> flex items-center justify-center text-base shrink-0">
                                            <i class="fa-solid <?= $watchedSol ? 'fa-check' : 'fa-pen-to-square' ?>"></i>
                                        </div>
                                        <div>
                                            <div class="text-xs sm:text-sm font-black text-slate-900 dark:text-white">
                                                2. سماع فيديو الحل والتدريبات
                                            </div>
                                            <div class="text-[11px] <?= $watchedSol ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400' ?>">
                                                <?= $watchedSol ? 'تمت المشاهدة بنجاح ✓' : 'يلزم سماع فيديو حل التمارين' ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (!$watchedSol && $solVid): ?>
                                        <a href="/course.php?id=<?= urlencode($courseId) ?>&v=<?= urlencode($solVid['id']) ?>" class="px-4 py-2 bg-primary hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow transition shrink-0 flex items-center gap-1.5">
                                            <i class="fa-solid fa-play text-[10px]"></i> مشاهدة الحل
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- 3. تاسك / امتحان الحصة -->
                            <?php if ($hasQz): ?>
                                <div class="p-4 rounded-2xl border <?= $passedQz ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/50 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' ?> flex items-center justify-between gap-3 shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl <?= $passedQz ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40' : 'bg-amber-100 text-amber-600 dark:bg-amber-900/40' ?> flex items-center justify-center text-base shrink-0">
                                            <i class="fa-solid <?= $passedQz ? 'fa-check' : 'fa-award' ?>"></i>
                                        </div>
                                        <div>
                                            <div class="text-xs sm:text-sm font-black text-slate-900 dark:text-white">
                                                3. اجتياز تاسك / امتحان الحصة (50% فأعلى)
                                            </div>
                                            <div class="text-[11px] <?= $passedQz ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400' ?>">
                                                <?= $passedQz ? 'تم اجتياز الامتحان بنجاح ✓' : 'يلزم أداء الامتحان وتحقيق نسبة 50% على الأقل' ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (!$passedQz && $quizItem): ?>
                                        <a href="/quiz.php?id=<?= urlencode($quizItem['id']) ?>" class="px-4 py-2 bg-primary hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow transition shrink-0 flex items-center gap-1.5">
                                            <i class="fa-solid fa-pen-to-square text-[10px]"></i> بدء الامتحان الآن
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        </div>

                        <!-- Back to Previous Lesson -->
                        <div class="text-center pt-2">
                            <?php if ($prevId): ?>
                                <a href="/course.php?id=<?= urlencode($courseId) ?>&col=<?= urlencode($prevId) ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-black text-xs rounded-2xl transition shadow-sm">
                                    <i class="fa-solid fa-arrow-right"></i>
                                    <span>العودة للحصة السابقة ("<?= htmlspecialchars($prevTitle) ?>")</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($hasSelectedLesson && $activeCollection && !$activeCollection['isPurchased']): ?>
                    <!-- 3. UNPURCHASED SINGLE LESSON LOCK SCREEN WITH INLINE CODE REDEEM -->
                    <div class="course-card p-8 md:p-14 text-center space-y-6">
                        <div class="w-20 h-20 rounded-3xl bg-amber-50 dark:bg-amber-950/40 text-gold-custom flex items-center justify-center text-3xl mx-auto shadow-inner border border-amber-200 dark:border-amber-800">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <div class="space-y-3 max-w-lg mx-auto">
                            <span class="inline-block px-3 py-1 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-xs font-black rounded-full mb-1">
                                🔒 حصة مغلقة
                            </span>
                            <h2 class="text-xl md:text-2xl font-black text-[#0d3753] dark:text-white">
                                حصة: <?= htmlspecialchars($activeCollection['title']) ?>
                            </h2>
                            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                                هذه الحصة مغلقة وغير مفعلة بحسابك. لفك إغلاق هذه الحصة والحصول على محتوياتها بالكامل (فيديو الشرح، فيديو الحل، ملخص الدرس، والتاسك)، يرجى التواصل مع الدعم الفني لاستلام كود تفعيل الحصة وتفعيله مباشرة أدناه:
                            </p>
                        </div>

                        <!-- Instant Direct Code Redemption for this Lesson -->
                        <div class="max-w-md mx-auto bg-slate-50 dark:bg-slate-800/60 p-5 rounded-3xl border border-slate-200 dark:border-slate-700 space-y-3 shadow-inner">
                            <label class="block text-xs font-black text-slate-700 dark:text-slate-300 text-right">
                                <i class="fa-solid fa-key text-gold-custom ml-1"></i> تفعيل كود الحصة مباشرة في حسابك:
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="directLessonCodeInput" placeholder="أدخل كود الحصة (مثال: HSA-XXXXX)" class="flex-1 px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-bold font-mono uppercase text-slate-800 dark:text-white outline-none focus:border-primary">
                                <button type="button" id="directLessonBtn" onclick="redeemDirectLessonCode()" class="px-5 py-2.5 bg-primary hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow transition shrink-0 flex items-center gap-1.5">
                                    <i class="fa-solid fa-bolt"></i> تفعيل الحصة
                                </button>
                            </div>
                            <div id="directLessonAlert" class="text-xs font-bold p-3 rounded-xl hidden"></div>
                        </div>

                        <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                            <a href="https://wa.me/201092037257?text=<?= urlencode('السلام عليكم، أود الحصول على كود تفعيل لحصة: ' . $activeCollection['title'] . ' في كورس: ' . $course['title']) ?>" target="_blank" class="px-6 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-2xl shadow-lg transition flex items-center gap-2">
                                <i class="fa-brands fa-whatsapp text-sm"></i>
                                <span>طلب كود الحصة عبر واتساب</span>
                            </a>

                            <a href="/purchase.php?id=<?= urlencode($courseId) ?>" class="px-5 py-3.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-bold text-xs rounded-2xl transition flex items-center gap-2">
                                <i class="fa-solid fa-graduation-cap"></i>
                                <span>أو الاشتراك في الكورس بالكامل (<?= number_format($course['price'], 0) ?> ج.م)</span>
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- 4. WELCOMING COURSE OVERVIEW (NO LESSON PICKED YET) -->
                    <div class="course-card p-8 md:p-12 space-y-6">
                        <div class="text-center max-w-xl mx-auto space-y-2">
                            <span class="inline-block px-3.5 py-1 bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300 text-xs font-black rounded-full mb-1">
                                📚 خريطة وفهرس حصص الكورس
                            </span>
                            <h2 class="text-2xl md:text-3xl font-black text-[#0d3753] dark:text-white">
                                مرحباً بك في <?= htmlspecialchars($course['title']) ?>
                            </h2>
                            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">
                                اختر الحصة التي ترغب في حضورها من الحصص المتوفرة أدناه، أو قم بتفعيل كود الحصة للبدء في المشاهدة:
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4">
                            <?php foreach ($collections as $cIdx => $cItem): ?>
                                <div class="p-5 rounded-3xl border <?= $cItem['isUnlocked'] ? 'border-teal-200 bg-teal-50/30 dark:border-teal-900/40 dark:bg-teal-950/20' : 'border-slate-200 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-900/30' ?> flex flex-col justify-between space-y-4 shadow-sm hover:shadow-md transition">
                                    <div>
                                        <div class="flex items-center justify-between mb-2.5">
                                            <span class="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center text-xs font-black shadow-sm">
                                                <?= $cIdx + 1 ?>
                                            </span>
                                            <?php if ($cItem['isUnlocked']): ?>
                                                <span class="text-[10px] bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 font-bold px-2.5 py-1 rounded-full flex items-center gap-1">
                                                    <i class="fa-solid fa-lock-open text-[9px]"></i> مفعلة بحسابك
                                                </span>
                                            <?php elseif (!empty($cItem['isPrerequisiteLocked'])): ?>
                                                <span class="text-[10px] bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 font-bold px-2.5 py-1 rounded-full flex items-center gap-1">
                                                    <i class="fa-solid fa-lock text-[9px]"></i> مقفلة: أكمل السابقة
                                                </span>
                                            <?php else: ?>
                                                <span class="text-[10px] bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300 font-bold px-2.5 py-1 rounded-full flex items-center gap-1">
                                                    <i class="fa-solid fa-lock text-[9px]"></i> مغلقة
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="text-sm font-black text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($cItem['title']) ?>
                                        </h3>
                                        
                                        <!-- 4 components display: شرح + حل + تلخيص + تاسك -->
                                        <div class="grid grid-cols-2 gap-1.5 text-[10px] font-bold mt-3">
                                            <div class="p-1.5 rounded-lg bg-white/80 dark:bg-slate-800/80 border border-slate-100 dark:border-slate-700 flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                                                <i class="fa-solid fa-play text-emerald-500"></i>
                                                <span>فيديو الشرح</span>
                                            </div>
                                            <div class="p-1.5 rounded-lg bg-white/80 dark:bg-slate-800/80 border border-slate-100 dark:border-slate-700 flex items-center gap-1.5 <?= !empty($cItem['videos'][1]) ? 'text-slate-600 dark:text-slate-300' : 'text-slate-400' ?>">
                                                <i class="fa-solid fa-pen-to-square text-teal-600"></i>
                                                <span>فيديو الحل</span>
                                            </div>
                                            <div class="p-1.5 rounded-lg bg-white/80 dark:bg-slate-800/80 border border-slate-100 dark:border-slate-700 flex items-center gap-1.5 <?= !empty($cItem['files']) ? 'text-slate-600 dark:text-slate-300' : 'text-slate-400' ?>">
                                                <i class="fa-solid fa-file-pdf text-red-500"></i>
                                                <span>ملخص الدرس PDF</span>
                                            </div>
                                            <div class="p-1.5 rounded-lg bg-white/80 dark:bg-slate-800/80 border border-slate-100 dark:border-slate-700 flex items-center gap-1.5 <?= !empty($cItem['quizzes']) ? 'text-amber-700 dark:text-amber-400' : 'text-slate-400' ?>">
                                                <i class="fa-solid fa-award text-amber-500"></i>
                                                <span>تاسك / امتحان</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pt-2">
                                        <?php if ($cItem['isUnlocked']): ?>
                                            <a href="/course.php?id=<?= urlencode($courseId) ?>&col=<?= urlencode($cItem['id']) ?>" class="w-full py-2.5 rounded-xl bg-primary hover:bg-teal-700 text-white text-xs font-black transition flex items-center justify-center gap-1.5 shadow-sm">
                                                <i class="fa-solid fa-play text-[10px]"></i>
                                                <span>مشاهدة محتوى الحصة</span>
                                            </a>
                                        <?php elseif (!empty($cItem['isPrerequisiteLocked'])): ?>
                                            <a href="/course.php?id=<?= urlencode($courseId) ?>&col=<?= urlencode($cItem['id']) ?>" class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-black transition flex items-center justify-center gap-1.5 shadow-sm">
                                                <i class="fa-solid fa-list-check text-[10px]"></i>
                                                <span>متطلبات فتح الحصة ⚠️</span>
                                            </a>
                                        <?php else: ?>
                                            <a href="/course.php?id=<?= urlencode($courseId) ?>&col=<?= urlencode($cItem['id']) ?>" class="w-full py-2.5 rounded-xl bg-[#c79a32] hover:bg-yellow-500 text-[#0d3753] text-xs font-black transition flex items-center justify-center gap-1.5 shadow-sm">
                                                <i class="fa-solid fa-key text-[10px]"></i>
                                                <span>فك إغلاق الحصة</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- SIDEBAR: Syllabus & Lessons Playlist -->
            <div class="course-card p-5 md:p-6 space-y-6">
                
                <!-- Sidebar Header -->
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div>
                        <h3 class="text-base md:text-lg font-black text-[#0d3753] dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-primary"></i> فهرس الدروس والمحاضرات
                        </h3>
                        <span class="text-[11px] text-slate-400 font-semibold block mt-0.5">
                            <?= $totalVideosCount ?> حصص متوفرة في الكورس
                        </span>
                    </div>

                    <?php if ($canAccess && $totalVideosCount > 0 && $activeVideoIndex >= 0): ?>
                        <span class="text-[11px] font-black bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300 px-2.5 py-1 rounded-lg">
                            <?= ($activeVideoIndex + 1) ?> / <?= $totalVideosCount ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Collections List -->
                <div class="space-y-4">
                    <?php if (empty($collections)): ?>
                        <p class="text-xs text-slate-400 text-center py-8">سيتم إضافة الدروس قريباً</p>
                    <?php else: ?>
                        <?php foreach ($collections as $idx => $col): 
                            $isColActive = ($activeCollection && $activeCollection['id'] === $col['id']);
                        ?>
                            <div class="border <?= $isColActive ? 'border-primary dark:border-teal-500' : 'border-slate-200 dark:border-slate-800' ?> rounded-2xl overflow-hidden bg-slate-50/50 dark:bg-slate-900/40 transition">
                                
                                <!-- Collection Header -->
                                <a href="/course.php?id=<?= urlencode($courseId) ?>&col=<?= urlencode($col['id']) ?>" class="p-3.5 bg-slate-100 dark:bg-slate-800/80 font-black text-xs text-[#0d3753] dark:text-white flex items-center justify-between block hover:bg-slate-200/80 transition">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-5 h-5 rounded-full <?= $isColActive ? 'bg-primary' : 'bg-slate-400 dark:bg-slate-600' ?> text-white flex items-center justify-center text-[10px] shrink-0">
                                            <?= ($idx + 1) ?>
                                        </span>
                                        <span class="truncate"><?= htmlspecialchars($col['title']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <?php if ($col['isUnlocked']): ?>
                                            <span class="text-[10px] bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                                                <i class="fa-solid fa-lock-open text-[9px]"></i> مفعلة
                                            </span>
                                        <?php elseif (!empty($col['isPrerequisiteLocked'])): ?>
                                            <span class="text-[10px] bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                                                <i class="fa-solid fa-lock text-[9px]"></i> بشروط
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300 font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                                                <i class="fa-solid fa-lock text-[9px]"></i> مغلقة
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </a>

                                <!-- Collection Items (Videos, Files, Quizzes) -->
                                <div class="divide-y divide-slate-200/60 dark:divide-slate-800/60 p-1.5 space-y-1">
                                    
                                    <!-- Videos -->
                                    <?php foreach ($col['videos'] as $vIdx => $v): 
                                        $isActive = ($activeVideo && $activeVideo['id'] === $v['id']);
                                        $isWatched = in_array($v['id'], $watchedVideoIds);
                                        $videoLabel = ($vIdx === 0) ? 'فيديو الشرح' : 'فيديو الحل';
                                    ?>
                                        <a href="/course.php?id=<?= urlencode($courseId) ?>&v=<?= urlencode($v['id']) ?>" 
                                           class="p-2.5 rounded-xl flex items-center justify-between text-xs transition block <?= $isActive ? 'lesson-active-item' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium' ?>">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <i class="fa-solid <?= $isActive ? 'fa-circle-play text-primary' : ($isWatched ? 'fa-circle-check text-emerald-500' : ($col['isUnlocked'] ? ($vIdx === 0 ? 'fa-play text-emerald-500' : 'fa-pen-to-square text-teal-600') : 'fa-lock text-slate-400')) ?> text-xs shrink-0"></i>
                                                <span class="truncate"><?= htmlspecialchars($v['title'] ?: $videoLabel) ?></span>
                                            </div>
                                            <?php if ($isActive): ?>
                                                <span class="text-[10px] text-primary dark:text-teal-400 font-black shrink-0 mr-2 flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-primary dark:bg-teal-400 animate-ping"></span>
                                                    يعمل الآن
                                                </span>
                                            <?php elseif ($isWatched): ?>
                                                <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold shrink-0 mr-2 flex items-center gap-1">
                                                    تمت المشاهدة ✓
                                                </span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>

                                    <!-- Files / Notes -->
                                    <?php foreach ($col['files'] as $f): ?>
                                        <div class="p-2 rounded-xl flex items-center justify-between text-xs text-slate-600 dark:text-slate-400 bg-white/70 dark:bg-slate-800/40">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <i class="fa-solid fa-file-pdf text-red-500 text-sm shrink-0"></i>
                                                <span class="truncate"><?= htmlspecialchars($f['title']) ?></span>
                                            </div>
                                            <?php if ($col['isUnlocked']): ?>
                                                <a href="<?= htmlspecialchars($f['link']) ?>" target="_blank" download class="text-primary hover:underline font-bold text-[10px] shrink-0 mr-2">
                                                    تحميل PDF
                                                </a>
                                            <?php else: ?>
                                                <i class="fa-solid fa-lock text-slate-300 text-xs shrink-0 mr-2"></i>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Quizzes -->
                                    <?php if (!empty($col['quizzes'])): ?>
                                        <?php foreach ($col['quizzes'] as $q): 
                                            $isQzPassed = in_array($q['id'], $passedQuizIds);
                                        ?>
                                            <a href="/quiz.php?id=<?= urlencode($q['id']) ?>" class="p-2 rounded-xl flex items-center justify-between text-xs text-slate-600 dark:text-slate-400 bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/50 dark:border-amber-800/30 hover:border-amber-400 transition block">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <i class="fa-solid <?= $isQzPassed ? 'fa-circle-check text-emerald-500' : 'fa-award text-amber-500' ?> text-sm shrink-0"></i>
                                                    <span class="truncate font-bold text-amber-800 dark:text-amber-300"><?= htmlspecialchars($q['title']) ?></span>
                                                </div>
                                                <span class="text-[10px] <?= $isQzPassed ? 'text-emerald-600' : 'text-amber-600 dark:text-amber-400' ?> font-bold shrink-0 mr-2">
                                                    <?= $isQzPassed ? 'تم الاجتياز ✓' : ($q['quizTime'] . ' دقيقة') ?>
                                                </span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>
</div>

<!-- ========================================================
     Activation Modal Dialog (Redeem Code)
     ======================================================== -->
<div id="activateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden">
    <div class="bg-white dark:bg-[#12283a] rounded-3xl max-w-md w-full p-6 md:p-8 space-y-6 shadow-2xl border border-slate-200 dark:border-slate-700 animate-in fade-in zoom-in-95 duration-200">
        
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="text-lg font-black text-[#0d3753] dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-key text-gold-custom"></i> <span id="modalTitleText">تفعيل كود الحصة أو الكورس</span>
            </h3>
            <button onclick="closeActivationModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-white flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <div class="space-y-2">
            <p id="modalDescText" class="text-xs text-slate-500 dark:text-slate-300 leading-relaxed">
                ادخل كود الكارت المستلم من السنتر أو المدرس لتفعيل الحصة أو الكورس فوراً في حسابك.
            </p>
            <div id="modalAlertBox" class="p-3 rounded-xl text-xs font-bold hidden"></div>
        </div>

        <form id="activationForm" onsubmit="handleRedeemCode(event)" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">كود التفعيل:</label>
                <input 
                    type="text" 
                    id="redeemCodeInput" 
                    placeholder="مثال: HSA-AB12CD أو MY-XXXX" 
                    dir="ltr"
                    required
                    class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-mono text-center font-bold tracking-wider focus:outline-none focus:ring-2 focus:ring-primary uppercase transition"
                />
            </div>

            <button 
                type="submit" 
                id="submitRedeemBtn" 
                class="w-full py-3.5 bg-primary hover:bg-teal-700 text-white font-black text-xs rounded-xl shadow-md transition flex items-center justify-center gap-2">
                <span>تأكيد وشحن الكود</span>
            </button>
        </form>

    </div>
</div>

<!-- ========================================================
     Security, Anti-Recording & Dynamic Watermark Scripts
     ======================================================== -->
<script>
let currentModalColId = null;

// Modal Handlers
function openActivationModal(collectionId = null) {
    currentModalColId = collectionId;
    const m = document.getElementById('activateModal');
    const title = document.getElementById('modalTitleText');
    const desc = document.getElementById('modalDescText');
    if (collectionId) {
        if (title) title.textContent = 'تفعيل كود الحصة المستلم من السنتر';
        if (desc) desc.textContent = 'ادخل كود كارت الحصة لتفعيل هذه الحصة والبدء بمشاهدتها فوراً.';
    } else {
        if (title) title.textContent = 'تفعيل كود الكورس';
        if (desc) desc.textContent = 'ادخل كود الكارت المستلم من السنتر أو المدرس لتفعيل اشتراكك في الكورس.';
    }
    if (m) {
        m.classList.remove('hidden');
        const inp = document.getElementById('redeemCodeInput');
        if (inp) inp.focus();
    }
}

function closeActivationModal() {
    const m = document.getElementById('activateModal');
    if (m) m.classList.add('hidden');
}

// 1-Click Pay for Single Lesson via Wallet
async function payLessonWallet(collectionId, title, price) {
    if (!confirm(`هل تريد تأكيد دفع ${price} ج.م من رصيد محفظتك لشراء حصة "${title}"؟`)) {
        return;
    }

    try {
        const res = await fetch('/api/custom.php?action=pay-from-wallet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                courseId: <?= json_encode($courseId) ?>,
                collectionId: collectionId 
            })
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'تم شراء الحصة بنجاح!');
            window.location.reload();
        } else {
            alert(data.error || 'فشلت عملية الدفع من المحفظة.');
        }
    } catch (err) {
        alert('حدث خطأ في الاتصال بالخادم، يرجى المحاولة ثانية.');
    }
}

// AJAX Code Redemption
async function handleRedeemCode(e) {
    e.preventDefault();
    const input = document.getElementById('redeemCodeInput');
    const alertBox = document.getElementById('modalAlertBox');
    const btn = document.getElementById('submitRedeemBtn');
    const code = input.value.trim();

    if (!code) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري التحقق...';
    alertBox.className = 'p-3 rounded-xl text-xs font-bold hidden';

    try {
        const res = await fetch('/api/custom.php?action=redeem-course-code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code })
        });
        const data = await res.json();

        if (data.success) {
            alertBox.className = 'p-3 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 block';
            alertBox.textContent = data.message || 'تم التفعيل بنجاح!';
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        } else {
            alertBox.className = 'p-3 rounded-xl text-xs font-bold bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300 block';
            alertBox.textContent = data.error || 'الكود غير صحيح أو مستخدم مسبقاً.';
            btn.disabled = false;
            btn.innerHTML = '<span>تأكيد وشحن الكود</span>';
        }
    } catch (err) {
        alertBox.className = 'p-3 rounded-xl text-xs font-bold bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300 block';
        alertBox.textContent = 'حدث خطأ في الاتصال بالخادم، يرجى المحاولة ثانية.';
        btn.disabled = false;
        btn.innerHTML = '<span>تأكيد وشحن الكود</span>';
    }
}

// Direct Lesson Code Redemption (From lesson lock card)
async function redeemDirectLessonCode() {
    const input = document.getElementById('directLessonCodeInput');
    const alertBox = document.getElementById('directLessonAlert');
    const btn = document.getElementById('directLessonBtn');
    if (!input || !btn || !alertBox) return;

    const code = input.value.trim();
    if (!code) {
        alertBox.className = 'text-xs font-bold p-3 rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 block';
        alertBox.textContent = 'يرجى إدخال كود الحصة أولاً.';
        return;
    }

    btn.disabled = true;
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin ml-1"></i> جاري التفعيل...';
    alertBox.className = 'text-xs font-bold p-3 rounded-xl hidden';

    try {
        const res = await fetch('/api/custom.php?action=redeem-course-code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code })
        });
        const data = await res.json();
        if (data.success) {
            alertBox.className = 'text-xs font-bold p-3 rounded-xl bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 block';
            alertBox.textContent = data.message || 'تم تفعيل الحصة بنجاح! جاري التحديث...';
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        } else {
            alertBox.className = 'text-xs font-bold p-3 rounded-xl bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300 block';
            alertBox.textContent = data.error || 'الكود غير صحيح أو مستخدم مسبقاً.';
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    } catch(err) {
        alertBox.className = 'text-xs font-bold p-3 rounded-xl bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300 block';
        alertBox.textContent = 'حدث خطأ في الاتصال بالخادم، يرجى المحاولة ثانية.';
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}

// Secure Fullscreen Toggler (Leaves watermarks and anti-piracy shield strictly on top)
function toggleSecureFullscreen() {
    const wrapper = document.getElementById('secure-player-wrapper');
    if (!wrapper) return;

    if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.mozFullScreenElement && !document.msFullscreenElement) {
        if (wrapper.requestFullscreen) {
            wrapper.requestFullscreen().catch(err => console.error(err));
        } else if (wrapper.webkitRequestFullscreen) {
            wrapper.webkitRequestFullscreen();
        } else if (wrapper.mozRequestFullScreen) {
            wrapper.mozRequestFullScreen();
        } else if (wrapper.msRequestFullscreen) {
            wrapper.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen().catch(err => console.error(err));
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
}

function updateFullscreenBtnState() {
    const btn = document.getElementById('secureFullscreenBtn');
    if (!btn) return;
    const isFull = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement);
    if (isFull) {
        btn.innerHTML = '<i class="fa-solid fa-compress ml-1"></i><span class="hidden sm:inline">إنهاء الشاشة الكاملة</span>';
        btn.setAttribute('title', 'إنهاء الشاشة الكاملة');
    } else {
        btn.innerHTML = '<i class="fa-solid fa-expand ml-1"></i><span class="hidden sm:inline">شاشة كاملة</span>';
        btn.setAttribute('title', 'شاشة كاملة آمنة');
    }
}

['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'].forEach(function(evt) {
    document.addEventListener(evt, updateFullscreenBtnState);
});

// ==============================================================
// Advanced Anti-Piracy, Dynamic Forensic Watermark & Anti-Recording Shield
// ==============================================================
(function() {
    const playerWrapper = document.getElementById('secure-player-wrapper');
    const wm = document.getElementById('dynamic-watermark');
    const shield = document.getElementById('tamper-shield');
    const mount = document.getElementById('player-mount-point');

    if (!playerWrapper) return;

    // 0. Ensure Player is mounted (PHP renders it directly; JS ensures fallback if needed)
    if (mount && !mount.querySelector('iframe') && !mount.querySelector('video')) {
        const rawToken = mount.getAttribute('data-player');
        if (rawToken) {
            try {
                const salt = 'MY_SECURE_VIDEO_GUARD_2026';
                const bin = atob(rawToken);
                let dec = '';
                for (let i = 0; i < bin.length; i++) {
                    dec += String.fromCharCode(bin.charCodeAt(i) ^ salt.charCodeAt(i % salt.length));
                }
                const source = JSON.parse(dec);
                mount.innerHTML = '';

                if (source.type === 'youtube') {
                    const ifr = document.createElement('iframe');
                    ifr.id = 'video-frame';
                    ifr.src = source.embedUrl;
                    ifr.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
                    ifr.allowFullscreen = true;
                    ifr.className = 'w-full h-full border-0 block bg-black';
                    mount.appendChild(ifr);
                } else if (source.type === 'dailymotion') {
                    const ifr = document.createElement('iframe');
                    ifr.id = 'video-frame';
                    ifr.src = source.embedUrl;
                    ifr.allow = 'autoplay; fullscreen; picture-in-picture';
                    ifr.allowFullscreen = true;
                    ifr.className = 'w-full h-full border-0 block bg-black';
                    mount.appendChild(ifr);
                } else if (source.type === 'mp4') {
                    const vid = document.createElement('video');
                    vid.id = 'direct-video-player';
                    vid.controls = true;
                    vid.controlsList = 'nodownload';
                    vid.className = 'w-full h-full object-contain bg-black';
                    const src = document.createElement('source');
                    src.src = source.url;
                    src.type = 'video/mp4';
                    vid.appendChild(src);
                    mount.appendChild(vid);
                }
            } catch(err) {}
        }
    }

    // 1. Dynamic Moving Watermark Coordinates (Moving every 3.5s)
    function animateWatermark() {
        if (!wm) return;
        const minX = 4, maxX = 60;
        const minY = 6, maxY = 76;
        const x = Math.floor(Math.random() * (maxX - minX + 1)) + minX;
        const y = Math.floor(Math.random() * (maxY - minY + 1)) + minY;
        const op = (Math.random() * 0.30 + 0.55).toFixed(2);

        wm.style.top = y + '%';
        wm.style.left = x + '%';
        wm.style.opacity = op;
    }
    if (wm) {
        setInterval(animateWatermark, 3500);
        animateWatermark();
    }

    // 2. Real-time Clock for Watermark Badge
    function updateWatermarkClock() {
        const clockEl = document.getElementById('wm-live-time');
        if (clockEl) {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            clockEl.textContent = h + ':' + m + ':' + s;
        }
    }
    setInterval(updateWatermarkClock, 1000);
    updateWatermarkClock();

    // 3. Blackout Tamper Shield Controls
    let isShieldActive = false;
    let shieldRestoreTimer = null;

    function activateSecurityShield(reason) {
        isShieldActive = true;
        if (shieldRestoreTimer) clearTimeout(shieldRestoreTimer);

        if (shield) {
            shield.style.display = 'flex';
            shield.classList.add('show-shield');
        }
        if (mount) {
            mount.style.filter = 'blur(45px)';
            mount.style.opacity = '0';
        }

        const directVid = document.getElementById('direct-video-player');
        if (directVid && !directVid.paused) {
            try { directVid.pause(); } catch(e) {}
        }

        // Wipe clipboard content on screen capture or copy attempt
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText("⚠️ محتوى تعليمي محمي بحقوق الملكية الفكرية - منصة الأستاذ محمد يوسف");
            }
        } catch(e) {}
    }

    function deactivateSecurityShield() {
        if (!isShieldActive) return;
        // Keep shield for 700ms cooldown to ensure screen capture tools miss the video frames
        if (shieldRestoreTimer) clearTimeout(shieldRestoreTimer);
        shieldRestoreTimer = setTimeout(function() {
            isShieldActive = false;
            if (shield) {
                shield.style.display = 'none';
                shield.classList.remove('show-shield');
            }
            if (mount) {
                mount.style.filter = 'none';
                mount.style.opacity = '1';
            }
        }, 700);
    }

    // 4. Trigger Shield when Window loses focus (Snipping tool, screen recorders, app switcher)
    window.addEventListener('blur', function() {
        activateSecurityShield('window_blur');
    });

    window.addEventListener('focus', function() {
        deactivateSecurityShield();
    });

    // 5. Trigger Shield when Tab visibility changes (switching tabs or minimizing)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            activateSecurityShield('tab_hidden');
        } else {
            deactivateSecurityShield();
        }
    });

    // 6. Keyboard & Screenshot Traps
    window.addEventListener('keydown', function(e) {
        // PrintScreen Key
        if (e.key === 'PrintScreen' || e.keyCode === 44) {
            e.preventDefault();
            e.stopPropagation();
            activateSecurityShield('printscreen');
            setTimeout(deactivateSecurityShield, 3500);
            return false;
        }

        // F12 (DevTools)
        if (e.keyCode === 123 || e.key === 'F12') {
            e.preventDefault();
            e.stopPropagation();
            activateSecurityShield('f12');
            setTimeout(deactivateSecurityShield, 2500);
            return false;
        }

        // Ctrl+Shift+I / J / C (DevTools Inspect)
        if (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 67 || e.key === 'I' || e.key === 'J' || e.key === 'C')) {
            e.preventDefault();
            e.stopPropagation();
            activateSecurityShield('inspect_devtools');
            setTimeout(deactivateSecurityShield, 2500);
            return false;
        }

        // Ctrl+U (View Source), Ctrl+S (Save Page), Ctrl+P (Print Page)
        if (e.ctrlKey && (e.keyCode === 85 || e.keyCode === 83 || e.keyCode === 80 || e.key === 'u' || e.key === 's' || e.key === 'p')) {
            e.preventDefault();
            e.stopPropagation();
            activateSecurityShield('save_or_print');
            setTimeout(deactivateSecurityShield, 2500);
            return false;
        }

        // Meta/Windows Key + Shift + S (Snipping Tool)
        if ((e.metaKey || e.ctrlKey) && e.shiftKey && (e.key === 's' || e.key === 'S' || e.keyCode === 83)) {
            e.preventDefault();
            e.stopPropagation();
            activateSecurityShield('snipping_shortcut');
            setTimeout(deactivateSecurityShield, 3500);
            return false;
        }
    }, true);

    window.addEventListener('keyup', function(e) {
        if (e.key === 'PrintScreen' || e.keyCode === 44) {
            activateSecurityShield('printscreen_keyup');
            setTimeout(deactivateSecurityShield, 3500);
        }
    }, true);

    // 7. Poison Screen Recording MediaDevices API
    try {
        if (navigator.mediaDevices && typeof navigator.mediaDevices.getDisplayMedia === 'function') {
            navigator.mediaDevices.getDisplayMedia = function() {
                activateSecurityShield('screen_recording_api');
                return Promise.reject(new DOMException("تسجيل الشاشة محظور لحماية الملكية الفكرية.", "NotAllowedError"));
            };
        }
    } catch(e) {}

    // 8. Prevent Context Menu, Copy, Selection, Dragging across the player
    ['contextmenu', 'selectstart', 'dragstart', 'copy', 'cut'].forEach(function(evt) {
        playerWrapper.addEventListener(evt, function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }, true);
    });

    // 9. Anti-Tamper DevTools Mutation Observer
    try {
        const observer = new MutationObserver(function() {
            const wmCheck = document.getElementById('dynamic-watermark');
            const shieldCheck = document.getElementById('tamper-shield');
            if (!wmCheck || !shieldCheck || wmCheck.style.display === 'none' || wmCheck.style.visibility === 'hidden') {
                if (mount) {
                    mount.innerHTML = `
                        <div class="w-full h-full flex flex-col items-center justify-center p-6 text-center bg-black text-red-500 font-sans">
                            <i class="fa-solid fa-triangle-exclamation text-4xl mb-3 animate-bounce"></i>
                            <h4 class="text-xl font-bold mb-1">تم إيقاف المشغل لأسباب أمنية</h4>
                            <p class="text-xs text-slate-400">تم رصد محاولة تلاعب بعناصر الحماية. يرجى إعادة تحميل الصفحة.</p>
                        </div>
                    `;
                }
                observer.disconnect();
            }
        });
        observer.observe(playerWrapper, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
    } catch(e) {}

    // Automatic Video Watch Confirmation Ping
    <?php if ($canAccessActiveVideo && $activeVideo && $user): ?>
    setTimeout(function() {
        try {
            fetch('/api/custom.php?action=mark-video-watched', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    videoId: <?= json_encode($activeVideo['id']) ?>,
                    courseId: <?= json_encode($courseId) ?>
                })
            }).catch(function(e) {});
        } catch(e) {}
    }, 5000);
    <?php endif; ?>

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
