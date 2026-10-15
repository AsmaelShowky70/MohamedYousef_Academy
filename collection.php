<?php
require_once __DIR__ . '/config/helpers.php';

$db = getDB();
$collectionId = trim($_GET['id'] ?? '');

if (!$collectionId) {
    header("Location: /courses.php");
    exit;
}

// 1. Fetch Collection
$stmt = $db->prepare("SELECT id, courseId, title FROM Collection WHERE id = ? LIMIT 1");
$stmt->execute([$collectionId]);
$col = $stmt->fetch();

if (!$col) {
    header("Location: /courses.php");
    exit;
}

// 2. Fetch first video in this collection
$stmtV = $db->prepare("SELECT id FROM Video WHERE collectionId = ? ORDER BY `order` ASC, id ASC LIMIT 1");
$stmtV->execute([$col['id']]);
$firstVideo = $stmtV->fetch();

if ($firstVideo) {
    header("Location: /course.php?id=" . urlencode($col['courseId']) . "&v=" . urlencode($firstVideo['id']));
} else {
    header("Location: /course.php?id=" . urlencode($col['courseId']));
}
exit;
