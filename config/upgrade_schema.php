<?php
require_once __DIR__ . '/db.php';
$db = getDB();

echo "Running migrations...\n";

// 1. Add maxDevices, studentType, centerCode to User table
try {
    $cols = $db->query("SHOW COLUMNS FROM User LIKE 'maxDevices'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE User ADD COLUMN maxDevices INT(11) DEFAULT 1 AFTER role");
        echo "[✓] Column maxDevices added to User table.\n";
    } else {
        echo "[i] Column maxDevices already exists in User table.\n";
    }

    $cols = $db->query("SHOW COLUMNS FROM User LIKE 'studentType'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE User ADD COLUMN studentType ENUM('ONLINE', 'CENTER') NOT NULL DEFAULT 'ONLINE' AFTER role");
        $db->exec("CREATE INDEX idx_user_studentType ON User(studentType)");
        echo "[✓] Column studentType added to User table.\n";
    } else {
        echo "[i] Column studentType already exists in User table.\n";
    }

    $cols = $db->query("SHOW COLUMNS FROM User LIKE 'centerCode'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE User ADD COLUMN centerCode VARCHAR(100) NULL DEFAULT NULL AFTER studentType");
        $db->exec("CREATE INDEX idx_user_centerCode ON User(centerCode)");
        echo "[✓] Column centerCode added to User table.\n";
    } else {
        echo "[i] Column centerCode already exists in User table.\n";
    }

    $cols = $db->query("SHOW COLUMNS FROM User LIKE 'permissions'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE User ADD COLUMN permissions TEXT NULL DEFAULT NULL AFTER role");
        echo "[✓] Column permissions added to User table.\n";
    } else {
        echo "[i] Column permissions already exists in User table.\n";
    }
} catch (Exception $e) {
    echo "[!] Error adding columns to User: " . $e->getMessage() . "\n";
}

// 1.5 Add points column to Question table
try {
    $qCols = $db->query("SHOW COLUMNS FROM Question LIKE 'points'")->fetchAll();
    if (empty($qCols)) {
        $db->exec("ALTER TABLE Question ADD COLUMN points INT(11) NOT NULL DEFAULT 1 AFTER rightAnswer");
        echo "[✓] Column points added to Question table.\n";
    } else {
        echo "[i] Column points already exists in Question table.\n";
    }
} catch (Exception $e) {
    echo "[!] Error adding points to Question: " . $e->getMessage() . "\n";
}

// 2. Ensure default sections exist for all academic years
try {
    $years = $db->query("SELECT id, name FROM Year")->fetchAll();
    foreach ($years as $y) {
        $secCount = (int)$db->query("SELECT COUNT(*) FROM Section WHERE yearId = " . $db->quote($y['id']))->fetchColumn();
        if ($secCount === 0) {
            $s1Id = 'SEC_' . substr(md5($y['id'] . '_far'), 0, 5);
            $s2Id = 'SEC_' . substr(md5($y['id'] . '_shahr'), 0, 5);
            
            $insSec = $db->prepare("INSERT INTO Section (id, title, `order`, yearId) VALUES (?, ?, ?, ?)");
            $insSec->execute([$s1Id, 'نظام الفروع', 1, $y['id']]);
            $insSec->execute([$s2Id, 'نظام الأشهر', 2, $y['id']]);
            echo "[✓] Created default sections for year: {$y['name']}\n";
        }
    }
} catch (Exception $e) {
    echo "[!] Error checking/creating sections: " . $e->getMessage() . "\n";
}

// 3. Ensure GalleryImage table has all images from api/uploads and public/uploads
try {
    $upDir = __DIR__ . '/../api/uploads/';
    if (is_dir($upDir)) {
        $files = scandir($upDir);
        $insGal = $db->prepare("INSERT IGNORE INTO GalleryImage (id, url, title, createdAt) VALUES (?, ?, ?, NOW())");
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
                $url = '/api/uploads/' . $f;
                $chk = $db->prepare("SELECT id FROM GalleryImage WHERE url = ?");
                $chk->execute([$url]);
                if (!$chk->fetch()) {
                    $gId = 'G_' . substr(md5($f), 0, 6);
                    $title = pathinfo($f, PATHINFO_FILENAME);
                    $insGal->execute([$gId, $url, $title]);
                }
            }
        }
        echo "[✓] GalleryImage synced with uploads folder.\n";
    }
} catch (Exception $e) {
    echo "[!] Error syncing gallery: " . $e->getMessage() . "\n";
}

echo "Migrations completed.\n";
