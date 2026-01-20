<?php
session_start();
// ตรวจสอบว่ามีการล็อกอินเป็นแอดมินแล้วหรือไม่
if (isset($_SESSION['admin_loggedin']) && $_SESSION['admin_loggedin'] === true) {
    // ถ้ามี session แสดงว่าล็อกอินแล้ว ให้เด้งไปหน้า dashboard ทันที
    header("Location: admin_dashboard.php");
    exit;
}

$login_error = '';

// ตรวจสอบเมื่อมีการส่งฟอร์มล็อกอิน
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Include database connection file
    include 'db_connect.php'; 

    $email_input = $_POST['email'];
    $password_input = $_POST['password'];

    // เตรียมคำสั่ง SQL เพื่อป้องกัน SQL Injection และตรวจสอบบทบาทเป็น 'admin'
    $stmt = $conn->prepare("SELECT first_name, password_hash FROM employees WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", $email_input);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($db_first_name, $db_password_hash);
    
    // ตรวจสอบว่ามีผู้ใช้ในฐานข้อมูลหรือไม่
    if ($stmt->num_rows > 0) {
        $stmt->fetch();
        // แก้ไข: ใช้ password_verify() เพื่อตรวจสอบรหัสผ่านที่เข้ารหัสแล้ว
        if (password_verify($password_input, (string)$db_password_hash)) {
            $_SESSION['admin_loggedin'] = true;
            $_SESSION['admin_name'] = $db_first_name;
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $login_error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $login_error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO | ผู้ดูแลระบบ</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
            padding: 2rem;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            background-color: white;
            padding: 2.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
        }
        .btn {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.375rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            background-color: #dc2626; /* Red-600 */
            color: white;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #b91c1c; /* Red-700 */
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800">
    <!-- ส่วนหัว (Header) -->
    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <!-- Hino Logo -->
            <div class="h-10 bg-red-600 px-5 flex items-center">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>
            <!-- Navigation -->
            <div class="flex items-center space-x-8 text-sm font-semibold">
                <a href="home.php" class="hover:text-red-600 transition-colors duration-300">หน้าหลัก</a>
                <a href="login.php" class="hover:text-red-600 transition-colors duration-300">เข้าสู่ระบบ (ลูกค้า)</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="login-container">
            <h2 class="text-2xl font-bold text-center mb-6">เข้าสู่ระบบผู้ดูแลระบบ</h2>
            <?php if (!empty($login_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $login_error; ?></span>
                </div>
            <?php endif; ?>
            <form action="admin_login.php" method="POST">
                <div class="form-group">
                    <label for="email">อีเมล</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">เข้าสู่ระบบ</button>
            </form>
        </div>
    </main>
    
    <!-- ส่วนท้าย (Footer) -->
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy;  2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>
    
</body>
</html>