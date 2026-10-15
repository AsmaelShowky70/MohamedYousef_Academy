<?php
// ========================================================
// Secure Video Stream Delivery Engine
// Protected by Session, Enrollment & Range Chunk Streaming
// ========================================================
require_once __DIR__ . '/../config/helpers.php';

// Disable error display to avoid corrupting binary stream
ini_set('display_errors', '0');
error_reporting(0);

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    die('Unauthorized');
}

$videoId = $_GET['id'] ?? '';
if (!$videoId) {
    http_response_code(400);
    die('Bad Request: Video ID required');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM Video WHERE id = ? LIMIT 1");
$stmt->execute([$videoId]);
$video = $stmt->fetch();

if (!$video) {
    http_response_code(404);
    die('Video not found');
}

$isAdmin = in_array($user['role'], ['ADMIN', 'SUPERADMIN']);
$hasAccess = $isAdmin;

if (!$hasAccess) {
    // 1. Check Course Enrollment
    if (!empty($video['courseId'])) {
        $stmtC = $db->prepare("SELECT 1 FROM _CourseToUser WHERE A = ? AND B = ? LIMIT 1");
        $stmtC->execute([$video['courseId'], $user['id']]);
        if ($stmtC->fetch()) {
            $hasAccess = true;
        }

        // Check if course is free
        if (!$hasAccess) {
            $stmtFree = $db->prepare("SELECT price FROM Course WHERE id = ?");
            $stmtFree->execute([$video['courseId']]);
            $price = $stmtFree->fetchColumn();
            if ($price !== false && (float)$price == 0.0) {
                $hasAccess = true;
            }
        }

        // Check active full CourseCode (collectionId is null)
        if (!$hasAccess) {
            $stmtCode = $db->prepare("SELECT 1 FROM CourseCode WHERE userId = ? AND courseId = ? AND (collectionId IS NULL OR collectionId = '') AND expires > NOW() LIMIT 1");
            $stmtCode->execute([$user['id'], $video['courseId']]);
            if ($stmtCode->fetch()) {
                $hasAccess = true;
            }
        }
    }

    // 2. Check Collection Enrollment (Direct or active single-lesson code)
    if (!$hasAccess && !empty($video['collectionId'])) {
        $stmtCol = $db->prepare("SELECT 1 FROM _CollectionToUser WHERE A = ? AND B = ? LIMIT 1");
        $stmtCol->execute([$video['collectionId'], $user['id']]);
        if ($stmtCol->fetch()) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            $stmtColCode = $db->prepare("SELECT 1 FROM CourseCode WHERE userId = ? AND collectionId = ? AND expires > NOW() LIMIT 1");
            $stmtColCode->execute([$user['id'], $video['collectionId']]);
            if ($stmtColCode->fetch()) {
                $hasAccess = true;
            }
        }
    }
}

if (!$hasAccess) {
    http_response_code(403);
    die('Forbidden: You are not enrolled in this course or lesson');
}

// Log video view
try {
    $vCheck = $db->prepare("SELECT id, viewCount FROM VideoView WHERE userId = ? AND videoId = ? LIMIT 1");
    $vCheck->execute([$user['id'], $video['id']]);
    $existingView = $vCheck->fetch();
    if ($existingView) {
        $db->prepare("UPDATE VideoView SET viewCount = viewCount + 1, updatedAt = NOW() WHERE id = ?")
           ->execute([$existingView['id']]);
    } else {
        $db->prepare("INSERT INTO VideoView (id, userId, videoId, courseId, viewCount, createdAt, updatedAt) VALUES (?, ?, ?, ?, 1, NOW(), NOW())")
           ->execute([generateId(5), $user['id'], $video['id'], $video['courseId']]);
    }
} catch (Exception $e) {
    // Ignore logging failures
}

$link = trim($video['link']);

// Resolve local file path
$baseDir = dirname(__DIR__);
$filePath = null;

if (file_exists($link)) {
    $filePath = $link;
} elseif (file_exists($baseDir . '/' . ltrim($link, '/\\'))) {
    $filePath = $baseDir . '/' . ltrim($link, '/\\');
} elseif (file_exists($baseDir . '/public/' . ltrim($link, '/\\'))) {
    $filePath = $baseDir . '/public/' . ltrim($link, '/\\');
} elseif (file_exists($baseDir . '/public/uploads/' . ltrim($link, '/\\'))) {
    $filePath = $baseDir . '/public/uploads/' . ltrim($link, '/\\');
}

if (!$filePath || !is_file($filePath)) {
    // If not local file, redirect if external URL
    if (filter_var($link, FILTER_VALIDATE_URL)) {
        header('Location: ' . $link);
        exit;
    }
    http_response_code(404);
    die('Video file source not found');
}

// Stream video with Partial Content Range support
$fp = @fopen($filePath, 'rb');
if (!$fp) {
    http_response_code(500);
    die('Could not open video stream');
}

$size   = filesize($filePath);
$length = $size;
$start  = 0;
$end    = $size - 1;

header("Content-Type: video/mp4");
header("Accept-Ranges: bytes");
header("Cache-Control: private, no-cache, no-store, must-revalidate");
header("Pragma: no-cache");

if (isset($_SERVER['HTTP_RANGE'])) {
    $c_start = $start;
    $c_end   = $end;

    list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
    if (strpos($range, ',') !== false) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes $start-$end/$size");
        exit;
    }
    if ($range === '-') {
        $c_start = $size - substr($range, 1);
    } else {
        $range  = explode('-', $range);
        $c_start = $range[0];
        $c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size - 1;
    }
    $c_end = ($c_end > $end) ? $end : $c_end;
    if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes $start-$end/$size");
        exit;
    }
    $start  = $c_start;
    $end    = $c_end;
    $length = $end - $start + 1;
    fseek($fp, $start);
    header('HTTP/1.1 206 Partial Content');
}

header("Content-Range: bytes $start-$end/$size");
header("Content-Length: " . $length);

$buffer = 1024 * 64; // 64KB chunk
while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
    if ($pos + $buffer > $end) {
        $buffer = $end - $pos + 1;
    }
    echo fread($fp, $buffer);
    flush();
}
fclose($fp);
exit;
