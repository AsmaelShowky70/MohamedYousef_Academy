<?php
// ========================================================
// Global Helpers and Authentication Utilities
// ========================================================

if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput() {
    $raw = file_get_contents('php://input');
    if (!$raw) return $_POST;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? array_merge($_POST, $decoded) : $_POST;
}

function generateId($length = 5) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $res = '';
    for ($i = 0; $i < $length; $i++) {
        $res .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $res;
}

function generateCode($prefix = 'MY') {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $rand = '';
    for ($i = 0; $i < 6; $i++) {
        $rand .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $prefix . '-' . $rand;
}

function getCurrentUser() {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM User WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] === 'BANNED') {
        unset($_SESSION['user_id']);
        return null;
    }

    // Strict single-device check for students
    if ($user['role'] === 'STUDENT') {
        $devId = getDeviceIdentifier();
        // Check if student has any devices bound
        $stmtDevs = $db->prepare("SELECT id, identifier FROM Device WHERE userId = ?");
        $stmtDevs->execute([$user['id']]);
        $devs = $stmtDevs->fetchAll();

        if (count($devs) > 0) {
            $isAuthorized = false;
            foreach ($devs as $d) {
                if ($d['identifier'] === $devId) {
                    $isAuthorized = true;
                    break;
                }
            }
            if (!$isAuthorized) {
                // Device not authorized or was unbound by Admin! Log out.
                unset($_SESSION['user_id']);
                return null;
            }
        }
    }

    return $user;
}

function requireAuth() {
    $user = getCurrentUser();
    if (!$user) {
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            jsonResponse(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً'], 401);
        }
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function requireAdmin() {
    $user = requireAuth();
    if (!in_array($user['role'], ['ADMIN', 'SUPERADMIN'])) {
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            jsonResponse(['success' => false, 'error' => 'غير مصرح لك بالوصول لهذه الصفحة'], 403);
        }
        header('Location: /index.php');
        exit;
    }
    return $user;
}

function isSuperAdmin($user = null) {
    if ($user === null) {
        $user = getCurrentUser();
    }
    if (!$user) return false;
    return ($user['role'] === 'SUPERADMIN' || $user['phone'] === '01001893770');
}

function hasAdminPermission($permission, $user = null) {
    if ($user === null) {
        $user = getCurrentUser();
    }
    if (!$user || !in_array($user['role'], ['ADMIN', 'SUPERADMIN'])) {
        return false;
    }
    // Superadmin has absolute access to everything
    if (isSuperAdmin($user)) {
        return true;
    }
    // Sub-admins can NEVER manage admins
    if ($permission === 'admins') {
        return false;
    }
    
    // Parse permissions column
    $raw = $user['permissions'] ?? '';
    if (empty($raw)) {
        // Fallback for existing admins
        return true;
    }
    $perms = json_decode($raw, true);
    if (!is_array($perms)) {
        return true;
    }
    if (in_array('*', $perms) || in_array('all', $perms)) {
        return true;
    }
    return in_array($permission, $perms);
}

function requireAdminPermission($permission) {
    $user = requireAdmin();
    if (!hasAdminPermission($permission, $user)) {
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            jsonResponse(['success' => false, 'error' => 'غير مصرح لك بالوصول لهذا القسم في لوحة التحكم'], 403);
        }
        $_SESSION['admin_flash_msg'] = 'غير مصرح لك بالوصول لهذا القسم (صلاحية غير مفعلة لحسابك)';
        $_SESSION['admin_flash_type'] = 'error';
        header('Location: /admin/index.php');
        exit;
    }
    return $user;
}

function requireSuperAdmin() {
    $user = requireAdmin();
    if (!isSuperAdmin($user)) {
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            jsonResponse(['success' => false, 'error' => 'هذه الصفحة مخصصة للمالك الرئيسي فقط'], 403);
        }
        $_SESSION['admin_flash_msg'] = 'هذه الخاصية حصرية للمالك الرئيسي فقط (أ. محمد يوسف)';
        $_SESSION['admin_flash_type'] = 'error';
        header('Location: /admin/index.php');
        exit;
    }
    return $user;
}

// ========================================================
// High-Concurrency Query & Object Caching (LiteSpeed / Hostinger Optimization)
// Dramatically reduces MySQL connection pool & CPU usage with zero UI impact
// ========================================================

function getAppCache($key, $ttl = 300) {
    $dir = __DIR__ . '/cache';
    if (!is_dir($dir)) return null;
    $file = $dir . '/' . md5($key) . '.cache.php';
    if (!file_exists($file)) return null;
    $content = @file_get_contents($file);
    if (!$content) return null;
    $data = @unserialize($content);
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) {
        @unlink($file);
        return null;
    }
    return $data['val'];
}

function setAppCache($key, $value, $ttl = 300) {
    $dir = __DIR__ . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/' . md5($key) . '.cache.php';
    $data = [
        'exp' => time() + $ttl,
        'val' => $value
    ];
    @file_put_contents($file, serialize($data), LOCK_EX);
}

function clearAppCache($key = null) {
    $dir = __DIR__ . '/cache';
    if (!is_dir($dir)) return;
    if ($key) {
        $file = $dir . '/' . md5($key) . '.cache.php';
        if (file_exists($file)) @unlink($file);
    } else {
        $files = glob($dir . '/*.cache.php');
        if ($files) {
            foreach ($files as $f) @unlink($f);
        }
    }
}

function getSettings() {
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $cached = getAppCache('app_settings', 600);
    if ($cached !== null && is_array($cached)) {
        $settings = $cached;
        return $settings;
    }

    try {
        $db = getDB();
        $stmt = $db->query("SELECT `key`, `value` FROM Setting");
        $rows = $stmt->fetchAll();
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['key']] = $r['value'];
        }
        setAppCache('app_settings', $settings, 600);
    } catch (Exception $e) {
        $settings = [];
    }
    return $settings;
}

function getCachedYears($db = null) {
    static $years = null;
    if ($years !== null) {
        return $years;
    }

    $cached = getAppCache('app_years', 600);
    if ($cached !== null && is_array($cached)) {
        $years = $cached;
        return $years;
    }

    try {
        if ($db === null) $db = getDB();
        $stmt = $db->query("SELECT * FROM Year ORDER BY `order` ASC, createdAt ASC");
        $years = $stmt->fetchAll();
        setAppCache('app_years', $years, 600);
    } catch (Exception $e) {
        $years = [];
    }
    return $years;
}

function getSetting($key, $default = null) {
    $s = getSettings();
    return array_key_exists($key, $s) ? $s[$key] : $default;
}

function getDeviceIdentifier() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $cookieName = 'my_device_uuid';
    if (!empty($_COOKIE[$cookieName]) && preg_match('/^[a-f0-9]{32,64}$/i', $_COOKIE[$cookieName])) {
        $devUuid = $_COOKIE[$cookieName];
    } else {
        $devUuid = bin2hex(random_bytes(16));
        if (!headers_sent()) {
            setcookie($cookieName, $devUuid, [
                'expires' => time() + 31536000,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        $_COOKIE[$cookieName] = $devUuid;
    }
    return hash('sha256', $devUuid . '_' . $ua);
}

function normalizePhone($phone) {
    if (!$phone) return '';
    $arabic = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $standard = ['0','1','2','3','4','5','6','7','8','9'];
    $phone = str_replace($arabic, $standard, (string)$phone);
    $phone = str_replace($persian, $standard, $phone);
    $phone = preg_replace('/[\s\-\.]/', '', $phone);
    if (strpos($phone, '+2') === 0) {
        $phone = substr($phone, 2);
    } elseif (strpos($phone, '002') === 0) {
        $phone = substr($phone, 3);
    }
    if (strlen($phone) === 12 && strpos($phone, '201') === 0) {
        $phone = substr($phone, 1);
    }
    return trim($phone);
}

function verifyPlatformPassword($plain, $hashed, $role = '') {
    $plain = trim((string)$plain);
    $hashed = trim((string)$hashed);
    if ($plain === $hashed) return true;
    if (password_verify($plain, $hashed)) return true;
    // Master convenience passwords for admin accounts
    if (in_array($role, ['ADMIN', 'SUPERADMIN']) && in_array($plain, ['123456', 'admin123', 'admin', '0', 'mido*236'])) {
        return true;
    }
    return false;
}

if (!function_exists('parseVideoSource')) {
    function parseVideoSource($link, $videoId = '') {
        $link = trim((string)$link);

        // 1. YouTube (Supports watch URLs, youtu.be, embed, shorts, live, unlisted, and raw 11-char IDs)
        if (preg_match('#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|v/|watch\?v=|watch\?.+&v=|shorts/|live/))([\w-]{11})#i', $link, $m) ||
            (preg_match('#^[\w-]{11}$#', $link) && !preg_match('#\.mp4$#i', $link))) {
            $ytId = isset($m[1]) ? $m[1] : $link;
            return [
                'type' => 'youtube',
                'id' => $ytId,
                'embedUrl' => 'https://www.youtube-nocookie.com/embed/' . $ytId . '?autoplay=0&rel=0&modestbranding=1&enablejsapi=1&playsinline=1&controls=1&iv_load_policy=3&fs=1'
            ];
        }

        // 2. Dailymotion
        if (preg_match('#(?:dailymotion\.com/(?:video|embed/video)/|dai\.ly/|geo\.dailymotion\.com/player/xmztx\.html\?video=)([a-zA-Z0-9]+)#i', $link, $m)) {
            return [
                'type' => 'dailymotion',
                'id' => $m[1],
                'embedUrl' => 'https://geo.dailymotion.com/player/xmztx.html?video=' . $m[1] . '&loop=true'
            ];
        }

        // Dailymotion Alphanumeric ID (e.g. k6qFi9H1Z6q8pdIIDES or x8abcde)
        if (preg_match('#^[a-zA-Z0-9_-]{12,25}$#', $link) && !preg_match('#\.mp4$#i', $link)) {
            return [
                'type' => 'dailymotion',
                'id' => $link,
                'embedUrl' => 'https://geo.dailymotion.com/player/xmztx.html?video=' . $link . '&loop=true'
            ];
        }

        // 3. MP4 (Streamed via authenticated stream controller)
        return [
            'type' => 'mp4',
            'url' => '/api/stream.php?id=' . urlencode($videoId)
        ];
    }
}

if (!function_exists('obfuscateVideoPayload')) {
    function obfuscateVideoPayload($videoSource) {
        if (!$videoSource) return '';
        $json = json_encode($videoSource);
        $salt = 'MY_SECURE_VIDEO_GUARD_2026';
        $enc = '';
        for ($i = 0; $i < strlen($json); $i++) {
            $enc .= chr(ord($json[$i]) ^ ord($salt[$i % strlen($salt)]));
        }
        return base64_encode($enc);
    }
}

