<?php
$pageTitle = "جميع الكورسات - منصة الأستاذ محمد يوسف";
require_once __DIR__ . '/includes/header.php';
$db = getDB();

// 1. Fetch available years for filter (from high-speed query cache)
$years = getCachedYears($db);

// 2. Query filters
$search = trim($_GET['search'] ?? '');
$type = trim($_GET['type'] ?? '');
$selectedYear = trim($_GET['year'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 9;
$offset = ($page - 1) * $limit;

// 3. Build conditions
$where = ["published = 1"];
$params = [];

if ($search !== '') {
    $where[] = "(title LIKE ? OR description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($type === 'free') {
    $where[] = "price = 0";
} elseif ($type === 'paid') {
    $where[] = "price > 0";
}

if ($selectedYear !== '') {
    $where[] = "sectionId IN (SELECT id FROM Section WHERE yearId = ?)";
    $params[] = $selectedYear;
}

$whereSql = implode(' AND ', $where);

// Total count
$countStmt = $db->prepare("SELECT COUNT(*) FROM Course WHERE {$whereSql}");
$countStmt->execute($params);
$totalCourses = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCourses / $limit));

// Fetch courses
$coursesStmt = $db->prepare("
    SELECT * FROM Course 
    WHERE {$whereSql} 
    ORDER BY `order` ASC, createdAt DESC 
    LIMIT {$limit} OFFSET {$offset}
");
$coursesStmt->execute($params);
$courses = $coursesStmt->fetchAll();

// 4. Check purchased/enrolled courses for current user (Requirement #11)
$fullEnrolledCourseIds = [];
$lessonEnrolledCourseIds = [];
if ($currentUser) {
    // A. Full Course Enrollments
    $fStmt = $db->prepare("
        SELECT A as courseId FROM _CourseToUser WHERE B = ?
        UNION
        SELECT DISTINCT courseId FROM CourseCode 
        WHERE userId = ? AND (collectionId IS NULL OR collectionId = '') AND expires > NOW()
    ");
    $fStmt->execute([$currentUser['id'], $currentUser['id']]);
    $fullEnrolledCourseIds = $fStmt->fetchAll(PDO::FETCH_COLUMN);

    // B. Single Lesson / Collection Enrollments
    $lStmt = $db->prepare("
        SELECT DISTINCT col.courseId 
        FROM _CollectionToUser cu
        JOIN Collection col ON cu.A = col.id
        WHERE cu.B = ?
        UNION
        SELECT DISTINCT courseId FROM CourseCode 
        WHERE userId = ? AND collectionId IS NOT NULL AND collectionId != '' AND expires > NOW()
    ");
    $lStmt->execute([$currentUser['id'], $currentUser['id']]);
    $allLessonCourses = $lStmt->fetchAll(PDO::FETCH_COLUMN);
    $lessonEnrolledCourseIds = array_values(array_diff($allLessonCourses, $fullEnrolledCourseIds));
}

// Helper to build URL query with modified parameter
function filterUrl($key, $val) {
    $q = $_GET;
    if ($val === '' || $val === null) {
        unset($q[$key]);
    } else {
        $q[$key] = $val;
    }
    $q['page'] = 1;
    return '?' . http_build_query($q);
}

function pageUrl($p) {
    $q = $_GET;
    $q['page'] = $p;
    return '?' . http_build_query($q);
}
?>

<div class="min-h-screen bg-slate-50/60 pb-28">
    <!-- Top Header -->
    <div class="flex flex-col justify-center items-center pt-24 pb-8">
        <a href="/" class="transition-transform hover:scale-105 duration-300">
            <img src="/public/identity/logo.png" alt="Logo" class="h-20 w-auto object-contain mx-auto mb-4 drop-shadow-sm">
        </a>
        <h1 class="text-3xl md:text-4xl font-black text-[#0d3753] tracking-tight">جميع الكورسات والمحاضرات</h1>
        <p class="text-sm font-bold text-slate-500 mt-2">اختر المحتوى التعليمي المناسب لمرحلتك الدراسية وابدأ التعلم الآن</p>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-8 lg:flex-row" dir="rtl">
            
            <!-- Sidebar Filters (1/4 width) -->
            <aside class="w-full lg:w-1/4 space-y-6 rounded-2xl bg-white dark:bg-[#12283a] p-6 shadow-sm border border-slate-200/80 dark:border-slate-800 h-fit sticky top-24">
                
                <!-- Search -->
                <div>
                    <h3 class="mb-3 text-lg font-black text-[#0d3753] dark:text-white border-b-2 border-[#0f766e] pb-2 flex items-center justify-between">
                        <span>بحث</span>
                        <i class="fa-solid fa-magnifying-glass text-xs text-[#0f766e]"></i>
                    </h3>
                    <form method="GET" action="" class="relative">
                        <?php if ($type): ?><input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>"><?php endif; ?>
                        <?php if ($selectedYear): ?><input type="hidden" name="year" value="<?= htmlspecialchars($selectedYear) ?>"><?php endif; ?>
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="ابحث عن كورس..." 
                            value="<?= htmlspecialchars($search) ?>" 
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 px-4 py-2.5 text-xs font-bold text-slate-800 dark:text-slate-100 placeholder-slate-400 outline-none transition focus:border-[#0f766e] focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-[#0f766e]/20"
                        />
                        <?php if ($search): ?>
                            <a href="<?= filterUrl('search', '') ?>" class="absolute left-3 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Classification / Type Filter -->
                <div>
                    <h3 class="mb-3 text-lg font-black text-[#0d3753] dark:text-white border-b-2 border-[#0f766e] pb-2">التصنيف</h3>
                    <div class="flex flex-col gap-2.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                        <label class="flex items-center gap-2.5 cursor-pointer hover:text-[#0f766e] dark:hover:text-teal-400 transition">
                            <input type="radio" name="filter_type" onchange="location.href='<?= filterUrl('type', '') ?>'" <?= $type === '' ? 'checked' : '' ?> class="w-4 h-4 text-[#0f766e] accent-[#0f766e]">
                            <span>الكل</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer hover:text-[#0f766e] dark:hover:text-teal-400 transition">
                            <input type="radio" name="filter_type" onchange="location.href='<?= filterUrl('type', 'free') ?>'" <?= $type === 'free' ? 'checked' : '' ?> class="w-4 h-4 text-[#0f766e] accent-[#0f766e]">
                            <span>مجاني</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer hover:text-[#0f766e] dark:hover:text-teal-400 transition">
                            <input type="radio" name="filter_type" onchange="location.href='<?= filterUrl('type', 'paid') ?>'" <?= $type === 'paid' ? 'checked' : '' ?> class="w-4 h-4 text-[#0f766e] accent-[#0f766e]">
                            <span>مدفوع</span>
                        </label>
                    </div>
                </div>

                <!-- Academic Year Filter -->
                <div>
                    <h3 class="mb-3 text-lg font-black text-[#0d3753] dark:text-white border-b-2 border-[#0f766e] pb-2">السنة الدراسية</h3>
                    <div class="flex flex-col gap-2.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                        <label class="flex items-center gap-2.5 cursor-pointer hover:text-[#0f766e] dark:hover:text-teal-400 transition">
                            <input type="radio" name="filter_year" onchange="location.href='<?= filterUrl('year', '') ?>'" <?= $selectedYear === '' ? 'checked' : '' ?> class="w-4 h-4 text-[#0f766e] accent-[#0f766e]">
                            <span>الكل</span>
                        </label>
                        <?php foreach ($years as $y): ?>
                            <label class="flex items-center gap-2.5 cursor-pointer hover:text-[#0f766e] dark:hover:text-teal-400 transition">
                                <input type="radio" name="filter_year" onchange="location.href='<?= filterUrl('year', $y['id']) ?>'" <?= $selectedYear === $y['id'] ? 'checked' : '' ?> class="w-4 h-4 text-[#0f766e] accent-[#0f766e]">
                                <span><?= htmlspecialchars($y['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($search || $type || $selectedYear): ?>
                    <div class="pt-2">
                        <a href="/courses.php" class="block w-full text-center py-2 rounded-xl text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 transition">
                            <i class="fa-solid fa-rotate-right ml-1"></i> إعادة ضبط الفلاتر
                        </a>
                    </div>
                <?php endif; ?>

            </aside>

            <!-- Course Grid (3/4 width) -->
            <div class="w-full lg:w-3/4 space-y-8">
                <?php if (empty($courses)): ?>
                    <div class="py-20 text-center rounded-2xl bg-white border border-slate-200/80 p-8 shadow-sm">
                        <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4 text-2xl">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-1">لا توجد كورسات مطابقة لبحثك</h3>
                        <p class="text-xs text-slate-500 mb-4">جرب البحث بكلمات أخرى أو اختر مرحلة دراسية مختلفة</p>
                        <a href="/courses.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0f766e] text-white text-xs font-bold hover:bg-[#0d635c] transition">
                            عرض جميع الكورسات
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($courses as $c): ?>
                            <?php 
                                $isFullEnrolled = in_array($c['id'], $fullEnrolledCourseIds);
                                $isLessonEnrolled = in_array($c['id'], $lessonEnrolledCourseIds);
                                $thumb = $c['thumbnail'] ?: '/public/identity/logo.png';
                            ?>
                            <div class="group relative max-w-[540px] max-lg:max-w-full">
                                <!-- Card Image with Hover Zoom -->
                                <div class="w-full overflow-hidden rounded-xl shadow-md bg-slate-100 aspect-video">
                                    <img 
                                        src="<?= htmlspecialchars($thumb) ?>" 
                                        alt="<?= htmlspecialchars($c['title']) ?>" 
                                        class="w-full h-full object-cover transform transition duration-500 ease-out group-hover:scale-110 group-hover:brightness-105"
                                        loading="lazy"
                                    />
                                </div>

                                <!-- Card Body Floating Over Bottom Image -->
                                <div class="px-3">
                                    <div class="duration-300 min-h-[200px] -translate-y-8 space-y-3.5 rounded-2xl bg-white dark:bg-[#12283a] px-5 py-5 shadow-xl border border-slate-100 dark:border-slate-800 transition-all group-hover:-translate-y-9 group-hover:shadow-2xl">
                                        
                                        <!-- Title & Price Badge -->
                                        <div class="flex items-start justify-between border-b border-slate-100 dark:border-slate-800 pb-3 gap-2">
                                            <h2 class="text-base font-black text-slate-900 dark:text-white group-hover:text-[#0f766e] dark:group-hover:text-teal-400 transition-colors line-clamp-1">
                                                <?= htmlspecialchars($c['title']) ?>
                                            </h2>
                                            
                                            <div class="max-h-fit rounded-lg bg-[#c79a32] px-2.5 py-1 text-center text-xs font-black text-white shrink-0 transition-transform duration-300 group-hover:rotate-2">
                                                <?php if ($isFullEnrolled): ?>
                                                    <span class="rounded-md bg-emerald-600 text-white px-2 py-0.5 text-[11px] font-black">مشترك في الكورس كاملاً</span>
                                                <?php elseif ($isLessonEnrolled): ?>
                                                    <span class="rounded-md bg-amber-500 text-white px-2 py-0.5 text-[11px] font-black">مشترك في حصة منفصلة</span>
                                                <?php elseif ($c['price'] > 0): ?>
                                                    <span class="ml-1 rounded-md bg-white px-2 py-0.5 text-black font-mono text-[11px] font-bold"><?= number_format((float)$c['price'], 0) ?></span> جنيهاً
                                                <?php else: ?>
                                                    مجاني
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Description -->
                                        <div class="space-y-2">
                                            <p class="font-normal text-slate-500 dark:text-slate-400 text-xs line-clamp-2 leading-relaxed">
                                                <?= htmlspecialchars($c['description'] ?: 'شرح وتدريبات تفاعلية متكاملة لمادة اللغة العربية') ?>
                                            </p>
                                        </div>

                                        <!-- Action Buttons -->
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

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mt-12 flex justify-center w-full" dir="ltr">
                            <nav class="flex items-center gap-1.5 bg-white dark:bg-[#12283a] p-2 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                                <!-- Previous -->
                                <a 
                                    href="<?= pageUrl($page - 1) ?>" 
                                    class="inline-flex items-center gap-1 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition <?= $page <= 1 ? 'pointer-events-none opacity-40' : '' ?>"
                                >
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                                    <span class="hidden sm:inline">السابق</span>
                                </a>

                                <!-- Page Numbers -->
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <?php if ($p == 1 || $p == $totalPages || ($p >= $page - 1 && $p <= $page + 1)): ?>
                                        <a 
                                            href="<?= pageUrl($p) ?>" 
                                            class="w-8 h-8 flex items-center justify-center rounded-xl text-xs font-bold transition <?= $p === $page ? 'bg-[#0f766e] text-white shadow-sm' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>"
                                        >
                                            <?= $p ?>
                                        </a>
                                    <?php elseif ($p == $page - 2 || $p == $page + 2): ?>
                                        <span class="w-6 text-center text-slate-400 text-xs">...</span>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Next -->
                                <a 
                                    href="<?= pageUrl($page + 1) ?>" 
                                    class="inline-flex items-center gap-1 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition <?= $page >= $totalPages ? 'pointer-events-none opacity-40' : '' ?>"
                                >
                                    <span class="hidden sm:inline">التالي</span>
                                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </a>
                            </nav>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
