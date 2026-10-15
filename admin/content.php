<?php
require_once __DIR__ . '/../config/helpers.php';
$adminUser = requireAdmin();
requireAdminPermission('content');
$db = getDB();

$msg = $_SESSION['content_msg'] ?? '';
$msgType = $_SESSION['content_msg_type'] ?? '';
unset($_SESSION['content_msg'], $_SESSION['content_msg_type']);

// Handle Delete Content
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['type']) && isset($_GET['id'])) {
    $delType = strtoupper($_GET['type']);
    $delId = $_GET['id'];
    
    try {
        if ($delType === 'VIDEO') {
            $db->prepare("DELETE FROM Video WHERE id = ?")->execute([$delId]);
            $_SESSION['content_msg'] = 'تم حذف الفيديو بنجاح';
            $_SESSION['content_msg_type'] = 'success';
        } elseif ($delType === 'FILE') {
            $db->prepare("DELETE FROM File WHERE id = ?")->execute([$delId]);
            $_SESSION['content_msg'] = 'تم حذف الملف بنجاح';
            $_SESSION['content_msg_type'] = 'success';
        } elseif ($delType === 'QUIZ') {
            $db->prepare("DELETE FROM Quiz WHERE id = ?")->execute([$delId]);
            $_SESSION['content_msg'] = 'تم حذف الامتحان بنجاح';
            $_SESSION['content_msg_type'] = 'success';
        }
    } catch (Exception $e) {
        $_SESSION['content_msg'] = 'حدث خطأ أثناء الحذف: ' . $e->getMessage();
        $_SESSION['content_msg_type'] = 'error';
    }
    header("Location: content.php");
    exit;
}

// Handle Add / Edit Content
// Handle Add / Edit Content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_content'])) {
    $cType = strtoupper($_POST['content_type'] ?? 'VIDEO');
    $courseId = trim($_POST['courseId'] ?? '');
    $collectionId = trim($_POST['collectionId'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $published = isset($_POST['published']) ? 1 : 0;
    $editId = trim($_POST['edit_id'] ?? '');

    if (empty($courseId)) {
        $_SESSION['content_msg'] = 'يرجى تحديد الكورس التابع له المحتوى أو الامتحان';
        $_SESSION['content_msg_type'] = 'error';
    } elseif (empty($title)) {
        $_SESSION['content_msg'] = 'عنوان المحتوى / الامتحان مطلوب';
        $_SESSION['content_msg_type'] = 'error';
    } else {
        // Auto-assign or auto-create collection if none selected (#17)
        if (empty($collectionId) || $collectionId === '__AUTO__') {
            $chkCol = $db->prepare("SELECT id FROM Collection WHERE courseId = ? ORDER BY `order` ASC LIMIT 1");
            $chkCol->execute([$courseId]);
            $collectionId = $chkCol->fetchColumn();
            if (!$collectionId) {
                $collectionId = generateId(5);
                $db->prepare("INSERT INTO Collection (id, title, price, courseId, `order`, updatedAt) VALUES (?, 'دروس ومحاضرات الكورس', 70, ?, 1, NOW())")
                   ->execute([$collectionId, $courseId]);
            }
        }

        try {
            if ($cType === 'VIDEO') {
                $link = trim($_POST['video_link'] ?? '');
                $source = $_POST['video_source'] ?? 'YOUTUBE';
                if ($source === 'DAILYMOTION' && !empty($link) && !str_starts_with($link, 'http')) {
                    $link = 'https://www.dailymotion.com/video/' . $link;
                }
                
                if (empty($link)) {
                    throw new Exception('رابط الفيديو مطلوب');
                }

                if ($editId) {
                    $stmt = $db->prepare("UPDATE Video SET title = ?, description = ?, link = ?, courseId = ?, collectionId = ?, published = ?, updatedAt = NOW() WHERE id = ?");
                    $stmt->execute([$title, $description, $link, $courseId, $collectionId, $published, $editId]);
                    $_SESSION['content_msg'] = 'تم تحديث الفيديو بنجاح!';
                } else {
                    $vId = generateId(5);
                    $stmt = $db->prepare("INSERT INTO Video (id, title, description, link, courseId, collectionId, published, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$vId, $title, $description, $link, $courseId, $collectionId, $published]);
                    $_SESSION['content_msg'] = 'تم إضافة الفيديو بنجاح!';
                }
                $_SESSION['content_msg_type'] = 'success';

            } elseif ($cType === 'FILE') {
                $link = trim($_POST['file_link'] ?? '');
                
                // Handle optional file upload
                if (!empty($_FILES['file_upload']['name'])) {
                    $upDir = __DIR__ . '/../public/uploads/';
                    if (!is_dir($upDir)) mkdir($upDir, 0777, true);
                    $ext = pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION);
                    $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['file_upload']['name'], PATHINFO_FILENAME));
                    $fName = $cleanName . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $upDir . $fName)) {
                        $link = '/api/uploads/' . $fName;
                    }
                }

                if (empty($link)) {
                    throw new Exception('رابط أو ملف المستند مطلوب');
                }

                if ($editId) {
                    $stmt = $db->prepare("UPDATE File SET title = ?, description = ?, link = ?, courseId = ?, collectionId = ?, published = ?, updatedAt = NOW() WHERE id = ?");
                    $stmt->execute([$title, $description, $link, $courseId, $collectionId, $published, $editId]);
                    $_SESSION['content_msg'] = 'تم تحديث الملف بنجاح!';
                } else {
                    $fId = generateId(5);
                    $stmt = $db->prepare("INSERT INTO File (id, title, description, link, courseId, collectionId, published, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$fId, $title, $description, $link, $courseId, $collectionId, $published]);
                    $_SESSION['content_msg'] = 'تم إضافة الملف بنجاح!';
                }
                $_SESSION['content_msg_type'] = 'success';

            } elseif ($cType === 'QUIZ') {
                $quizTime = max(1, (int)($_POST['quiz_time'] ?? 30));
                $rawQuestions = $_POST['questions'] ?? [];

                if ($editId) {
                    $stmt = $db->prepare("UPDATE Quiz SET title = ?, description = ?, quizTime = ?, courseId = ?, collectionId = ?, published = ?, updatedAt = NOW() WHERE id = ?");
                    $stmt->execute([$title, $description, $quizTime, $courseId, $collectionId, $published, $editId]);
                    $quizId = $editId;
                    // Delete old questions if updating
                    if (!empty($rawQuestions)) {
                        $db->prepare("DELETE FROM Question WHERE quizId = ?")->execute([$quizId]);
                    }
                    $_SESSION['content_msg'] = 'تم تحديث بيانات الامتحان بنجاح!';
                } else {
                    $quizId = generateId(5);
                    $stmt = $db->prepare("INSERT INTO Quiz (id, title, description, quizTime, courseId, collectionId, published, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$quizId, $title, $description, $quizTime, $courseId, $collectionId, $published]);
                    $_SESSION['content_msg'] = 'تم إنشاء الامتحان بنجاح!';
                }

                // Insert questions
                if (is_array($rawQuestions) && count($rawQuestions) > 0) {
                    $qOrder = 0;
                    foreach ($rawQuestions as $q) {
                        $qHead = trim($q['head'] ?? '');
                        if (!$qHead) continue;
                        $qImg = trim($q['image'] ?? null);
                        $qType = $q['type'] ?? 'MCQ';
                        $qOpts = [];
                        if ($qType === 'MCQ' && isset($q['options']) && is_array($q['options'])) {
                            foreach ($q['options'] as $oIdx => $opt) {
                                $trimmedOpt = trim($opt);
                                if ($trimmedOpt !== '') {
                                    $qOpts[] = $trimmedOpt;
                                }
                            }
                        }

                        // Determine right answer: either from correct_index radio or right text
                        $qRight = trim($q['right'] ?? '');
                        if (isset($q['correct_index']) && isset($q['options'][$q['correct_index']])) {
                            $radioVal = trim($q['options'][$q['correct_index']]);
                            if ($radioVal !== '') {
                                $qRight = $radioVal;
                            }
                        }
                        if ($qType === 'WRITTEN') {
                            $qRight = trim($q['right_written'] ?? $qRight);
                        }

                        $qId = generateId(5);
                        $insQ = $db->prepare("INSERT INTO Question (id, questionHead, options, rightAnswer, image, quizId, `order`) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $insQ->execute([$qId, $qHead, json_encode($qOpts, JSON_UNESCAPED_UNICODE), $qRight, $qImg ?: null, $quizId, $qOrder++]);
                    }
                }
                $_SESSION['content_msg_type'] = 'success';
            }
        } catch (Exception $e) {
            $_SESSION['content_msg'] = 'فشل الحفظ: ' . $e->getMessage();
            $_SESSION['content_msg_type'] = 'error';
        }
    }
    header("Location: content.php");
    exit;
}

// Filters
$fType = $_GET['filterType'] ?? 'ALL';
$fCourse = $_GET['filterCourse'] ?? 'ALL';
$fStatus = $_GET['filterStatus'] ?? 'ALL';
$fSearch = trim($_GET['search'] ?? '');

// Fetch all courses and their collections for the cascading dropdown
$courses = $db->query("SELECT id, title FROM Course ORDER BY `order` ASC")->fetchAll();
$allCollections = $db->query("SELECT id, title, courseId FROM Collection ORDER BY `order` ASC")->fetchAll();

// Group collections by courseId for fast JS lookup
$collectionsByCourse = [];
foreach ($allCollections as $col) {
    $collectionsByCourse[$col['courseId']][] = $col;
}

// Build unified items query (Videos + Files + Quizzes)
$items = [];

// 1. Videos
if ($fType === 'ALL' || $fType === 'VIDEO') {
    $vSql = "SELECT v.id, v.title, v.description, v.link, v.published, v.createdAt, 'VIDEO' as itemType, 
                    c.title as courseTitle, c.id as courseId, col.title as collectionTitle, col.id as collectionId
             FROM Video v
             JOIN Course c ON v.courseId = c.id
             JOIN Collection col ON v.collectionId = col.id
             WHERE 1=1";
    $vParams = [];
    if ($fCourse !== 'ALL') { $vSql .= " AND v.courseId = ?"; $vParams[] = $fCourse; }
    if ($fStatus === 'PUBLISHED') { $vSql .= " AND v.published = 1"; }
    elseif ($fStatus === 'UNPUBLISHED') { $vSql .= " AND v.published = 0"; }
    if ($fSearch) { $vSql .= " AND (v.title LIKE ? OR col.title LIKE ?)"; $vParams[] = "%$fSearch%"; $vParams[] = "%$fSearch%"; }
    
    $stmt = $db->prepare($vSql);
    $stmt->execute($vParams);
    foreach ($stmt->fetchAll() as $row) { $items[] = $row; }
}

// 2. Files
if ($fType === 'ALL' || $fType === 'FILE') {
    $fSql = "SELECT f.id, f.title, f.description, f.link, f.published, f.createdAt, 'FILE' as itemType, 
                    c.title as courseTitle, c.id as courseId, col.title as collectionTitle, col.id as collectionId
             FROM File f
             JOIN Course c ON f.courseId = c.id
             JOIN Collection col ON f.collectionId = col.id
             WHERE 1=1";
    $fParams = [];
    if ($fCourse !== 'ALL') { $fSql .= " AND f.courseId = ?"; $fParams[] = $fCourse; }
    if ($fStatus === 'PUBLISHED') { $fSql .= " AND f.published = 1"; }
    elseif ($fStatus === 'UNPUBLISHED') { $fSql .= " AND f.published = 0"; }
    if ($fSearch) { $fSql .= " AND (f.title LIKE ? OR col.title LIKE ?)"; $fParams[] = "%$fSearch%"; $fParams[] = "%$fSearch%"; }
    
    $stmt = $db->prepare($fSql);
    $stmt->execute($fParams);
    foreach ($stmt->fetchAll() as $row) { $items[] = $row; }
}

// 3. Quizzes
if ($fType === 'ALL' || $fType === 'QUIZ') {
    $qSql = "SELECT q.id, q.title, q.description, '' as link, q.published, q.createdAt, 'QUIZ' as itemType, 
                    c.title as courseTitle, c.id as courseId, col.title as collectionTitle, col.id as collectionId
             FROM Quiz q
             JOIN Course c ON q.courseId = c.id
             JOIN Collection col ON q.collectionId = col.id
             WHERE 1=1";
    $qParams = [];
    if ($fCourse !== 'ALL') { $qSql .= " AND q.courseId = ?"; $qParams[] = $fCourse; }
    if ($fStatus === 'PUBLISHED') { $qSql .= " AND q.published = 1"; }
    elseif ($fStatus === 'UNPUBLISHED') { $qSql .= " AND q.published = 0"; }
    if ($fSearch) { $qSql .= " AND (q.title LIKE ? OR col.title LIKE ?)"; $qParams[] = "%$fSearch%"; $qParams[] = "%$fSearch%"; }
    
    $stmt = $db->prepare($qSql);
    $stmt->execute($qParams);
    foreach ($stmt->fetchAll() as $row) { $items[] = $row; }
}

// Sort items by createdAt descending
usort($items, function($a, $b) {
    return strtotime($b['createdAt']) <=> strtotime($a['createdAt']);
});

$adminTitle = "المحتوى - منصة أ. محمد يوسف";
$activeAdminNav = "content";
require_once __DIR__ . '/header.php';
?>

<div class="space-y-6">
    <!-- Header Title & Add Button -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-[#0d3753] tracking-tight">المحتوى</h1>
            <p class="text-xs text-slate-500 font-medium mt-1">إدارة جميع الفيديوهات، الملفات والامتحانات التفاعلية عبر الكورسات (<?= count($items) ?> عنصر)</p>
        </div>
        <button onclick="openAddContentModal()" class="btn-add-action inline-flex items-center gap-2 px-5 py-2.5 bg-[#0f766e] hover:bg-[#0d3753] text-white font-bold text-xs rounded-xl shadow transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>إضافة محتوى جديد</span>
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-3.5 rounded-xl text-xs font-bold <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Filter & Search Bar Matching Original Next.js Design -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
        <!-- Search Input -->
        <div class="relative md:col-span-2">
            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <input type="text" id="searchInput" value="<?= htmlspecialchars($fSearch) ?>" placeholder="ابحث عن العنوان، الكورس أو المجموعة..." 
                   onkeydown="if(event.key==='Enter') applyFilters()"
                   class="w-full pr-9 pl-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold outline-none focus:bg-white focus:border-[#0f766e]"/>
        </div>

        <!-- Filter by Type -->
        <div>
            <select id="filterType" onchange="applyFilters()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 outline-none focus:bg-white focus:border-[#0f766e]">
                <option value="ALL" <?= $fType === 'ALL' ? 'selected' : '' ?>>جميع الأنواع</option>
                <option value="VIDEO" <?= $fType === 'VIDEO' ? 'selected' : '' ?>>🎬 فيديو</option>
                <option value="FILE" <?= $fType === 'FILE' ? 'selected' : '' ?>>📄 ملف / مستند</option>
                <option value="QUIZ" <?= $fType === 'QUIZ' ? 'selected' : '' ?>>❓ امتحان تفاعلي</option>
            </select>
        </div>

        <!-- Filter by Course -->
        <div>
            <select id="filterCourse" onchange="applyFilters()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 outline-none focus:bg-white focus:border-[#0f766e]">
                <option value="ALL" <?= $fCourse === 'ALL' ? 'selected' : '' ?>>كل الكورسات</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= htmlspecialchars($c['id']) ?>" <?= $fCourse === $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filter by Status -->
        <div>
            <select id="filterStatus" onchange="applyFilters()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 outline-none focus:bg-white focus:border-[#0f766e]">
                <option value="ALL" <?= $fStatus === 'ALL' ? 'selected' : '' ?>>كل الحالات</option>
                <option value="PUBLISHED" <?= $fStatus === 'PUBLISHED' ? 'selected' : '' ?>>منشور</option>
                <option value="UNPUBLISHED" <?= $fStatus === 'UNPUBLISHED' ? 'selected' : '' ?>>غير منشور</option>
            </select>
        </div>
    </div>

    <!-- Content Items Table -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
        <table class="w-full text-right text-xs">
            <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200">
                <tr>
                    <th class="p-4">النوع</th>
                    <th class="p-4">العنوان</th>
                    <th class="p-4">الكورس</th>
                    <th class="p-4">المجموعة / الباب</th>
                    <th class="p-4">الحالة</th>
                    <th class="p-4">تاريخ الإنشاء</th>
                    <th class="p-4 text-center">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-400 font-bold">
                            لا توجد عناصر تطابق معايير البحث والفلترة.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Type Badge -->
                            <td class="p-4">
                                <?php if ($it['itemType'] === 'VIDEO'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-red-700 rounded-lg font-bold text-[11px]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                                        فيديو
                                    </span>
                                <?php elseif ($it['itemType'] === 'FILE'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg font-bold text-[11px]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        ملف
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 rounded-lg font-bold text-[11px]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        امتحان
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Title -->
                            <td class="p-4 font-bold text-[#0d3753] max-w-xs truncate">
                                <?= htmlspecialchars($it['title']) ?>
                            </td>

                            <!-- Course -->
                            <td class="p-4 font-semibold text-slate-600">
                                <?= htmlspecialchars($it['courseTitle']) ?>
                            </td>

                            <!-- Collection -->
                            <td class="p-4 font-semibold text-slate-500">
                                <?= htmlspecialchars($it['collectionTitle']) ?>
                            </td>

                            <!-- Status -->
                            <td class="p-4">
                                <?php if ($it['published']): ?>
                                    <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full font-bold text-[10px]">منشور</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 rounded-full font-bold text-[10px]">مسودة</span>
                                <?php endif; ?>
                            </td>

                            <!-- Created Date -->
                            <td class="p-4 text-slate-400 font-mono text-[11px]" dir="ltr">
                                <?= date('Y-m-d', strtotime($it['createdAt'])) ?>
                            </td>

                            <!-- Actions -->
                            <td class="p-4 text-center">
                                <div class="inline-flex items-center gap-2">
                                    <?php if ($it['link']): ?>
                                        <a href="<?= htmlspecialchars($it['link']) ?>" target="_blank" title="معاينة الرابط" class="p-1.5 text-slate-400 hover:text-[#0f766e] transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                        </a>
                                    <?php endif; ?>

                                    <a href="content.php?action=delete&type=<?= strtolower($it['itemType']) ?>&id=<?= urlencode($it['id']) ?>" 
                                       onclick="return confirm('هل أنت متأكد من رغبتك في حذف هذا المحتوى؟')" 
                                       title="حذف" class="p-1.5 text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ======================================================== -->
<!-- Unified Add / Edit Content Modal with Backdrop Click Close -->
<!-- ======================================================== -->
<div id="contentModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto" onclick="if(event.target === this) closeModal('contentModal')">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 space-y-6 shadow-2xl relative my-8 max-h-[90vh] overflow-y-auto">
        <!-- Close Button -->
        <button type="button" onclick="closeModal('contentModal')" class="absolute left-6 top-6 text-slate-400 hover:text-slate-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>

        <div>
            <h3 class="text-xl font-black text-[#0d3753]" id="modalTitle">إضافة محتوى جديد إلى الكورس</h3>
            <p class="text-xs text-slate-400 mt-0.5">حدد الكورس والمجموعة ونوع المحتوى (فيديو، ملف، أو امتحان تفاعلي)</p>
        </div>

        <form method="POST" action="content.php" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="save_content" value="1"/>
            <input type="hidden" name="edit_id" id="formEditId" value=""/>

            <!-- Card 1: Metadata & Location -->
            <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/60 space-y-4">
                <h4 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-[#0f766e]"></span>
                    البيانات الوصفية والموقع
                </h4>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الكورس *</label>
                        <select name="courseId" id="modalCourseSelect" onchange="onCourseChange()" required class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold outline-none focus:border-[#0f766e]">
                            <option value="">اختر الكورس...</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الحصة / الباب / المجموعة *</label>
                        <select name="collectionId" id="modalCollectionSelect" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold outline-none focus:border-[#0f766e]">
                            <option value="">اختر الكورس أولاً</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">نوع المحتوى *</label>
                    <select name="content_type" id="modalContentType" onchange="onContentTypeChange()" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold outline-none focus:border-[#0f766e]">
                        <option value="VIDEO">🎬 فيديو / حصة مسجلة</option>
                        <option value="FILE">📄 ملف / مذكرة PDF</option>
                        <option value="QUIZ">❓ امتحان تفاعلي</option>
                    </select>
                </div>
            </div>

            <!-- Card 2: Content Details -->
            <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/60 space-y-4">
                <h4 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-[#c79a32]"></span>
                    تفاصيل المحتوى
                </h4>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">عنوان المحتوى *</label>
                    <input type="text" name="title" id="contentTitle" required placeholder="مثال: شرح باب النعت والمؤكد" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold outline-none focus:border-[#0f766e]"/>
                </div>

                <!-- Video Fields -->
                <div id="videoFieldsSection" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">مصدر الفيديو</label>
                        <input type="hidden" name="video_source" id="videoSourceInput" value="YOUTUBE"/>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" id="btnSourceYouTube" onclick="setVideoSource('YOUTUBE')" class="flex flex-col items-center justify-center p-3 rounded-xl border-2 border-[#0f766e] bg-[#0f766e]/5 transition text-center gap-1.5">
                                <svg class="h-6 w-6 text-red-600" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.498 6.163a3.003 3.003 0 0 0-2.11-2.108C19.53 3.53 12 3.53 12 3.53s-7.53 0-9.388.525A3.003 3.003 0 0 0 .502 6.163C0 8.02 0 12 0 12s0 3.98.502 5.837a3.003 3.003 0 0 0 2.11 2.108C4.47 20.47 12 20.47 12 20.47s7.53 0 9.388-.525a3.003 3.003 0 0 0 2.11-2.108C24 15.98 24 12 24 12s0-3.98-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                                <span class="font-bold text-xs text-slate-800">يوتيوب (YouTube)</span>
                            </button>

                            <button type="button" id="btnSourceDailymotion" onclick="setVideoSource('DAILYMOTION')" class="flex flex-col items-center justify-center p-3 rounded-xl border-2 border-slate-200 bg-white hover:bg-slate-50 transition text-center gap-1.5">
                                <span class="w-6 h-6 rounded-md bg-[#0066dc] text-white flex items-center justify-center font-black text-xs">d</span>
                                <span class="font-bold text-xs text-slate-800">ديلي موشن (Dailymotion)</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1" id="videoLinkLabel">رابط فيديو يوتيوب *</label>
                        <input type="text" name="video_link" id="videoLinkInput" placeholder="https://www.youtube.com/watch?v=..." dir="ltr" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold outline-none focus:border-[#0f766e]"/>
                    </div>
                </div>

                <!-- File Fields -->
                <div id="fileFieldsSection" class="hidden space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رابط الملف المباشر (أو رابط خارجي)</label>
                        <input type="text" name="file_link" id="fileLinkInput" placeholder="https://example.com/document.pdf" dir="ltr" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold outline-none focus:border-[#0f766e]"/>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">أو ارفع ملف PDF / Word من جهازك</label>
                        <input type="file" name="file_upload" accept=".pdf,.doc,.docx,.zip,.png,.jpg" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold"/>
                    </div>
                </div>

                <!-- Quiz Fields -->
                <div id="quizFieldsSection" class="hidden space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">مدة الامتحان (بالدقائق) *</label>
                        <input type="number" name="quiz_time" value="30" min="1" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold outline-none focus:border-[#0f766e]"/>
                    </div>

                    <!-- Questions Builder -->
                    <div class="border border-slate-200 rounded-2xl p-4 bg-white space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h5 class="text-xs font-black text-[#0d3753]">منشئ أسئلة الامتحان</h5>
                            <button type="button" onclick="addQuestionItem()" class="btn-add-action px-3 py-1.5 bg-[#0f766e] hover:bg-[#0d3753] text-white font-bold text-xs rounded-lg transition flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                <span>إضافة سؤال</span>
                            </button>
                        </div>

                        <div id="questionsContainer" class="space-y-4">
                            <!-- Injected dynamically via JS -->
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الوصف (اختياري)</label>
                    <textarea name="description" id="contentDescription" rows="2" placeholder="وصف محتوى الحصة أو تفاصيل الملف..." class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold outline-none focus:border-[#0f766e]"></textarea>
                </div>

                <!-- Publish Immediately -->
                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="published" id="publishedCheckbox" value="1" checked class="w-4 h-4 accent-[#0f766e] rounded"/>
                    <label for="publishedCheckbox" class="text-xs font-bold text-slate-700 cursor-pointer">انشر فوراً (مرئي للطلاب على المنصة)</label>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('contentModal')" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition">
                    إلغاء
                </button>
                <button type="submit" class="btn-add-action px-6 py-2.5 bg-[#0f766e] hover:bg-[#0d3753] text-white font-black text-xs rounded-xl shadow transition">
                    حفظ المحتوى
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- Dynamic Data & Cascading Logic Scripts -->
<!-- ======================================================== -->
<script>
const collectionsData = <?= json_encode($collectionsByCourse, JSON_UNESCAPED_UNICODE) ?>;
let qCount = 0;

function applyFilters() {
    const s = document.getElementById('searchInput').value.trim();
    const t = document.getElementById('filterType').value;
    const c = document.getElementById('filterCourse').value;
    const st = document.getElementById('filterStatus').value;

    const params = new URLSearchParams();
    if (s) params.set('search', s);
    if (t !== 'ALL') params.set('filterType', t);
    if (c !== 'ALL') params.set('filterCourse', c);
    if (st !== 'ALL') params.set('filterStatus', st);

    window.location.href = 'content.php?' + params.toString();
}

function openAddContentModal() {
    document.getElementById('modalTitle').textContent = 'إضافة محتوى جديد إلى الكورس';
    document.getElementById('formEditId').value = '';
    document.getElementById('contentTitle').value = '';
    document.getElementById('contentDescription').value = '';
    document.getElementById('videoLinkInput').value = '';
    document.getElementById('fileLinkInput').value = '';
    document.getElementById('publishedCheckbox').checked = true;
    document.getElementById('modalContentType').value = 'VIDEO';
    onContentTypeChange();
    setVideoSource('YOUTUBE');

    // Reset questions
    document.getElementById('questionsContainer').innerHTML = '';
    qCount = 0;
    addQuestionItem();

    document.getElementById('contentModal').classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function onCourseChange() {
    const cId = document.getElementById('modalCourseSelect').value;
    const colSelect = document.getElementById('modalCollectionSelect');
    colSelect.innerHTML = '';

    if (!cId) {
        colSelect.innerHTML = '<option value="">اختر الكورس أولاً</option>';
        return;
    }

    if (!collectionsData[cId] || collectionsData[cId].length === 0) {
        colSelect.innerHTML = '<option value="__AUTO__">✨ حصة / باب افتراضي تلقائي للكورس</option>';
        return;
    }

    colSelect.innerHTML = '<option value="">اختر الحصة / الباب...</option>';
    collectionsData[cId].forEach(col => {
        const opt = document.createElement('option');
        opt.value = col.id;
        opt.textContent = 'حصة / باب: ' + col.title + (col.price ? ' (' + col.price + ' ج.م)' : '');
        colSelect.appendChild(opt);
    });
    colSelect.innerHTML += '<option value="__AUTO__">✨ إنشاء حصة / باب جديد تلقائياً</option>';
}

function onContentTypeChange() {
    const t = document.getElementById('modalContentType').value;
    document.getElementById('videoFieldsSection').classList.toggle('hidden', t !== 'VIDEO');
    document.getElementById('fileFieldsSection').classList.toggle('hidden', t !== 'FILE');
    document.getElementById('quizFieldsSection').classList.toggle('hidden', t !== 'QUIZ');
}

function setVideoSource(src) {
    document.getElementById('videoSourceInput').value = src;
    const btnYt = document.getElementById('btnSourceYouTube');
    const btnDm = document.getElementById('btnSourceDailymotion');
    const lbl = document.getElementById('videoLinkLabel');
    const inp = document.getElementById('videoLinkInput');

    if (src === 'YOUTUBE') {
        btnYt.className = 'flex flex-col items-center justify-center p-3 rounded-xl border-2 border-[#0f766e] bg-[#0f766e]/5 transition text-center gap-1.5';
        btnDm.className = 'flex flex-col items-center justify-center p-3 rounded-xl border-2 border-slate-200 bg-white hover:bg-slate-50 transition text-center gap-1.5';
        lbl.textContent = 'رابط فيديو يوتيوب (Full URL) *';
        inp.placeholder = 'https://www.youtube.com/watch?v=...';
    } else {
        btnDm.className = 'flex flex-col items-center justify-center p-3 rounded-xl border-2 border-[#0f766e] bg-[#0f766e]/5 transition text-center gap-1.5';
        btnYt.className = 'flex flex-col items-center justify-center p-3 rounded-xl border-2 border-slate-200 bg-white hover:bg-slate-50 transition text-center gap-1.5';
        lbl.textContent = 'معرف فيديو ديلي موشن أو الرابط *';
        inp.placeholder = 'e.g. x8n5g9b أو الرابط الكامل';
    }
}

function isRadioChecked(qIdx, optIdx) {
    const r = document.querySelector(`input[name="questions[${qIdx}][correct_index]"][value="${optIdx}"]`);
    return r && r.checked;
}

function syncCorrectRadio(qIdx, optIdx) {
    const inp = document.getElementById(`q_${qIdx}_opt_${optIdx}`);
    const rightInp = document.getElementById(`q_${qIdx}_right`);
    if (inp && rightInp) {
        rightInp.value = inp.value.trim();
    }
}

function addQuestionItem() {
    const idx = qCount++;
    const container = document.getElementById('questionsContainer');
    const qDiv = document.createElement('div');
    qDiv.className = 'border border-slate-200 rounded-2xl p-4 sm:p-5 bg-slate-50/70 space-y-3.5 relative shadow-sm';
    qDiv.id = `q_card_${idx}`;

    qDiv.innerHTML = `
        <div class="flex items-center justify-between border-b border-slate-200 pb-2.5">
            <span class="font-black text-xs text-[#0d3753] bg-teal-50 text-teal-800 border border-teal-200 px-3 py-1 rounded-lg">السؤال #${idx + 1}</span>
            <button type="button" onclick="document.getElementById('q_card_${idx}').remove()" class="text-red-500 hover:text-red-700 p-1 rounded-lg hover:bg-red-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
            </button>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">نص السؤال *</label>
            <input type="text" name="questions[${idx}][head]" required placeholder="مثال: ما هو إعراب كلمة زيد في الجملة..." class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold outline-none focus:border-[#0f766e]"/>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">رابط صورة توضيحية للسؤال (اختياري)</label>
            <input type="text" name="questions[${idx}][image]" placeholder="https://... أو رابط صورة" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold outline-none focus:border-[#0f766e]"/>
        </div>

        <div class="flex items-center gap-4 py-1 text-xs font-bold text-slate-700">
            <span>نوع السؤال:</span>
            <label class="inline-flex items-center gap-1.5 cursor-pointer bg-white px-3 py-1.5 rounded-lg border border-slate-200">
                <input type="radio" name="questions[${idx}][type]" value="MCQ" checked onchange="toggleQuestionType(${idx}, 'MCQ')" class="accent-[#0f766e]"/>
                اختياري متعدد (MCQ)
            </label>
            <label class="inline-flex items-center gap-1.5 cursor-pointer bg-white px-3 py-1.5 rounded-lg border border-slate-200">
                <input type="radio" name="questions[${idx}][type]" value="WRITTEN" onchange="toggleQuestionType(${idx}, 'WRITTEN')" class="accent-[#0f766e]"/>
                سؤال مقالي (Written)
            </label>
        </div>

        <!-- Options Container for MCQ -->
        <div id="q_options_${idx}" class="space-y-2.5 pt-1">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-bold text-slate-700">الخيارات الأربعة (حدد الإجابة الصحيحة بالزر المجاور للخيار)</label>
                <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">🔘 علم على الإجابة الصحيحة</span>
            </div>
            
            <div id="opts_list_${idx}" class="space-y-2">
                <!-- Option A -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-white p-2.5 rounded-xl border border-slate-200">
                    <div class="flex items-center gap-2 flex-1">
                        <span class="text-xs font-black text-slate-500 w-5 text-center">أ</span>
                        <input type="text" name="questions[${idx}][options][0]" id="q_${idx}_opt_0" oninput="if(isRadioChecked(${idx}, 0)) syncCorrectRadio(${idx}, 0)" placeholder="نص الخيار (أ)" class="flex-1 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold outline-none focus:border-[#0f766e] focus:bg-white"/>
                    </div>
                    <label class="flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-emerald-50 hover:border-emerald-500 cursor-pointer text-xs font-bold text-slate-700 transition">
                        <input type="radio" name="questions[${idx}][correct_index]" value="0" checked onchange="syncCorrectRadio(${idx}, 0)" class="accent-emerald-600 w-4 h-4"/>
                        <span>الإجابة الصحيحة</span>
                    </label>
                </div>

                <!-- Option B -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-white p-2.5 rounded-xl border border-slate-200">
                    <div class="flex items-center gap-2 flex-1">
                        <span class="text-xs font-black text-slate-500 w-5 text-center">ب</span>
                        <input type="text" name="questions[${idx}][options][1]" id="q_${idx}_opt_1" oninput="if(isRadioChecked(${idx}, 1)) syncCorrectRadio(${idx}, 1)" placeholder="نص الخيار (ب)" class="flex-1 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold outline-none focus:border-[#0f766e] focus:bg-white"/>
                    </div>
                    <label class="flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-emerald-50 hover:border-emerald-500 cursor-pointer text-xs font-bold text-slate-700 transition">
                        <input type="radio" name="questions[${idx}][correct_index]" value="1" onchange="syncCorrectRadio(${idx}, 1)" class="accent-emerald-600 w-4 h-4"/>
                        <span>الإجابة الصحيحة</span>
                    </label>
                </div>

                <!-- Option C -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-white p-2.5 rounded-xl border border-slate-200">
                    <div class="flex items-center gap-2 flex-1">
                        <span class="text-xs font-black text-slate-500 w-5 text-center">ج</span>
                        <input type="text" name="questions[${idx}][options][2]" id="q_${idx}_opt_2" oninput="if(isRadioChecked(${idx}, 2)) syncCorrectRadio(${idx}, 2)" placeholder="نص الخيار (ج)" class="flex-1 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold outline-none focus:border-[#0f766e] focus:bg-white"/>
                    </div>
                    <label class="flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-emerald-50 hover:border-emerald-500 cursor-pointer text-xs font-bold text-slate-700 transition">
                        <input type="radio" name="questions[${idx}][correct_index]" value="2" onchange="syncCorrectRadio(${idx}, 2)" class="accent-emerald-600 w-4 h-4"/>
                        <span>الإجابة الصحيحة</span>
                    </label>
                </div>

                <!-- Option D -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-white p-2.5 rounded-xl border border-slate-200">
                    <div class="flex items-center gap-2 flex-1">
                        <span class="text-xs font-black text-slate-500 w-5 text-center">د</span>
                        <input type="text" name="questions[${idx}][options][3]" id="q_${idx}_opt_3" oninput="if(isRadioChecked(${idx}, 3)) syncCorrectRadio(${idx}, 3)" placeholder="نص الخيار (د)" class="flex-1 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold outline-none focus:border-[#0f766e] focus:bg-white"/>
                    </div>
                    <label class="flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-emerald-50 hover:border-emerald-500 cursor-pointer text-xs font-bold text-slate-700 transition">
                        <input type="radio" name="questions[${idx}][correct_index]" value="3" onchange="syncCorrectRadio(${idx}, 3)" class="accent-emerald-600 w-4 h-4"/>
                        <span>الإجابة الصحيحة</span>
                    </label>
                </div>
            </div>

            <!-- Hidden right input fallback -->
            <input type="hidden" name="questions[${idx}][right]" id="q_${idx}_right" value=""/>
        </div>

        <!-- Written Answer Container -->
        <div id="q_written_${idx}" class="hidden pt-1">
            <label class="block text-xs font-bold text-slate-700 mb-1">الإجابة النموذجية المكتوبة *</label>
            <input type="text" name="questions[${idx}][right_written]" placeholder="اكتب الإجابة النموذجية هنا..." class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold outline-none focus:border-[#0f766e]"/>
        </div>
    `;

    container.appendChild(qDiv);
}

function toggleQuestionType(idx, type) {
    const optDiv = document.getElementById(`q_options_${idx}`);
    const wrDiv = document.getElementById(`q_written_${idx}`);
    if (type === 'MCQ') {
        optDiv.classList.remove('hidden');
        wrDiv.classList.add('hidden');
    } else {
        optDiv.classList.add('hidden');
        wrDiv.classList.remove('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
