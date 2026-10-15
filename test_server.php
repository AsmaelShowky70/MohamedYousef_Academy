<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config/db.php';

echo "<div style='font-family:sans-serif;direction:rtl;padding:25px;background:#f8fafc;border-radius:15px;max-width:750px;margin:20px auto;border:1px solid #cbd5e1;box-shadow:0 4px 15px rgba(0,0,0,0.06);'>";

// -------------------------------------------------------------
// 1. PHP Version
// -------------------------------------------------------------
echo "<h2 style='color:#0f766e;border-bottom:2px solid #0f766e;padding-bottom:6px;'>1. حالة تشغيل PHP على السيرفر</h2>";
echo "<p style='color:green;font-weight:bold;'>✓ سيرفر PHP يعمل بكفاءة! الإصدار الحالي: " . phpversion() . "</p>";

// -------------------------------------------------------------
// 2. Database Connection & Self-Healing
// -------------------------------------------------------------
echo "<h2 style='color:#0f766e;border-bottom:2px solid #0f766e;padding-bottom:6px;'>2. فحص وضبط قاعدة البيانات (InfinityFree MySQL)</h2>";

$autoFixMessage = '';
if (isset($_GET['action']) && $_GET['action'] === 'auto_fix') {
    try {
        $db = getDB();
        $sqlFile = __DIR__ . '/database/schema_and_data.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $db->exec($sql);
            $autoFixMessage = "<div style='padding:12px;background:#dcfce7;color:#166534;border:1px solid #86efac;border-radius:8px;font-weight:bold;margin:10px 0;'>✓ تم استيراد الجداول وإعادة بناء التوافق بنجاح تام!</div>";
        }
    } catch (Throwable $e) {
        $autoFixMessage = "<div style='padding:12px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:8px;font-weight:bold;margin:10px 0;'>خطأ أثناء الاستيراد: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

try {
    $db = getDB();
    echo "<p style='color:green;font-weight:bold;'>✓ تم الاتصال بقاعدة البيانات بنجاح تام!</p>";

    if ($autoFixMessage) {
        echo $autoFixMessage;
    }

    $existingTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p style='font-size:13px;color:#475569;'>الجداول الحالية في قاعدة البيانات (<strong>" . count($existingTables) . "</strong> جدول): " . implode(', ', array_slice($existingTables, 0, 10)) . (count($existingTables) > 10 ? '...' : '') . "</p>";

    if (empty($existingTables)) {
        echo "<div style='padding:15px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:10px;margin:15px 0;'>";
        echo "<p style='font-weight:bold;margin:0 0 10px;'>⚠️ قاعدة البيانات متصلة ولكنها فارغة حالياً (لم يتم استيراد الجداول بعد).</p>";
        echo "<a href='test_server.php?action=auto_fix' style='display:inline-block;padding:8px 16px;background:#d97706;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;'>اضغط هنا لاستيراد وإنشاء الجداول تلقائياً بضغطة زر واحدة 🚀</a>";
        echo "</div>";
    } else {
        // Try reading User table
        $hasUser = in_array('User', $existingTables, true) || in_array('user', $existingTables, true);
        if (!$hasUser) {
            echo "<div style='padding:15px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:10px;margin:15px 0;'>";
            echo "<p style='font-weight:bold;margin:0 0 10px;'>⚠️ جدول المستخدمين غير موجود.</p>";
            echo "<a href='test_server.php?action=auto_fix' style='display:inline-block;padding:8px 16px;background:#b91c1c;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;'>اضغط هنا لتثبيت قاعدة البيانات الآن 🚀</a>";
            echo "</div>";
        } else {
            // Auto repair views if needed
            ensureTableCaseCompatibility($db);

            $stmt = $db->query("SELECT id, fullName, role, phone FROM User LIMIT 10");
            $users = $stmt->fetchAll();
            echo "<p style='color:green;font-weight:bold;'>✓ تم فحص جدول المستخدمين وتوافق الحروف بنجاح تام!</p>";
            echo "<p style='font-size:13px;'>عرض عينة من الحسابات المسجلة:</p>";
            echo "<ul style='font-size:13px;line-height:1.7;'>";
            foreach ($users as $u) {
                echo "<li><strong>" . htmlspecialchars($u['fullName']) . "</strong> (" . htmlspecialchars($u['role']) . ") - <span dir='ltr'>" . htmlspecialchars($u['phone']) . "</span></li>";
            }
            echo "</ul>";
        }
    }
} catch (Throwable $e) {
    echo "<div style='padding:15px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:10px;margin:15px 0;'>";
    echo "<p style='font-weight:bold;margin:0 0 10px;'>✗ تنبيه بقاعدة البيانات: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='font-size:12px;margin:0 0 10px;'>يمكنك إصلاح وضبط الجداول واستيرادها تلقائياً بالضغط أدناه:</p>";
    echo "<a href='test_server.php?action=auto_fix' style='display:inline-block;padding:8px 16px;background:#0f766e;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;'>إصلاح واستيراد قاعدة البيانات تلقائياً الآن 🛠️</a>";
    echo "</div>";
}

// -------------------------------------------------------------
// 3. Folder & Files Check
// -------------------------------------------------------------
echo "<h2 style='color:#0f766e;border-bottom:2px solid #0f766e;padding-bottom:6px;'>3. فحص المجلدات والملفات</h2>";
$checks = [
    'config/db.php',
    'config/helpers.php',
    'database/schema_and_data.sql',
    'includes/header.php',
    'includes/footer.php',
    'public/css/app.css',
    'public/identity/logo.png'
];
echo "<ul style='font-size:13px;line-height:1.7;'>";
foreach ($checks as $c) {
    $exists = file_exists(__DIR__ . '/' . $c);
    echo "<li>" . $c . ": " . ($exists ? '<strong style="color:green;">موجود ✓</strong>' : '<strong style="color:red;">مفقود ✗</strong>') . "</li>";
}
echo "</ul>";

echo "<hr style='margin:25px 0;border:0;border-top:1px solid #e2e8f0;'>";
echo "<div style='text-align:center;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;'>";
echo "<a href='index.php' style='display:inline-block;padding:12px 24px;background:#0f766e;color:#fff;text-decoration:none;border-radius:10px;font-weight:bold;font-size:14px;'>الدخول للصفحة الرئيسية للمنصة</a>";
echo "<a href='test_server.php?action=auto_fix' style='display:inline-block;padding:12px 20px;background:#475569;color:#fff;text-decoration:none;border-radius:10px;font-weight:bold;font-size:14px;'>إعادة مزامنة وإصلاح الجداول ⚙️</a>";
echo "</div>";

echo "</div>";
