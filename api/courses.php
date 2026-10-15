<?php
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');
$input = getJsonInput();
$action = $_GET['action'] ?? ($input['action'] ?? 'list');

$db = getDB();
$user = getCurrentUser();

if ($action === 'years') {
    $stmt = $db->query("
        SELECT y.*, 
               (SELECT COUNT(*) FROM Course c JOIN Section s ON c.sectionId = s.id WHERE s.yearId = y.id) as coursesCount
        FROM Year y
        ORDER BY y.order ASC, y.name ASC
    ");
    $years = $stmt->fetchAll();
    jsonResponse(['success' => true, 'years' => $years]);
}

if ($action === 'list') {
    $yearId = $_GET['yearId'] ?? '';
    $sectionId = $_GET['sectionId'] ?? '';

    $sql = "
        SELECT c.*, s.title AS sectionTitle, y.name AS yearName, y.id AS yearId
        FROM Course c
        LEFT JOIN Section s ON c.sectionId = s.id
        LEFT JOIN Year y ON s.yearId = y.id
        WHERE c.published = 1
    ";
    $params = [];

    if ($yearId) {
        $sql .= " AND y.id = ?";
        $params[] = $yearId;
    }
    if ($sectionId) {
        $sql .= " AND s.id = ?";
        $params[] = $sectionId;
    }

    $sql .= " ORDER BY c.order ASC, c.createdAt DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();

    // Check enrollment for current student
    $enrolledCourseIds = [];
    if ($user) {
        $stmtEnr = $db->prepare("SELECT A FROM _CourseToUser WHERE B = ?");
        $stmtEnr->execute([$user['id']]);
        $enrolledCourseIds = $stmtEnr->fetchAll(PDO::FETCH_COLUMN);
    }

    foreach ($courses as &$c) {
        $c['isEnrolled'] = in_array($c['id'], $enrolledCourseIds);
        $c['price'] = (float)$c['price'];
    }

    jsonResponse(['success' => true, 'courses' => $courses]);
}

if ($action === 'get') {
    $id = $_GET['id'] ?? ($input['id'] ?? '');
    if (!$id) jsonResponse(['success' => false, 'error' => 'معرف الكورس مطلوب'], 400);

    $stmt = $db->prepare("
        SELECT c.*, s.title AS sectionTitle, y.name AS yearName, y.id AS yearId
        FROM Course c
        LEFT JOIN Section s ON c.sectionId = s.id
        LEFT JOIN Year y ON s.yearId = y.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $course = $stmt->fetch();
    if (!$course) jsonResponse(['success' => false, 'error' => 'الكورس غير موجود'], 404);

    $isEnrolled = false;
    $isAdmin = $user && in_array($user['role'], ['ADMIN', 'SUPERADMIN']);
    if ($user) {
        $stmtChk = $db->prepare("SELECT 1 FROM _CourseToUser WHERE A = ? AND B = ? LIMIT 1");
        $stmtChk->execute([$id, $user['id']]);
        $isEnrolled = (bool)$stmtChk->fetch();
    }

    // Collections
    $stmtCol = $db->prepare("SELECT * FROM Collection WHERE courseId = ? ORDER BY `order` ASC, id ASC");
    $stmtCol->execute([$id]);
    $collections = $stmtCol->fetchAll();

    foreach ($collections as &$col) {
        // Videos
        $stmtVid = $db->prepare("SELECT id, title, description, link, `order`, published FROM Video WHERE collectionId = ? AND (published = 1 OR ?) ORDER BY `order` ASC");
        $stmtVid->execute([$col['id'], $isAdmin ? 1 : 0]);
        $col['videos'] = $stmtVid->fetchAll();

        // Files
        $stmtFiles = $db->prepare("SELECT id, title, description, link, `order`, published FROM File WHERE collectionId = ? AND (published = 1 OR ?) ORDER BY `order` ASC");
        $stmtFiles->execute([$col['id'], $isAdmin ? 1 : 0]);
        $col['files'] = $stmtFiles->fetchAll();

        // Quizzes
        $stmtQ = $db->prepare("SELECT id, title, description, quizTime, `order`, published FROM Quiz WHERE collectionId = ? AND (published = 1 OR ?) ORDER BY `order` ASC");
        $stmtQ->execute([$col['id'], $isAdmin ? 1 : 0]);
        $col['quizzes'] = $stmtQ->fetchAll();
    }

    $course['isEnrolled'] = $isEnrolled || $isAdmin;
    $course['collections'] = $collections;

    jsonResponse(['success' => true, 'course' => $course]);
}

jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
