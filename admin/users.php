<?php
require_once __DIR__ . '/../config/helpers.php';
$adminUser = requireAdmin();
requireAdminPermission('students');
$db = getDB();
$adminTitle = "إدارة الطلاب والمستخدمين - منصة أ. محمد يوسف";
$activeAdminNav = "users";
require_once __DIR__ . '/header.php';

$msg = '';
$msgType = '';

// Handle Add Student
// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $fullName = trim($_POST['fullName'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $parentPhone = trim($_POST['parentPhone'] ?? '');
    $yearId = !empty($_POST['yearId']) ? $_POST['yearId'] : null;
    $password = trim($_POST['password'] ?? '12345678');
    $status = $_POST['status'] ?? 'ACTIVE';
    $studentType = ($_POST['studentType'] ?? 'ONLINE') === 'CENTER' ? 'CENTER' : 'ONLINE';
    $centerCode = ($studentType === 'CENTER') ? trim($_POST['centerCode'] ?? '') : null;
    if ($centerCode === '') $centerCode = null;

    if (empty($fullName) || empty($phone)) {
        $msg = 'الاسم ورقم الهاتف حقول مطلوبة';
        $msgType = 'error';
    } else {
        // Check phone duplicate
        $chk = $db->prepare("SELECT id FROM User WHERE phone = ?");
        $chk->execute([$phone]);
        if ($chk->fetch()) {
            $msg = 'رقم الهاتف مسجل بالفعل لطالب آخر';
            $msgType = 'error';
        } else {
            $userId = generateId(5);
            $parts = explode(' ', $fullName);
            $firstName = $parts[0] ?? $fullName;
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $attId = random_int(100000, 999999);

            $ins = $db->prepare("
                INSERT INTO User (id, phone, password, fullName, firstName, parentPhone, role, status, studentType, centerCode, yearId, attendanceID, walletBalance, joinedAt) 
                VALUES (?, ?, ?, ?, ?, ?, 'STUDENT', ?, ?, ?, ?, ?, 0.00, NOW())
            ");
            $ins->execute([$userId, $phone, $hashed, $fullName, $firstName, $parentPhone, $status, $studentType, $centerCode, $yearId, $attId]);
            $msg = 'تمت إضافة الطالب بنجاح!';
            $msgType = 'success';
        }
    }
}

// Handle Status Change (Ban/Activate)
if (isset($_GET['action']) && isset($_GET['userId'])) {
    $targetId = $_GET['userId'];
    if ($_GET['action'] === 'ban') {
        $db->prepare("UPDATE User SET status = 'BANNED' WHERE id = ?")->execute([$targetId]);
        $msg = 'تم حظر الطالب بنجاح';
        $msgType = 'success';
    } elseif ($_GET['action'] === 'activate') {
        $db->prepare("UPDATE User SET status = 'ACTIVE' WHERE id = ?")->execute([$targetId]);
        $msg = 'تم تفعيل حساب الطالب بنجاح';
        $msgType = 'success';
    } elseif ($_GET['action'] === 'delete') {
        $db->prepare("DELETE FROM User WHERE id = ? AND role = 'STUDENT'")->execute([$targetId]);
        $msg = 'تم حذف الطالب نهائياً';
        $msgType = 'success';
    } elseif ($_GET['action'] === 'reset_devices') {
        $db->prepare("DELETE FROM Device WHERE userId = ?")->execute([$targetId]);
        $msg = 'تم فك ربط أجهزة الطالب بنجاح! يمكنه الآن الدخول من جهاز جديد.';
        $msgType = 'success';
    }
}

// Filters
$q = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$yearFilter = $_GET['yearId'] ?? '';
$typeFilter = $_GET['studentType'] ?? '';

// Fast counts for tabs
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM User WHERE role = 'STUDENT'")->fetchColumn();
$totalCenter = (int)$db->query("SELECT COUNT(*) FROM User WHERE role = 'STUDENT' AND studentType = 'CENTER'")->fetchColumn();
$totalOnline = (int)$db->query("SELECT COUNT(*) FROM User WHERE role = 'STUDENT' AND (studentType = 'ONLINE' OR studentType IS NULL)")->fetchColumn();

$sql = "
    SELECT u.*, y.name AS yearName,
           (SELECT COUNT(*) FROM CourseCode cc WHERE cc.userId = u.id) as codesCount,
           (SELECT COUNT(*) FROM Device d WHERE d.userId = u.id) as devicesCount
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
if ($statusFilter !== '') {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
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
$users = $stmt->fetchAll();

$years = $db->query("SELECT id, name FROM Year ORDER BY `order` ASC")->fetchAll();
?>

<!-- Title and Top Action Bar -->
<div class="flex flex-col sm:flex-row items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-black text-[#0d3753] tracking-tight">إدارة المستخدمين والطلاب</h2>
        <p class="text-xs text-slate-500 font-medium mt-1">عرض وتفعيل وفلترة حسابات طلاب السنتر والأونلاين</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="studentsmarks.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span>كشف التقييم والدرجات</span>
        </a>
        <button onclick="openModal('addStudentModal')" class="btn-add-action inline-flex items-center gap-2 px-5 py-2.5 bg-[#0f766e] hover:bg-[#0d3753] text-white font-bold text-xs rounded-xl shadow transition cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
            <span>إضافة طالب جديد</span>
        </button>
    </div>
</div>

<?php if ($msg): ?>
    <div class="p-3.5 rounded-xl text-xs font-bold <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- Quick Tabs for Center vs Online -->
<div class="flex items-center gap-2 border-b border-slate-200 pb-2">
    <a href="users.php<?= $yearFilter ? '?yearId='.urlencode($yearFilter) : '' ?>" class="px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 <?= $typeFilter === '' ? 'bg-[#0f766e] text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
        <span>جميع الطلاب</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] <?= $typeFilter === '' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' ?>"><?= $totalStudents ?></span>
    </a>
    <a href="users.php?studentType=CENTER<?= $yearFilter ? '&yearId='.urlencode($yearFilter) : '' ?>" class="px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 <?= $typeFilter === 'CENTER' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-amber-800 hover:bg-amber-50 border border-amber-200' ?>">
        <span>🏢 طلاب السنتر</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] <?= $typeFilter === 'CENTER' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-900' ?>"><?= $totalCenter ?></span>
    </a>
    <a href="users.php?studentType=ONLINE<?= $yearFilter ? '&yearId='.urlencode($yearFilter) : '' ?>" class="px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 <?= $typeFilter === 'ONLINE' ? 'bg-sky-600 text-white shadow-sm' : 'bg-white text-sky-800 hover:bg-sky-50 border border-sky-200' ?>">
        <span>🌐 طلاب أونلاين</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] <?= $typeFilter === 'ONLINE' ? 'bg-white/20 text-white' : 'bg-sky-100 text-sky-900' ?>"><?= $totalOnline ?></span>
    </a>
</div>

<!-- Search and Filter Bar -->
<div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
    <form method="GET" action="users.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
        <?php if ($typeFilter): ?>
            <input type="hidden" name="studentType" value="<?= htmlspecialchars($typeFilter) ?>">
        <?php endif; ?>

        <!-- Search -->
        <div class="sm:col-span-4 relative">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="بحث بالاسم، الهاتف، كود السنتر، كود الحضور..."
                   class="w-full pl-3 pr-9 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-[#0f766e] outline-none transition"/>
            <svg class="h-4 w-4 text-slate-400 absolute right-3 top-3 pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>

        <!-- Student Type Dropdown (If not using tabs) -->
        <div class="sm:col-span-3">
            <select name="studentType" onchange="this.form.submit()" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-[#0f766e] outline-none transition">
                <option value="">جميع الأنواع (سنتر وأونلاين)</option>
                <option value="CENTER" <?= $typeFilter === 'CENTER' ? 'selected' : '' ?>>🏢 طلاب السنتر فقط</option>
                <option value="ONLINE" <?= $typeFilter === 'ONLINE' ? 'selected' : '' ?>>🌐 طلاب أونلاين فقط</option>
            </select>
        </div>

        <!-- Status Filter -->
        <div class="sm:col-span-2">
            <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-[#0f766e] outline-none transition">
                <option value="">جميع الحالات</option>
                <option value="ACTIVE" <?= $statusFilter === 'ACTIVE' ? 'selected' : '' ?>>نشط</option>
                <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>قيد الانتظار</option>
                <option value="BANNED" <?= $statusFilter === 'BANNED' ? 'selected' : '' ?>>محظور</option>
            </select>
        </div>

        <!-- Year Filter -->
        <div class="sm:col-span-2">
            <select name="yearId" onchange="this.form.submit()" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-[#0f766e] outline-none transition">
                <option value="">جميع الصفوف</option>
                <?php foreach ($years as $yr): ?>
                    <option value="<?= htmlspecialchars($yr['id']) ?>" <?= $yearFilter === $yr['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($yr['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Actions -->
        <div class="sm:col-span-1 flex items-center gap-1.5">
            <button type="submit" class="w-full py-2.5 bg-[#0f766e] hover:bg-[#0d3753] text-white text-xs font-bold rounded-xl transition">
                فلترة
            </button>
            <?php if ($q !== '' || $statusFilter !== '' || $yearFilter !== '' || $typeFilter !== ''): ?>
                <a href="users.php" title="إلغاء الفلترة" class="p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
        <span class="text-xs font-bold text-slate-500">إجمالي نتائج البحث: <strong class="text-slate-900"><?= count($users) ?></strong> طالب</span>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-right text-xs">
            <thead class="bg-slate-50/80 text-slate-600 font-bold border-b border-slate-200">
                <tr>
                    <th class="p-4">الطالب</th>
                    <th class="p-4">نوع الطالب</th>
                    <th class="p-4">رقم الهاتف</th>
                    <th class="p-4">هاتف ولي الأمر</th>
                    <th class="p-4">الصف الدراسي</th>
                    <th class="p-4">كود الحضور</th>
                    <th class="p-4">الأجهزة المربوطة</th>
                    <th class="p-4">الحالة</th>
                    <th class="p-4 text-center">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($users as $u): ?>
                    <?php 
                        $isActive = ($u['status'] === 'ACTIVE');
                        $isPending = ($u['status'] === 'PENDING');
                        $isBanned = ($u['status'] === 'BANNED');
                        $maxDev = (int)($u['maxDevices'] ?? 1);
                        $devCount = (int)($u['devicesCount'] ?? 0);
                        $isCenter = (($u['studentType'] ?? 'ONLINE') === 'CENTER');
                    ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="p-4 font-bold text-slate-900">
                            <a href="student_profile.php?id=<?= urlencode($u['id']) ?>" class="group flex items-center gap-2.5 hover:text-[#0f766e] transition">
                                <div class="w-8 h-8 rounded-full <?= $isCenter ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-teal-50 border border-teal-200 text-[#0f766e]' ?> group-hover:bg-[#0f766e] group-hover:text-white transition flex items-center justify-center font-bold text-xs shrink-0">
                                    <?= mb_substr($u['fullName'] ?: $u['firstName'], 0, 1) ?>
                                </div>
                                <div>
                                    <span class="block group-hover:underline"><?= htmlspecialchars($u['fullName'] ?: $u['firstName']) ?></span>
                                    <span class="text-[10px] text-slate-400 font-mono font-normal"><?= substr($u['joinedAt'], 0, 10) ?></span>
                                </div>
                            </a>
                        </td>
                        <td class="p-4">
                            <?php if ($isCenter): ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-black bg-amber-100 text-amber-900 border border-amber-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 9h1"/><path d="M9 13h1"/><path d="M9 17h1"/><path d="M14 9h1"/><path d="M14 13h1"/><path d="M14 17h1"/></svg>
                                    <span>طالب سنتر</span>
                                    <?php if (!empty($u['centerCode'])): ?>
                                        <span class="font-mono bg-white px-1.5 py-0.5 rounded border border-amber-300 text-amber-950 font-black"><?= htmlspecialchars($u['centerCode']) ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-sky-50 text-sky-700 border border-sky-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                                    <span>أونلاين</span>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 font-mono font-bold text-slate-700" dir="ltr"><?= htmlspecialchars($u['phone']) ?></td>
                        <td class="p-4 font-mono text-slate-500" dir="ltr"><?= htmlspecialchars($u['parentPhone'] ?: '---') ?></td>
                        <td class="p-4 font-bold text-slate-700"><?= htmlspecialchars($u['yearName'] ?: 'غير محدد') ?></td>
                        <td class="p-4 font-mono font-bold text-[#0f766e]"><?= htmlspecialchars($u['attendanceID'] ?: '---') ?></td>
                        <td class="p-4 font-mono font-bold">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] <?= $devCount >= $maxDev ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700' ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                                <?= $devCount ?> / <?= $maxDev ?>
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold <?= $isActive ? 'bg-green-50 text-green-700 border border-green-200' : ($isPending ? 'bg-yellow-50 text-yellow-700 border border-yellow-200' : 'bg-red-50 text-red-700 border border-red-200') ?>">
                                <?= $isActive ? 'نشط' : ($isPending ? 'انتظار' : 'محظور') ?>
                            </span>
                        </td>
                        <td class="p-4 text-center">
                            <div class="inline-flex items-center gap-1.5">
                                <a href="student_profile.php?id=<?= urlencode($u['id']) ?>" title="عرض ملف الطالب والمتابعة والتقارير" class="p-1.5 rounded-lg bg-teal-50 text-teal-700 hover:bg-teal-100 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                                <?php if ($isBanned): ?>
                                    <a href="users.php?action=activate&userId=<?= urlencode($u['id']) ?>" title="فك الحظر وتفعيل" class="p-1.5 rounded-lg bg-green-50 text-green-700 hover:bg-green-100 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    </a>
                                <?php else: ?>
                                    <a href="users.php?action=ban&userId=<?= urlencode($u['id']) ?>" title="حظر الطالب" onclick="return confirm('هل تريد حظر هذا الطالب من المنصة؟')" class="p-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                    </a>
                                <?php endif; ?>
                                <a href="users.php?action=reset_devices&userId=<?= urlencode($u['id']) ?>" title="فك ربط أجهزة الطالب" onclick="return confirm('هل تريد فك ربط أجهزة هذا الطالب للسماح له بالدخول من جهاز جديد؟')" class="p-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                </a>
                                <a href="users.php?action=delete&userId=<?= urlencode($u['id']) ?>" title="حذف الطالب" onclick="return confirm('تحذير: هل أنت متأكد من حذف هذا الطالب نهائياً؟')" class="p-1.5 rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="9" class="text-center p-8 text-slate-400">لا توجد حسابات تطابق خيارات البحث</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Student Modal -->
<div id="addStudentModal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" onclick="if(event.target === this) closeModal('addStudentModal')">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 space-y-5 shadow-2xl relative">
        <button type="button" onclick="closeModal('addStudentModal')" class="absolute left-6 top-6 text-slate-400 hover:text-slate-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>

        <h3 class="text-lg font-black text-[#0d3753]">إضافة طالب جديد يدوياً</h3>

        <form method="POST" action="users.php" class="space-y-4">
            <input type="hidden" name="add_student" value="1"/>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الكامل (ثلاثي باللغة العربية) *</label>
                <input type="text" name="fullName" required placeholder="محمد أحمد محمود" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
            </div>

            <!-- Student Type & Center Code -->
            <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl space-y-2">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-amber-950 mb-1">نوع الطالب *</label>
                        <select name="studentType" id="addStudentType" onchange="toggleCenterCodeInput(this.value)" class="w-full px-3 py-2 bg-white border border-amber-300 rounded-lg text-xs font-bold outline-none focus:border-amber-600">
                            <option value="ONLINE">🌐 طالب أونلاين</option>
                            <option value="CENTER">🏢 طالب سنتر</option>
                        </select>
                    </div>
                    <div id="centerCodeWrapper" class="hidden">
                        <label class="block text-xs font-bold text-amber-950 mb-1">كود السنتر</label>
                        <input type="text" name="centerCode" id="addCenterCode" placeholder="مثال: C-105" class="w-full px-3 py-2 bg-white border border-amber-300 rounded-lg text-xs font-bold outline-none focus:border-amber-600"/>
                    </div>
                </div>
                <p class="text-[10px] text-amber-800 font-medium">إذا كان طالب سنتر، أدخل كوده لتمييزه والبحث عنه بسهولة.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم الهاتف *</label>
                    <input type="text" name="phone" required placeholder="010XXXXXXXX" dir="ltr" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">هاتف ولي الأمر</label>
                    <input type="text" name="parentPhone" placeholder="01XXXXXXXXX" dir="ltr" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الصف الدراسي</label>
                    <select name="yearId" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]">
                        <option value="">بدون تحديد</option>
                        <?php foreach ($years as $yr): ?>
                            <option value="<?= htmlspecialchars($yr['id']) ?>"><?= htmlspecialchars($yr['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">كلمة المرور الافتراضية</label>
                    <input type="text" name="password" value="12345678" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]"/>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">حالة الحساب</label>
                <select name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#0f766e]">
                    <option value="ACTIVE">نشط (مفعل فوراً)</option>
                    <option value="PENDING">قيد الانتظار</option>
                </select>
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-add-action w-full py-3 bg-[#0f766e] hover:bg-[#0d3753] text-white font-black text-xs rounded-xl shadow transition">
                    حفظ وإضافة الطالب
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
function toggleCenterCodeInput(val) {
    const wrap = document.getElementById('centerCodeWrapper');
    if (val === 'CENTER') {
        wrap.classList.remove('hidden');
    } else {
        wrap.classList.add('hidden');
        document.getElementById('addCenterCode').value = '';
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
