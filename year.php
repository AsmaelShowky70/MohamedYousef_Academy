<?php
$yearId = $_GET['id'] ?? ($_GET['yearId'] ?? '');
if (!$yearId) {
    header("Location: /courses.php");
    exit;
}

require_once __DIR__ . '/includes/header.php';
$db = getDB();

// Fetch Year
$stmt = $db->prepare("SELECT * FROM Year WHERE id = ? LIMIT 1");
$stmt->execute([$yearId]);
$year = $stmt->fetch();

if (!$year) {
    echo '<div class="min-h-screen flex items-center justify-center text-center p-6"><h1 class="text-3xl font-bold text-slate-800">المرحلة الدراسية غير موجودة</h1></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = htmlspecialchars($year['name']) . " - منصة الأستاذ محمد يوسف";

$hasSpecializations = !empty($year['hasSpecializations']);
$currentSpec = $_GET['spec'] ?? null;
if ($currentSpec && !in_array($currentSpec, ['ADABI', 'ELMI'])) {
    $currentSpec = null;
}

// Fetch sections for this year
$secStmt = $db->prepare("SELECT * FROM Section WHERE yearId = ? ORDER BY `order` ASC, id ASC");
$secStmt->execute([$yearId]);
$sections = $secStmt->fetchAll();

// Fetch all published courses in this year
$cSql = "SELECT * FROM Course WHERE sectionId IN (SELECT id FROM Section WHERE yearId = ?) AND published = 1";
$cParams = [$yearId];
if ($hasSpecializations && $currentSpec) {
    $cSql .= " AND (specialization IS NULL OR specialization = ? OR specialization = '')";
    $cParams[] = $currentSpec;
}
$cSql .= " ORDER BY `order` ASC, createdAt DESC";

$cStmt = $db->prepare($cSql);
$cStmt->execute($cParams);
$courses = $cStmt->fetchAll();

// Check user enrollments (Requirement #11)
$fullEnrolledCourseIds = [];
$lessonEnrolledCourseIds = [];
if ($currentUser) {
    // Full Course Enrollments
    $fStmt = $db->prepare("
        SELECT A as courseId FROM _CourseToUser WHERE B = ?
        UNION
        SELECT DISTINCT courseId FROM CourseCode WHERE userId = ? AND (collectionId IS NULL OR collectionId = '') AND expires > NOW()
    ");
    $fStmt->execute([$currentUser['id'], $currentUser['id']]);
    $fullEnrolledCourseIds = $fStmt->fetchAll(PDO::FETCH_COLUMN);

    // Single Lesson Enrollments
    $lStmt = $db->prepare("
        SELECT DISTINCT col.courseId 
        FROM _CollectionToUser cu
        JOIN Collection col ON cu.A = col.id
        WHERE cu.B = ?
        UNION
        SELECT DISTINCT courseId FROM CourseCode WHERE userId = ? AND collectionId IS NOT NULL AND collectionId != '' AND expires > NOW()
    ");
    $lStmt->execute([$currentUser['id'], $currentUser['id']]);
    $allLessonCourses = $lStmt->fetchAll(PDO::FETCH_COLUMN);
    $lessonEnrolledCourseIds = array_values(array_diff($allLessonCourses, $fullEnrolledCourseIds));
}

// Group courses by section
$sectionCourses = [];
$unsectionedCourses = [];

foreach ($courses as $c) {
    if (!empty($c['sectionId'])) {
        $sectionCourses[$c['sectionId']][] = $c;
    } else {
        $unsectionedCourses[] = $c;
    }
}
?>

<div class="min-h-screen bg-slate-50/60 pb-32">
    <!-- Hero / Stage Banner -->
    <div class="relative overflow-hidden bg-gradient-to-b from-[#0d3753] to-[#0f766e] text-white pt-28 pb-16 px-4">
        <div class="max-w-6xl mx-auto text-center relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/10 text-emerald-200 mb-4 backdrop-blur-sm border border-white/10">
                <i class="fa-solid fa-graduation-cap"></i> المرحلة الدراسية
            </span>
            <h1 class="text-3xl md:text-5xl font-black mb-4 tracking-tight"><?= htmlspecialchars($year['name']) ?></h1>
            <p class="text-white/80 max-w-xl mx-auto text-sm md:text-base font-medium">كافة الكورسات والمحاضرات والامتحانات المخصصة لطلاب <?= htmlspecialchars($year['name']) ?></p>

            <!-- Subject Quick Links (Requirement #21) -->
            <?php if (!empty($year['nahwLink']) || !empty($year['sarfLink']) || !empty($year['balaghaLink']) || !empty($year['adabLink'])): ?>
                <div class="mt-6 pt-4 border-t border-white/10 max-w-2xl mx-auto">
                    <span class="text-[11px] font-black uppercase tracking-wider text-teal-200 block mb-3 text-center">فروع المادة المقررة</span>
                    <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center justify-center gap-2.5 sm:gap-3">
                        <?php if (!empty($year['nahwLink'])): ?>
                            <a href="<?= htmlspecialchars($year['nahwLink']) ?>" target="_blank" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white/15 hover:bg-white/25 active:scale-95 text-xs font-bold text-white transition border border-white/20 flex items-center justify-center gap-2 shadow-sm">
                                <i class="fa-solid fa-book-bookmark text-[#c79a32]"></i> <span>فرع النحو</span>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($year['sarfLink'])): ?>
                            <a href="<?= htmlspecialchars($year['sarfLink']) ?>" target="_blank" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white/15 hover:bg-white/25 active:scale-95 text-xs font-bold text-white transition border border-white/20 flex items-center justify-center gap-2 shadow-sm">
                                <i class="fa-solid fa-book-bookmark text-[#c79a32]"></i> <span>فرع الصرف</span>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($year['balaghaLink'])): ?>
                            <a href="<?= htmlspecialchars($year['balaghaLink']) ?>" target="_blank" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white/15 hover:bg-white/25 active:scale-95 text-xs font-bold text-white transition border border-white/20 flex items-center justify-center gap-2 shadow-sm">
                                <i class="fa-solid fa-feather-pointed text-[#c79a32]"></i> <span>فرع البلاغة</span>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($year['adabLink'])): ?>
                            <a href="<?= htmlspecialchars($year['adabLink']) ?>" target="_blank" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white/15 hover:bg-white/25 active:scale-95 text-xs font-bold text-white transition border border-white/20 flex items-center justify-center gap-2 shadow-sm">
                                <i class="fa-solid fa-scroll text-[#c79a32]"></i> <span>الأدب والنصوص</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10">

        <!-- Specialization Selector (If year hasSpecializations and none selected) -->
        <?php if ($hasSpecializations && !$currentSpec): ?>
            <div class="flex flex-col items-center gap-8 py-8 animate-in fade-in duration-500">
                <div class="text-center space-y-3">
                    <h2 class="text-3xl md:text-4xl font-extrabold text-[#0d3753]">اختر تخصصك</h2>
                    <p class="text-slate-500 text-base md:text-lg">حدد تخصصك لعرض الكورسات والمحاضرات المناسبة لك</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full max-w-2xl">
                    <!-- Adabi Option -->
                    <a href="?id=<?= urlencode($yearId) ?>&spec=ADABI" class="group relative overflow-hidden rounded-2xl border-2 border-transparent bg-gradient-to-br from-amber-50 to-orange-50 p-8 shadow-lg transition-all duration-300 hover:scale-[1.03] hover:border-amber-400 hover:shadow-xl hover:shadow-amber-500/10">
                        <div class="absolute top-0 right-0 h-32 w-32 -translate-y-8 translate-x-8 rounded-full bg-amber-400/10 blur-2xl transition-all group-hover:bg-amber-400/20"></div>
                        <div class="relative flex flex-col items-center gap-4 text-center">
                            <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 shadow-inner text-3xl">
                                <i class="fa-solid fa-book-open"></i>
                            </div>
                            <h3 class="text-2xl font-black text-amber-800">أدبي</h3>
                            <p class="text-sm font-bold text-amber-700/70">القسم الأدبي - لغة عربية</p>
                        </div>
                    </a>

                    <!-- Elmi Option -->
                    <a href="?id=<?= urlencode($yearId) ?>&spec=ELMI" class="group relative overflow-hidden rounded-2xl border-2 border-transparent bg-gradient-to-br from-blue-50 to-indigo-50 p-8 shadow-lg transition-all duration-300 hover:scale-[1.03] hover:border-blue-400 hover:shadow-xl hover:shadow-blue-500/10">
                        <div class="absolute top-0 right-0 h-32 w-32 -translate-y-8 translate-x-8 rounded-full bg-blue-400/10 blur-2xl transition-all group-hover:bg-blue-400/20"></div>
                        <div class="relative flex flex-col items-center gap-4 text-center">
                            <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-blue-100 text-blue-600 shadow-inner text-3xl">
                                <i class="fa-solid fa-atom"></i>
                            </div>
                            <h3 class="text-2xl font-black text-blue-800">علمي</h3>
                            <p class="text-sm font-bold text-blue-700/70">القسم العلمي - لغة عربية</p>
                        </div>
                    </a>
                </div>
            </div>

        <?php else: ?>

            <!-- If specialization is active: Show controls banner -->
            <?php if ($hasSpecializations && $currentSpec): ?>
                <div class="flex items-center justify-between mb-10 bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
                    <a href="?id=<?= urlencode($yearId) ?>" class="flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-200">
                        <i class="fa-solid fa-arrows-rotate text-xs"></i>
                        <span>تغيير التخصص</span>
                    </a>
                    <div class="rounded-xl px-5 py-2 text-xs font-black shadow-sm <?= $currentSpec === 'ADABI' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-blue-100 text-blue-800 border border-blue-200' ?>">
                        <?= $currentSpec === 'ADABI' ? '📚 القسم الأدبي' : '🔬 القسم العلمي' ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Render Sections and Courses -->
            <div class="space-y-16">
                <?php 
                $renderedAny = false;

                // Loop over configured sections
                foreach ($sections as $sec): 
                    $secList = $sectionCourses[$sec['id']] ?? [];
                    if (empty($secList)) continue;
                    $renderedAny = true;
                ?>
                    <section class="space-y-6">
                        <!-- Section Header Badge (Requirement #21) -->
                        <div class="flex items-center justify-center">
                            <div class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-6 py-3 rounded-2xl bg-white dark:bg-[#12283a] border-2 border-[#0f766e]/30 shadow-md text-center">
                                <span class="w-8 h-8 rounded-xl bg-teal-50 dark:bg-teal-900/40 text-[#0f766e] dark:text-teal-400 flex items-center justify-center">
                                    <i class="fa-solid fa-layer-group text-sm"></i>
                                </span>
                                <h2 class="text-base sm:text-xl font-black text-[#0d3753] dark:text-white"><?= htmlspecialchars($sec['title']) ?></h2>
                            </div>
                        </div>

                        <!-- Courses Grid -->
                        <div class="grid min-h-[50%] grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($secList as $c): ?>
                                <?php 
                                    $isFullEnrolled = in_array($c['id'], $fullEnrolledCourseIds);
                                    $isLessonEnrolled = in_array($c['id'], $lessonEnrolledCourseIds);
                                    $thumb = $c['thumbnail'] ?: '/public/identity/logo.png';
                                ?>
                                <div class="group relative max-w-[540px] max-lg:max-w-full">
                                    <div class="w-full overflow-hidden rounded-xl shadow-md bg-slate-100 aspect-video">
                                        <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($c['title']) ?>" class="w-full h-full object-cover transform transition duration-500 ease-out group-hover:scale-110 group-hover:brightness-105" loading="lazy"/>
                                    </div>
                                    <div class="px-3">
                                        <div class="duration-300 min-h-[200px] -translate-y-8 space-y-3.5 rounded-2xl bg-white dark:bg-[#12283a] px-5 py-5 shadow-xl border border-slate-100 dark:border-slate-800 transition-all group-hover:-translate-y-9 group-hover:shadow-2xl">
                                            <div class="flex items-start justify-between border-b border-slate-100 dark:border-slate-800 pb-3 gap-2">
                                                <h3 class="text-base font-black text-slate-900 dark:text-white group-hover:text-[#0f766e] dark:group-hover:text-teal-400 transition-colors line-clamp-1"><?= htmlspecialchars($c['title']) ?></h3>
                                                <div class="max-h-fit rounded-lg bg-[#c79a32] px-2.5 py-1 text-center text-xs font-black text-white shrink-0">
                                                    <?php if ($isFullEnrolled): ?>
                                                        <span class="rounded-md bg-emerald-600 text-white px-2 py-0.5 text-[11px] font-black">مشترك في الكورس كاملاً</span>
                                                    <?php elseif ($isLessonEnrolled): ?>
                                                        <span class="rounded-md bg-amber-500 text-white px-2 py-0.5 text-[11px] font-black">مشترك في حصة منفصلة</span>
                                                    <?php elseif ($c['price'] > 0): ?>
                                                        <span class="ml-1 rounded-md bg-white px-2 py-0.5 text-black font-mono text-[11px] font-bold"><?= number_format((float)$c['price'], 0) ?></span> ج.م
                                                    <?php else: ?>
                                                        مجاني
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <p class="font-normal text-slate-500 dark:text-slate-400 text-xs line-clamp-2 leading-relaxed"><?= htmlspecialchars($c['description'] ?: 'شرح وتدريبات متكاملة للمنهج') ?></p>
                                            <div class="flex flex-col justify-center gap-2 pt-2">
                                                <a href="/course.php?id=<?= urlencode($c['id']) ?>" class="w-full">
                                                    <button class="w-full py-2.5 rounded-xl font-bold text-xs border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-700 dark:text-slate-200 transition duration-200 hover:-translate-y-[2px] hover:border-[#c79a32] hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center justify-center gap-1.5">
                                                        <i class="fa-solid fa-play text-[10px] text-[#0f766e] dark:text-teal-400"></i>
                                                        <span><?= $isLessonEnrolled ? 'الدخول لمتابعة الحصة' : 'الدخول إلي الكورس' ?></span>
                                                    </button>
                                                </a>
                                                <?php if (!$isFullEnrolled && $c['price'] > 0): ?>
                                                    <a href="/purchase.php?id=<?= urlencode($c['id']) ?>" class="w-full">
                                                        <button class="w-full py-2.5 rounded-xl font-bold text-xs border-2 border-[#c79a32] bg-[#c79a32] text-white transition duration-200 hover:-translate-y-[2px] hover:bg-[#b08728] shadow-sm flex items-center justify-center gap-1.5">
                                                            <i class="fa-solid fa-cart-shopping text-[10px]"></i>
                                                            <span><?= $isLessonEnrolled ? 'ترقية للكورس كاملاً (' . number_format($c['price'], 0) . ' ج.م)' : 'اشترك الان !' ?></span>
                                                        </button>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <!-- Unsectioned courses -->
                <?php if (!empty($unsectionedCourses)): ?>
                    <?php $renderedAny = true; ?>
                    <section class="space-y-6">
                        <?php if (!empty($sections)): ?>
                            <div class="flex justify-center">
                                <div class="inline-flex items-center gap-2.5 px-6 py-2.5 rounded-2xl bg-white dark:bg-[#12283a] border border-slate-200 dark:border-slate-800 shadow-sm text-center">
                                    <h2 class="text-lg md:text-xl font-black text-[#0d3753] dark:text-teal-400">كورسات عامة</h2>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="grid min-h-[50%] grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($unsectionedCourses as $c): ?>
                                <?php 
                                    $isFullEnrolled = in_array($c['id'], $fullEnrolledCourseIds);
                                    $isLessonEnrolled = in_array($c['id'], $lessonEnrolledCourseIds);
                                    $thumb = $c['thumbnail'] ?: '/public/identity/logo.png';
                                ?>
                                <div class="group relative max-w-[540px] max-lg:max-w-full">
                                    <div class="w-full overflow-hidden rounded-xl shadow-md bg-slate-100 dark:bg-slate-800 aspect-video">
                                        <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($c['title']) ?>" class="w-full h-full object-cover transform transition duration-500 ease-out group-hover:scale-110 group-hover:brightness-105" loading="lazy"/>
                                    </div>
                                    <div class="px-3">
                                        <div class="duration-300 min-h-[200px] -translate-y-8 space-y-3.5 rounded-2xl bg-white dark:bg-[#12283a] px-5 py-5 shadow-xl border border-slate-100 dark:border-slate-800 transition-all group-hover:-translate-y-9 group-hover:shadow-2xl">
                                            <div class="flex items-start justify-between border-b border-slate-100 dark:border-slate-800 pb-3 gap-2">
                                                <h3 class="text-base font-black text-slate-900 dark:text-white group-hover:text-[#0f766e] dark:group-hover:text-teal-400 transition-colors line-clamp-1"><?= htmlspecialchars($c['title']) ?></h3>
                                                <div class="max-h-fit rounded-lg bg-[#c79a32] px-2.5 py-1 text-center text-xs font-black text-white shrink-0">
                                                    <?php if ($isFullEnrolled): ?>
                                                        <span class="rounded-md bg-emerald-600 text-white px-2 py-0.5 text-[11px] font-black">مشترك في الكورس كاملاً</span>
                                                    <?php elseif ($isLessonEnrolled): ?>
                                                        <span class="rounded-md bg-amber-500 text-white px-2 py-0.5 text-[11px] font-black">مشترك في حصة منفصلة</span>
                                                    <?php elseif ($c['price'] > 0): ?>
                                                        <span class="ml-1 rounded-md bg-white px-2 py-0.5 text-black font-mono text-[11px] font-bold"><?= number_format((float)$c['price'], 0) ?></span> ج.م
                                                    <?php else: ?>
                                                        مجاني
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <p class="font-normal text-slate-500 dark:text-slate-400 text-xs line-clamp-2 leading-relaxed"><?= htmlspecialchars($c['description'] ?: 'شرح وتدريبات متكاملة للمنهج') ?></p>
                                            <div class="flex flex-col justify-center gap-2 pt-2">
                                                <a href="/course.php?id=<?= urlencode($c['id']) ?>" class="w-full">
                                                    <button class="w-full py-2.5 rounded-xl font-bold text-xs border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-700 dark:text-slate-200 transition duration-200 hover:-translate-y-[2px] hover:border-[#c79a32] hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center justify-center gap-1.5">
                                                        <i class="fa-solid fa-play text-[10px] text-[#0f766e] dark:text-teal-400"></i>
                                                        <span><?= $isLessonEnrolled ? 'الدخول لمتابعة الحصة' : 'الدخول إلي الكورس' ?></span>
                                                    </button>
                                                </a>
                                                <?php if (!$isFullEnrolled && $c['price'] > 0): ?>
                                                    <a href="/purchase.php?id=<?= urlencode($c['id']) ?>" class="w-full">
                                                        <button class="w-full py-2.5 rounded-xl font-bold text-xs border-2 border-[#c79a32] bg-[#c79a32] text-white transition duration-200 hover:-translate-y-[2px] hover:bg-[#b08728] shadow-sm flex items-center justify-center gap-1.5">
                                                            <i class="fa-solid fa-cart-shopping text-[10px]"></i>
                                                            <span><?= $isLessonEnrolled ? 'ترقية للكورس كاملاً (' . number_format($c['price'], 0) . ' ج.م)' : 'اشترك الان !' ?></span>
                                                        </button>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!$renderedAny): ?>
                    <div class="py-20 text-center rounded-2xl bg-white border border-slate-200 p-8 shadow-sm">
                        <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4 text-2xl">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-1">لا توجد كورسات متاحة حالياً في هذا القسم</h3>
                        <p class="text-xs text-slate-500 mb-4">سيتم إضافة المحاضرات والدروس قريباً</p>
                        <a href="/courses.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0f766e] text-white text-xs font-bold hover:bg-[#0d635c] transition">
                            عرض جميع الكورسات
                        </a>
                    </div>
                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
