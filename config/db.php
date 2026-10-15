<?php
// ========================================================
// إعدادات الاتصال بقاعدة البيانات (Database Connection)
// متوافق 100% مع: Hostinger - XAMPP المحلي - السيرفرات السحابية
// ========================================================

// --------------------------------------------------------
// [1] فحص ملف الإعدادات السري المخصص (إذا كان موجوداً على السيرفر)
// --------------------------------------------------------
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
}

// --------------------------------------------------------
// [2] فحص البيئة تلقائياً (Localhost vs Hostinger Live)
// --------------------------------------------------------
$isLocal = false;
$serverHost = $_SERVER['HTTP_HOST'] ?? '';
$serverAddr = $_SERVER['SERVER_ADDR'] ?? '';

if (php_sapi_name() === 'cli' || 
    strpos($serverHost, 'localhost') !== false || 
    strpos($serverHost, '127.0.0.1') !== false || 
    $serverAddr === '127.0.0.1' || 
    $serverAddr === '::1') {
    $isLocal = true;
}

if ($isLocal) {
    // بيئة التطوير المحلية (Local XAMPP)
    defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'mohamed_youssef');
    defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'root');
    defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    defined('DB_PORT') or define('DB_PORT', getenv('DB_PORT') ?: '3306');
} else {
    // بيئة هوستينجر المباشرة (Hostinger Live Server)
    // ملاحظة: في هوستينجر يكون DB_HOST دائماً 'localhost'
    defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'mohamed_youssef');
    defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'root');
    defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    defined('DB_PORT') or define('DB_PORT', getenv('DB_PORT') ?: '3306');
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            ensureTableCaseCompatibility($pdo);
        } catch (PDOException $e) {
            $errMsg = 'تعذر الاتصال بقاعدة البيانات. تأكد من صحة بيانات السيرفر في config/db.php: ' . $e->getMessage();
            if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                die(json_encode(['success' => false, 'error' => $errMsg], JSON_UNESCAPED_UNICODE));
            } else {
                die('<div style="font-family:sans-serif;direction:rtl;padding:30px;text-align:center;color:#b91c1c;background:#fee2e2;border:1px solid #f87171;border-radius:12px;margin:50px auto;max-width:600px;">
                    <h2 style="margin-top:0;">خطأ في الاتصال بقاعدة البيانات</h2>
                    <p style="font-size:14px;line-height:1.6;">' . htmlspecialchars($errMsg) . '</p>
                    <p style="font-size:12px;color:#6b7280;">يرجى مراجعة ملف <code>config/db.php</code> والتأكد من إدخال بيانات الاستضافة الصحيحة (Host, Database, User, Password).</p>
                </div>');
            }
        }
    }
    return $pdo;
}

/**
 * يضمن التوافق التام لأسماء الجداول بين Linux (حساس لحالة الأحرف مثل InfinityFree) و Windows
 * وينشئ الجداول تلقائياً إذا كانت قاعدة البيانات فارغة تماماً.
 */
function ensureTableCaseCompatibility($pdo) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        // إذا كانت قاعدة البيانات فارغة، استيراد schema_and_data.sql تلقائياً
        if (empty($tables)) {
            $sqlFile = __DIR__ . '/../database/schema_and_data.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                $pdo->exec($sqlContent);
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        $tableMap = [
            'user' => 'User',
            'course' => 'Course',
            'year' => 'Year',
            'section' => 'Section',
            'collection' => 'Collection',
            'device' => 'Device',
            'quiz' => 'Quiz',
            'quizattempt' => 'QuizAttempt',
            'question' => 'Question',
            'video' => 'Video',
            'videoview' => 'VideoView',
            'coursecode' => 'CourseCode',
            '_coursetouser' => '_CourseToUser',
            '_collectiontouser' => '_CollectionToUser',
            'galleryimage' => 'GalleryImage',
            'attendance' => 'Attendance',
            'codegroup' => 'CodeGroup',
            'file' => 'File',
            'progress' => 'Progress',
            'setting' => 'Setting',
            'wallettransaction' => 'WalletTransaction'
        ];

        foreach ($tableMap as $lower => $pascal) {
            $hasLower = in_array($lower, $tables, true);
            $hasPascal = in_array($pascal, $tables, true);

            if ($hasLower && !$hasPascal) {
                // إذا كان الجدول مستورد بحروف صغيرة على لينكس، نقوم بإعادة تسميته ليطابق تماماً
                try {
                    $pdo->exec("RENAME TABLE `$lower` TO `$pascal`");
                } catch (Throwable $e) {
                    // تم تجاهل أي خطأ لضمان عدم توقف الصفحة
                }
            }
        }
    } catch (Throwable $e) {
        // تجاهل أي استثناء حتى لا يتعطل الموقع
    }
}
