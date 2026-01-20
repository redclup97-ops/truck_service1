<?php
// ไฟล์: admin_edit_customer.php
session_start();

// Include database connection file
include 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$admin_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'ผู้ดูแลระบบ';
$message = '';
$customer = null;

// Handle form submission for updating customer data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = $_POST['customer_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($first_name) && !empty($last_name) && !empty($email)) {
        if (!empty($password)) {
            // Hash the new password before storing in the password_hash column
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE customers SET first_name = ?, last_name = ?, email = ?, password_hash = ? WHERE customer_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $hashed_password, $customer_id);
        } else {
            // Update only name and email if password field is empty
            $sql = "UPDATE customers SET first_name = ?, last_name = ?, email = ? WHERE customer_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $first_name, $last_name, $email, $customer_id);
        }
        
        if ($stmt->execute()) {
            $message = "แก้ไขข้อมูลสมาชิกสำเร็จแล้ว!";
        } else {
            $message = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $conn->error;
        }

        $stmt->close();
    } else {
        $message = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}

// Fetch customer data to display in the form
if (isset($_GET['id'])) {
    $customer_id = $_GET['id'];
    $sql = "SELECT customer_id, first_name, last_name, email FROM customers WHERE customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
    } else {
        $message = "ไม่พบข้อมูลสมาชิก";
    }

    $stmt->close();
} else {
    // Redirect if no ID is provided
    header("Location: admin_dashboard.php");
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO | แก้ไขข้อมูลสมาชิก</title>
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
        }
        .form-container {
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            padding: 2rem;
            background-color: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-primary {
            background-color: #dc2626;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #b91c1c;
        }
        .btn-secondary {
            background-color: #6b7280;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-secondary:hover {
            background-color: #4b5563;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <!-- Header -->
    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center rounded-md">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>
            <div class="flex items-center space-x-8 text-sm font-semibold">
                <a href="admin_dashboard.php" class="text-white hover:text-red-600 transition-colors duration-300">แผงควบคุม</a>
                <div class="text-white">
                    สวัสดี , <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                </div>
                <a href="admin_logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="form-container">
            <h2 class="text-2xl font-bold text-center mb-6">แก้ไขข้อมูลสมาชิก</h2>
            
            <?php if (!empty($message)): ?>
                <div class="p-4 mb-4 text-sm text-center
                <?php echo strpos($message, 'สำเร็จ') !== false ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> 
                rounded-lg" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($customer): ?>
                <form action="admin_edit_customer.php?id=<?php echo htmlspecialchars($customer['customer_id']); ?>" method="POST" class="space-y-4">
                    <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customer['customer_id']); ?>">

                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">ชื่อจริง</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($customer['first_name']); ?>" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2">
                    </div>

                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">นามสกุล</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($customer['last_name']); ?>" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">อีเมล</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่านใหม่ (หากต้องการเปลี่ยน)</label>
                        <input type="password" id="password" name="password"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2">
                    </div>

                    <div class="flex justify-end space-x-4">
                        <a href="admin_dashboard.php" class="btn btn-secondary">ยกเลิก</a>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy; 2024 Hino Motors Thailand. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
