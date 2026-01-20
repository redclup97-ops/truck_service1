<?php
session_start();
// ล้างตัวแปร session ทั้งหมด
$_SESSION = array();

// ถ้ามีการใช้ session ที่ถูกเก็บในคุกกี้ ให้ลบ session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย session
session_destroy();

// Redirect ไปยังหน้าล็อกอิน
header("Location: admin_login.php");
exit;
?>
