<?php
session_start();
include 'db_connect.php'; 

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$customer_id = $_SESSION['customer_id'];

// ดึงชื่อและนามสกุลจากฐานข้อมูลโดยตรงเพื่อความแม่นยำ
$user_full_name = '';
$sql_get_user_info = "SELECT first_name, last_name FROM customers WHERE customer_id = ?";
$stmt_get_user_info = $conn->prepare($sql_get_user_info);
$stmt_get_user_info->bind_param("i", $customer_id);
$stmt_get_user_info->execute();
$result_user_info = $stmt_get_user_info->get_result();
if ($result_user_info->num_rows > 0) {
    $user_data = $result_user_info->fetch_assoc();
    $user_full_name = htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']);
}
$stmt_get_user_info->close();


$request_id = $_GET['request_id'] ?? null;
if (!$request_id) {
    header("Location: summary.php");
    exit;
}

// ดึงข้อมูลรายการซ่อมที่ต้องการแก้ไข
$sql_fetch = "SELECT sr.*, v.brand, v.model, v.license_plate 
              FROM service_requests sr
              JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
              WHERE sr.request_id = ? AND v.customer_id = ?";
$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("ii", $request_id, $customer_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();
$request = $result_fetch->fetch_assoc();

if (!$request || $request['status'] != 'pending') {
    header("Location: summary.php");
    exit;
}

// โค้ดสำหรับประมวลผลการแก้ไขฟอร์ม
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_description = $_POST['description'];
    $new_appointment_date = $_POST['appointment_date'];

    $sql_update = "UPDATE service_requests SET description = ?, appointment_date = ? WHERE request_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssi", $new_description, $new_appointment_date, $request_id);

    if ($stmt_update->execute()) {
        header("Location: summary.php");
        exit;
    } else {
        $error = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $stmt_update->error;
    }
    }
$stmt_fetch->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขรายการแจ้งซ่อม</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="image/logo.png"/>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .main-content {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            flex-grow: 1;
            padding: 2rem;
        }
        .form-container {
            width: 100%;
            max-width: 600px;
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-section {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        .btn-accent {
            background-color: #3b82f6; /* Blue-500 */
            color: white;
        }
        .btn-accent:hover {
            background-color: #2563eb; /* Blue-600 */
        }
        .btn-primary {
            background-color: #dc2626; /* Red-600 */
            color: white;
        }
        .btn-primary:hover {
            background-color: #b91c1c; /* Red-700 */
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800">
    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center rounded-lg">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>
            <div class="flex items-center space-x-8 text-sm font-semibold">
                <div class="flex items-center space-x-4">
                    <?php if ($is_logged_in): ?>
                        <div class="text-white">
                            สวัสดี, <strong>คุณ <?php echo $user_full_name; ?></strong>
                        </div>
                        <a href="service_request.php" class="hover:text-red-600 transition-colors duration-300">นัดหมายเข้าซ่อม</a>
                        <a href="summary.php" class="hover:text-red-600 transition-colors duration-300">รายการซ่อม</a>
                        <a href="logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
                    <?php else: ?>
                        <a href="login.php" class="bg-red-600 text-white font-bold py-2 px-4 rounded-full shadow hover:bg-red-700 transition-colors duration-300">เข้าสู่ระบบ</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <div class="main-content">
        <div class="form-container">
            <h2>แก้ไขรายการแจ้งซ่อม</h2>
            <?php if(isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
            <form action="edit_request.php?request_id=<?php echo htmlspecialchars($request['request_id']); ?>" method="POST">
                <div class="form-section">
                    <h3>ข้อมูลรถยนต์</h3>
                    <p><strong>ยี่ห้อ:</strong> <?php echo htmlspecialchars($request['brand']); ?></p>
                    <p><strong>รุ่น:</strong> <?php echo htmlspecialchars($request['model']); ?></p>
                    <p><strong>ทะเบียน:</strong> <?php echo htmlspecialchars($request['license_plate']); ?></p>
                </div>
                
                <div class="form-section">
                    <h3>รายละเอียดการแจ้งซ่อม</h3>
                    <div class="form-group">
                        <label for="description">อาการที่แจ้งซ่อม</label>
                        <textarea id="description" name="description" rows="5" required><?php echo htmlspecialchars($request['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="appointment_date">วันที่ต้องการเข้ารับบริการ</label>
                        <input type="datetime-local" id="appointment_date" name="appointment_date" value="<?php echo date('Y-m-d\TH:i', strtotime($request['appointment_date'])); ?>" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-accent">บันทึกการแก้ไข</button>
                    <a href="summary.php" class="btn btn-primary">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy; 2024 Hino Motors Thailand. All rights reserved.</p>
        </div>
    </footer>
    
</body>
</html>
