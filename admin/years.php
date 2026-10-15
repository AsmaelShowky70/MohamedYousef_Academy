<?php
require_once __DIR__ . '/../config/helpers.php';
$adminUser = requireAdmin();
requireAdminPermission('courses');
$db = getDB();

$msg = $_SESSION['year_msg'] ?? '';
$msgType = $_SESSION['year_msg_type'] ?? '';
unset($_SESSION['year_msg'], $_SESSION['year_msg_type']);

// Handle Delete Year
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['yearId'])) {
    $delId = $_GET['yearId'];
    try {
        $db->prepare("DELETE FROM Year WHERE id = ?")->execute([$delId]);
        $_SESSION['year_msg'] = 'تم حذف المرحلة الدراسية بنجاح';
        $_SESSION['year_msg_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['year_msg'] = 'فشل حذف المرحلة: ' . $e->getMessage();
        $_SESSION['year_msg_type'] = 'error';
    }
    header("Location: years.php");
    exit;
}

// Handle Add Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_year'])) {
    $name = trim($_POST['name'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $order = (int)($_POST['order'] ?? 1);
    $nahwLink = trim($_POST['nahwLink'] ?? '');
    $sarfLink = trim($_POST['sarfLink'] ?? '');
    $balaghaLink = trim($_POST['balaghaLink'] ?? '');
    $adabLink = trim($_POST['adabLink'] ?? '');

    // Handle direct file upload for year image
    if (!empty($_FILES['image_file']['name'])) {
        $upDir = __DIR__ . '/../public/uploads/';
        if (!is_dir($upDir)) mkdir($upDir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['image_file']['name'], PATHINFO_FILENAME));
        $fName = $cleanName . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $upDir . $fName)) {
            $image = 'public/uploads/' . $fName;
            $apiUpDir = __DIR__ . '/../api/uploads/';
            if (!is_dir($apiUpDir)) mkdir($apiUpDir, 0777, true);
            @copy($upDir . $fName, $apiUpDir . $fName);
        }
    }

    if (empty($name)) {
        $_SESSION['year_msg'] = 'اسم المرحلة الدراسية مطلوب';
        $_SESSION['year_msg_type'] = 'error';
    } else {
        $yId = generateId(5);
        $ins = $db->prepare("
            INSERT INTO Year (id, name, image, `order`, nahwLink, sarfLink, balaghaLink, adabLink, hasSpecializations, createdAt, updatedAt) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $ins->execute([$yId, $name, $image, $order, $nahwLink, $sarfLink, $balaghaLink, $adabLink]);
        $_SESSION['year_msg'] = 'تم إضافة المرحلة الدراسية بنجاح!';
        $_SESSION['year_msg_type'] = 'success';
    }
    header("Location: years.php");
    exit;
}

// Handle Update Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_year'])) {
    $yId = $_POST['yearId'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $order = (int)($_POST['order'] ?? 1);
    $nahwLink = trim($_POST['nahwLink'] ?? '');
    $sarfLink = trim($_POST['sarfLink'] ?? '');
    $balaghaLink = trim($_POST['balaghaLink'] ?? '');
    $adabLink = trim($_POST['adabLink'] ?? '');

    // Handle direct file upload for year image
    if (!empty($_FILES['image_file']['name'])) {
        $upDir = __DIR__ . '/../public/uploads/';
        if (!is_dir($upDir)) mkdir($upDir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['image_file']['name'], PATHINFO_FILENAME));
        $fName = $cleanName . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $upDir . $fName)) {
            $image = 'public/uploads/' . $fName;
            $apiUpDir = __DIR__ . '/../api/uploads/';
            if (!is_dir($apiUpDir)) mkdir($apiUpDir, 0777, true);
            @copy($upDir . $fName, $apiUpDir . $fName);
        }
    }

    if (empty($image) && $yId) {
        $findImg = $db->prepare("SELECT image FROM Year WHERE id = ?");
        $findImg->execute([$yId]);
        $image = $findImg->fetchColumn() ?: '';
    }

    if ($yId && $name) {
        $upd = $db->prepare("
            UPDATE Year 
            SET name = ?, image = ?, `order` = ?, nahwLink = ?, sarfLink = ?, balaghaLink = ?, adabLink = ?, updatedAt = NOW() 
            WHERE id = ?
        ");
        $upd->execute([$name, $image, $order, $nahwLink, $sarfLink, $balaghaLink, $adabLink, $yId]);
        $_SESSION['year_msg'] = 'تم تحديث بيانات المرحلة بنجاح!';
        $_SESSION['year_msg_type'] = 'success';
    }
    header("Location: years.php");
    exit;
}

// Fetch Years with Stats
$years = $db->query("
    SELECT y.*, 
           (SELECT COUNT(*) FROM User u WHERE u.yearId = y.id AND u.role = 'STUDENT') as studentsCount,
           (SELECT COUNT(*) FROM Section s WHERE s.yearId = y.id) as sectionsCount
    FROM Year y 
    ORDER BY y.`order` ASC, y.createdAt ASC
")->fetchAll();

$adminTitle = "السنوات والمراحل الدراسية - منصة أ. محمد يوسف";
$activeAdminNav = "years";
require_once __DIR__ . '/header.php';
?>

<!-- Title & Action Bar -->
<div class="flex flex-col sm:flex-row items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-black text-[#0d3753] tracking-tight">إدارة السنوات والمراحل الدراسية</h2>
        <p class="text-xs text-slate-500 font-medium mt-1">إضافة وتعديل الصفوف الدراسية، الصور التعريفية، وروابط فروع اللغة العربية</p>
    </div>
    <button onclick="openModal('addYearModal')" class="btn-add-action inline-flex items-center gap-2 px-5 py-2.5 bg-[#0f766e] hover:bg-[#0d3753] text-white font-bold text-xs rounded-xl shadow transition">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>إضافة سنة دراسية جديدة</span>
    </button>
</div>

<?php if ($msg): ?>
    <div class="p-3.5 rounded-xl text-xs font-bold <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- Years Grid Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <?php foreach ($years as $yr): ?>
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm flex flex-col justify-between hover:shadow-md transition">
            <!-- Header Image -->
            <div class="h-48 bg-[#0d3753] relative overflow-hidden">
                <?php if (!empty($yr['image'])): ?>
                    <img src="<?= htmlspecialchars($yr['image']) ?>" alt="<?= htmlspecialchars($yr['name']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full flex flex-col items-center justify-center text-[#c79a32] p-4 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        <span class="text-xs font-bold text-white/80 mt-2"><?= htmlspecialchars($yr['name']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent"></div>
                <div class="absolute bottom-3 right-4 left-4 flex items-center justify-between text-white">
                    <div>
                        <span class="text-[10px] text-amber-300 font-bold block">ترتيب العرض: #<?= $yr['order'] ?? 1 ?></span>
                        <h3 class="text-lg font-black tracking-wide"><?= htmlspecialchars($yr['name']) ?></h3>
                    </div>
                    <span class="bg-[#0f766e] text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow">
                        <?= $yr['studentsCount'] ?> طالب
                    </span>
                </div>
            </div>

            <!-- Links Info -->
            <div class="p-5 space-y-4 flex-1 flex flex-col justify-between text-xs">
                <div class="space-y-2">
                    <div class="flex items-center justify-between py-1 border-b border-slate-100">
                        <span class="font-bold text-slate-500">رابط النحو:</span>
                        <span class="font-mono text-slate-800 truncate max-w-[140px]" dir="ltr"><?= htmlspecialchars($yr['nahwLink'] ?: 'غير محدد') ?></span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-slate-100">
                        <span class="font-bold text-slate-500">رابط الصرف:</span>
                        <span class="font-mono text-slate-800 truncate max-w-[140px]" dir="ltr"><?= htmlspecialchars($yr['sarfLink'] ?: 'غير محدد') ?></span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-slate-100">
                        <span class="font-bold text-slate-500">رابط البلاغة:</span>
                        <span class="font-mono text-slate-800 truncate max-w-[140px]" dir="ltr"><?= htmlspecialchars($yr['balaghaLink'] ?: 'غير محدد') ?></span>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="font-bold text-slate-500">الأدب والنصوص:</span>
                        <span class="font-mono text-slate-800 truncate max-w-[140px]" dir="ltr"><?= htmlspecialchars($yr['adabLink'] ?: 'غير محدد') ?></span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center gap-2">
                    <button type="button" onclick="openEditYearModal(<?= htmlspecialchars(json_encode($yr)) ?>)" class="flex-1 py-2.5 bg-slate-100 hover:bg-[#0f766e] hover:text-white text-slate-700 font-bold rounded-xl transition text-center flex items-center justify-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        <span>تعديل</span>
                    </button>
                    <a href="years.php?action=delete&yearId=<?= urlencode($yr['id']) ?>" onclick="return confirm('هل أنت متأكد من حذف هذه السنة؟ سيتم إلغاء ربط الأقسام بها.')" class="p-2.5 bg-red-50 hover:bg-red-500 hover:text-white text-red-500 rounded-xl transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ======================================================== -->
<!-- Add Year Modal with Backdrop Click Close -->
<!-- ======================================================== -->
<div id="addYearModal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto" onclick="if(event.target === this) closeModal('addYearModal')">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 space-y-4 shadow-2xl relative my-8">
        <button type="button" onclick="closeModal('addYearModal')" class="absolute left-6 top-6 text-slate-400 hover:text-slate-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>

        <h3 class="text-lg font-black text-[#0d3753]">إضافة سنة دراسية جديدة</h3>

        <form method="POST" action="years.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="add_year" value="1"/>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">اسم المرحلة الدراسية *</label>
                <input type="text" name="name" required placeholder="مثال: الصف الأول الثانوي" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الترتيب *</label>
                    <input type="number" name="order" value="1" min="1" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رفع صورة المرحلة</label>
                    <input type="file" name="image_file" accept="image/*" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none"/>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">أو رابط صورة المرحلة</label>
                <input type="text" name="image" placeholder="/api/uploads/... أو https://..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
            </div>

            <div class="bg-teal-50 border border-teal-200/80 p-3 rounded-xl text-teal-800 text-[11px] font-semibold space-y-1">
                <p class="font-bold flex items-center gap-1.5"><i class="fa-solid fa-circle-info"></i> ما هي روابط فروع المادة أدناه؟</p>
                <p class="text-teal-700/80">روابط سريعة اختيارية (مثل رابط قائمة يوتيوب أو قناة تليجرام أو ملف Drive) تظهر كأزرار وصول سريع للطلاب أعلى صفحة المرحلة. يمكنك تركها فارغة إن لم تكن بحاجة إليها.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رابط النحو (اختياري)</label>
                    <input type="text" name="nahwLink" placeholder="https://..." dir="ltr" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رابط الصرف (اختياري)</label>
                    <input type="text" name="sarfLink" placeholder="https://..." dir="ltr" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رابط البلاغة</label>
                    <input type="text" name="balaghaLink" placeholder="https://..." dir="ltr" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رابط الأدب والنصوص</label>
                    <input type="text" name="adabLink" placeholder="https://..." dir="ltr" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-add-action w-full py-3 bg-[#0f766e] hover:bg-[#0d3753] text-white font-black text-xs rounded-xl shadow transition">
                    حفظ وإنشاء المرحلة
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- Edit Year Modal with Backdrop Click Close -->
<!-- ======================================================== -->
<div id="editYearModal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto" onclick="if(event.target === this) closeModal('editYearModal')">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 space-y-4 shadow-2xl relative my-8">
        <button type="button" onclick="closeModal('editYearModal')" class="absolute left-6 top-6 text-slate-400 hover:text-slate-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>

        <h3 class="text-lg font-black text-[#0d3753]" id="editModalTitle">تعديل المرحلة الدراسية</h3>

        <form method="POST" action="years.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="update_year" value="1"/>
            <input type="hidden" name="yearId" id="editYearId"/>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">اسم المرحلة *</label>
                <input type="text" name="name" id="editYearName" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الترتيب *</label>
                    <input type="number" name="order" id="editYearOrder" value="1" min="1" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رفع صورة جديدة</label>
                    <input type="file" name="image_file" accept="image/*" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none"/>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">رابط صورة المرحلة</label>
                <input type="text" name="image" id="editYearImage" placeholder="/api/uploads/... أو https://..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
            </div>

            <div class="bg-teal-50 border border-teal-200/80 p-3 rounded-xl text-teal-800 text-[11px] font-semibold space-y-1">
                <p class="font-bold flex items-center gap-1.5"><i class="fa-solid fa-circle-info"></i> ما هي روابط فروع المادة أدناه؟</p>
                <p class="text-teal-700/80">روابط سريعة اختيارية (مثل رابط قائمة يوتيوب أو قناة تليجرام أو ملف Drive) تظهر كأزرار وصول سريع للطلاب أعلى صفحة المرحلة. يمكنك تركها فارغة إن لم تكن بحاجة إليها.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رابط النحو (اختياري)</label>
                    <input type="text" name="nahwLink" id="editNahwLink" placeholder="https://..." dir="ltr" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رابط الصرف (اختياري)</label>
                    <input type="text" name="sarfLink" id="editSarfLink" placeholder="https://..." dir="ltr" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رابط البلاغة</label>
                    <input type="text" name="balaghaLink" id="editBalaghaLink" placeholder="https://..." dir="ltr" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رابط الأدب والنصوص</label>
                    <input type="text" name="adabLink" id="editAdabLink" placeholder="https://..." dir="ltr" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-add-action w-full py-3 bg-[#0f766e] hover:bg-[#0d3753] text-white font-black text-xs rounded-xl shadow transition">
                    حفظ التعديلات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
function openEditYearModal(yr) {
    document.getElementById('editYearId').value = yr.id;
    document.getElementById('editYearName').value = yr.name || '';
    document.getElementById('editYearImage').value = yr.image || '';
    document.getElementById('editYearOrder').value = yr.order || 1;
    document.getElementById('editNahwLink').value = yr.nahwLink || '';
    document.getElementById('editSarfLink').value = yr.sarfLink || '';
    document.getElementById('editBalaghaLink').value = yr.balaghaLink || '';
    document.getElementById('editAdabLink').value = yr.adabLink || '';
    document.getElementById('editModalTitle').textContent = 'تعديل: ' + yr.name;
    document.getElementById('editYearModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
