<?php
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');
$admin = requireAdmin();
$input = getJsonInput();
$action = $_GET['action'] ?? ($input['action'] ?? 'stats');

$db = getDB();

if ($action === 'stats') {
    $stats = [];
    $stats['totalStudents'] = (int)$db->query("SELECT COUNT(*) FROM User WHERE role = 'STUDENT'")->fetchColumn();
    $stats['activeStudents'] = (int)$db->query("SELECT COUNT(*) FROM User WHERE role = 'STUDENT' AND status = 'ACTIVE'")->fetchColumn();
    $stats['totalCourses'] = (int)$db->query("SELECT COUNT(*) FROM Course")->fetchColumn();
    $stats['totalCodes'] = (int)$db->query("SELECT COUNT(*) FROM CourseCode")->fetchColumn();
    $stats['usedCodes'] = (int)$db->query("SELECT COUNT(*) FROM CourseCode WHERE userId IS NOT NULL")->fetchColumn();
    $stats['totalVideos'] = (int)$db->query("SELECT COUNT(*) FROM Video")->fetchColumn();
    $stats['totalWallet'] = (float)$db->query("SELECT SUM(walletBalance) FROM User")->fetchColumn();

    jsonResponse(['success' => true, 'stats' => $stats]);
}

if ($action === 'users') {
    $q = trim($_GET['q'] ?? '');
    $role = $_GET['role'] ?? '';
    $status = $_GET['status'] ?? '';
    $yearId = $_GET['yearId'] ?? '';

    $sql = "
        SELECT u.*, y.name AS yearName,
               (SELECT COUNT(*) FROM CourseCode cc WHERE cc.userId = u.id) as codesCount,
               (SELECT COUNT(*) FROM Device d WHERE d.userId = u.id) as devicesCount
        FROM User u
        LEFT JOIN Year y ON u.yearId = y.id
        WHERE 1=1
    ";
    $params = [];

    if ($q) {
        $sql .= " AND (u.fullName LIKE ? OR u.phone LIKE ? OR u.parentPhone LIKE ? OR u.attendanceID LIKE ?)";
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    if ($role) {
        $sql .= " AND u.role = ?";
        $params[] = $role;
    }
    if ($status) {
        $sql .= " AND u.status = ?";
        $params[] = $status;
    }
    if ($yearId) {
        $sql .= " AND u.yearId = ?";
        $params[] = $yearId;
    }

    $sql .= " ORDER BY u.joinedAt DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    jsonResponse(['success' => true, 'users' => $users]);
}

if ($action === 'update-user-status') {
    $userId = $input['userId'] ?? '';
    $status = $input['status'] ?? '';
    if (!in_array($status, ['ACTIVE', 'PENDING', 'BANNED'])) {
        jsonResponse(['success' => false, 'error' => 'حالة غير صالحة'], 400);
    }
    $db->prepare("UPDATE User SET status = ? WHERE id = ?")->execute([$status, $userId]);
    jsonResponse(['success' => true, 'message' => 'تم تحديث حالة المستخدم بنجاح']);
}

if ($action === 'update-user') {
    $userId = $input['userId'] ?? '';
    $fullName = trim($input['fullName'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $parentPhone = trim($input['parentPhone'] ?? '');
    $yearId = $input['yearId'] ?? null;
    $walletBalance = (float)($input['walletBalance'] ?? 0);
    $password = trim($input['password'] ?? '');

    if (!$userId || !$fullName || !$phone) {
        jsonResponse(['success' => false, 'error' => 'الاسم ورقم الهاتف مطلوبان'], 400);
    }

    $sql = "UPDATE User SET fullName = ?, phone = ?, parentPhone = ?, yearId = ?, walletBalance = ?";
    $params = [$fullName, $phone, $parentPhone, $yearId ?: null, $walletBalance];

    if ($password !== '') {
        $sql .= ", password = ?";
        $params[] = $password;
    }

    $sql .= " WHERE id = ?";
    $params[] = $userId;

    $db->prepare($sql)->execute($params);
    jsonResponse(['success' => true, 'message' => 'تم حفظ بيانات الطالب بنجاح']);
}

if ($action === 'delete-user') {
    $userId = $input['userId'] ?? '';
    if (!$userId) jsonResponse(['success' => false, 'error' => 'معرف الطالب مطلوب'], 400);

    // Prevent deleting owner
    $stmt = $db->prepare("SELECT role, phone FROM User WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if ($u && $u['role'] === 'SUPERADMIN' && $u['phone'] === '01001893770') {
        jsonResponse(['success' => false, 'error' => 'لا يمكن حذف حساب مالك المنصة الرئيسي'], 403);
    }

    $db->prepare("DELETE FROM User WHERE id = ?")->execute([$userId]);
    jsonResponse(['success' => true, 'message' => 'تم حذف الحساب بنجاح']);
}

if ($action === 'reset-device') {
    $userId = $input['userId'] ?? '';
    if (!$userId) jsonResponse(['success' => false, 'error' => 'معرف الطالب مطلوب'], 400);

    $db->prepare("DELETE FROM Device WHERE userId = ?")->execute([$userId]);
    $db->prepare("UPDATE CourseCode SET boundDeviceIdentifier = NULL WHERE userId = ?")->execute([$userId]);

    jsonResponse(['success' => true, 'message' => 'تم فك ربط جميع أجهزة الطالب وكروت الاشتراك بنجاح']);
}

if ($action === 'update-settings') {
    $settings = $input['settings'] ?? [];
    $stmt = $db->prepare("INSERT INTO Setting (`key`, `value`, updatedAt) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updatedAt = NOW()");
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, (string)$v]);
    }
    clearAppCache('app_settings');
    jsonResponse(['success' => true, 'message' => 'تم حفظ الإعدادات بنجاح']);
}

if ($action === 'generate-batch-codes') {
    $courseId = $input['courseId'] ?? '';
    $collectionId = !empty($input['collectionId']) ? trim($input['collectionId']) : null;
    $count = min(100, max(1, (int)($input['count'] ?? 10)));
    $days = (int)($input['days'] ?? 30);
    $accessTimes = (int)($input['accessTimes'] ?? 10);
    $prefix = strtoupper(trim($input['prefix'] ?? ($collectionId ? 'HSA' : 'MY')));

    if (!$courseId) jsonResponse(['success' => false, 'error' => 'يجب اختيار الكورس'], 400);

    $collectionTitle = null;
    if ($collectionId) {
        $stmtCol = $db->prepare("SELECT title FROM Collection WHERE id = ? AND courseId = ?");
        $stmtCol->execute([$collectionId, $courseId]);
        $collectionTitle = $stmtCol->fetchColumn();
        if (!$collectionTitle) {
            jsonResponse(['success' => false, 'error' => 'الحصة المحددة غير موجودة في هذا الكورس'], 404);
        }
    }

    $groupId = generateId(5);
    $expiresDate = date('Y-m-d H:i:s', time() + ($days * 86400));

    $db->prepare("INSERT INTO CodeGroup (id, courseId, collectionId, expiresAt, maxUses) VALUES (?, ?, ?, ?, ?)")->execute([$groupId, $courseId, $collectionId, $expiresDate, 1]);

    $ins = $db->prepare("INSERT INTO CourseCode (id, code, courseId, collectionId, expires, accessTimes, codeGroupId) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $generated = [];

    for ($i = 0; $i < $count; $i++) {
        $cId = generateId(5);
        $codeStr = generateCode($prefix);
        try {
            $ins->execute([$cId, $codeStr, $courseId, $collectionId, $expiresDate, $accessTimes, $groupId]);
            $generated[] = $codeStr;
        } catch (Exception $e) {
            // In case of rare collision
            $codeStr = generateCode($prefix . 'X');
            $ins->execute([$cId, $codeStr, $courseId, $collectionId, $expiresDate, $accessTimes, $groupId]);
            $generated[] = $codeStr;
        }
    }

    $msg = $collectionId
        ? "تم توليد $count كود بنجاح لحصة: $collectionTitle"
        : "تم توليد $count كود بنجاح للكورس كاملاً";

    jsonResponse([
        'success' => true,
        'message' => $msg,
        'codes' => $generated,
        'isLesson' => !empty($collectionId),
        'collectionTitle' => $collectionTitle
    ]);
}

jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
