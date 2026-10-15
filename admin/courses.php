<?php
require_once __DIR__ . '/../config/helpers.php';
$adminUser = requireAdmin();
requireAdminPermission('courses');
$db = getDB();

// Handle Flash Message
$msg = $_SESSION['course_msg'] ?? '';
$msgType = $_SESSION['course_msg_type'] ?? '';
unset($_SESSION['course_msg'], $_SESSION['course_msg_type']);

// 1. Handle Add / Edit Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_course']) || isset($_POST['edit_course']))) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $specialization = $_POST['specialization'] ?? null;
    if ($specialization === '') $specialization = null;
    $sectionId = !empty($_POST['sectionId']) ? $_POST['sectionId'] : null;
    $thumbnail = trim($_POST['thumbnail'] ?? '');
    $isEdit = isset($_POST['edit_course']);
    $courseId = trim($_POST['course_id'] ?? '');

    // Handle direct thumbnail upload
    if (!empty($_FILES['thumbnail_file']['name'])) {
        $upDir = __DIR__ . '/../public/uploads/';
        if (!is_dir($upDir)) mkdir($upDir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['thumbnail_file']['name'], PATHINFO_EXTENSION));
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['thumbnail_file']['name'], PATHINFO_FILENAME));
        $fName = $cleanName . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $upDir . $fName)) {
            $thumbnail = '/api/uploads/' . $fName;
            $apiUpDir = __DIR__ . '/../api/uploads/';
            if (!is_dir($apiUpDir)) mkdir($apiUpDir, 0777, true);
            @copy($upDir . $fName, $apiUpDir . $fName);

            // Also register in GalleryImage table (#8)
            try {
                $gId = generateId(5);
                $db->prepare("INSERT INTO GalleryImage (id, url, title, createdAt) VALUES (?, ?, ?, NOW())")
                   ->execute([$gId, $thumbnail, $title ?: $fName]);
            } catch (Exception $e) {}
        }
    }

    if (empty($title)) {
        $_SESSION['course_msg'] = 'عنوان الكورس مطلوب';
        $_SESSION['course_msg_type'] = 'error';
    } else {
        if ($isEdit && $courseId) {
            $upd = $db->prepare("
                UPDATE Course 
                SET title = ?, description = ?, price = ?, specialization = ?, sectionId = ?, thumbnail = IF(? != '', ?, thumbnail), updatedAt = NOW() 
                WHERE id = ?
            ");
            $upd->execute([$title, $description, $price, $specialization, $sectionId, $thumbnail, $thumbnail, $courseId]);
            $_SESSION['course_msg'] = 'تم تعديل بيانات الكورس بنجاح!';
        } else {
            $cId = generateId(5);
            $order = (int)$db->query("SELECT COALESCE(MAX(`order`), 0) + 1 FROM Course")->fetchColumn();
            
            $ins = $db->prepare("
                INSERT INTO Course (id, title, description, price, specialization, sectionId, thumbnail, `order`, published, featured, createdAt, updatedAt) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())
            ");
            $ins->execute([$cId, $title, $description, $price, $specialization, $sectionId, $thumbnail, $order]);
            $_SESSION['course_msg'] = 'تم إنشاء الكورس بنجاح!';
        }
        $_SESSION['course_msg_type'] = 'success';
    }
    header("Location: /admin/courses.php");
    exit;
}

// 2. Handle Delete Course
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['courseId'])) {
    $delId = $_GET['courseId'];
    try {
        $db->prepare("DELETE FROM Course WHERE id = ?")->execute([$delId]);
        $_SESSION['course_msg'] = 'تم حذف الكورس بنجاح';
        $_SESSION['course_msg_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['course_msg'] = 'فشل حذف الكورس: ' . $e->getMessage();
        $_SESSION['course_msg_type'] = 'error';
    }
    header("Location: /admin/courses.php");
    exit;
}

// 3. Handle Add Lesson (Collection) (#10 & #16)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lesson'])) {
    $cId = trim($_POST['lesson_course_id'] ?? '');
    $lTitle = trim($_POST['lesson_title'] ?? '');
    $lPrice = (float)($_POST['lesson_price'] ?? 70.0);
    $lOrder = (int)($_POST['lesson_order'] ?? 1);

    if (empty($cId) || empty($lTitle)) {
        $_SESSION['course_msg'] = 'يرجى إدخال عنوان الحصة / الدرس';
        $_SESSION['course_msg_type'] = 'error';
    } else {
        $colId = generateId(5);
        $insCol = $db->prepare("INSERT INTO Collection (id, title, price, courseId, `order`, updatedAt) VALUES (?, ?, ?, ?, ?, NOW())");
        $insCol->execute([$colId, $lTitle, $lPrice, $cId, $lOrder]);
        $_SESSION['course_msg'] = "تم إضافة الحصة ($lTitle) بسعر " . number_format($lPrice, 0) . " ج.م بنجاح!";
        $_SESSION['course_msg_type'] = 'success';
    }
    header("Location: /admin/courses.php");
    exit;
}

// 4. Handle Edit Lesson (Collection) (#10 & #16)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_lesson'])) {
    $colId = trim($_POST['lesson_id'] ?? '');
    $lTitle = trim($_POST['lesson_title'] ?? '');
    $lPrice = (float)($_POST['lesson_price'] ?? 70.0);
    $lOrder = (int)($_POST['lesson_order'] ?? 1);

    if (empty($colId) || empty($lTitle)) {
        $_SESSION['course_msg'] = 'بيانات الحصة غير مكتملة';
        $_SESSION['course_msg_type'] = 'error';
    } else {
        $updCol = $db->prepare("UPDATE Collection SET title = ?, price = ?, `order` = ?, updatedAt = NOW() WHERE id = ?");
        $updCol->execute([$lTitle, $lPrice, $lOrder, $colId]);
        $_SESSION['course_msg'] = "تم تعديل الحصة وتحديث سعرها إلى " . number_format($lPrice, 0) . " ج.م بنجاح!";
        $_SESSION['course_msg_type'] = 'success';
    }
    header("Location: /admin/courses.php");
    exit;
}

// 5. Handle Delete Lesson (Collection)
if (isset($_GET['action']) && $_GET['action'] === 'delete_lesson' && isset($_GET['lessonId'])) {
    $delLessonId = $_GET['lessonId'];
    try {
        $db->prepare("DELETE FROM Collection WHERE id = ?")->execute([$delLessonId]);
        $_SESSION['course_msg'] = 'تم حذف الحصة بنجاح';
        $_SESSION['course_msg_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['course_msg'] = 'فشل حذف الحصة: ' . $e->getMessage();
        $_SESSION['course_msg_type'] = 'error';
    }
    header("Location: /admin/courses.php");
    exit;
}

$adminTitle = "إدارة الكورسات والمحاضرات - منصة أ. محمد يوسف";
$activeAdminNav = "courses";
require_once __DIR__ . '/header.php';

// Fetch Courses with Stats
$courses = $db->query("
    SELECT c.*, s.title as sectionTitle, y.name as yearName,
           (SELECT COUNT(*) FROM Collection col WHERE col.courseId = c.id) as collectionsCount,
           (SELECT COUNT(*) FROM Video v WHERE v.courseId = c.id) as videosCount
    FROM Course c
    LEFT JOIN Section s ON c.sectionId = s.id
    LEFT JOIN Year y ON s.yearId = y.id
    ORDER BY c.`order` ASC, c.createdAt DESC
")->fetchAll();

// Fetch Years and Sections (#7)
$years = $db->query("SELECT * FROM Year ORDER BY `order` ASC")->fetchAll();
$sections = $db->query("SELECT s.*, y.name as yearName FROM Section s LEFT JOIN Year y ON s.yearId = y.id ORDER BY y.`order` ASC, s.`order` ASC")->fetchAll();

// Fetch Gallery Images for Thumbnail Selector (#8)
$galleryImages = $db->query("SELECT * FROM GalleryImage ORDER BY createdAt DESC")->fetchAll();

// Fetch all collections grouped by courseId (#10 & #16)
$rawCollections = $db->query("SELECT * FROM Collection ORDER BY `order` ASC, id ASC")->fetchAll();
$collectionsByCourse = [];
foreach ($rawCollections as $col) {
    $collectionsByCourse[$col['courseId']][] = $col;
}
?>

<!-- Title & Action -->
<div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-black text-[#0d3753] tracking-tight">إدارة الكورسات والمحاضرات والحصص</h2>
        <p class="text-xs text-slate-500 font-medium mt-1">إنشاء وتعديل الكورسات، إضافة الدروس وتحديد سعر كل حصة على حدة، واختيار الصور السابقة (<?= count($courses) ?> كورس)</p>
    </div>
    <button onclick="openAddCourseModal()" class="btn-add-action inline-flex items-center gap-2 px-5 py-2.5 bg-[#0f766e] hover:bg-[#0d3753] text-white font-bold text-xs rounded-xl shadow transition cursor-pointer">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>إضافة كورس جديد</span>
    </button>
</div>

<?php if ($msg): ?>
    <div class="mb-6 p-4 rounded-xl text-xs font-bold <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- Courses Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
    <?php foreach ($courses as $c): ?>
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm flex flex-col justify-between hover:shadow-md transition">
            <!-- Thumbnail Image -->
            <div class="h-48 bg-slate-900 relative overflow-hidden">
                <?php if (!empty($c['thumbnail'])): ?>
                    <img src="<?= htmlspecialchars($c['thumbnail']) ?>" alt="<?= htmlspecialchars($c['title']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full flex flex-col items-center justify-center bg-[#0d3753] text-[#c79a32] p-4 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        <span class="text-xs font-bold text-white/90 mt-2"><?= htmlspecialchars($c['title']) ?></span>
                    </div>
                <?php endif; ?>
                
                <span class="absolute top-3 right-3 bg-[#0d3753]/90 text-white text-[10px] font-bold px-3 py-1 rounded-full shadow backdrop-blur-sm">
                    <?= htmlspecialchars($c['yearName'] ?? 'جميع الصفوف') ?>
                </span>
                <span class="absolute bottom-3 left-3 bg-[#0f766e] text-white text-xs font-black px-3.5 py-1 rounded-full shadow">
                    <?= number_format($c['price'], 2) ?> ج.م
                </span>
            </div>

            <!-- Content Details -->
            <div class="p-5 space-y-4 flex-1 flex flex-col justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900 line-clamp-1"><?= htmlspecialchars($c['title']) ?></h3>
                    <p class="text-xs text-slate-500 line-clamp-2 mt-1"><?= htmlspecialchars($c['description'] ?: 'لا يوجد وصف مختصر للكورس') ?></p>
                    
                    <div class="flex items-center gap-4 text-xs font-bold text-slate-500 pt-3">
                        <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-800 border border-amber-200 px-2.5 py-1 rounded-lg">
                            <i class="fa-solid fa-layer-group text-amber-600"></i> <?= $c['collectionsCount'] ?> حصص ودروس
                        </span>
                        <span class="inline-flex items-center gap-1.5 bg-teal-50 text-teal-800 border border-teal-200 px-2.5 py-1 rounded-lg">
                            <i class="fa-solid fa-video text-teal-600"></i> <?= $c['videosCount'] ?> فيديوهات
                        </span>
                    </div>
                </div>

                <!-- Lessons / Collections Quick Button (#10 & #16) -->
                <div class="pt-2">
                    <button type="button" onclick="openLessonsManager('<?= $c['id'] ?>', '<?= htmlspecialchars(addslashes($c['title'])) ?>')" class="w-full py-2 px-3 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 rounded-xl text-xs font-black transition flex items-center justify-center gap-2 cursor-pointer shadow-sm">
                        <i class="fa-solid fa-folder-plus text-amber-600"></i>
                        <span>إدارة دروس وحصص الكورس وتسعيرها (<?= $c['collectionsCount'] ?>)</span>
                    </button>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <a href="/course.php?id=<?= urlencode($c['id']) ?>" target="_blank" class="text-xs font-bold text-[#0f766e] hover:underline flex items-center gap-1">
                        <span>معاينة الكورس</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="openEditCourseModal(<?= htmlspecialchars(json_encode($c)) ?>)" class="text-xs font-bold text-slate-700 hover:text-[#0f766e] transition flex items-center gap-1 cursor-pointer">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>تعديل</span>
                        </button>
                        <a href="courses.php?action=delete&courseId=<?= urlencode($c['id']) ?>" onclick="return confirm('تحذير: هل أنت متأكد من حذف هذا الكورس وجميع محتوياته نهائياً؟')" class="text-xs font-bold text-red-600 hover:text-red-800 transition flex items-center gap-1">
                            <i class="fa-solid fa-trash-can"></i>
                            <span>حذف</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ======================================================== -->
<!-- MODAL 1: Add / Edit Course Modal -->
<!-- ======================================================== -->
<div id="courseModal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto" onclick="if(event.target === this) closeModal('courseModal')">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 space-y-4 shadow-2xl relative my-8">
        <button type="button" onclick="closeModal('courseModal')" class="absolute left-6 top-6 text-slate-400 hover:text-slate-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>

        <h3 class="text-lg font-black text-[#0d3753]" id="courseModalTitle">إضافة كورس جديد</h3>

        <form method="POST" action="courses.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="add_course" id="isAddCourseFlag" value="1"/>
            <input type="hidden" name="course_id" id="editCourseId" value=""/>
            
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">عنوان الكورس *</label>
                <input type="text" name="title" id="courseTitleInput" required placeholder="مثال: مادة النحو - الشهر الثالث" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">وصف الكورس</label>
                <textarea name="description" id="courseDescInput" rows="2" placeholder="وصف محتويات الكورس..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold outline-none focus:bg-white focus:border-[#0f766e]"></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">سعر الكورس الكامل (ج.م) *</label>
                    <input type="number" step="0.01" name="price" id="coursePriceInput" value="250" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الشعبة / التخصص</label>
                    <select name="specialization" id="courseSpecInput" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]">
                        <option value="">عام (علمي وأدبي)</option>
                        <option value="ELMI">علمي</option>
                        <option value="ADABI">أدبي</option>
                    </select>
                </div>
            </div>
            
            <!-- Grouped Sections by Academic Year (#7) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">القسم الدراسي والمرحلة (يظهر لكافة الصفوف) *</label>
                <select name="sectionId" id="courseSectionInput" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]">
                    <option value="">-- اختر القسم والمرحلة الدراسية --</option>
                    <?php foreach ($years as $yr): 
                        $yrSecs = array_filter($sections, fn($s) => $s['yearId'] === $yr['id']);
                    ?>
                        <optgroup label="🎓 <?= htmlspecialchars($yr['name']) ?>">
                            <?php foreach ($yrSecs as $sec): ?>
                                <option value="<?= htmlspecialchars($sec['id']) ?>">
                                    <?= htmlspecialchars($yr['name'] . ' - ' . $sec['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Thumbnail Selector with Gallery Picker (#8) -->
            <div class="space-y-2 p-3 bg-slate-50 border border-slate-200 rounded-2xl">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-slate-700">صورة الكورس (Thumbnail) *</label>
                    <button type="button" onclick="openGalleryModal()" class="text-[11px] font-bold text-[#0f766e] hover:underline flex items-center gap-1 cursor-pointer">
                        <i class="fa-solid fa-images"></i>
                        <span>اختيار من الصور السابقة (<?= count($galleryImages) ?>)</span>
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Thumbnail Preview -->
                    <div class="w-16 h-16 rounded-xl border border-slate-300 bg-white overflow-hidden shrink-0 flex items-center justify-center" id="thumbPreviewWrapper">
                        <img id="thumbPreviewImg" src="/heroPhotos/heroBg.png" alt="Preview" class="w-full h-full object-cover hidden">
                        <span id="thumbPreviewPlaceholder" class="text-[10px] text-slate-400 font-bold text-center">لا توجد صورة</span>
                    </div>

                    <div class="flex-1 space-y-2">
                        <input type="text" name="thumbnail" id="courseThumbInput" oninput="updateThumbPreview(this.value)" placeholder="رابط الصورة أو اختر من المعرض..." class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold outline-none focus:border-[#0f766e]"/>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">أو ارفع صورة جديدة من جهازك:</label>
                            <input type="file" name="thumbnail_file" accept="image/*" class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px] font-bold outline-none"/>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="pt-2">
                <button type="submit" id="courseSubmitBtn" class="btn-add-action w-full py-3 bg-[#0f766e] hover:bg-[#0d3753] text-white font-black text-xs rounded-xl shadow transition cursor-pointer">
                    حفظ وإنشاء الكورس
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 2: Gallery Picker Modal (#8) -->
<!-- ======================================================== -->
<div id="galleryPickerModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto" onclick="if(event.target === this) closeModal('galleryPickerModal')">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 space-y-4 shadow-2xl relative my-8 max-h-[85vh] flex flex-col justify-between">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-images text-[#0f766e]"></i>
                    معرض الصور السابقة للكورسات والمراحل
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">اضغط على أي صورة لتحديدها مباشرة للكورس</p>
            </div>
            <button type="button" onclick="closeModal('galleryPickerModal')" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="overflow-y-auto pr-1 flex-1 py-2">
            <?php if (!empty($galleryImages)): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    <?php foreach ($galleryImages as $gImg): ?>
                        <div onclick="selectGalleryThumb('<?= htmlspecialchars($gImg['url'], ENT_QUOTES) ?>')" class="group relative aspect-video rounded-xl overflow-hidden border-2 border-slate-200 hover:border-[#0f766e] hover:shadow-md cursor-pointer transition">
                            <img src="<?= htmlspecialchars($gImg['url']) ?>" alt="<?= htmlspecialchars($gImg['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition">
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                <span class="bg-[#0f766e] text-white text-[10px] font-bold px-2 py-1 rounded-md">اختيار هذه الصورة</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-xs text-slate-400 text-center py-10">لا توجد صور سابقة في المعرض. قم برفع صورة جديدة وستُضاف تلقائياً للمعرض.</p>
            <?php endif; ?>
        </div>

        <div class="border-t border-slate-100 pt-3 text-left">
            <button type="button" onclick="closeModal('galleryPickerModal')" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition">
                إغلاق
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 3: Lessons & Collections Manager (#10 & #16) -->
<!-- ======================================================== -->
<div id="lessonsManagerModal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto" onclick="if(event.target === this) closeModal('lessonsManagerModal')">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 space-y-6 shadow-2xl relative my-8 max-h-[90vh] overflow-y-auto">
        <button type="button" onclick="closeModal('lessonsManagerModal')" class="absolute left-6 top-6 text-slate-400 hover:text-slate-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>

        <div class="border-b border-slate-100 pb-3">
            <h3 class="text-lg font-black text-[#0d3753] flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-amber-600"></i>
                <span>إدارة الحصص والدروس: </span>
                <span id="lessonsManagerCourseTitle" class="text-[#0f766e]"></span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">يمكنك إضافة حصص منفردة، تعديل أسماء الدروس وتحديد سعر كل حصة على حدة بدقة</p>
        </div>

        <!-- Add New Lesson Form -->
        <form method="POST" action="courses.php" class="p-4 bg-amber-50/70 border border-amber-200 rounded-2xl space-y-3">
            <input type="hidden" name="add_lesson" value="1"/>
            <input type="hidden" name="lesson_course_id" id="managerCourseId" value=""/>

            <div class="text-xs font-black text-amber-900 flex items-center gap-1.5">
                <i class="fa-solid fa-plus-circle text-amber-600"></i>
                <span>إضافة حصة / درس جديد للكورس:</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                <div class="sm:col-span-6">
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">عنوان الحصة / الدرس *</label>
                    <input type="text" name="lesson_title" required placeholder="مثال: الحصة الأولى - همزة الوصل" class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-bold outline-none focus:border-amber-600"/>
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">سعر الحصة (ج.م) *</label>
                    <input type="number" step="0.5" name="lesson_price" value="70" required class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-bold outline-none focus:border-amber-600"/>
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">ترتيب الحصة</label>
                    <input type="number" name="lesson_order" value="1" class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-bold outline-none focus:border-amber-600"/>
                </div>
            </div>

            <div class="flex justify-end pt-1">
                <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow transition cursor-pointer flex items-center gap-1.5">
                    <i class="fa-solid fa-check"></i>
                    <span>حفظ وإضافة الحصة</span>
                </button>
            </div>
        </form>

        <!-- Existing Lessons List -->
        <div>
            <h4 class="text-xs font-black text-slate-800 mb-3 flex items-center justify-between">
                <span>الحصص والدروس الحالية في هذا الكورس:</span>
                <span class="text-slate-400 font-mono text-[11px]" id="lessonsCountBadge"></span>
            </h4>
            <div id="lessonsListContainer" class="space-y-3">
                <!-- Dynamically rendered by JS -->
            </div>
        </div>
    </div>
</div>

<script>
const collectionsByCourse = <?= json_encode($collectionsByCourse, JSON_UNESCAPED_UNICODE) ?>;

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function updateThumbPreview(url) {
    const img = document.getElementById('thumbPreviewImg');
    const placeholder = document.getElementById('thumbPreviewPlaceholder');
    if (url && url.trim() !== '') {
        img.src = url;
        img.classList.remove('hidden');
        placeholder.classList.add('hidden');
    } else {
        img.classList.add('hidden');
        placeholder.classList.remove('hidden');
    }
}

function openGalleryModal() {
    document.getElementById('galleryPickerModal').classList.remove('hidden');
}

function selectGalleryThumb(url) {
    document.getElementById('courseThumbInput').value = url;
    updateThumbPreview(url);
    closeModal('galleryPickerModal');
}

function openAddCourseModal() {
    document.getElementById('courseModalTitle').textContent = 'إضافة كورس جديد';
    document.getElementById('isAddCourseFlag').name = 'add_course';
    document.getElementById('editCourseId').value = '';
    document.getElementById('courseTitleInput').value = '';
    document.getElementById('courseDescInput').value = '';
    document.getElementById('coursePriceInput').value = '250';
    document.getElementById('courseSpecInput').value = '';
    document.getElementById('courseSectionInput').value = '';
    document.getElementById('courseThumbInput').value = '';
    updateThumbPreview('');
    document.getElementById('courseSubmitBtn').textContent = 'حفظ وإنشاء الكورس';
    document.getElementById('courseModal').classList.remove('hidden');
}

function openEditCourseModal(c) {
    document.getElementById('courseModalTitle').textContent = 'تعديل الكورس: ' + c.title;
    document.getElementById('isAddCourseFlag').name = 'edit_course';
    document.getElementById('editCourseId').value = c.id;
    document.getElementById('courseTitleInput').value = c.title || '';
    document.getElementById('courseDescInput').value = c.description || '';
    document.getElementById('coursePriceInput').value = c.price || '0';
    document.getElementById('courseSpecInput').value = c.specialization || '';
    document.getElementById('courseSectionInput').value = c.sectionId || '';
    document.getElementById('courseThumbInput').value = c.thumbnail || '';
    updateThumbPreview(c.thumbnail || '');
    document.getElementById('courseSubmitBtn').textContent = 'حفظ التعديلات';
    document.getElementById('courseModal').classList.remove('hidden');
}

function openLessonsManager(courseId, courseTitle) {
    document.getElementById('managerCourseId').value = courseId;
    document.getElementById('lessonsManagerCourseTitle').textContent = courseTitle;
    
    const list = collectionsByCourse[courseId] || [];
    const container = document.getElementById('lessonsListContainer');
    const badge = document.getElementById('lessonsCountBadge');
    badge.textContent = `${list.length} حصة`;
    container.innerHTML = '';

    if (list.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-xs text-slate-400 bg-slate-50 border border-dashed border-slate-200 rounded-2xl">
                لا توجد حصص مضافة لهذا الكورس بعد. يمكنك إضافة الحصة الأولى من النموذج أعلاه.
            </div>
        `;
    } else {
        list.forEach((col, idx) => {
            const card = document.createElement('div');
            card.className = 'p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3';
            card.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-800 font-bold text-xs flex items-center justify-center shrink-0">
                        ${col.order || (idx + 1)}
                    </span>
                    <div>
                        <h5 class="text-xs font-bold text-slate-900">${col.title}</h5>
                        <span class="text-[11px] font-mono font-black text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 inline-block mt-0.5">
                            سعر الحصة: ${col.price ? Number(col.price).toFixed(2) : '70.00'} ج.م
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-end sm:self-center">
                    <button type="button" onclick="editLessonInline('${col.id}', '${col.title.replace(/'/g, "\\'")}', '${col.price || 70}', '${col.order || (idx + 1)}')" class="px-3 py-1 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-xs font-bold transition cursor-pointer">
                        تعديل السعر / الاسم
                    </button>
                    <a href="courses.php?action=delete_lesson&lessonId=${col.id}" onclick="return confirm('هل تريد حذف هذه الحصة؟')" class="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-xs font-bold transition">
                        حذف
                    </a>
                </div>
            `;
            container.appendChild(card);
        });
    }

    document.getElementById('lessonsManagerModal').classList.remove('hidden');
}

function editLessonInline(id, title, price, order) {
    const newTitle = prompt('تعديل عنوان الحصة:', title);
    if (!newTitle) return;
    const newPrice = prompt('تعديل سعر الحصة (بالجنيه المصري):', price);
    if (newPrice === null) return;
    const newOrder = prompt('تعديل ترتيب الحصة:', order);

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'courses.php';
    form.innerHTML = `
        <input type="hidden" name="edit_lesson" value="1">
        <input type="hidden" name="lesson_id" value="${id}">
        <input type="hidden" name="lesson_title" value="${newTitle}">
        <input type="hidden" name="lesson_price" value="${newPrice}">
        <input type="hidden" name="lesson_order" value="${newOrder || 1}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
