<?php
// ไฟล์: admin_delete_employee.php
session_start();

// เชื่อมต่อฐานข้อมูล
include 'db_connect.php';

// ตรวจสอบว่าผู้ใช้เป็นผู้ดูแลระบบหรือไม่
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// ตรวจสอบว่ามีการส่งค่า employee_id ผ่าน method GET หรือไม่
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $employee_id = $_GET['id'];

    // ใช้ prepared statement เพื่อป้องกัน SQL Injection
    $sql = "DELETE FROM employees WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    
    // ตรวจสอบความถูกต้องของการเตรียมคำสั่ง
    if ($stmt === false) {
        $message = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง: " . htmlspecialchars($conn->error);
        // header("Location: admin_dashboard.php?message=" . urlencode($message));
        exit;
    }

    $stmt->bind_param("i", $employee_id);

    // ทำการ execute คำสั่ง
    if ($stmt->execute()) {
        $message = "ลบข้อมูลพนักงาน ID " . htmlspecialchars($employee_id) . " สำเร็จแล้ว!";
        header("Location: admin_dashboard.php?message=" . urlencode($message));
        exit;
    } else {
        $message = "เกิดข้อผิดพลาดในการลบข้อมูล: " . htmlspecialchars($stmt->error);
        header("Location: admin_dashboard.php?message=" . urlencode($message));
        exit;
    }
    
    $stmt->close();
} else {
    // ถ้าไม่มี ID ที่ระบุ ให้เปลี่ยนเส้นทางกลับไปที่หน้า dashboard พร้อมข้อความแจ้งเตือน
    $message = "ไม่พบ ID พนักงานที่ต้องการลบ";
    header("Location: admin_dashboard.php?message=" . urlencode($message));
    exit;
}

$conn->close();
?>
