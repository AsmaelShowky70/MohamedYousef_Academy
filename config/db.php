<?php
// ========================================================
// إعدادات الاتصال بقاعدة البيانات (Database Connection)
// متوافق 100% مع: Hostinger - XAMPP المحلي - السيرفرات السحابية
// ========================================================

// --------------------------------------------------------
// [1] فحص ملف الإعدادات السري المخصص (فحص أكثر من مسار لتجنب أخطاء التسمية)
// --------------------------------------------------------
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
} elseif (file_exists(dirname(__DIR__) . '/db_config.php')) {
    require_once dirname(__DIR__) . '/db_config.php';
} elseif (file_exists(__DIR__ . '/db_config.php.txt')) {
    require_once __DIR__ . '/db_config.php.txt';
} elseif (file_exists(__DIR__ . '/db_config.sample.php')) {
    // في حال قام المستخدم بالتعديل داخل ملف sample مباشرة دون تغيير اسمه
    $sampleCode = @file_get_contents(__DIR__ . '/db_config.sample.php');
    if ($sampleCode && strpos($sampleCode, 'ضع_اسم_قاعدة_البيانات_هنا') === false) {
        require_once __DIR__ . '/db_config.sample.php';
    }
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
            $hasConfigFile = file_exists(__DIR__ . '/db_config.php') || file_exists(dirname(__DIR__) . '/db_config.php');
            $errMsg = 'تعذر الاتصال بقاعدة البيانات: ' . $e->getMessage();
            
            if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                die(json_encode(['success' => false, 'error' => $errMsg], JSON_UNESCAPED_UNICODE));
            } else {
                $statusNotice = '';
                if (!$hasConfigFile && DB_USER === 'root') {
                    $statusNotice = '<div style="background:#fff;padding:15px;border-radius:10px;margin:18px 0;text-align:right;font-size:13px;color:#1e293b;border:1px solid #fecaca;line-height:1.7;">
                        <strong style="color:#b91c1c;display:block;margin-bottom:6px;">⚠️ لم يتم العثور على ملف <code>config/db_config.php</code> على الاستضافة:</strong>
                        1. افتح <strong>File Manager</strong> في هوستينجر واذهب إلى <code>public_html/config</code>.<br>
                        2. ستجد ملفاً باسم <code>db_config.sample.php</code> قم بتغيير اسمه إلى <code>db_config.php</code>.<br>
                        3. افتحه وضع اسم قاعدة البيانات واسم المستخدم والباسورد التي أنشأتها في هوستينجر ثم اضغط Save.
                    </div>';
                } else {
                    $statusNotice = '<div style="background:#fff;padding:15px;border-radius:10px;margin:18px 0;text-align:right;font-size:13px;color:#1e293b;border:1px solid #fecaca;line-height:1.7;">
                        <strong style="color:#b91c1c;display:block;margin-bottom:6px;">⚠️ خطأ في مطابقة البيانات:</strong>
                        تم قراءة الملف ولكن خادم هوستينجر رفض المستخدم (<code>' . htmlspecialchars(DB_USER) . '</code>) أو كلمة المرور.<br>
                        يرجى مراجعة بيانات قاعدة البيانات في لوحة تحكم هوستينجر والتأكد من كتابة البادئة كاملة (مثل: <code>u123456789_...</code>).
                    </div>';
                }

                die('<div style="font-family:system-ui,-apple-system,sans-serif;direction:rtl;padding:30px;text-align:center;color:#b91c1c;background:#fee2e2;border:1px solid #f87171;border-radius:16px;margin:50px auto;max-width:650px;box-shadow:0 10px 25px rgba(0,0,0,0.05);">
                    <h2 style="margin-top:0;font-size:22px;color:#991b1b;">خطأ في الاتصال بقاعدة البيانات</h2>
                    <p style="font-size:14px;line-height:1.6;color:#7f1d1d;">' . htmlspecialchars($errMsg) . '</p>
                    ' . $statusNotice . '
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
