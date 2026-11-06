<?php
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');
$input = getJsonInput();
$action = $_GET['action'] ?? ($input['action'] ?? '');

$db = getDB();

if ($action === 'login') {
    $phone = trim($input['phone'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!$phone || $password === '') {
        jsonResponse(['success' => false, 'error' => 'يرجى إدخال رقم الهاتف وكلمة المرور'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM User WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'error' => 'بيانات الدخول غير صحيحة'], 401);
    }

    // Verify password: check plain text first, or password_verify (bcrypt)
    $validPassword = false;
    if ($user['password'] === $password) {
        $validPassword = true;
    } elseif (password_verify($password, $user['password'])) {
        $validPassword = true;
    }

    if (!$validPassword) {
        jsonResponse(['success' => false, 'error' => 'كلمة المرور غير صحيحة'], 401);
    }

    if ($user['status'] === 'BANNED') {
        jsonResponse(['success' => false, 'error' => 'هذا الحساب محظور من قبل الإدارة، يرجى التواصل مع الدعم'], 403);
    }

    if ($user['status'] === 'PENDING') {
        jsonResponse(['success' => false, 'error' => 'الحساب قيد المراجعة في انتظار موافقة الإدارة'], 403);
    }

    // Device binding check for students
    $devDetails = getDeviceDetails();
    $devError = '';
    if (!checkStudentDeviceAccess($user['id'], $devDetails, $devError)) {
        jsonResponse([
            'success' => false,
            'error' => $devError
        ], 403);
    }

    // Update lastLoginAt
    $db->prepare("UPDATE User SET lastLoginAt = NOW() WHERE id = ?")->execute([$user['id']]);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_phone'] = $user['phone'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['fullName'] ?? $user['firstName'];
    setRememberToken($user['id'], $user['password']);

    $redirect = in_array($user['role'], ['ADMIN', 'SUPERADMIN']) ? '/admin/index.php' : '/profile.php';

    jsonResponse([
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'redirect' => $redirect,
        'user' => [
            'id' => $user['id'],
            'fullName' => $user['fullName'],
            'firstName' => $user['firstName'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'status' => $user['status'],
            'studentType' => $user['studentType'] ?? 'ONLINE',
            'centerCode' => $user['centerCode'] ?? null,
            'yearId' => $user['yearId'],
            'walletBalance' => (float)$user['walletBalance']
        ]
    ]);
}

if ($action === 'register') {
    $fullName = trim($input['fullName'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $parentPhone = trim($input['parentPhone'] ?? '');
    $password = trim($input['password'] ?? '');
    $yearId = trim($input['yearId'] ?? '');
    $rawCenterCode = trim($input['centerCode'] ?? '');

    if (!$fullName || !$phone || !$parentPhone || !$password || !$yearId) {
        jsonResponse(['success' => false, 'error' => 'جميع الحقول مطلوبة لإتمام التسجيل'], 400);
    }

    $studentType = (!empty($rawCenterCode)) ? 'CENTER' : 'ONLINE';
    $centerCode = (!empty($rawCenterCode)) ? $rawCenterCode : null;

    // Check if phone already registered
    $stmt = $db->prepare("SELECT id FROM User WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'رقم الهاتف مسجل بالفعل في النظام! يرجى تسجيل الدخول'], 400);
    }

    $requireApproval = getSetting('requireApproval', 'false') === 'true';
    $status = $requireApproval ? 'PENDING' : 'ACTIVE';

    $userId = generateId(5);
    $firstName = explode(' ', $fullName)[0] ?? $fullName;

    // Attendance ID: generate next integer
    $stmtMax = $db->query("SELECT MAX(attendanceID) as maxId FROM User");
    $maxRow = $stmtMax->fetch();
    $nextAttendanceId = ($maxRow['maxId'] ?? 30000) + 1;

    $stmtIns = $db->prepare("INSERT INTO User (id, firstName, fullName, phone, parentPhone, password, role, status, studentType, centerCode, yearId, attendanceID, walletBalance, joinedAt) VALUES (?, ?, ?, ?, ?, ?, 'STUDENT', ?, ?, ?, ?, ?, 0, NOW())");
    $stmtIns->execute([$userId, $firstName, $fullName, $phone, $parentPhone, $password, $status, $studentType, $centerCode, $yearId, $nextAttendanceId]);

    // Register initial device for new student
    $devDetails = getDeviceDetails();
    bindUserDevice($userId, $devDetails);

    if ($status === 'ACTIVE') {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_phone'] = $phone;
        $_SESSION['user_role'] = 'STUDENT';
        $_SESSION['user_name'] = $fullName;
        setRememberToken($userId, $password);
        jsonResponse([
            'success' => true,
            'message' => 'تم إنشاء الحساب بنجاح! تم تسجيل دخولك تلقائياً',
            'redirect' => '/profile.php'
        ]);
    } else {
        jsonResponse([
            'success' => true,
            'message' => 'تم إنشاء الحساب بنجاح وهو قيد المراجعة من الإدارة لتفعيله',
            'redirect' => '/login.php'
        ]);
    }
}

if ($action === 'logout') {
    unset($_SESSION['user_id']);
    clearRememberToken();
    session_destroy();
    jsonResponse(['success' => true, 'redirect' => '/login.php']);
}

if ($action === 'me') {
    $user = getCurrentUser();
    if (!$user) {
        jsonResponse(['success' => false, 'user' => null]);
    }
    jsonResponse(['success' => true, 'user' => $user]);
}

jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
