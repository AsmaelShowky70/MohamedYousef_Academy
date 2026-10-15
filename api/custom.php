<?php
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');
$input = getJsonInput();
$action = $_GET['action'] ?? ($input['action'] ?? '');

$db = getDB();

// 1. GET student-codes
if ($action === 'student-codes') {
    $studentId = $_GET['studentId'] ?? ($input['studentId'] ?? '');
    if (!$studentId) {
        jsonResponse(['success' => false, 'error' => 'studentId is required'], 400);
    }

    $stmt = $db->prepare("
        SELECT cc.*, c.title AS courseTitle, c.price AS coursePrice,
               col.title AS collectionTitle, col.price AS collectionPrice
        FROM CourseCode cc
        LEFT JOIN Course c ON cc.courseId = c.id
        LEFT JOIN Collection col ON cc.collectionId = col.id
        WHERE cc.userId = ?
        ORDER BY cc.expires DESC
    ");
    $stmt->execute([$studentId]);
    $codes = $stmt->fetchAll();

    $now = time();
    $enriched = [];
    foreach ($codes as $c) {
        $expTime = strtotime($c['expires']);
        $diff = $expTime - $now;
        $remainingDays = $diff > 0 ? (int)ceil($diff / 86400) : 0;
        $isExpired = $diff <= 0;
        $isLesson = !empty($c['collectionId']);

        $enriched[] = [
            'id' => $c['id'],
            'code' => $c['code'],
            'courseId' => $c['courseId'],
            'courseTitle' => $c['courseTitle'] ?? 'كورس غير معروف',
            'coursePrice' => (float)($c['coursePrice'] ?? 0),
            'collectionId' => $c['collectionId'] ?? null,
            'collectionTitle' => $c['collectionTitle'] ?? null,
            'collectionPrice' => $isLesson ? (float)($c['collectionPrice'] ?? 0) : null,
            'isLesson' => $isLesson,
            'typeLabel' => $isLesson ? ('حصة: ' . ($c['collectionTitle'] ?? '')) : 'كورس كامل',
            'expires' => $c['expires'],
            'accessTimes' => (int)$c['accessTimes'],
            'boundDeviceIdentifier' => $c['boundDeviceIdentifier'],
            'isExpired' => $isExpired,
            'remainingDays' => $remainingDays,
            'isBound' => !empty($c['boundDeviceIdentifier'])
        ];
    }

    jsonResponse(['success' => true, 'codes' => $enriched]);
}

// 2. POST create-student-code
if ($action === 'create-student-code') {
    requireAdmin();

    $studentId = trim($input['studentId'] ?? '');
    $courseId = trim($input['courseId'] ?? '');
    $collectionId = !empty($input['collectionId']) ? trim($input['collectionId']) : null;
    $days = (int)($input['days'] ?? 30);
    $accessTimes = (int)($input['accessTimes'] ?? 10);
    $customCode = strtoupper(trim($input['code'] ?? ''));

    if (!$studentId) jsonResponse(['success' => false, 'error' => 'معرف الطالب مطلوب (studentId)'], 400);
    if (!$courseId) jsonResponse(['success' => false, 'error' => 'يجب اختيار الكورس (courseId)'], 400);
    if ($days <= 0) jsonResponse(['success' => false, 'error' => 'عدد الأيام يجب أن يكون رقماً أكبر من صفر'], 400);

    $stmtSt = $db->prepare("SELECT id, fullName, firstName, phone FROM User WHERE id = ?");
    $stmtSt->execute([$studentId]);
    $student = $stmtSt->fetch();
    if (!$student) jsonResponse(['success' => false, 'error' => 'الطالب غير موجود'], 404);

    $stmtCo = $db->prepare("SELECT id, title FROM Course WHERE id = ?");
    $stmtCo->execute([$courseId]);
    $course = $stmtCo->fetch();
    if (!$course) jsonResponse(['success' => false, 'error' => 'الكورس غير موجود'], 404);

    $collection = null;
    if ($collectionId) {
        $stmtCol = $db->prepare("SELECT id, title, price FROM Collection WHERE id = ? AND courseId = ?");
        $stmtCol->execute([$collectionId, $courseId]);
        $collection = $stmtCol->fetch();
        if (!$collection) jsonResponse(['success' => false, 'error' => 'الحصة المحددة غير موجودة في هذا الكورس'], 404);
    }

    $prefix = $collectionId ? 'HSA' : 'MY';
    if (!$customCode) {
        $customCode = generateCode($prefix);
        $stmtChk = $db->prepare("SELECT id FROM CourseCode WHERE code = ?");
        for ($i = 0; $i < 10; $i++) {
            $stmtChk->execute([$customCode]);
            if (!$stmtChk->fetch()) break;
            $customCode = generateCode($prefix);
        }
    } else {
        $stmtChk = $db->prepare("SELECT id FROM CourseCode WHERE code = ?");
        $stmtChk->execute([$customCode]);
        if ($stmtChk->fetch()) {
            jsonResponse(['success' => false, 'error' => 'هذا الكود مستخدم بالفعل في النظام، يرجى كتابة كود آخر'], 400);
        }
    }

    $expiresDate = date('Y-m-d H:i:s', time() + ($days * 86400));
    $codeId = generateId(5);

    $ins = $db->prepare("INSERT INTO CourseCode (id, code, courseId, collectionId, userId, expires, accessTimes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$codeId, $customCode, $courseId, $collectionId, $studentId, $expiresDate, $accessTimes]);

    if ($collectionId) {
        // Single lesson: link to _CollectionToUser
        $db->prepare("INSERT IGNORE INTO _CollectionToUser (A, B) VALUES (?, ?)")->execute([$collectionId, $studentId]);
    } else {
        // Full course: link to _CourseToUser
        $db->prepare("INSERT IGNORE INTO _CourseToUser (A, B) VALUES (?, ?)")->execute([$courseId, $studentId]);
    }

    jsonResponse([
        'success' => true,
        'code' => [
            'id' => $codeId,
            'code' => $customCode,
            'courseId' => $courseId,
            'courseTitle' => $course['title'],
            'collectionId' => $collectionId,
            'collectionTitle' => $collection ? $collection['title'] : null,
            'isLesson' => !empty($collectionId),
            'studentName' => $student['fullName'] ?? $student['firstName'],
            'expires' => $expiresDate,
            'accessTimes' => $accessTimes,
            'remainingDays' => $days
        ]
    ], 201);
}

// 3. DELETE delete-student-code
if ($action === 'delete-student-code') {
    requireAdmin();
    $codeId = $_GET['id'] ?? ($input['id'] ?? '');
    if (!$codeId) jsonResponse(['success' => false, 'error' => 'معرف الكود مطلوب (id)'], 400);

    $db->prepare("DELETE FROM CourseCode WHERE id = ?")->execute([$codeId]);
    jsonResponse(['success' => true, 'message' => 'تم حذف الكود وإلغاء الاشتراك بنجاح']);
}

// 4. POST unbind-student-code
if ($action === 'unbind-student-code') {
    requireAdmin();
    $codeId = $input['id'] ?? ($_GET['id'] ?? '');
    if (!$codeId) jsonResponse(['success' => false, 'error' => 'معرف الكود مطلوب (id)'], 400);

    $db->prepare("UPDATE CourseCode SET boundDeviceIdentifier = NULL WHERE id = ?")->execute([$codeId]);
    jsonResponse(['success' => true, 'message' => 'تم فك ربط الجهاز بنجاح']);
}

// 5. GET search-students
if ($action === 'search-students') {
    $q = trim($_GET['q'] ?? ($input['q'] ?? ''));
    if (!$q) jsonResponse(['success' => true, 'students' => []]);

    $like = '%' . $q . '%';
    $stmt = $db->prepare("
        SELECT id, firstName, fullName, phone, attendanceID
        FROM User
        WHERE role = 'STUDENT'
          AND (fullName LIKE ? OR firstName LIKE ? OR phone LIKE ? OR attendanceID LIKE ?)
        LIMIT 15
    ");
    $stmt->execute([$like, $like, $like, $like]);
    $students = $stmt->fetchAll();

    jsonResponse(['success' => true, 'students' => $students]);
}

// 6. POST activate-code / redeem-course-code (by Student)
if ($action === 'activate-code' || $action === 'redeem-course-code') {
    $user = requireAuth();
    $codeStr = strtoupper(trim($input['code'] ?? ''));

    if (!$codeStr) {
        jsonResponse(['success' => false, 'error' => 'يرجى إدخال كود التفعيل'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM CourseCode WHERE code = ? LIMIT 1");
    $stmt->execute([$codeStr]);
    $code = $stmt->fetch();

    if (!$code) {
        jsonResponse(['success' => false, 'error' => 'هذا الكود غير صحيح أو تم استخدامه'], 404);
    }

    if (strtotime($code['expires']) < time()) {
        jsonResponse(['success' => false, 'error' => 'هذا الكود انتهت صلاحيته'], 400);
    }

    $devId = getDeviceIdentifier();

    // Check if code was already bound to another student or another device
    if (!empty($code['userId']) && $code['userId'] !== $user['id']) {
        jsonResponse(['success' => false, 'error' => 'هذا الكود مخصص لحساب طالب آخر ولا يمكن استخدامه'], 403);
    }

    if (!empty($code['boundDeviceIdentifier']) && $code['boundDeviceIdentifier'] !== $devId) {
        jsonResponse(['success' => false, 'error' => 'هذا الكود مفعل بالفعل على جهاز آخر! إذا قمت بتغيير جهازك تواصل مع الأدمن لفك الربط'], 403);
    }

    // Activate and bind
    $upd = $db->prepare("UPDATE CourseCode SET userId = ?, boundDeviceIdentifier = ? WHERE id = ?");
    $upd->execute([$user['id'], $devId, $code['id']]);

    // Fetch course title
    $stmtC = $db->prepare("SELECT title FROM Course WHERE id = ?");
    $stmtC->execute([$code['courseId']]);
    $course = $stmtC->fetch();

    if (!empty($code['collectionId'])) {
        // SINGLE LESSON CODE
        $db->prepare("INSERT IGNORE INTO _CollectionToUser (A, B) VALUES (?, ?)")->execute([$code['collectionId'], $user['id']]);

        $stmtCol = $db->prepare("SELECT title FROM Collection WHERE id = ?");
        $stmtCol->execute([$code['collectionId']]);
        $collectionTitle = $stmtCol->fetchColumn();

        jsonResponse([
            'success' => true,
            'message' => 'تم تفعيل الحصة بنجاح! مبروك اشتراكك في: ' . ($collectionTitle ?: 'الحصة') . ' (كورس ' . ($course['title'] ?? '') . ')',
            'courseId' => $code['courseId'],
            'collectionId' => $code['collectionId'],
            'isLesson' => true
        ]);
    } else {
        // FULL COURSE CODE
        $db->prepare("INSERT IGNORE INTO _CourseToUser (A, B) VALUES (?, ?)")->execute([$code['courseId'], $user['id']]);

        jsonResponse([
            'success' => true,
            'message' => 'تم تفعيل الكورس بنجاح! مبروك اشتراكك في: ' . ($course['title'] ?? 'الكورس'),
            'courseId' => $code['courseId'],
            'isLesson' => false
        ]);
    }
}

// 7. POST pay-from-wallet
if ($action === 'pay-from-wallet') {
    $user = requireAuth();
    $courseId = trim($input['courseId'] ?? ($input['id'] ?? ''));
    $collectionId = !empty($input['collectionId']) ? trim($input['collectionId']) : null;

    if (!$courseId) {
        jsonResponse(['success' => false, 'error' => 'معرف الكورس مطلوب'], 400);
    }

    $cStmt = $db->prepare("SELECT * FROM Course WHERE id = ? LIMIT 1");
    $cStmt->execute([$courseId]);
    $course = $cStmt->fetch();

    if (!$course) {
        jsonResponse(['success' => false, 'error' => 'الكورس غير موجود'], 404);
    }

    $collection = null;
    if ($collectionId) {
        $colStmt = $db->prepare("SELECT * FROM Collection WHERE id = ? AND courseId = ? LIMIT 1");
        $colStmt->execute([$collectionId, $courseId]);
        $collection = $colStmt->fetch();
        if (!$collection) {
            jsonResponse(['success' => false, 'error' => 'الحصة غير موجودة في هذا الكورس'], 404);
        }
    }

    $price = $collection ? (float)$collection['price'] : (float)$course['price'];
    $currentBalance = (float)$user['walletBalance'];

    $targetName = $collection ? ('حصة: ' . $collection['title']) : ('كورس: ' . $course['title']);

    if ($currentBalance < $price) {
        jsonResponse(['success' => false, 'error' => 'رصيد محفظتك الحالي (' . number_format($currentBalance, 2) . ' ج.م) غير كافٍ لسعر ' . $targetName . ' (' . number_format($price, 2) . ' ج.م)'], 400);
    }

    // Deduct balance
    $db->prepare("UPDATE User SET walletBalance = walletBalance - ? WHERE id = ?")->execute([$price, $user['id']]);

    // Record wallet transaction
    $txId = generateId(5);
    $txType = $collection ? 'COLLECTION_PURCHASE' : 'WALLET_PAYMENT';
    $txDesc = 'شراء ' . $targetName;
    $refId = $collection ? $collectionId : $courseId;
    $txStmt = $db->prepare("INSERT INTO WalletTransaction (id, userId, amount, type, description, referenceId, createdAt) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $txStmt->execute([$txId, $user['id'], -$price, $txType, $txDesc, $refId]);

    // Create CourseCode
    $codeId = generateId(5);
    $devId = getDeviceIdentifier();
    $cCode = generateCode($collection ? 'WAL-H' : 'WAL');
    $insCode = $db->prepare("INSERT INTO CourseCode (id, code, courseId, collectionId, userId, expires, accessTimes, boundDeviceIdentifier) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 365 DAY), 99, ?)");
    $insCode->execute([$codeId, $cCode, $courseId, $collectionId, $user['id'], $devId]);

    if ($collection) {
        $db->prepare("INSERT IGNORE INTO _CollectionToUser (A, B) VALUES (?, ?)")->execute([$collectionId, $user['id']]);
        jsonResponse([
            'success' => true,
            'message' => 'تم شراء وتفعيل الحصة (' . $collection['title'] . ') بنجاح من المحفظة!',
            'courseId' => $courseId,
            'collectionId' => $collectionId,
            'isLesson' => true
        ]);
    } else {
        $db->prepare("INSERT IGNORE INTO _CourseToUser (A, B) VALUES (?, ?)")->execute([$courseId, $user['id']]);
        jsonResponse([
            'success' => true,
            'message' => 'تم شراء وتفعيل الكورس بنجاح من المحفظة!',
            'courseId' => $courseId,
            'isLesson' => false
        ]);
    }
}

// 8. POST check-vfc-payment
if ($action === 'check-vfc-payment') {
    $user = requireAuth();
    $senderPhone = trim($input['senderPhone'] ?? ($input['phone'] ?? ''));
    $courseId = trim($input['courseId'] ?? ($input['id'] ?? ''));
    $collectionId = !empty($input['collectionId']) ? trim($input['collectionId']) : null;

    if (!$senderPhone) {
        jsonResponse(['success' => false, 'error' => 'الرجاء إدخال رقم الهاتف المحول منه'], 400);
    }
    if (!$courseId) {
        jsonResponse(['success' => false, 'error' => 'الكورس مطلوب'], 400);
    }

    $cStmt = $db->prepare("SELECT * FROM Course WHERE id = ? LIMIT 1");
    $cStmt->execute([$courseId]);
    $course = $cStmt->fetch();

    if (!$course) {
        jsonResponse(['success' => false, 'error' => 'الكورس غير موجود'], 404);
    }

    $collection = null;
    if ($collectionId) {
        $colStmt = $db->prepare("SELECT * FROM Collection WHERE id = ? AND courseId = ? LIMIT 1");
        $colStmt->execute([$collectionId, $courseId]);
        $collection = $colStmt->fetch();
        if (!$collection) {
            jsonResponse(['success' => false, 'error' => 'الحصة غير موجودة في هذا الكورس'], 404);
        }
    }

    $price = $collection ? (float)$collection['price'] : (float)$course['price'];
    $codeId = generateId(5);
    $devId = getDeviceIdentifier();
    $cCode = generateCode($collection ? 'VFC-H' : 'VFC');

    // Create subscription code
    $insCode = $db->prepare("INSERT INTO CourseCode (id, code, courseId, collectionId, userId, expires, accessTimes, boundDeviceIdentifier) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 365 DAY), 99, ?)");
    $insCode->execute([$codeId, $cCode, $courseId, $collectionId, $user['id'], $devId]);

    // Record wallet transaction for records
    $txId = generateId(5);
    $targetName = $collection ? ('حصة: ' . $collection['title']) : ('كورس: ' . $course['title']);
    $txType = $collection ? 'COLLECTION_PURCHASE' : 'COURSE_PURCHASE';
    $refId = $collection ? $collectionId : $courseId;
    $txDesc = 'شراء عبر فودافون كاش من رقم: ' . $senderPhone . ' - ' . $targetName;
    $txStmt = $db->prepare("INSERT INTO WalletTransaction (id, userId, amount, type, description, referenceId, createdAt) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $txStmt->execute([$txId, $user['id'], -$price, $txType, $txDesc, $refId]);

    if ($collection) {
        $db->prepare("INSERT IGNORE INTO _CollectionToUser (A, B) VALUES (?, ?)")->execute([$collectionId, $user['id']]);
        jsonResponse([
            'success' => true,
            'message' => 'تم تأكيد التحويل وتفعيل الحصة (' . $collection['title'] . ') في حسابك!',
            'courseId' => $courseId,
            'collectionId' => $collectionId,
            'isLesson' => true
        ]);
    } else {
        $db->prepare("INSERT IGNORE INTO _CourseToUser (A, B) VALUES (?, ?)")->execute([$courseId, $user['id']]);
        jsonResponse([
            'success' => true,
            'message' => 'تم تأكيد التحويل بنجاح وتفعيل الكورس في حسابك!',
            'courseId' => $courseId,
            'isLesson' => false
        ]);
    }
}

// 9. POST remove-device
if ($action === 'remove-device') {
    $user = requireAuth();
    $deviceId = trim($input['deviceId'] ?? ($input['id'] ?? ''));

    if (!$deviceId) {
        jsonResponse(['success' => false, 'error' => 'معرف الجهاز مطلوب'], 400);
    }

    $del = $db->prepare("DELETE FROM Device WHERE id = ? AND userId = ?");
    $del->execute([$deviceId, $user['id']]);

    jsonResponse([
        'success' => true,
        'message' => 'تم إزالة الجهاز بنجاح من حسابك'
    ]);
}

jsonResponse(['success' => false, 'error' => 'Endpoint not found'], 404);

