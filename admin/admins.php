<?php
require_once __DIR__ . '/../config/helpers.php';
$adminUser = requireAdmin();
requireSuperAdmin();
$db = getDB();

$msg = $_SESSION['admins_msg'] ?? '';
$msgType = $_SESSION['admins_msg_type'] ?? '';
unset($_SESSION['admins_msg'], $_SESSION['admins_msg_type']);

// Handle POST actions (Add / Edit / Delete Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add New Admin
    if ($action === 'add_admin') {
        $fullName = trim($_POST['fullName'] ?? '');
        $phone = normalizePlatformPhone($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $perms = $_POST['permissions'] ?? [];
        if (!is_array($perms)) $perms = [];

        if (empty($fullName) || empty($phone) || empty($password)) {
            $_SESSION['admins_msg'] = 'الاسم ورقم الهاتف وكلمة المرور حقول مطلوبة!';
            $_SESSION['admins_msg_type'] = 'error';
        } else {
            // Check duplicate phone
            $chk = $db->prepare("SELECT id FROM User WHERE phone = ?");
            $chk->execute([$phone]);
            if ($chk->fetch()) {
                $_SESSION['admins_msg'] = 'رقم الهاتف مسجل بالفعل لمستخدم آخر!';
                $_SESSION['admins_msg_type'] = 'error';
            } else {
                $newId = generateId(5);
                $parts = explode(' ', $fullName);
                $firstName = $parts[0] ?? $fullName;
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $permsJson = json_encode(array_values($perms), JSON_UNESCAPED_UNICODE);

                $ins = $db->prepare("
                    INSERT INTO User (id, phone, password, fullName, firstName, role, permissions, status, joinedAt) 
                    VALUES (?, ?, ?, ?, ?, 'ADMIN', ?, 'ACTIVE', NOW())
                ");
                $ins->execute([$newId, $phone, $hashed, $fullName, $firstName, $permsJson]);

                $_SESSION['admins_msg'] = 'تم إضافة المشرف الجديد وتحديد صلاحياته بنجاح!';
                $_SESSION['admins_msg_type'] = 'success';
            }
        }
        header("Location: admins.php");
        exit;
    }

    // 2. Edit Admin Permissions / Password
    if ($action === 'edit_admin') {
        $targetId = trim($_POST['target_id'] ?? '');
        $fullName = trim($_POST['fullName'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        $perms = $_POST['permissions'] ?? [];
        if (!is_array($perms)) $perms = [];

        // Verify target exists and is not superadmin
        $stmtChk = $db->prepare("SELECT id, role, phone FROM User WHERE id = ?");
        $stmtChk->execute([$targetId]);
        $target = $stmtChk->fetch();

        if (!$target) {
            $_SESSION['admins_msg'] = 'المشرف غير موجود!';
            $_SESSION['admins_msg_type'] = 'error';
        } elseif ($target['role'] === 'SUPERADMIN' || $target['phone'] === '01001893770') {
            $_SESSION['admins_msg'] = 'لا يمكن تعديل صلاحيات المالك الرئيسي من هنا، فهو يملك كافة الصلاحيات دائماً!';
            $_SESSION['admins_msg_type'] = 'error';
        } else {
            $permsJson = json_encode(array_values($perms), JSON_UNESCAPED_UNICODE);
            $parts = explode(' ', $fullName);
            $firstName = $parts[0] ?? $fullName;

            if ($newPassword !== '') {
                $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                $up = $db->prepare("UPDATE User SET fullName = ?, firstName = ?, permissions = ?, password = ? WHERE id = ?");
                $up->execute([$fullName, $firstName, $permsJson, $hashed, $targetId]);
            } else {
                $up = $db->prepare("UPDATE User SET fullName = ?, firstName = ?, permissions = ? WHERE id = ?");
                $up->execute([$fullName, $firstName, $permsJson, $targetId]);
            }

            $_SESSION['admins_msg'] = 'تم تحديث بيانات وصلاحيات المشرف بنجاح!';
            $_SESSION['admins_msg_type'] = 'success';
        }
        header("Location: admins.php");
        exit;
    }

    // 3. Delete Admin
    if ($action === 'delete_admin') {
        $targetId = trim($_POST['target_id'] ?? '');

        // Check target
        $stmtChk = $db->prepare("SELECT id, role, phone, fullName FROM User WHERE id = ?");
        $stmtChk->execute([$targetId]);
        $target = $stmtChk->fetch();

        if (!$target) {
            $_SESSION['admins_msg'] = 'المشرف غير موجود!';
            $_SESSION['admins_msg_type'] = 'error';
        } elseif ($target['role'] === 'SUPERADMIN' || $target['phone'] === '01001893770') {
            $_SESSION['admins_msg'] = 'خطأ أمني: محظور نهائياً حذف حساب المالك الرئيسي للمنصة!';
            $_SESSION['admins_msg_type'] = 'error';
        } else {
            $db->prepare("DELETE FROM User WHERE id = ? AND role = 'ADMIN'")->execute([$targetId]);
            $_SESSION['admins_msg'] = 'تم حذف حساب المشرف (' . htmlspecialchars($target['fullName']) . ') بنجاح!';
            $_SESSION['admins_msg_type'] = 'success';
        }
        header("Location: admins.php");
        exit;
    }
}

// Fetch all admins
$stmtAdmins = $db->query("
    SELECT * FROM User 
    WHERE role IN ('ADMIN', 'SUPERADMIN') 
    ORDER BY CASE WHEN role = 'SUPERADMIN' THEN 0 ELSE 1 END, joinedAt DESC
");
$admins = $stmtAdmins->fetchAll();

$availablePermissions = [
    'students' => [
        'title' => 'إدارة الطلاب والمستخدمين',
        'desc' => 'عرض بيانات الطلاب، تعديل الحسابات، فك ربط الأجهزة، وتفعيل الكورسات والحصص يدوياً',
        'icon' => 'fa-users',
        'color' => 'teal'
    ],
    'courses' => [
        'title' => 'إدارة الكورسات والمراحل',
        'desc' => 'إضافة وتعديل وحذف الكورسات، والسنوات الدراسية والأقسام',
        'icon' => 'fa-graduation-cap',
        'color' => 'blue'
    ],
    'content' => [
        'title' => 'إدارة المحتوى التعليمي',
        'desc' => 'رفع وحذف وتعديل الفيديوهات، الملفات والـ PDF، وإنشاء الامتحانات',
        'icon' => 'fa-photo-film',
        'color' => 'indigo'
    ],
    'codes' => [
        'title' => 'إدارة وتوليد الأكواد',
        'desc' => 'توليد دفعات كروت الاشتراك، طباعة الأكواد وتصديرها إكسل وفك الربط',
        'icon' => 'fa-key',
        'color' => 'amber'
    ],
    'marks' => [
        'title' => 'درجات الطلاب ومتابعة الحضور',
        'desc' => 'عرض نتائج الامتحانات، تسجيل الحضور بالباركود، وتصدير شيتات الدرجات',
        'icon' => 'fa-chart-line',
        'color' => 'emerald'
    ],
    'settings' => [
        'title' => 'إعدادات المنصة وسياسات القبول',
        'desc' => 'تعديل زر الواتساب، رقم الدعم الفني، وسياسة القبول التلقائي',
        'icon' => 'fa-gear',
        'color' => 'purple'
    ]
];

$adminTitle = "إدارة المشرفين وصلاحيات الأدمن - منصة أ. محمد يوسف";
$activeAdminNav = "admins";
require_once __DIR__ . '/header.php';
?>

<div class="space-y-6">
    <!-- Header Title & Stats -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 text-xs font-black bg-amber-100 text-amber-800 rounded-lg flex items-center gap-1.5 border border-amber-200">
                    <i class="fa-solid fa-crown text-amber-600"></i>
                    قسم المالك الرئيسي فقط
                </span>
                <span class="text-xs text-slate-400 font-bold"><?= count($admins) ?> مشرفين مسجلين</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-[#0d3753] tracking-tight mt-1">إدارة المشرفين وصلاحيات المسؤولين</h1>
            <p class="text-xs text-slate-500 font-medium mt-0.5">يمكنك إضافة أدمن جديد للمنصة وتحديد الأقسام التي يحق له التحكم فيها بدقة لمنع التعديل غير المصرح به.</p>
        </div>

        <button onclick="document.getElementById('addAdminModal').classList.remove('hidden')" 
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#0f766e] hover:bg-[#0d3753] text-white font-bold text-xs rounded-xl shadow transition cursor-pointer">
            <i class="fa-solid fa-user-plus"></i>
            <span>إضافة مشرف جديد +</span>
        </button>
    </div>

    <!-- Flash message -->
    <?php if ($msg): ?>
        <div class="p-4 rounded-xl text-xs font-bold flex items-center gap-2 <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-red-600' ?>"></i>
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- Info Notice Box -->
    <div class="bg-gradient-to-r from-teal-50/80 via-white to-amber-50/60 p-5 rounded-2xl border border-teal-200/70 shadow-sm flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-[#0f766e] text-white flex items-center justify-center text-lg flex-shrink-0 shadow-md">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="text-xs leading-relaxed text-slate-700">
            <h4 class="font-black text-[#0d3753] text-sm mb-1">قواعد أمان الصلاحيات في المنصة:</h4>
            <p class="text-slate-600 font-medium mb-1.5">• بصفتك <strong class="text-teal-700">المالك الرئيسي (أ. محمد يوسف)</strong>، تمتلك وحدك صلاحية إضافة، تعديل وحذف المشرفين، ولا يستطيع أي أدمن آخر رؤية هذا القسم أو تعديل أي أدمن غيره.</p>
            <p class="text-slate-600 font-medium">• أي مشرف تقوم بإضافته تظهر له في قائمته الجانبية فقط الصفحات التي منحتها له، ولن يتمكن من الدخول أو تعديل أي قسم آخر.</p>
        </div>
    </div>

    <!-- Admins Table -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-800">قائمة المسؤولين والمشرفين الحاليين</h3>
            <span class="text-xs font-bold text-slate-400"><?= count($admins) ?> مسجلين</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500 font-black border-b border-slate-200/70">
                        <th class="py-3.5 px-4">المسؤول</th>
                        <th class="py-3.5 px-4">رقم الهاتف للدخول</th>
                        <th class="py-3.5 px-4">الرتبة</th>
                        <th class="py-3.5 px-4">الصلاحيات الممنوحة</th>
                        <th class="py-3.5 px-4">تاريخ الإضافة</th>
                        <th class="py-3.5 px-4 text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700 font-bold">
                    <?php foreach ($admins as $adm): 
                        $isSuper = ($adm['role'] === 'SUPERADMIN' || $adm['phone'] === '01001893770');
                        $rawPerms = $adm['permissions'] ?? '';
                        $permsArray = !empty($rawPerms) ? json_decode($rawPerms, true) : [];
                        if (!is_array($permsArray)) $permsArray = [];
                    ?>
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl <?= $isSuper ? 'bg-amber-100 text-amber-700' : 'bg-teal-100 text-teal-700' ?> flex items-center justify-center font-black text-xs">
                                        <i class="fa-solid <?= $isSuper ? 'fa-crown' : 'fa-user-tie' ?>"></i>
                                    </div>
                                    <div>
                                        <div class="font-black text-slate-900"><?= htmlspecialchars($adm['fullName']) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($adm['firstName']) ?></div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-3.5 px-4">
                                <span class="font-mono text-slate-800 dir-ltr inline-block"><?= htmlspecialchars($adm['phone']) ?></span>
                            </td>

                            <td class="py-3.5 px-4">
                                <?php if ($isSuper): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-black bg-amber-50 text-amber-800 border border-amber-200">
                                        <i class="fa-solid fa-crown text-amber-500 text-[10px]"></i>
                                        المالك الرئيسي (Super Admin)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-black bg-teal-50 text-teal-800 border border-teal-200">
                                        <i class="fa-solid fa-user-shield text-teal-600 text-[10px]"></i>
                                        مشرف معتمد (Admin)
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="py-3.5 px-4">
                                <?php if ($isSuper): ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-slate-900 text-white">كافة الصلاحيات مطلقة (Full Access)</span>
                                <?php else: ?>
                                    <?php if (empty($permsArray)): ?>
                                        <span class="text-slate-400 text-[11px]">كافة الصلاحيات العادية</span>
                                    <?php else: ?>
                                        <div class="flex flex-wrap gap-1 max-w-xs">
                                            <?php foreach ($permsArray as $p): ?>
                                                <?php if (isset($availablePermissions[$p])): ?>
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 flex items-center gap-1">
                                                        <i class="fa-solid <?= $availablePermissions[$p]['icon'] ?> text-[9px] text-teal-600"></i>
                                                        <?= htmlspecialchars($availablePermissions[$p]['title']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td class="py-3.5 px-4 text-slate-400 text-[11px]">
                                <?= htmlspecialchars(date('Y/m/d', strtotime($adm['joinedAt'] ?? 'now'))) ?>
                            </td>

                            <td class="py-3.5 px-4 text-center">
                                <?php if ($isSuper): ?>
                                    <span class="text-[11px] text-amber-700 font-bold bg-amber-50/60 px-2 py-1 rounded-lg border border-amber-100">
                                        محمي تلقائياً
                                    </span>
                                <?php else: ?>
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" 
                                                onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                                    'id' => $adm['id'],
                                                    'fullName' => $adm['fullName'],
                                                    'phone' => $adm['phone'],
                                                    'permissions' => $permsArray
                                                ])) ?>)"
                                                class="px-2.5 py-1 text-[11px] font-bold bg-teal-50 text-teal-700 hover:bg-teal-100 rounded-lg transition cursor-pointer">
                                            <i class="fa-solid fa-pen-to-square ml-0.5"></i> تعديل الصلاحيات
                                        </button>

                                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف حساب المشرف (<?= htmlspecialchars($adm['fullName']) ?>) نهائياً؟ لن يتمكن من الدخول للمنصة مجدداً.')" class="inline">
                                            <input type="hidden" name="action" value="delete_admin">
                                            <input type="hidden" name="target_id" value="<?= htmlspecialchars($adm['id']) ?>">
                                            <button type="submit" class="px-2 py-1 text-[11px] font-bold bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition cursor-pointer">
                                                <i class="fa-solid fa-trash-can ml-0.5"></i> حذف
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- ADD ADMIN MODAL -->
<!-- ========================================================= -->
<div id="addAdminModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-base font-black text-[#0d3753] flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-teal-600"></i>
                إضافة مشرف جديد وتحديد الصلاحيات
            </h3>
            <button onclick="document.getElementById('addAdminModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
        </div>

        <form method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="add_admin">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الاسم بالكامل:</label>
                    <input type="text" name="fullName" required placeholder="مثال: أحمد مصطفى (مساعد)" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:border-teal-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم الهاتف للدخول:</label>
                    <input type="text" name="phone" required placeholder="010XXXXXXXX" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:border-teal-600 dir-ltr text-right">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">كلمة المرور:</label>
                <input type="password" name="password" required placeholder="كلمة مرور الحساب" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:border-teal-600">
            </div>

            <div class="pt-2">
                <label class="block text-xs font-black text-slate-800 mb-2">الصلاحيات الممنوحة لهذا المشرف (حدد الأقسام المسموحة):</label>
                <div class="space-y-2 max-h-56 overflow-y-auto p-3 bg-slate-50/70 border border-slate-200 rounded-xl">
                    <?php foreach ($availablePermissions as $pKey => $pInfo): ?>
                        <label class="flex items-start gap-3 p-2 bg-white rounded-lg border border-slate-200/80 hover:border-teal-400 cursor-pointer transition">
                            <input type="checkbox" name="permissions[]" value="<?= $pKey ?>" class="mt-0.5 rounded text-teal-600 focus:ring-teal-500 w-4 h-4 cursor-pointer">
                            <div>
                                <div class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                                    <i class="fa-solid <?= $pInfo['icon'] ?> text-teal-600 text-[11px]"></i>
                                    <?= htmlspecialchars($pInfo['title']) ?>
                                </div>
                                <div class="text-[10px] text-slate-400 font-medium leading-tight mt-0.5"><?= htmlspecialchars($pInfo['desc']) ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('addAdminModal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl transition cursor-pointer">إلغاء</button>
                <button type="submit" class="px-5 py-2 bg-[#0f766e] hover:bg-[#0d3753] text-white text-xs font-bold rounded-xl shadow transition cursor-pointer">حفظ وإضافة المشرف</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================= -->
<!-- EDIT ADMIN MODAL -->
<!-- ========================================================= -->
<div id="editAdminModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-base font-black text-[#0d3753] flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-teal-600"></i>
                تعديل صلاحيات وبيانات المشرف
            </h3>
            <button onclick="document.getElementById('editAdminModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
        </div>

        <form method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="edit_admin">
            <input type="hidden" name="target_id" id="edit_target_id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الاسم:</label>
                    <input type="text" name="fullName" id="edit_fullName" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:border-teal-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم الهاتف (للعرض فقط):</label>
                    <input type="text" id="edit_phone" disabled class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-500 outline-none dir-ltr text-right">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">تغيير كلمة المرور (اتركه فارغاً إذا كنت لا تود تغييره):</label>
                <input type="password" name="new_password" placeholder="أدخل كلمة مرور جديدة أو اتركه فارغاً" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:border-teal-600">
            </div>

            <div class="pt-2">
                <label class="block text-xs font-black text-slate-800 mb-2">تعديل الصلاحيات الممنوحة:</label>
                <div class="space-y-2 max-h-56 overflow-y-auto p-3 bg-slate-50/70 border border-slate-200 rounded-xl">
                    <?php foreach ($availablePermissions as $pKey => $pInfo): ?>
                        <label class="flex items-start gap-3 p-2 bg-white rounded-lg border border-slate-200/80 hover:border-teal-400 cursor-pointer transition">
                            <input type="checkbox" name="permissions[]" value="<?= $pKey ?>" id="edit_perm_<?= $pKey ?>" class="mt-0.5 rounded text-teal-600 focus:ring-teal-500 w-4 h-4 cursor-pointer">
                            <div>
                                <div class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                                    <i class="fa-solid <?= $pInfo['icon'] ?> text-teal-600 text-[11px]"></i>
                                    <?= htmlspecialchars($pInfo['title']) ?>
                                </div>
                                <div class="text-[10px] text-slate-400 font-medium leading-tight mt-0.5"><?= htmlspecialchars($pInfo['desc']) ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('editAdminModal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl transition cursor-pointer">إلغاء</button>
                <button type="submit" class="px-5 py-2 bg-[#0f766e] hover:bg-[#0d3753] text-white text-xs font-bold rounded-xl shadow transition cursor-pointer">تحديث الصلاحيات</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(admin) {
    document.getElementById('edit_target_id').value = admin.id;
    document.getElementById('edit_fullName').value = admin.fullName;
    document.getElementById('edit_phone').value = admin.phone;

    // Reset checkboxes
    const keys = ['students', 'courses', 'content', 'codes', 'marks', 'settings'];
    keys.forEach(k => {
        const cb = document.getElementById('edit_perm_' + k);
        if (cb) {
            cb.checked = (admin.permissions && admin.permissions.includes(k));
        }
    });

    document.getElementById('editAdminModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
