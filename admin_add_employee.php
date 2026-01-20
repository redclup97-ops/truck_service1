<?php
 session_start();
 include 'db_connect.php'; 
 if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

 $admin_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'ผู้ดูแลระบบ';
 $message = '';
 $message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
     $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

     if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($role)) {
        $message = "ผิดพลาด! โปรดกรอกข้อมูลให้ครบทุกช่อง";
        $message_type = "error";
    } else {
         $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt_check_employees = $conn->prepare("SELECT email FROM employees WHERE email = ?");
        $stmt_check_employees->bind_param("s", $email);
        $stmt_check_employees->execute();
        $stmt_check_employees->store_result();

        $stmt_check_customers = $conn->prepare("SELECT email FROM customers WHERE email = ?");
        $stmt_check_customers->bind_param("s", $email);
        $stmt_check_customers->execute();
        $stmt_check_customers->store_result();

        if ($stmt_check_employees->num_rows > 0 || $stmt_check_customers->num_rows > 0) {
            $message = "ผิดพลาด! อีเมลนี้มีอยู่ในระบบแล้ว";
            $message_type = "error";
        } else {
             if ($role === 'customer') {
                $stmt = $conn->prepare("INSERT INTO customers (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);
            } else {  
                $stmt = $conn->prepare("INSERT INTO employees (first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $first_name, $last_name, $email, $hashed_password, $role);
            }
            
            if ($stmt->execute()) {
                $message = "สำเร็จ! เพิ่มผู้ใช้งานใหม่เรียบร้อยแล้ว";
                $message_type = "success";
            } else {
                $message = "ผิดพลาด! ไม่สามารถเพิ่มผู้ใช้งานใหม่ได้: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        $stmt_check_employees->close();
        $stmt_check_customers->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO Admin | เพิ่มผู้ใช้งานใหม่</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Kanit Font -->
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
            flex-grow: 1;
            padding: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center rounded-md">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>
            <div class="flex items-center space-x-8 text-sm font-semibold">
                <!-- Add a back button -->
                <a href="#" onclick="history.back()" class="text-white hover:text-red-600 transition-colors duration-300">ย้อนกลับ</a>
                
                <!-- Display the user's name with "ผู้ดูแลระบบ" as a fallback -->
                <div class="text-white">
                    สวัสดี , <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                </div>
                <a href="admin_dashboard.php" class="hover:text-red-600 transition-colors duration-300">แผงควบคุม</a>
                <a href="admin_logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-xl">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-8">เพิ่มผู้ใช้งานใหม่</h1>

            <!-- Message alert section -->
            <?php if (!empty($message)): ?>
            <div class="p-4 mb-4 rounded-lg
                <?php echo ($message_type == 'success') ? 'bg-green-100 text-green-700 border-green-500' : 'bg-red-100 text-red-700 border-red-500'; ?> border-l-4" role="alert">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            <?php endif; ?>

            <form method="post" action="admin_add_employee.php">
                <div class="mb-4">
                    <label for="first_name" class="block text-gray-700 font-semibold mb-2">ชื่อ:</label>
                    <input type="text" id="first_name" name="first_name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="last_name" class="block text-gray-700 font-semibold mb-2">นามสกุล:</label>
                    <input type="text" id="last_name" name="last_name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 font-semibold mb-2">อีเมล:</label>
                    <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-gray-700 font-semibold mb-2">รหัสผ่าน:</label>
                    <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-6">
                    <label for="role" class="block text-gray-700 font-semibold mb-2">บทบาท:</label>
                    <select id="role" name="role" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <!-- <option value="employee">ช่างซ่อม</option> -->
                        <option value="admin">แอดมิน</option>
                        <option value="customer">ลูกค้า</option>
                    </select>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-xl shadow-lg transition duration-300">
                        เพิ่มผู้ใช้งาน
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer Section -->
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy;  2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
