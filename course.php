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

$allVideos = [];
$totalVideosCount = 0;
$totalFilesCount = 0;
$totalQuizzesCount = 0;

foreach ($collections as &$col) {
    $col['isUnlocked'] = $isFullCourseAccess || in_array($col['id'], $enrolledCollectionIds);

    // Videos
    $stmtV = $db->prepare("SELECT * FROM Video WHERE collectionId = ? ORDER BY `order` ASC, id ASC");
    $stmtV->execute([$col['id']]);
    $col['videos'] = $stmtV->fetchAll();
    $totalVideosCount += count($col['videos']);
    foreach ($col['videos'] as $v) {
        $v['isUnlocked'] = $col['isUnlocked'];
        $allVideos[] = $v;
    }

    // Files
    $stmtF = $db->prepare("SELECT * FROM File WHERE collectionId = ? ORDER BY `order` ASC, id ASC");
    $stmtF->execute([$col['id']]);
    $col['files'] = $stmtF->fetchAll();
    $totalFilesCount += count($col['files']);

    // Quizzes (if Quiz table exists)
    try {
        $stmtQ = $db->prepare("SELECT * FROM Quiz WHERE collectionId = ? ORDER BY `order` ASC, id ASC");
        $stmtQ->execute([$col['id']]);
        $col['quizzes'] = $stmtQ->fetchAll();
        $totalQuizzesCount += count($col['quizzes']);
    } catch (Exception $e) {
        $col['quizzes'] = [];
    }
}
unset($col);

// Active video to play
$activeVideoId = trim($_GET['v'] ?? '');
$activeVideo = null;
$activeVideoIndex = -1;
$activeCollection = null;

if (!empty($allVideos)) {
    if ($activeVideoId) {
        foreach ($allVideos as $idx => $v) {
            if ($v['id'] === $activeVideoId) {
                $activeVideo = $v;
                $activeVideoIndex = $idx;
                break;
            }
        }
    }
    
    // If no video requested or not found, choose best default:
    if (!$activeVideo) {
        // If user is not full course enrolled, look for first unlocked video
        if (!$isFullCourseAccess && !empty($enrolledCollectionIds)) {
            foreach ($allVideos as $idx => $v) {
                if (in_array($v['collectionId'], $enrolledCollectionIds)) {
                    $activeVideo = $v;
                    $activeVideoIndex = $idx;
                    break;
                }
            }
        }
        // Fallback to first video
        if (!$activeVideo) {
            $activeVideo = $allVideos[0];
            $activeVideoIndex = 0;
        }
    }

    // Find active collection
    foreach ($collections as $col) {
        if ($col['id'] === $activeVideo['collectionId']) {
            $activeCollection = $col;
            break;
        }
    }
}

// Check access for active video
$canAccessActiveVideo = $isFullCourseAccess || ($activeVideo && in_array($activeVideo['collectionId'], $enrolledCollectionIds));
$canAccess = $isFullCourseAccess; // for course-level banner checks

// Next and Previous videos
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

// Helper: parse video source
if (!function_exists('parseVideoSource')) {
    function parseVideoSource($link, $videoId = '') {
        $link = trim((string)$link);

        // 1. YouTube (Supports watch URLs, youtu.be, embed, shorts, live, unlisted, and raw 11-char IDs)
        if (preg_match('#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|v/|watch\?v=|watch\?.+&v=|shorts/|live/))([\w-]{11})#i', $link, $m) ||
            (preg_match('#^[\w-]{11}$#', $link) && !preg_match('#\.mp4$#i', $link))) {
            $ytId = isset($m[1]) ? $m[1] : $link;
            return [
                'type' => 'youtube',
                'id' => $ytId,
                'embedUrl' => 'https://www.youtube-nocookie.com/embed/' . $ytId . '?autoplay=0&rel=0&modestbranding=1&enablejsapi=1&playsinline=1&controls=1&iv_load_policy=3&fs=1'
            ];
        }

        // 2. Dailymotion
        if (preg_match('#(?:dailymotion\.com/(?:video|embed/video)/|dai\.ly/|geo\.dailymotion\.com/player/xmztx\.html\?video=)([a-zA-Z0-9]+)#i', $link, $m)) {
            return [
                'type' => 'dailymotion',
                'id' => $m[1],
                'embedUrl' => 'https://geo.dailymotion.com/player/xmztx.html?video=' . $m[1] . '&loop=true'
            ];
        }

        // Dailymotion Alphanumeric ID (e.g. k6qFi9H1Z6q8pdIIDES or x8abcde)
        if (preg_match('#^[a-zA-Z0-9_-]{12,25}$#', $link) && !preg_match('#\.mp4$#i', $link)) {
            return [
                'type' => 'dailymotion',
                'id' => $link,
                'embedUrl' => 'https://geo.dailymotion.com/player/xmztx.html?video=' . $link . '&loop=true'
            ];
        }

        // 3. MP4 (Streamed via authenticated stream controller)
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

/* Dynamic Floating Watermark */
.floating-watermark-badge {
    position: absolute;
    z-index: 25;
    pointer-events: none;
    user-select: none;
    -webkit-user-select: none;
    transition: top 1.8s cubic-bezier(0.4, 0, 0.2, 1), left 1.8s cubic-bezier(0.4, 0, 0.2, 1), opacity 1.2s ease-in-out;
    opacity: 0.70;
}

.floating-watermark-inner {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 9999px;
    background: rgba(13, 23, 42, 0.75);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.9);
    white-space: nowrap;
}

/* Subtle Corner / Tiled Watermark Grid (Anti-Crop Defense) */
.watermark-tiled-grid {
    position: absolute;
    inset: 0;
    z-index: 20;
    pointer-events: none;
    user-select: none;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 16px 20px;
    opacity: 0.08;
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
}

/* Security Screen Protection Shield (Only active when triggered) */
.security-screen-shield {
    display: none !important;
    position: absolute;
    inset: 0;
    z-index: 50;
    background: #050811;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px;
    text-align: center;
    color: #ffffff;
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

<div class="course-page-wrapper">
    <div class="course-container space-y-8">

        <!-- Top Navigation / Breadcrumbs -->
        <div class="flex flex-wrap items-center justify-between gap-4 text-xs font-bold text-slate-500 dark:text-slate-400">
            <div class="flex items-center gap-2">
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
                <span class="text-slate-900 dark:text-white font-extrabold line-clamp-1"><?= htmlspecialchars($course['title']) ?></span>
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

                    <?php if (!empty($collections)): ?>
                        <div class="text-[11px] font-bold text-amber-700 bg-amber-50 dark:bg-amber-950/40 p-2 rounded-xl border border-amber-200/60">
                            <span>أو يمكنك حضور الحصص بشكل منفصل (بالحصة)</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($user): ?>
                        <div class="space-y-2">
                            <a href="/purchase.php?id=<?= urlencode($courseId) ?>" class="block w-full py-2.5 bg-primary hover:bg-teal-700 text-white font-black text-xs rounded-xl shadow-md transition">
                                <i class="fa-solid fa-cart-shopping ml-1"></i> اشترك في الكورس كاملاً
                            </a>
                            <button onclick="openActivationModal()" class="w-full py-2.5 bg-gold-custom hover:bg-yellow-500 text-navy-custom font-black text-xs rounded-xl shadow transition">
                                <i class="fa-solid fa-key ml-1"></i> تفعيل كود (كورس أو حصة)
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

                <?php if ($canAccessActiveVideo && $activeVideo && $videoSource): ?>
                    <!-- Secure Video Cinema Player -->
                    <div id="secure-player-wrapper" class="player-cinema-box select-none" oncontextmenu="return false;">

                        <!-- Subtle Diagonal Tiled Watermarks (Anti-Crop Defense) -->
                        <div class="watermark-tiled-grid">
                            <div class="flex justify-between">
                                <span><?= htmlspecialchars(!empty($user['email']) ? $user['email'] : ($user['phone'] . '@student')) ?> • <?= htmlspecialchars($user['fullName']) ?></span>
                                <span><?= htmlspecialchars($user['phone']) ?> • ID:#<?= htmlspecialchars($user['attendanceID'] ?? $user['id']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span><?= htmlspecialchars($user['phone']) ?> • ID:#<?= htmlspecialchars($user['attendanceID'] ?? $user['id']) ?></span>
                                <span><?= htmlspecialchars(!empty($user['email']) ? $user['email'] : ($user['phone'] . '@student')) ?> • <?= htmlspecialchars($user['fullName']) ?></span>
                            </div>
                        </div>

                        <!-- Dynamic Floating Anti-Recording Watermark Badge -->
                        <div id="dynamic-watermark" class="floating-watermark-badge">
                            <div class="floating-watermark-inner">
                                <i class="fa-solid fa-shield-halved text-amber-400 text-xs"></i>
                                <span><?= htmlspecialchars(!empty($user['email']) ? $user['email'] : ($user['phone'] . '@student')) ?></span>
                                <span>•</span>
                                <span><?= htmlspecialchars($user['fullName'] ?: ($user['firstName'] . ' ' . $user['lastName'])) ?></span>
                                <span>•</span>
                                <span dir="ltr"><?= htmlspecialchars($user['phone']) ?></span>
                                <span>•</span>
                                <span>#<?= htmlspecialchars($user['attendanceID'] ?? $user['id']) ?></span>
                            </div>
                        </div>

                        <!-- Player Mount Point -->
                        <div id="player-mount-point" class="w-full h-full" data-player="<?= htmlspecialchars($encryptedVideoPayload) ?>">
                            <?php if ($videoSource['type'] === 'youtube'): ?>
                                <iframe id="video-frame" src="<?= htmlspecialchars($videoSource['embedUrl']) ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen class="w-full h-full border-0 block bg-black"></iframe>
                            <?php elseif ($videoSource['type'] === 'dailymotion'): ?>
                                <iframe id="video-frame" src="<?= htmlspecialchars($videoSource['embedUrl']) ?>" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen class="w-full h-full border-0 block bg-black"></iframe>
                            <?php elseif ($videoSource['type'] === 'mp4'): ?>
                                <video id="direct-video-player" controls controlsList="nodownload" class="w-full h-full object-contain bg-black">
                                    <source src="<?= htmlspecialchars($videoSource['url']) ?>" type="video/mp4">
                                </video>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Video Controls & Metadata Bar -->
                    <div class="course-card p-6 space-y-4">
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

                        <!-- Video Description -->
                        <?php if (!empty($activeVideo['description'])): ?>
                            <div class="text-xs md:text-sm text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">
                                <?= htmlspecialchars($activeVideo['description']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Lecture Attachments & Files if present in this collection -->
                        <?php if ($activeCollection && !empty($activeCollection['files'])): ?>
                            <div class="pt-2">
                                <h4 class="text-xs font-black text-slate-800 dark:text-slate-200 mb-2.5 flex items-center gap-1.5">
                                    <i class="fa-solid fa-paperclip text-primary"></i> المذكرات والملفات المرفقة مع هذا الدرس:
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <?php foreach ($activeCollection['files'] as $f): ?>
                                        <a href="<?= htmlspecialchars($f['link']) ?>" target="_blank" download class="p-3 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-50 dark:bg-slate-800/60 hover:border-primary transition flex items-center justify-between text-xs group">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 flex items-center justify-center shrink-0">
                                                    <i class="fa-solid fa-file-pdf"></i>
                                                </div>
                                                <span class="font-bold text-slate-800 dark:text-slate-200 truncate group-hover:text-primary transition">
                                                    <?= htmlspecialchars($f['title']) ?>
                                                </span>
                                            </div>
                                            <span class="text-[11px] text-primary font-bold flex items-center gap-1 shrink-0">
                                                <i class="fa-solid fa-download"></i> تحميل
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                <?php elseif (!$canAccessActiveVideo): ?>
                    <!-- Unenrolled / Single Lesson Lock Screen -->
                    <?php 
                        $lessonPrice = (float)($activeCollection['price'] ?? 0);
                        $userWallet = (float)($user['walletBalance'] ?? 0);
                    ?>
                    <div class="course-card p-8 md:p-14 text-center space-y-6">
                        <div class="w-20 h-20 rounded-full bg-amber-50 dark:bg-amber-950/40 text-gold-custom flex items-center justify-center text-3xl mx-auto shadow-inner border border-amber-200 dark:border-amber-800">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <div class="space-y-2 max-w-lg mx-auto">
                            <span class="inline-block px-3 py-1 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-xs font-black rounded-full mb-1">
                                🎯 كود حصة / اشتراك درس
                            </span>
                            <h2 class="text-xl md:text-2xl font-black text-[#0d3753] dark:text-white">
                                <?= $activeCollection ? 'حصة: ' . htmlspecialchars($activeCollection['title']) : 'المحتوى متاح للمشتركين فقط' ?>
                            </h2>
                            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                                هذه الحصة غير مفعلة في حسابك. يمكنك تفعيل كود الحصة المستلم من السنتر، أو دفع قيمة الحصة مباشرة لحضورها ومشاهدة شرحها الآن.
                            </p>
                            <?php if ($lessonPrice > 0): ?>
                                <div class="pt-2">
                                    <span class="text-xs text-slate-400 font-bold block">قيمة الحصة المنفصلة:</span>
                                    <span class="text-2xl font-black text-[#0f766e] font-mono"><?= number_format($lessonPrice, 0) ?> ج.م</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                            <?php if ($user): ?>
                                <?php if ($activeCollection): ?>
                                    <a href="/purchase.php?id=<?= urlencode($courseId) ?>&collectionId=<?= urlencode($activeCollection['id']) ?>" class="px-6 py-3 bg-[#c79a32] hover:bg-yellow-500 text-[#0d3753] font-black text-xs rounded-xl shadow-md transition flex items-center gap-2">
                                        <i class="fa-solid fa-key"></i> تفعيل كود الحصة (<?= number_format($lessonPrice, 0) ?> ج.م)
                                    </a>
                                <?php endif; ?>

                                <a href="/purchase.php?id=<?= urlencode($courseId) ?>" class="px-6 py-3 bg-[#0f766e] hover:bg-[#0d3753] text-white font-bold text-xs rounded-xl shadow transition flex items-center gap-2">
                                    <i class="fa-solid fa-key"></i> أو تفعيل الكورس كاملاً (<?= number_format($course['price'], 0) ?> ج.م)
                                </a>

                                <a href="https://wa.me/201092037257?text=<?= urlencode('السلام عليكم، أود الحصول على كود تفعيل لكورس: ' . $course['title']) ?>" target="_blank" class="px-5 py-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 font-bold text-xs rounded-xl transition flex items-center gap-2">
                                    <i class="fa-brands fa-whatsapp text-emerald-600"></i> طلب كود عبر واتساب
                                </a>
                            <?php else: ?>
                                <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="px-8 py-3.5 bg-[#0f766e] hover:bg-[#0d3753] text-white font-black text-sm rounded-xl shadow-lg transition flex items-center gap-2">
                                    <i class="fa-solid fa-right-to-bracket"></i> تسجيل الدخول لحضور الحصة
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- No videos added yet -->
                    <div class="course-card p-16 text-center text-slate-500 dark:text-slate-400 space-y-3">
                        <i class="fa-solid fa-circle-play text-5xl text-slate-300 dark:text-slate-600 mx-auto"></i>
                        <h3 class="text-lg font-bold">لا توجد فيديوهات مضافة في هذا الكورس بعد</h3>
                        <p class="text-xs text-slate-400">سيتم إضافة المحتوى قريباً، ترقبوا التحديثات.</p>
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

                    <?php if ($canAccess && $totalVideosCount > 0): ?>
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
                            <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden bg-slate-50/50 dark:bg-slate-900/40 transition">
                                
                                <!-- Collection Header -->
                                <div class="p-3.5 bg-slate-100 dark:bg-slate-800/80 font-black text-xs text-[#0d3753] dark:text-white flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-5 h-5 rounded-full bg-primary text-white flex items-center justify-center text-[10px]">
                                            <?= ($idx + 1) ?>
                                        </span>
                                        <span><?= htmlspecialchars($col['title']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <?php if ($col['isUnlocked']): ?>
                                            <span class="text-[10px] bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                                                <i class="fa-solid fa-lock-open text-[9px]"></i> مفعلة
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                                                <i class="fa-solid fa-lock text-[9px]"></i> <?= (float)$col['price'] > 0 ? number_format($col['price'], 0) . ' ج.م' : 'مغلقة' ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-[10px] bg-white dark:bg-slate-700 px-2 py-0.5 rounded text-slate-500 dark:text-slate-300 font-bold">
                                            <?= count($col['videos']) ?> حصص
                                        </span>
                                    </div>
                                </div>

                                <!-- Collection Items (Videos, Files, Quizzes) -->
                                <div class="divide-y divide-slate-200/60 dark:divide-slate-800/60 p-1.5 space-y-1">
                                    
                                    <!-- Videos -->
                                    <?php foreach ($col['videos'] as $v): 
                                        $isActive = ($activeVideo && $activeVideo['id'] === $v['id']);
                                    ?>
                                        <a href="/course.php?id=<?= urlencode($courseId) ?>&v=<?= urlencode($v['id']) ?>" 
                                           class="p-2.5 rounded-xl flex items-center justify-between text-xs transition block <?= $isActive ? 'lesson-active-item' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium' ?>">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <i class="fa-solid <?= $isActive ? 'fa-circle-play text-primary' : ($col['isUnlocked'] ? 'fa-play text-emerald-500' : 'fa-lock text-slate-400') ?> text-xs shrink-0"></i>
                                                <span class="truncate"><?= htmlspecialchars($v['title']) ?></span>
                                            </div>
                                            <?php if ($isActive): ?>
                                                <span class="text-[10px] text-primary dark:text-teal-400 font-black shrink-0 mr-2 flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-primary dark:bg-teal-400 animate-ping"></span>
                                                    يعمل الآن
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
                                        <?php foreach ($col['quizzes'] as $q): ?>
                                            <div class="p-2 rounded-xl flex items-center justify-between text-xs text-slate-600 dark:text-slate-400 bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/50 dark:border-amber-800/30">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <i class="fa-solid fa-clipboard-question text-amber-500 text-sm shrink-0"></i>
                                                    <span class="truncate font-bold text-amber-800 dark:text-amber-300"><?= htmlspecialchars($q['title']) ?></span>
                                                </div>
                                                <span class="text-[10px] text-amber-600 dark:text-amber-400 font-bold shrink-0 mr-2">
                                                    <?= $q['quizTime'] ?> دقيقة
                                                </span>
                                            </div>
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

// Anti-Recording, Dynamic Floating Watermark & Security Traps
(function() {
    const playerWrapper = document.getElementById('secure-player-wrapper');
    const wm = document.getElementById('dynamic-watermark');
    if (!playerWrapper) return;

    // 0. Ensure Player is mounted (PHP renders it directly; JS ensures fallback if needed)
    const mount = document.getElementById('player-mount-point');
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

    if (!wm) return;

    // 1. Dynamic Moving Watermark Coordinates
    function animateWatermark() {
        const minX = 6, maxX = 60;
        const minY = 8, maxY = 75;
        const x = Math.floor(Math.random() * (maxX - minX + 1)) + minX;
        const y = Math.floor(Math.random() * (maxY - minY + 1)) + minY;
        const op = (Math.random() * 0.35 + 0.45).toFixed(2);

        wm.style.top = y + '%';
        wm.style.left = x + '%';
        wm.style.opacity = op;
    }
    setInterval(animateWatermark, 4200);
    animateWatermark();

    // 3. Anti-PrintScreen & Screen Capture Traps
    window.addEventListener('keyup', function(e) {
        if (e.key === 'PrintScreen' || e.keyCode === 44) {
            flashScreenShield();
        }
    });

    window.addEventListener('keydown', function(e) {
        // Prevent DevTools & Save shortcuts: F12, Ctrl+Shift+I/J/C, Ctrl+U/S/P
        if (e.keyCode === 123 ||
            (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 67)) ||
            (e.ctrlKey && (e.keyCode === 85 || e.keyCode === 83 || e.keyCode === 80))) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });

    function flashScreenShield() {
        try { navigator.clipboard.writeText(''); } catch(e) {}
        const shield = document.getElementById('tamper-shield');
        if (shield) {
            shield.classList.remove('hidden');
            setTimeout(function() {
                shield.classList.add('hidden');
            }, 3000);
        }
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
