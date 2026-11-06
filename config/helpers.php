<?php
// ========================================================
// Global Helpers and Authentication Utilities
// ========================================================

function isHttps() {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
    if (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 443)) return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false) return true;
    return false;
}

function getClientIP() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 365 * 24 * 60 * 60; // 1 year session lifetime
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.cookie_lifetime', (string)$lifetime);
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => isHttps(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

require_once __DIR__ . '/db.php';

function setRememberToken($userId, $passwordHash) {
    $secretKey = 'MohamedYousef_SecretAuthKey_2026';
    $hash = hash_hmac('sha256', $userId . $passwordHash, $secretKey);
    $token = $userId . ':' . $hash;
    $expire = time() + (365 * 24 * 60 * 60); // 1 year
    $secure = isHttps();
    setcookie('my_remember_token', $token, [
        'expires' => $expire,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearRememberToken() {
    $secure = isHttps();
    setcookie('my_remember_token', '', [
        'expires' => time() - 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    if (isset($_COOKIE['my_remember_token'])) {
        unset($_COOKIE['my_remember_token']);
    }
}

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

function ensureDeviceColumnsExist($db) {
    static $checked = false;
    if ($checked || !$db) return;
    $checked = true;
    try {
        $cols = $db->query("SHOW COLUMNS FROM Device LIKE 'ip'")->fetchAll();
        if (empty($cols)) {
            @$db->exec("ALTER TABLE Device ADD COLUMN ip VARCHAR(45) NULL DEFAULT NULL AFTER os");
        }
    } catch (Exception $e) {}
    try {
        $cols = $db->query("SHOW COLUMNS FROM Device LIKE 'deviceName'")->fetchAll();
        if (empty($cols)) {
            @$db->exec("ALTER TABLE Device ADD COLUMN deviceName VARCHAR(150) NULL DEFAULT NULL AFTER os");
        }
    } catch (Exception $e) {}
}

function getDeviceDetails() {
    $input = getJsonInput();
    $rawUuid = trim($input['device_uuid'] ?? ($_POST['device_uuid'] ?? ($_SERVER['HTTP_X_DEVICE_UUID'] ?? ($_COOKIE['my_device_uuid'] ?? ''))));

    if (!preg_match('/^[a-zA-Z0-9_\-]{16,128}$/', $rawUuid)) {
        $rawUuid = bin2hex(random_bytes(16));
    }

    if (!headers_sent() && (empty($_COOKIE['my_device_uuid']) || $_COOKIE['my_device_uuid'] !== $rawUuid)) {
        setcookie('my_device_uuid', $rawUuid, [
            'expires' => time() + (10 * 365 * 24 * 60 * 60),
            'path' => '/',
            'secure' => isHttps(),
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }
    $_COOKIE['my_device_uuid'] = $rawUuid;

    $clientDevName = trim($input['device_name'] ?? ($_POST['device_name'] ?? ($_SERVER['HTTP_X_DEVICE_NAME'] ?? ($_COOKIE['my_device_name'] ?? ''))));
    if ($clientDevName) {
        $clientDevName = urldecode($clientDevName);
    }

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $os = 'Unknown OS';
    $deviceType = 'pc';
    if (strpos($ua, 'Windows NT 10.0') !== false) $os = 'Windows 10/11';
    elseif (strpos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (strpos($ua, 'Android') !== false) {
        $os = 'Android';
        $deviceType = 'phone';
    } elseif (strpos($ua, 'iPhone') !== false) {
        $os = 'iOS';
        $deviceType = 'phone';
    } elseif (strpos($ua, 'iPad') !== false) {
        $os = 'iPadOS';
        $deviceType = 'tablet';
    } elseif (strpos($ua, 'Macintosh') !== false || strpos($ua, 'Mac OS X') !== false) {
        $os = 'macOS';
        $deviceType = 'pc';
    } elseif (strpos($ua, 'CrOS') !== false) {
        $os = 'ChromeOS';
        $deviceType = 'laptop';
    } elseif (strpos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }

    if (strpos($ua, 'Tablet') !== false) {
        $deviceType = 'tablet';
    }

    $browser = 'Unknown Browser';
    if (strpos($ua, 'SamsungBrowser') !== false) $browser = 'Samsung Internet';
    elseif (strpos($ua, 'Edg/') !== false) $browser = 'Microsoft Edge';
    elseif (strpos($ua, 'Chrome') !== false && strpos($ua, 'Edg') === false) $browser = 'Chrome';
    elseif (strpos($ua, 'Safari') !== false && strpos($ua, 'Chrome') === false) $browser = 'Safari';
    elseif (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';
    elseif (strpos($ua, 'Opera') !== false || strpos($ua, 'OPR') !== false) $browser = 'Opera';

    $deviceName = $clientDevName;
    if (!$deviceName) {
        if ($os === 'Android') {
            if (preg_match('/SM-[A-Z0-9]+/i', $ua, $m)) {
                $deviceName = 'Samsung ' . $m[0];
            } elseif (preg_match('/Redmi[^;\)]+/i', $ua, $m)) {
                $deviceName = $m[0];
            } elseif (preg_match('/POCO[^;\)]+/i', $ua, $m)) {
                $deviceName = $m[0];
            } elseif (preg_match('/Android\s+[\d\.]+;\s*([^;\)]+?)(?:\s+Build|\))/i', $ua, $m)) {
                $deviceName = trim($m[1]);
            } else {
                $deviceName = 'هاتف أندرويد';
            }
        } elseif ($os === 'iOS') {
            $deviceName = (strpos($ua, 'iPad') !== false) ? 'آيباد Apple' : 'آيفون Apple';
        } elseif (strpos($os, 'Windows') !== false) {
            $deviceName = 'كمبيوتر ويندوز';
        } elseif ($os === 'macOS') {
            $deviceName = 'جهاز ماك';
        } else {
            $deviceName = 'جهاز متصفح';
        }
    }

    $ip = getClientIP();

    return [
        'uuid' => $rawUuid,
        'identifier' => hash('sha256', 'MY_DEV_' . $rawUuid),
        'deviceType' => $deviceType,
        'os' => $os,
        'browser' => $browser,
        'deviceName' => $deviceName,
        'ip' => $ip
    ];
}

function getDeviceIdentifier() {
    $dev = getDeviceDetails();
    return $dev['identifier'];
}

function bindUserDevice($userId, $devDetails = null) {
    $db = getDB();
    ensureDeviceColumnsExist($db);
    if ($devDetails === null) {
        $devDetails = getDeviceDetails();
    }

    $devId = $devDetails['identifier'];
    $newRowId = generateId(5);

    $chk = $db->prepare("SELECT id FROM Device WHERE userId = ? AND identifier = ? LIMIT 1");
    $chk->execute([$userId, $devId]);
    $existing = $chk->fetch();

    if ($existing) {
        try {
            $up = $db->prepare("UPDATE Device SET lastActiveAt = NOW(), ip = ?, deviceName = ?, browser = ?, os = ?, deviceType = ? WHERE id = ?");
            $up->execute([$devDetails['ip'], $devDetails['deviceName'], $devDetails['browser'], $devDetails['os'], $devDetails['deviceType'], $existing['id']]);
        } catch (Exception $e) {
            try {
                $up = $db->prepare("UPDATE Device SET lastActiveAt = NOW() WHERE id = ?");
                $up->execute([$existing['id']]);
            } catch (Exception $e2) {}
        }
        return $existing['id'];
    }

    try {
        $ins = $db->prepare("INSERT INTO Device (id, userId, identifier, deviceType, browser, os, deviceName, ip, createdAt, lastActiveAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $ins->execute([$newRowId, $userId, $devId, $devDetails['deviceType'], $devDetails['browser'], $devDetails['os'], $devDetails['deviceName'], $devDetails['ip']]);
        return $newRowId;
    } catch (Exception $e) {
        try {
            $ins = $db->prepare("INSERT INTO Device (id, userId, identifier, deviceType, browser, os, createdAt, lastActiveAt) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $ins->execute([$newRowId, $userId, $devId, $devDetails['deviceType'], $devDetails['browser'], $devDetails['os']]);
            return $newRowId;
        } catch (Exception $e2) {
            return null;
        }
    }
}

function updateDeviceActivity($deviceRowId, $devDetails = null) {
    $db = getDB();
    if ($devDetails === null) {
        $devDetails = getDeviceDetails();
    }
    try {
        $db->prepare("UPDATE Device SET lastActiveAt = NOW(), ip = ?, deviceName = COALESCE(NULLIF(?, ''), deviceName), browser = ?, os = ? WHERE id = ?")
           ->execute([$devDetails['ip'], $devDetails['deviceName'], $devDetails['browser'], $devDetails['os'], $deviceRowId]);
    } catch (Exception $e) {
        try {
            $db->prepare("UPDATE Device SET lastActiveAt = NOW() WHERE id = ?")->execute([$deviceRowId]);
        } catch (Exception $e2) {}
    }
}

function checkStudentDeviceAccess($userId, $devDetails = null, &$errorMsg = '') {
    $db = getDB();
    ensureDeviceColumnsExist($db);
    if ($devDetails === null) {
        $devDetails = getDeviceDetails();
    }

    $uStmt = $db->prepare("SELECT maxDevices, role FROM User WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $u = $uStmt->fetch();

    if (!$u || strtoupper($u['role'] ?? '') !== 'STUDENT') {
        return true;
    }

    // Bypass device restrictions when admin is impersonating for preview
    if (!empty($_SESSION['admin_impersonator_id'])) {
        return true;
    }

    $maxDevices = max(1, (int)($u['maxDevices'] ?? 1));
    $stmtDevs = $db->prepare("SELECT * FROM Device WHERE userId = ? ORDER BY lastActiveAt DESC");
    $stmtDevs->execute([$userId]);
    $allDevs = $stmtDevs->fetchAll();

    $devId = $devDetails['identifier'];

    // 1. Check if device is already registered for this student
    foreach ($allDevs as $d) {
        if ($d['identifier'] === $devId) {
            updateDeviceActivity($d['id'], $devDetails);
            return true;
        }
    }

    // 1.5. Smart Re-Identification for the Same Physical Device:
    // If student cleared cookies, opened another browser window, or incognito on the same device/network
    // (matching OS + Browser + IP), update existing device slot rather than falsely locking student out.
    if (!empty($devDetails['os']) && !empty($devDetails['browser']) && !empty($devDetails['ip'])) {
        foreach ($allDevs as $d) {
            if ($d['os'] === $devDetails['os'] && $d['browser'] === $devDetails['browser'] && $d['ip'] === $devDetails['ip']) {
                $db->prepare("UPDATE Device SET identifier = ?, lastActiveAt = NOW() WHERE id = ?")->execute([$devId, $d['id']]);
                return true;
            }
        }
    }

    // 2. Can we bind this device as a new registered device?
    if (count($allDevs) < $maxDevices) {
        bindUserDevice($userId, $devDetails);
        return true;
    }

    // 3. Exceeded allowed devices: reject login
    $errorMsg = 'لقد تجاوزت الحد الأقصى للأجهزة المسموح بها (' . $maxDevices . ' جهاز فقط). الحساب مقيد للعمل على جهازك المسجل فقط. إذا قمت بتغيير جهازك يرجى التواصل مع الدعم الفني أو المدرس لفك الربط: 01092037257';
    return false;
}

function getCurrentUser() {
    $db = getDB();

    // Auto-login from persistent remember token if session is empty
    if (empty($_SESSION['user_id']) && !empty($_COOKIE['my_remember_token'])) {
        $parts = explode(':', $_COOKIE['my_remember_token'], 2);
        if (count($parts) === 2) {
            $cUserId = $parts[0];
            $cHash = $parts[1];
            $uStmt = $db->prepare("SELECT * FROM User WHERE id = ? LIMIT 1");
            $uStmt->execute([$cUserId]);
            $candidate = $uStmt->fetch();
            if ($candidate && $candidate['status'] !== 'BANNED' && $candidate['status'] !== 'PENDING') {
                $secretKey = 'MohamedYousef_SecretAuthKey_2026';
                $expectedHash = hash_hmac('sha256', $candidate['id'] . $candidate['password'], $secretKey);
                if (hash_equals($expectedHash, $cHash)) {
                    $devDetails = getDeviceDetails();
                    $devErr = '';
                    if (checkStudentDeviceAccess($candidate['id'], $devDetails, $devErr)) {
                        $_SESSION['user_id'] = $candidate['id'];
                        $_SESSION['user_phone'] = $candidate['phone'];
                        $_SESSION['user_role'] = $candidate['role'];
                        $_SESSION['user_name'] = $candidate['fullName'] ?? $candidate['firstName'];
                    } else {
                        clearRememberToken();
                        return null;
                    }
                }
            }
        }
    }

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $db->prepare("SELECT * FROM User WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] === 'BANNED') {
        unset($_SESSION['user_id']);
        clearRememberToken();
        return null;
    }

    // If an admin is impersonating a student for live preview, bypass device checks
    if (!empty($_SESSION['admin_impersonator_id'])) {
        return $user;
    }

    // Strict single-device check for students
    if (strtoupper($user['role'] ?? '') === 'STUDENT') {
        $devDetails = getDeviceDetails();
        $devErr = '';
        if (!checkStudentDeviceAccess($user['id'], $devDetails, $devErr)) {
            unset($_SESSION['user_id']);
            clearRememberToken();
            $_SESSION['device_lock_error'] = $devErr;
            return null;
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

