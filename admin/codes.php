<?php
require_once __DIR__ . '/../config/helpers.php';
$adminUser = requireAdmin();
requireAdminPermission('codes');
$db = getDB();

$yearFilter = $_GET['yearId'] ?? '';
$courseFilter = $_GET['courseId'] ?? '';
$typeFilter = $_GET['type'] ?? 'ALL';
$q = trim($_GET['q'] ?? '');

$sql = "
    SELECT cc.*, c.title AS courseTitle, y.name AS yearName, col.title AS collectionTitle, col.price AS collectionPrice,
           u.fullName AS studentName, u.phone AS studentPhone,
           (SELECT CONCAT(IFNULL(deviceType,'pc'), ' | ', IFNULL(os,''), ' | ', IFNULL(browser,'')) 
            FROM Device 
            WHERE userId = cc.userId 
            LIMIT 1) as boundDeviceInfo
    FROM CourseCode cc
    LEFT JOIN Course c ON cc.courseId = c.id
    LEFT JOIN Year y ON c.yearId = y.id
    LEFT JOIN Collection col ON cc.collectionId = col.id
    LEFT JOIN User u ON cc.userId = u.id
    WHERE 1=1
";
$params = [];
if ($yearFilter) {
    $sql .= " AND c.yearId = ?";
    $params[] = $yearFilter;
}
if ($courseFilter) {
    $sql .= " AND cc.courseId = ?";
    $params[] = $courseFilter;
}
if ($typeFilter === 'COURSE') {
    $sql .= " AND cc.collectionId IS NULL";
} elseif ($typeFilter === 'LESSON') {
    $sql .= " AND cc.collectionId IS NOT NULL";
}
if ($q !== '') {
    $sql .= " AND (cc.code LIKE ? OR u.fullName LIKE ? OR u.phone LIKE ?)";
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like]);
}

$sql .= " ORDER BY cc.expires DESC LIMIT 300";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$codes = $stmt->fetchAll();

// Handle Excel CSV Export (#5)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="platform_codes_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['الكود', 'النوع', 'الصف الدراسي', 'الكورس', 'الحصة المحددة', 'تاريخ الانتهاء', 'حالة الاستخدام', 'اسم الطالب', 'رقم الهاتف', 'الجهاز المستخدم']);
    foreach ($codes as $cd) {
        $typeStr = !empty($cd['collectionId']) ? 'حصة فردية' : 'كورس كامل';
        $statusStr = (strtotime($cd['expires']) < time()) ? 'منتهي' : (!empty($cd['userId']) ? 'مفعل ومستخدم' : 'جاهز للاستخدام');
        fputcsv($out, [
            $cd['code'],
            $typeStr,
            $cd['yearName'] ?: 'غير محدد',
            $cd['courseTitle'] ?: '---',
            $cd['collectionTitle'] ?: '---',
            substr($cd['expires'], 0, 10),
            $statusStr,
            $cd['studentName'] ?: '---',
            $cd['studentPhone'] ?: '---',
            $cd['boundDeviceInfo'] ?: (!empty($cd['boundDeviceIdentifier']) ? 'جهاز مربوط' : 'غير مربوط')
        ]);
    }
    fclose($out);
    exit;
}

$adminTitle = "أكواد وكروت الاشتراك والحصص";
$activeAdminNav = "codes";
require_once __DIR__ . '/header.php';

$years = $db->query("SELECT id, name FROM Year ORDER BY `order` ASC")->fetchAll();
$courses = $db->query("SELECT id, title, price, yearId FROM Course ORDER BY `order` ASC")->fetchAll();
$allCollections = $db->query("SELECT id, title, price, courseId FROM Collection ORDER BY `order` ASC")->fetchAll();

$coursesByYear = [];
foreach ($courses as $c) {
    $yId = $c['yearId'] ?: 'other';
    $coursesByYear[$yId][] = $c;
}

$collectionsByCourse = [];
foreach ($allCollections as $col) {
    $collectionsByCourse[$col['courseId']][] = $col;
}
?>

<!-- Generator & Header -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
    
    <!-- Batch Generator Form -->
    <div class="lg:col-span-5 bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm space-y-4">
        <div>
            <h2 class="text-base font-black text-navy-custom flex items-center gap-2">
                <i class="fa-solid fa-wand-magic-sparkles text-gold-custom"></i>
                <span>توليد كروت جديدة (كورس كامل أو حصة)</span>
            </h2>
            <p class="text-xs text-slate-400">طباعة وتوليد كروت شحن للسنتر لحضور كورس كامل أو حصة واحدة</p>
        </div>

        <form id="batchForm" onsubmit="submitBatch(event)" class="space-y-4">
            
            <!-- Code Type Radio Selector -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">نوع الكود المطلوب</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 cursor-pointer bg-slate-50 hover:bg-slate-100 transition" id="labelTypeCourse">
                        <input type="radio" name="targetType" value="COURSE" checked onchange="onTargetTypeChange()" class="accent-[#0f766e]">
                        <span class="text-xs font-bold text-slate-800">🎓 كورس كامل</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 cursor-pointer bg-slate-50 hover:bg-slate-100 transition" id="labelTypeLesson">
                        <input type="radio" name="targetType" value="LESSON" onchange="onTargetTypeChange()" class="accent-[#0f766e]">
                        <span class="text-xs font-bold text-slate-800">🎯 حصة واحدة (درس)</span>
                    </label>
                </div>
            </div>

            <!-- Year (Grade) Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الصف الدراسي *</label>
                <select id="batchYearId" onchange="onYearSelected()" required class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:border-[#0f766e]">
                    <option value="">جميع الصفوف أو اختر الصف...</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= htmlspecialchars($y['id']) ?>"><?= htmlspecialchars($y['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Course Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">اختر الكورس *</label>
                <select id="batchCourseId" onchange="onCourseSelected()" required class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:border-[#0f766e]">
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= htmlspecialchars($c['id']) ?>" data-year="<?= htmlspecialchars($c['yearId'] ?? '') ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Lesson Selection (Visible when LESSON is chosen) -->
            <div id="lessonSelectContainer" class="hidden">
                <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center justify-between">
                    <span>اختر الحصة / الدرس المحدد *</span>
                    <span class="text-[10px] text-amber-600 font-bold" id="lessonPriceHint"></span>
                </label>
                <select id="batchCollectionId" class="w-full px-3 py-2.5 bg-amber-50/60 border border-amber-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:border-[#0f766e]">
                    <option value="">اختر الحصة...</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">عدد الكروت المطلوبة</label>
                    <input type="number" id="batchCount" value="10" min="1" max="100" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">بادئة الكود (Prefix)</label>
                    <input type="text" id="batchPrefix" value="MY" maxlength="5" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none uppercase font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">صلاحية الكود (بالأيام)</label>
                    <input type="number" id="batchDays" value="30" min="1" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">مرات الدخول المسموحة</label>
                    <input type="number" id="batchAccess" value="10" min="1" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none">
                </div>
            </div>

            <button type="submit" id="batchBtn" class="w-full py-3 bg-gold-custom hover:bg-yellow-500 text-navy-custom font-black text-xs rounded-xl shadow transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-print"></i> توليد الكروت فوراً
            </button>
        </form>
    </div>

    <!-- Codes Table -->
    <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden flex flex-col justify-between">
        
        <!-- Filter Header -->
        <div class="p-6 border-b border-slate-100 flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-black text-navy-custom">الأكواد المسجلة بالنظام (<?= count($codes) ?>)</h3>
                    <p class="text-xs text-slate-400">تتبع الأكواد، الجهاز المرتبط لكل كود، والبحث بالاسم أو الهاتف أو الكود</p>
                </div>
                <a href="?export=excel&type=<?= urlencode($typeFilter) ?>&yearId=<?= urlencode($yearFilter) ?>&courseId=<?= urlencode($courseFilter) ?>&q=<?= urlencode($q) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm w-fit">
                    <i class="fa-solid fa-file-excel"></i>
                    <span>تحميل كملف إكسيل (Excel)</span>
                </a>
            </div>
            
            <form method="GET" action="/admin/codes.php" class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[200px]">
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="بحث بالكود، اسم الطالب، أو رقم الهاتف..." class="w-full pl-3 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 outline-none focus:border-[#0f766e]">
                    <i class="fa-solid fa-magnifying-glass text-slate-400 absolute right-3 top-2.5 text-xs pointer-events-none"></i>
                </div>

                <select name="type" onchange="this.form.submit()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 outline-none">
                    <option value="ALL" <?= $typeFilter === 'ALL' ? 'selected' : '' ?>>جميع الأنواع</option>
                    <option value="COURSE" <?= $typeFilter === 'COURSE' ? 'selected' : '' ?>>🎓 كورسات كاملة</option>
                    <option value="LESSON" <?= $typeFilter === 'LESSON' ? 'selected' : '' ?>>🎯 حصص فردية</option>
                </select>

                <select name="yearId" onchange="this.form.submit()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 outline-none">
                    <option value="">جميع الصفوف</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= htmlspecialchars($y['id']) ?>" <?= $yearFilter === $y['id'] ? 'selected' : '' ?>><?= htmlspecialchars($y['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="courseId" onchange="this.form.submit()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 outline-none">
                    <option value="">جميع الكورسات</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= htmlspecialchars($c['id']) ?>" <?= $courseFilter === $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="px-4 py-2 bg-[#0f766e] hover:bg-[#0d3753] text-white rounded-xl text-xs font-bold transition">
                    بحث
                </button>

                <?php if ($q !== '' || $yearFilter !== '' || $courseFilter !== '' || $typeFilter !== 'ALL'): ?>
                    <a href="/admin/codes.php" class="p-2 bg-slate-100 hover:bg-slate-200 rounded-xl text-slate-600 transition" title="إلغاء الفلترة">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">الكود</th>
                        <th class="p-3.5">النوع / المحتوى</th>
                        <th class="p-3.5">الصف / الكورس</th>
                        <th class="p-3.5">الطالب المفعل</th>
                        <th class="p-3.5">الجهاز المرتبط</th>
                        <th class="p-3.5">الحالة</th>
                        <th class="p-3.5 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($codes)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400 font-bold">لا توجد أكواد مسجلة مطابقة للفلترة أو البحث.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($codes as $cd): 
                            $isExp = strtotime($cd['expires']) < time();
                            $isUsed = !empty($cd['userId']);
                            $isLesson = !empty($cd['collectionId']);
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-3.5 font-mono font-black text-navy-custom select-all"><?= htmlspecialchars($cd['code']) ?></td>
                                <td class="p-3.5">
                                    <?php if ($isLesson): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-200" title="كود حضور حصة واحدة">
                                            <span>🎯 حصة:</span>
                                            <span><?= htmlspecialchars($cd['collectionTitle'] ?? 'حصة') ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-teal-100 text-teal-800 border border-teal-200" title="اشتراك في الكورس بالكامل">
                                            <span>🎓 كورس كامل</span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5">
                                    <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-bold block mb-1 w-fit">
                                        <?= htmlspecialchars($cd['yearName'] ?? 'عام') ?>
                                    </span>
                                    <span class="font-semibold text-slate-800 block"><?= htmlspecialchars($cd['courseTitle'] ?? '---') ?></span>
                                </td>
                                <td class="p-3.5">
                                    <?php if ($isUsed): ?>
                                        <span class="font-bold text-teal-800 block"><?= htmlspecialchars($cd['studentName'] ?? '') ?></span>
                                        <span class="font-mono text-[10px] text-slate-400" dir="ltr"><?= htmlspecialchars($cd['studentPhone'] ?? '') ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400">غير مستخدم بعد</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5">
                                    <?php if (!empty($cd['boundDeviceInfo'])): 
                                        $dParts = explode('|', $cd['boundDeviceInfo']);
                                        $dType = trim($dParts[0] ?? '');
                                        $dOs = trim($dParts[1] ?? '');
                                        $dBr = trim($dParts[2] ?? '');
                                        $dTypeArabic = ($dType === 'phone') ? 'هاتف' : (($dType === 'tablet') ? 'تابلت' : (($dType === 'laptop') ? 'لابتوب' : 'كمبيوتر'));
                                    ?>
                                        <div>
                                            <span class="font-bold text-slate-800 text-[11px] block flex items-center gap-1">
                                                <i class="fa-solid fa-mobile-screen text-blue-600"></i>
                                                <?= $dTypeArabic ?> (<?= htmlspecialchars($dOs ?: 'جهاز') ?>)
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-mono block"><?= htmlspecialchars($dBr) ?></span>
                                        </div>
                                        <button onclick="unbindCode('<?= $cd['id'] ?>')" class="mt-1 px-1.5 py-0.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded text-[9px] font-bold transition cursor-pointer" title="اضغط لفك الربط عن هذا الكود">
                                            فك ربط <i class="fa-solid fa-unlock ml-0.5"></i>
                                        </button>
                                    <?php elseif (!empty($cd['boundDeviceIdentifier'])): ?>
                                        <button onclick="unbindCode('<?= $cd['id'] ?>')" class="px-2 py-1 bg-amber-100 text-amber-800 rounded font-bold text-[10px] hover:bg-amber-200 cursor-pointer" title="اضغط لفك الربط">
                                            جهاز مربوط <i class="fa-solid fa-lock ml-0.5"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[10px]">غير مربوط</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5">
                                    <?php if ($isExp): ?>
                                        <span class="text-red-700 bg-red-100 px-2 py-0.5 rounded font-bold text-[10px]">منتهي</span>
                                    <?php elseif ($isUsed): ?>
                                        <span class="text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded font-bold text-[10px]">مفعل</span>
                                    <?php else: ?>
                                        <span class="text-blue-700 bg-blue-100 px-2 py-0.5 rounded font-bold text-[10px]">جاهز</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 text-center">
                                    <button onclick="deleteCode('<?= $cd['id'] ?>')" class="p-1.5 text-red-500 hover:text-red-700 transition" title="حذف الكود">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ======================================================== -->
<!-- Batch Generation Success & Printable Voucher Cards Modal -->
<!-- ======================================================== -->
<div id="printVouchersModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-6 md:p-8 space-y-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-lg font-black text-navy-custom flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i>
                    <span>تم توليد الكروت بنجاح!</span>
                </h3>
                <p class="text-xs text-slate-400 mt-0.5" id="vouchersSummaryText"></p>
            </div>
            <button onclick="closePrintModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-3">
            <button onclick="exportBatchToExcel()" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow transition flex items-center gap-2 cursor-pointer">
                <i class="fa-solid fa-file-excel"></i>
                <span>تحميل ملف إكسيل (Excel)</span>
            </button>
            <button onclick="printVouchers()" class="px-5 py-2.5 bg-[#0f766e] hover:bg-[#0d3753] text-white font-bold text-xs rounded-xl shadow transition flex items-center gap-2 cursor-pointer">
                <i class="fa-solid fa-file-pdf"></i>
                <span>طباعة / حفظ PDF</span>
            </button>
            <button onclick="copyGeneratedCodes()" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition flex items-center gap-2 cursor-pointer">
                <i class="fa-solid fa-copy"></i>
                <span id="copyBtnText">نسخ جميع الأكواد</span>
            </button>
        </div>

        <!-- Printable Cards Preview Area -->
        <div id="printableCardsArea" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Dynamically injected cards -->
        </div>
    </div>
</div>

<script>
const coursesByYear = <?= json_encode($coursesByYear, JSON_UNESCAPED_UNICODE) ?>;
const allCourses = <?= json_encode($courses, JSON_UNESCAPED_UNICODE) ?>;
const collectionsByCourse = <?= json_encode($collectionsByCourse, JSON_UNESCAPED_UNICODE) ?>;
let latestBatchCodes = [];
let latestBatchMeta = {};

function onYearSelected() {
    const yearId = document.getElementById('batchYearId').value;
    const courseSelect = document.getElementById('batchCourseId');
    courseSelect.innerHTML = '';

    const list = yearId ? (coursesByYear[yearId] || []) : allCourses;
    if (list.length === 0) {
        courseSelect.innerHTML = '<option value="">لا توجد كورسات مضافة لهذا الصف</option>';
    } else {
        list.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.title;
            courseSelect.appendChild(opt);
        });
    }
    onCourseSelected();
}

function onTargetTypeChange() {
    const isLesson = document.querySelector('input[name="targetType"]:checked').value === 'LESSON';
    const container = document.getElementById('lessonSelectContainer');
    const prefixInput = document.getElementById('batchPrefix');
    const daysInput = document.getElementById('batchDays');
    const accessInput = document.getElementById('batchAccess');

    if (isLesson) {
        container.classList.remove('hidden');
        prefixInput.value = 'HSA';
        daysInput.value = '14';
        accessInput.value = '5';
        populateLessonsDropdown();
    } else {
        container.classList.add('hidden');
        prefixInput.value = 'MY';
        daysInput.value = '30';
        accessInput.value = '10';
    }
}

function onCourseSelected() {
    const isLesson = document.querySelector('input[name="targetType"]:checked').value === 'LESSON';
    if (isLesson) {
        populateLessonsDropdown();
    }
}

function populateLessonsDropdown() {
    const courseId = document.getElementById('batchCourseId').value;
    const select = document.getElementById('batchCollectionId');
    const priceHint = document.getElementById('lessonPriceHint');
    select.innerHTML = '';

    const list = collectionsByCourse[courseId] || [];
    if (list.length === 0) {
        select.innerHTML = '<option value="">لا توجد حصص مضافة لهذا الكورس</option>';
        priceHint.textContent = '';
        return;
    }

    list.forEach((col, idx) => {
        const opt = document.createElement('option');
        opt.value = col.id;
        opt.textContent = col.title + ' (' + (col.price ? col.price + ' ج.م' : 'سعر الكورس') + ')';
        select.appendChild(opt);
    });

    select.onchange = function() {
        const selectedCol = list.find(c => c.id === select.value);
        if (selectedCol && selectedCol.price) {
            priceHint.textContent = 'سعر الحصة: ' + selectedCol.price + ' ج.م';
        } else {
            priceHint.textContent = '';
        }
    };
    select.onchange();
}

// Initial populate if needed
onTargetTypeChange();

function submitBatch(e) {
    e.preventDefault();
    const btn = document.getElementById('batchBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> جاري التوليد...';

    const isLesson = document.querySelector('input[name="targetType"]:checked').value === 'LESSON';
    const courseId = document.getElementById('batchCourseId').value;
    const collectionId = isLesson ? document.getElementById('batchCollectionId').value : null;

    if (!courseId) {
        showToast('يرجى اختيار الكورس أولاً', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-print"></i> توليد الكروت فوراً';
        return;
    }

    if (isLesson && !collectionId) {
        showToast('يرجى اختيار الحصة المحددة أولاً', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-print"></i> توليد الكروت فوراً';
        return;
    }

    const payload = {
        courseId: courseId,
        collectionId: collectionId,
        count: document.getElementById('batchCount').value,
        prefix: document.getElementById('batchPrefix').value,
        days: document.getElementById('batchDays').value,
        accessTimes: document.getElementById('batchAccess').value
    };

    fetch('/api/admin.php?action=generate-batch-codes', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-print"></i> توليد الكروت فوراً';

        if (data.success) {
            showToast(data.message, 'success');
            latestBatchCodes = data.codes || [];
            latestBatchMeta = {
                yearTitle: document.getElementById('batchYearId').selectedOptions[0]?.text || '',
                courseTitle: document.getElementById('batchCourseId').selectedOptions[0]?.text || '',
                collectionTitle: data.collectionTitle || '',
                isLesson: isLesson,
                days: payload.days,
                accessTimes: payload.accessTimes
            };
            openPrintModal();
        } else {
            showToast(data.error || 'حدث خطأ', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-print"></i> توليد الكروت فوراً';
        showToast('حدث خطأ في الاتصال بالخادم', 'error');
    });
}

function openPrintModal() {
    const modal = document.getElementById('printVouchersModal');
    const summary = document.getElementById('vouchersSummaryText');
    const cardsArea = document.getElementById('printableCardsArea');

    const yearPrefix = (latestBatchMeta.yearTitle && !latestBatchMeta.yearTitle.includes('جميع')) ? `[${latestBatchMeta.yearTitle}] ` : '';
    summary.textContent = latestBatchMeta.isLesson
        ? `كروت حصة: ${yearPrefix}${latestBatchMeta.collectionTitle} (${latestBatchMeta.courseTitle}) - عدد: ${latestBatchCodes.length}`
        : `كروت كورس: ${yearPrefix}${latestBatchMeta.courseTitle} - عدد: ${latestBatchCodes.length}`;

    cardsArea.innerHTML = '';
    latestBatchCodes.forEach(code => {
        const card = document.createElement('div');
        card.className = 'border-2 border-dashed border-slate-300 rounded-2xl p-4 bg-slate-50 flex flex-col justify-between text-center space-y-2';
        card.innerHTML = `
            <div class="border-b border-slate-200 pb-2">
                <div class="text-[10px] font-bold text-slate-400">منصة الأستاذ محمد يوسف التعليمية ${yearPrefix ? '• ' + yearPrefix : ''}</div>
                <div class="text-xs font-black text-navy-custom mt-0.5">
                    ${latestBatchMeta.isLesson ? ('🎯 كارت حصة: ' + latestBatchMeta.collectionTitle + ' (' + latestBatchMeta.courseTitle + ')') : ('🎓 كارت اشتراك: ' + latestBatchMeta.courseTitle)}
                </div>
            </div>
            <div class="py-2">
                <span class="inline-block px-4 py-2 bg-white border border-slate-300 rounded-xl font-mono text-base font-black tracking-widest text-[#0f766e] select-all shadow-inner">
                    ${code}
                </span>
            </div>
            <div class="flex items-center justify-between text-[10px] text-slate-500 font-bold border-t border-slate-200 pt-1.5">
                <span>الصلاحية: ${latestBatchMeta.days} يوم</span>
                <span>مرات الدخول: ${latestBatchMeta.accessTimes}</span>
            </div>
        `;
        cardsArea.appendChild(card);
    });

    modal.classList.remove('hidden');
}

function closePrintModal() {
    document.getElementById('printVouchersModal').classList.add('hidden');
    window.location.reload();
}

function copyGeneratedCodes() {
    const text = latestBatchCodes.join('\n');
    navigator.clipboard.writeText(text).then(() => {
        document.getElementById('copyBtnText').textContent = 'تم النسخ بنجاح ✓';
        setTimeout(() => {
            document.getElementById('copyBtnText').textContent = 'نسخ جميع الأكواد';
        }, 2000);
    });
}

function exportBatchToExcel() {
    if (!latestBatchCodes || latestBatchCodes.length === 0) {
        showToast('لا توجد أكواد لتصديرها', 'error');
        return;
    }
    let csvContent = "\uFEFFالكود,النوع,الصف الدراسي,الكورس,الحصة المحددة,فترة الصلاحية (أيام),مرات الدخول المسموحة\n";
    const typeTitle = latestBatchMeta.isLesson ? "حصة فردية" : "كورس كامل";
    const yearTitle = latestBatchMeta.yearTitle || "";
    const courseTitle = latestBatchMeta.courseTitle || "";
    const lessonTitle = latestBatchMeta.isLesson ? latestBatchMeta.collectionTitle : "كامل الكورس";
    latestBatchCodes.forEach(code => {
        csvContent += `"${code}","${typeTitle}","${yearTitle.replace(/"/g, '""')}","${courseTitle.replace(/"/g, '""')}","${lessonTitle.replace(/"/g, '""')}","${latestBatchMeta.days}","${latestBatchMeta.accessTimes}"\n`;
    });
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `codes_${latestBatchMeta.isLesson ? 'lesson' : 'course'}_${Date.now()}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function printVouchers() {
    const printWindow = window.open('', '', 'width=800,height=600');
    const yearPrefix = (latestBatchMeta.yearTitle && !latestBatchMeta.yearTitle.includes('جميع')) ? ` - ${latestBatchMeta.yearTitle}` : '';
    printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>طباعة كروت الشحن - منصة أ. محمد يوسف</title>
            <style>
                body { font-family: 'Cairo', Tahoma, sans-serif; margin: 20px; color: #111; }
                .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
                .card { border: 2px dashed #999; border-radius: 12px; padding: 12px; text-align: center; page-break-inside: avoid; }
                .header { font-size: 11px; font-weight: bold; color: #555; }
                .title { font-size: 13px; font-weight: 900; margin-top: 4px; color: #0d3753; }
                .code-box { font-family: monospace; font-size: 16px; font-weight: 900; background: #eee; padding: 6px 12px; border-radius: 8px; margin: 10px 0; letter-spacing: 2px; }
                .meta { display: flex; justify-content: space-between; font-size: 10px; font-weight: bold; color: #666; border-top: 1px solid #ddd; padding-top: 6px; }
                @media print { button { display: none; } }
            </style>
        </head>
        <body>
            <div style="text-align:center;margin-bottom:15px;">
                <h2 style="margin:0;">منصة الأستاذ محمد يوسف التعليمية ${yearPrefix}</h2>
                <p style="margin:4px 0;font-size:12px;">${latestBatchMeta.isLesson ? ('كروت حضور حصة: ' + latestBatchMeta.collectionTitle + ' (' + latestBatchMeta.courseTitle + ')') : ('كروت كورس: ' + latestBatchMeta.courseTitle)}</p>
            </div>
            <div class="grid">
                ${latestBatchCodes.map(c => `
                    <div class="card">
                        <div class="header">منصة الأستاذ محمد يوسف ${yearPrefix}</div>
                        <div class="title">${latestBatchMeta.isLesson ? ('كارت حضور حصة: ' + latestBatchMeta.collectionTitle + ' - ' + latestBatchMeta.courseTitle) : ('كارت كورس: ' + latestBatchMeta.courseTitle)}</div>
                        <div class="code-box">${c}</div>
                        <div class="meta">
                            <span>الصلاحية: ${latestBatchMeta.days} يوم</span>
                            <span>الدخول: ${latestBatchMeta.accessTimes} مرات</span>
                        </div>
                    </div>
                `).join('')}
            </div>
            <script>window.onload = function() { window.print(); };<\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
                            <span>الدخول: ${latestBatchMeta.accessTimes} مرات</span>
                        </div>
                    </div>
                `).join('')}
            </div>
            <script>window.onload = function() { window.print(); };<\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function unbindCode(id) {
    if (!confirm('هل تريد فك ربط جهاز هذا الكود؟ سيتمكن الطالب من تفعيله على جهاز آخر.')) return;
    fetch('/api/custom.php?action=unbind-student-code', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        }
    });
}

function deleteCode(id) {
    if (!confirm('هل تريد حذف هذا الكود نهائياً؟')) return;
    fetch('/api/custom.php?action=delete-student-code&id=' + id, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 600);
            }
        });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
