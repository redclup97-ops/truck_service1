<?php
// ไฟล์: edit_profile.php
session_start();
include 'db_connect.php'; 

// ตรวจสอบว่ามีการล็อกอินเป็นลูกค้าหรือไม่ ถ้าไม่ให้เปลี่ยนเส้นทางไปหน้า login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];
$message = '';
$message_type = '';
$customer = null;
$user_full_name = '';

// --- 1. ส่วนประมวลผลการส่งฟอร์ม (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ดึงและทำความสะอาดข้อมูล (Trim whitespace)
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']); 
    $password = trim($_POST['password']);
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        $message = "โปรดกรอก ชื่อ, นามสกุล, อีเมล และเบอร์โทรศัพท์ให้ครบถ้วน";
        $message_type = "error";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "รูปแบบอีเมลไม่ถูกต้อง";
        $message_type = "error";
    } else {
        // อัปเดตข้อมูลส่วนตัว
        if (!empty($password)) {
            // ถ้ามีการกรอกรหัสผ่านใหม่ ให้อัปเดตพร้อมรหัสผ่านที่ถูกแฮช
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_update = "UPDATE customers SET first_name = ?, last_name = ?, email = ?, phone = ?, password_hash = ? WHERE customer_id = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $hashed_password, $customer_id);
        } else {
            // ถ้าไม่มีการกรอกรหัสผ่านใหม่ ให้อัปเดตเฉพาะข้อมูลอื่น
            $sql_update = "UPDATE customers SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE customer_id = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $customer_id);
        }

        if ($stmt->execute()) {
            // อัปเดต session ด้วยข้อมูลใหม่
            $_SESSION['name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            
            $message = "บันทึกข้อมูลส่วนตัวสำเร็จแล้ว!";
            $message_type = "success";
        } else {
            $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . htmlspecialchars($stmt->error);
            $message_type = "error";
        }
        $stmt->close();
    }
}

// --- 2. ส่วนดึงข้อมูลลูกค้าปัจจุบันเพื่อแสดงในฟอร์ม (Fetch Current Customer Data) ---
$sql_fetch = "SELECT first_name, last_name, email, phone FROM customers WHERE customer_id = ?";
$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("i", $customer_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

if ($result_fetch->num_rows === 1) {
    $customer = $result_fetch->fetch_assoc();
    $user_full_name = htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']);
} else {
    // กรณีที่ไม่ควรเกิดขึ้น
    session_destroy();
    header("Location: login.php");
    exit;
}
$stmt_fetch->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลส่วนตัว</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="image/logo.png"/>
    <style>
        body { font-family: 'Kanit', sans-serif; }
        .btn-primary { 
            background-color: #ef4444; /* red-500 */
            color: white; 
            padding: 0.5rem 1.5rem; 
            border-radius: 0.375rem; 
            font-weight: 600; 
            transition: background-color 0.2s;
        }
        .btn-primary:hover { background-color: #dc2626; } /* red-600 */
        .btn-secondary { 
            background-color: #6b7280; /* gray-500 */
            color: white; 
            padding: 0.5rem 1.5rem; 
            border-radius: 0.375rem; 
            font-weight: 600; 
            transition: background-color 0.2s;
        }
        .btn-secondary:hover { background-color: #4b5563; } /* gray-600 */
    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex flex-col min-h-screen">

    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center rounded-lg">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>

            <div class="flex items-center space-x-8 text-sm font-semibold">
                <ul class="flex space-x-8">
                </ul>

                <div class="flex items-center space-x-4">
                    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                        <div class="text-white">
                            สวัสดี , <strong>คุณ <?php echo $user_full_name; ?></strong>
                        </div>
                        <a href="edit_profile.php" class="text-yellow-400 hover:text-red-600 transition-colors duration-300 border-b-2 border-yellow-400">
                            แก้ไขข้อมูล
                        </a>
                        <!-- <a href="vehicle_management.php" class="hover:text-yellow-400 transition-colors duration-300">จัดการรถยนต์</a> -->
                        <a href="service_request.php" class="hover:text-red-600 transition-colors duration-300">นัดหมายเข้าซ่อม</a>
                        <a href="summary.php" class="hover:text-red-600 transition-colors duration-300">รายการซ่อม</a>
                        <a href="logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <main class="flex-grow container mx-auto px-6 py-10">
        <div class="max-w-xl mx-auto bg-white p-8 rounded-xl shadow-2xl">
            <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-3">แก้ไขข้อมูลส่วนตัว</h1>
            
            <?php if (!empty($message)): ?>
                <div class="p-4 mb-4 text-sm rounded-lg 
                    <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($customer): ?>
                <form action="edit_profile.php" method="POST" class="space-y-6">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">ชื่อ</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($customer['first_name']); ?>" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border">
                        </div>
                        
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">นามสกุล</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($customer['last_name']); ?>" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">อีเมล</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border">
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border">
                    </div>

                    <hr class="border-t border-gray-200">

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่านใหม่ (หากต้องการเปลี่ยน)</label>
                        <input type="password" id="password" name="password" placeholder="เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border">
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-6">
                        <a href="summary.php" class="btn btn-secondary">ยกเลิก</a>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy; 2024 Hino Motors Thailand. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>