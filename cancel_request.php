<?php
// ไฟล์: cancel_request.php
session_start();
include 'db_connect.php';

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบว่ามี request_id ที่ถูกต้องส่งมาหรือไม่
if (!isset($_GET['request_id']) || !is_numeric($_GET['request_id'])) {
    // หากไม่มี request_id หรือไม่ถูกต้อง ให้เปลี่ยนเส้นทางกลับไปหน้าสรุป
    $_SESSION['error'] = "ไม่พบรายการแจ้งซ่อมที่ต้องการยกเลิก";
    header("Location: summary.php");
    exit;
}

$request_id = $_GET['request_id'];
$customer_id = $_SESSION['customer_id'];

// ใช้การจัดการ Transaction เพื่อความปลอดภัยของข้อมูล
$conn->begin_transaction();

try {
    // 1. ตรวจสอบความเป็นเจ้าของและสถานะของรายการก่อนทำการยกเลิก
    // เพิ่มการ JOIN เพื่อตรวจสอบว่ารายการซ่อมนี้เป็นของลูกค้าที่ล็อกอินอยู่จริง
    $sql_check = "SELECT sr.status FROM service_requests sr JOIN vehicles v ON sr.vehicle_id = v.vehicle_id WHERE sr.request_id = ? AND v.customer_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $request_id, $customer_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        throw new Exception("ไม่พบรายการแจ้งซ่อมหรือคุณไม่มีสิทธิ์ในการยกเลิกรายการนี้");
    }

    $request_data = $result_check->fetch_assoc();
    if ($request_data['status'] !== 'pending') {
        throw new Exception("ไม่สามารถยกเลิกรายการซ่อมนี้ได้ เนื่องจากสถานะไม่ได้อยู่ในระหว่างรอการตรวจสอบ");
    }
    
    // 2. อัปเดตสถานะเป็น 'cancelled'
    $sql_update = "UPDATE service_requests SET status = 'cancelled' WHERE request_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $request_id);

    if ($stmt_update->execute()) {
        $conn->commit(); // ยืนยันการเปลี่ยนแปลงในฐานข้อมูล
        $_SESSION['message'] = "ยกเลิกรายการแจ้งซ่อมสำเร็จแล้ว";
    } else {
        throw new Exception("เกิดข้อผิดพลาดในการยกเลิกรายการ: " . $stmt_update->error);
    }
    
    $stmt_check->close();
    $stmt_update->close();

} catch (Exception $e) {
    $conn->rollback(); // ยกเลิกการเปลี่ยนแปลงหากเกิดข้อผิดพลาด
    $_SESSION['error'] = $e->getMessage();
}

$conn->close();

// เปลี่ยนเส้นทางกลับไปที่หน้าสรุปรายการซ่อม
header("Location: summary.php");
exit;

?>
