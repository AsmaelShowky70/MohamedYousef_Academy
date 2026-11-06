<?php
require_once __DIR__ . '/../config/helpers.php';
$db = getDB();

if (!empty($_SESSION['admin_impersonator_id'])) {
    $adminId = $_SESSION['admin_impersonator_id'];
    $studentId = $_SESSION['user_id'] ?? '';
    unset($_SESSION['admin_impersonator_id'], $_SESSION['admin_impersonator_name']);
    
    $uStmt = $db->prepare("SELECT * FROM User WHERE id = ?");
    $uStmt->execute([$adminId]);
    $adm = $uStmt->fetch();
    if ($adm && in_array($adm['role'], ['ADMIN', 'SUPERADMIN'])) {
        $_SESSION['user_id'] = $adm['id'];
        $_SESSION['user_phone'] = $adm['phone'];
        $_SESSION['user_role'] = $adm['role'];
        $_SESSION['user_name'] = $adm['fullName'] ?? $adm['firstName'];
    }
    
    if ($studentId) {
        header("Location: /admin/student_profile.php?id=" . urlencode($studentId));
    } else {
        header("Location: /admin/index.php");
    }
    exit;
}

header("Location: /admin/index.php");
exit;
