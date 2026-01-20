<?php
// ไฟล์: admin_edit_employee.php
session_start();
include 'db_connect.php';

// ตรวจสอบว่าผู้ใช้เป็นผู้ดูแลระบบหรือไม่
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$employee = null;
$message = '';

// ส่วนที่ 1: ประมวลผลการส่งแบบฟอร์ม (เมื่อผู้ใช้กดบันทึก)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $employee_id = $_POST['employee_id'];
    // *** แก้ไข: รับ first_name และ last_name แยกกันจากฟอร์ม ***
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // ตรวจสอบว่ามีการเปลี่ยนบทบาทเป็นลูกค้าหรือไม่
    if ($role === 'customer') {
        // ขั้นตอนที่ 1: ดึงรหัสผ่านปัจจุบันของพนักงาน
        $sql_fetch = "SELECT password_hash FROM employees WHERE employee_id = ?";
        $stmt_fetch = $conn->prepare($sql_fetch);
        $stmt_fetch->bind_param("i", $employee_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        $current_data = $result_fetch->fetch_assoc();
        $stmt_fetch->close();

        // ใช้รหัสผ่านใหม่ถ้ามีการกรอกมา หรือใช้รหัสผ่านเดิมถ้าไม่ได้แก้ไข
        $password_to_use = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : $current_data['password_hash'];
        
        // ขั้นตอนที่ 2: เพิ่มข้อมูลเข้าไปในตาราง customers
        $sql_insert = "INSERT INTO customers (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("ssss", $first_name, $last_name, $email, $password_to_use);
        
        if ($stmt_insert->execute()) {
            $stmt_insert->close();
            
            // ขั้นตอนที่ 3: ลบข้อมูลออกจากตาราง employees
            $sql_delete = "DELETE FROM employees WHERE employee_id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $employee_id);
            
            if ($stmt_delete->execute()) {
                $message = "เปลี่ยนบทบาทพนักงาน ID " . htmlspecialchars($employee_id) . " เป็นลูกค้าเรียบร้อยแล้ว!";
            } else {
                $message = "เกิดข้อผิดพลาดในการลบข้อมูลพนักงานเดิม: " . $conn->error;
            }
            $stmt_delete->close();
        } else {
            $message = "เกิดข้อผิดพลาดในการเพิ่มข้อมูลลูกค้า: " . $conn->error;
        }

    } else {
        // หากไม่ได้เปลี่ยนบทบาท (ยังคงเป็นพนักงาน)
        if (!empty($password)) {
            // หากมีการกรอกรหัสผ่านใหม่
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE employees SET first_name = ?, last_name = ?, email = ?, password_hash = ?, role = ? WHERE employee_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $first_name, $last_name, $email, $hashed_password, $role, $employee_id);
        } else {
            // หากไม่ได้กรอกรหัสผ่านใหม่
            $sql = "UPDATE employees SET first_name = ?, last_name = ?, email = ?, role = ? WHERE employee_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $role, $employee_id);
        }

        if ($stmt->execute()) {
            $message = "แก้ไขข้อมูลพนักงาน ID " . htmlspecialchars($employee_id) . " สำเร็จแล้ว!";
        } else {
            $message = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $conn->error;
        }
        $stmt->close();
    }
}

// ส่วนที่ 2: ดึงข้อมูลพนักงานเพื่อแสดงในแบบฟอร์ม (ก่อนการแก้ไข)
if (isset($_GET['id'])) {
    $employee_id = $_GET['id'];
    $sql = "SELECT employee_id, first_name, last_name, email, role FROM employees WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
        // $employee จะมี first_name และ last_name แยกกันอยู่แล้ว
    } else {
        $message = "ไม่พบข้อมูลพนักงานที่ต้องการแก้ไข";
    }
    $stmt->close();
} else {
    // หากไม่มี ID ที่ระบุ ให้เปลี่ยนเส้นทางกลับไปที่หน้า dashboard
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
    <title>HINO | แก้ไขข้อมูลพนักงาน</title>
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

    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center rounded-md">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>
            <div class="flex items-center space-x-8 text-sm font-semibold">
                <a href="admin_dashboard.php" class="text-white hover:text-red-600 transition-colors duration-300">แผงควบคุม</a>
                <div class="text-white">
                    สวัสดี , <strong><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'ผู้ดูแลระบบ'); ?></strong>
                </div>
                <a href="admin_logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="form-container">
            <h2 class="text-2xl font-bold text-center mb-6">แก้ไขข้อมูลพนักงาน</h2>
            
            <?php if (!empty($message)): ?>
                <div class="p-4 mb-4 text-sm text-center
                <?php echo strpos($message, 'สำเร็จ') !== false ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> 
                rounded-lg" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($employee): ?>
                <form action="admin_edit_employee.php?id=<?php echo htmlspecialchars($employee['employee_id']); ?>" method="POST" class="space-y-4">
                    <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>">

                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">ชื่อจริง</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2">
                    </div>

                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">นามสกุล</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">อีเมล</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่านใหม่ (หากต้องการเปลี่ยน)</label>
                        <input type="password" id="password" name="password"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2">
                    </div>
                    
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">บทบาท (ปัจจุบัน: <?php echo htmlspecialchars($employee['role']); ?>)</label>
                        <select id="role" name="role"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2">
                            <option value="admin" <?php echo $employee['role'] == 'admin' ? 'selected' : ''; ?>>แอดมิน (Admin)</option>
                            <option value="technician" <?php echo $employee['role'] == 'technician' ? 'selected' : ''; ?>>ช่างซ่อม (Technician)</option>
                            <option value="receptionist" <?php echo $employee['role'] == 'receptionist' ? 'selected' : ''; ?>>พนักงานต้อนรับ (Receptionist)</option>
                            <option value="customer">เปลี่ยนเป็นลูกค้า (Customer)</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <a href="admin_dashboard.php" class="btn btn-secondary">ยกเลิก</a>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center text-gray-600">
                    <p>ไม่พบข้อมูลพนักงานที่สามารถแก้ไขได้</p>
                    <a href="admin_dashboard.php" class="inline-block mt-4 align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                        กลับสู่หน้าหลัก
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy;  2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>