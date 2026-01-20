<?php
// ไฟล์: admin_delete_customer.php
session_start();

// Include database connection file
include 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Ensure the request method is GET and an ID is provided
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $customer_id = $_GET['id'];

    // Use a prepared statement to prevent SQL injection
    $sql = "DELETE FROM customers WHERE customer_id = ?";
    $stmt = $conn->prepare($sql);
    
    // Check if the statement was prepared successfully
    if ($stmt === false) {
        $message = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง: " . htmlspecialchars($conn->error);
        header("Location: admin_dashboard.php?message=" . urlencode($message));
        exit;
    }

    $stmt->bind_param("i", $customer_id);

    // Execute the statement
    if ($stmt->execute()) {
        $message = "ลบข้อมูลสมาชิก ID " . htmlspecialchars($customer_id) . " สำเร็จแล้ว!";
        header("Location: admin_dashboard.php?message=" . urlencode($message));
        exit;
    } else {
        $message = "เกิดข้อผิดพลาดในการลบข้อมูล: " . htmlspecialchars($stmt->error);
        header("Location: admin_dashboard.php?message=" . urlencode($message));
        exit;
    }
    
    $stmt->close();
} else {
    // If no ID is provided, redirect to the dashboard with an error
    $message = "ไม่พบ ID สมาชิกที่ต้องการลบ";
    header("Location: admin_dashboard.php?message=" . urlencode($message));
    exit;
}

$conn->close();
?>
