<?php
session_start();
include 'db_connect.php'; 

// Check if admin is logged in
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$admin_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'ผู้ดูแลระบบ';
$message = $_GET['message'] ?? ''; // รับข้อความจากหน้าอื่น

// Function แปล role
function getTranslatedRole($role) {
    switch($role) {
        case 'admin': return 'แอดมิน';
        case 'employee': return 'ช่างซ่อม';
        default: return $role;
    }
}

// Function แปลสถานะเป็นภาษาไทย
function getThaiStatus($status) {
    switch($status) {
        case 'pending': return 'รอดำเนินการ';
        case 'in_progress': return 'กำลังดำเนินการ';
        case 'completed': return 'ซ่อมเสร็จแล้ว';
        case 'cancelled': return 'ยกเลิกแล้ว';
        default: return $status;
    }
}

// === โค้ดส่วนการค้นหาและดึงข้อมูลแจ้งซ่อมที่ถูกปรับปรุง ===

// ตรวจสอบค่าที่ส่งมาจากการค้นหา (ใช้ GET method)
$search_term = $_GET['search_term'] ?? ''; // รหัสแจ้งซ่อม / ชื่อลูกค้า / ทะเบียนรถ
$search_date = $_GET['search_date'] ?? ''; // วันที่แจ้งซ่อม (created_at)

$where_clauses = [];
$param_types = '';
$param_values = [];

if (!empty($search_term)) {
    // ค้นหาจาก รหัสแจ้งซ่อม (request_id), ชื่อลูกค้า, นามสกุลลูกค้า, ทะเบียนรถ
    // ใช้ CAST(sr.request_id AS CHAR) เพื่อให้สามารถใช้ LIKE กับ ID ได้ (รองรับการค้นหาแบบ partial ID)
    $where_clauses[] = "(
        CAST(sr.request_id AS CHAR) LIKE ? OR 
        c.first_name LIKE ? OR 
        c.last_name LIKE ? OR
        v.license_plate LIKE ?
    )";
    $param_types .= 'ssss';
    $like_term_param = "%" . $search_term . "%";
    $param_values[] = $like_term_param;
    $param_values[] = $like_term_param;
    $param_values[] = $like_term_param;
    $param_values[] = $like_term_param;
}

if (!empty($search_date)) {
    // ค้นหาตามวันที่แจ้งซ่อม (created_at) โดยเทียบเฉพาะส่วนวันที่
    $where_clauses[] = "DATE(sr.created_at) = ?";
    $param_types .= 's';
    $param_values[] = $search_date; // Assumes YYYY-MM-DD format from date input
}


// ดึงข้อมูลแจ้งซ่อม พร้อมเงื่อนไขการค้นหา
$sql_repairs = "
    SELECT 
        sr.request_id, sr.created_at, sr.description, sr.status,
        rs.pickup_date, c.first_name, c.last_name,
        v.brand, v.model, v.license_plate
    FROM service_requests sr
    JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
    JOIN customers c ON v.customer_id = c.customer_id
    LEFT JOIN repair_summary rs ON sr.request_id = rs.request_id
";

if (!empty($where_clauses)) {
    $sql_repairs .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_repairs .= " ORDER BY sr.created_at DESC";


// ใช้ prepared statement ในการดึงข้อมูลเพื่อป้องกัน SQL Injection
$stmt_repairs = $conn->prepare($sql_repairs);

if ($stmt_repairs === false) {
    // Handle error
    die('SQL error during prepare: ' . htmlspecialchars($conn->error));
}

if (!empty($param_values)) {
    // Bind parameters
    $stmt_repairs->bind_param($param_types, ...$param_values);
}

$stmt_repairs->execute();
$result_repairs = $stmt_repairs->get_result();
$repairs = $result_repairs->fetch_all(MYSQLI_ASSOC);
$stmt_repairs->close();

// === จบโค้ดส่วนการค้นหาและดึงข้อมูลแจ้งซ่อมที่ถูกปรับปรุง ===

// ดึงข้อมูลสถิติตามสถานะ (สำหรับ Dashboard Stats ด้านบน)
$sql_stats = "SELECT COUNT(*) as count, status FROM service_requests GROUP BY status";
$result_stats = $conn->query($sql_stats);

$total = 0;
$stats = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0
];

if ($result_stats->num_rows > 0) {
    while($row = $result_stats->fetch_assoc()) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = $row['count'];
        }
        $total += $row['count'];
    }
}

$pending = $stats['pending'];
$in_progress = $stats['in_progress'];
$completed = $stats['completed'];
$cancelled = $stats['cancelled'];

// ดึงข้อมูลพนักงาน
$sql_employees = "SELECT employee_id, first_name, last_name, email, role FROM employees ORDER BY employee_id ASC";
$result_employees = $conn->query($sql_employees);
$employees = $result_employees->fetch_all(MYSQLI_ASSOC);

// ดึงข้อมูลลูกค้า (เฉพาะบทบาท 'customer' ในตาราง employees)
$sql_customers = "SELECT c.customer_id, c.first_name, c.last_name, c.email, c.phone, COUNT(v.vehicle_id) AS total_vehicles
                  FROM customers c
                  LEFT JOIN vehicles v ON c.customer_id = v.customer_id
                  GROUP BY c.customer_id
                  ORDER BY c.customer_id ASC";
$result_customers = $conn->query($sql_customers);
$customers = $result_customers->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | HINO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="image/logo.png"/>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f3f4f6;
        }
        .sidebar {
            width: 250px;
        }
        .main-content {
            margin-left: 250px;
        }
        .table-auto {
            /* Fix table width for smaller screens */
            width: 100%;
        }
        /* Status badge colors */
        .status-pending { background-color: #fcd34d; color: #92400e; } /* amber */
        .status-in_progress { background-color: #3b82f6; color: #ffffff; } /* blue */
        .status-completed { background-color: #10b981; color: #ffffff; } /* emerald */
        .status-cancelled { background-color: #ef4444; color: #ffffff; } /* red */
    </style>
</head>

<body class="flex flex-col min-h-screen">
    <aside class="sidebar bg-gray-800 text-white fixed h-full p-6 space-y-6">
        <h1 class="text-3xl font-bold text-red-600 border-b border-gray-700 pb-4 mb-6">ADMIN PANEL</h1>
        <nav class="space-y-3">
    <a href="admin_dashboard.php" class="flex items-center space-x-2 p-3 rounded-lg bg-gray-700 text-red-400 font-semibold transition duration-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span>หน้าหลัก</span>
    </a>
    
    <a href="admin_reports.php" class="flex items-center space-x-2 p-3 rounded-lg hover:bg-gray-700 transition duration-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-4m-2 2h4m6 0a2 2 0 100-4 2 2 0 000 4zM21 12h-4m0 0H7m0 0a2 2 0 100-4 2 2 0 000 4zM12 21v-4m-2 2h4m6 0a2 2 0 100-4 2 2 0 000 4zM12 3v4m-2 2h4m6 0a2 2 0 100-4 2 2 0 000 4z" />
        </svg>
        <span>รายงานสรุป</span>
    </a>
    <a href="admin_add_employee.php" class="flex items-center space-x-2 p-3 rounded-lg hover:bg-gray-700 transition duration-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
        </svg>
        <span>เพิ่มผู้ใช้งาน</span>
    </a>
    <a href="admin_logout.php" class="flex items-center space-x-2 p-3 rounded-lg hover:bg-gray-700 transition duration-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
        <span>ออกจากระบบ</span>
    </a>
</nav>
        <div class="absolute bottom-6 left-6 text-sm text-gray-400">
            เข้าสู่ระบบโดย: <span class="font-semibold text-red-400"><?= htmlspecialchars($admin_name) ?></span>
        </div>
    </aside>

    <main class="main-content flex-1 p-10">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">แผงควบคุมผู้ดูแลระบบ (Admin Dashboard)</h1>
        
        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-bold">สำเร็จ!</p>
                <p><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>

        <section>
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">สถานะรายการแจ้งซ่อม</h2>
            <div class="bg-white shadow-lg rounded-xl p-6">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
                    <div class="p-4 bg-blue-100 rounded-lg"><p>งานทั้งหมด</p><p class="text-2xl font-bold text-blue-800"><?= $total ?></p></div>
                    <div class="p-4 bg-green-100 rounded-lg"><p>ซ่อมเสร็จแล้ว</p><p class="text-2xl font-bold text-green-800"><?= $completed ?></p></div>
                    <div class="p-4 bg-yellow-100 rounded-lg"><p>กำลังดำเนินการ</p><p class="text-2xl font-bold text-yellow-800"><?= $in_progress ?></p></div>
                    <div class="p-4 bg-gray-200 rounded-lg"><p>รอดำเนินการ</p><p class="text-2xl font-bold text-gray-700"><?= $pending ?></p></div>
                    <div class="p-4 bg-red-100 rounded-lg"><p>ยกเลิก</p><p class="text-2xl font-bold text-red-800"><?= $cancelled ?></p></div>
                </div>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">รายการแจ้งซ่อมทั้งหมด</h2>

            <div class="bg-white shadow-md rounded-xl p-6 mb-6">
                <form method="GET" action="admin_dashboard.php" class="flex flex-wrap items-end gap-4">
                    
                    <div class="flex-1 min-w-[200px] max-w-sm">
                        <label for="search_term" class="block text-sm font-medium text-gray-700">ค้นหา (รหัส / ชื่อ / ทะเบียนรถ)</label>
                        <input type="text" id="search_term" name="search_term" 
                               value="<?php echo htmlspecialchars($search_term); ?>"
                               placeholder="ระบุรหัส, ชื่อ หรือทะเบียน" 
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm">
                    </div>
                    
                    <div class="min-w-[150px] max-w-[200px]">
                        <label for="search_date" class="block text-sm font-medium text-gray-700">วันที่แจ้งซ่อม</label>
                        <input type="date" id="search_date" name="search_date" 
                               value="<?php echo htmlspecialchars($search_date); ?>"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm">
                    </div>

                    <div class="flex-shrink-0">
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md shadow-lg transition duration-200 w-full md:w-auto">
                            ค้นหา
                        </button>
                    </div>

                    <?php if (!empty($search_term) || !empty($search_date)): ?>
                        <div class="flex-shrink-0">
                            <a href="admin_dashboard.php" 
                               class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md shadow-lg transition duration-200 w-full md:w-auto block text-center">
                                ล้างค่า
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="overflow-x-auto bg-white shadow-lg rounded-xl p-4">
                <?php if (!empty($repairs)): ?>
                <table class="min-w-full divide-y divide-gray-200 table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รหัสแจ้งซ่อม</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วันที่แจ้งซ่อม</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ลูกค้า</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รถยนต์</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รายละเอียด</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($repairs as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['request_id']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['license_plate']) ?><br>
                                <span class="text-xs text-gray-400"><?= htmlspecialchars($row['brand'] . ' ' . $row['model']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                    $status_class = 'status-' . $row['status'];
                                    $status_text = getThaiStatus($row['status']);
                                ?>
                                <span class="inline-flex px-3 py-1 text-xs font-semibold leading-5 rounded-full <?= $status_class ?>">
                                    <?= htmlspecialchars($status_text) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 hover:text-blue-800">
                                <a href="admin_view_repair.php?id=<?= htmlspecialchars($row['request_id']) ?>" class="font-semibold">ดูรายละเอียด</a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="admin_edit_repair.php?id=<?= htmlspecialchars($row['request_id']) ?>" class="text-red-600 hover:text-red-900 mr-4">แก้ไขสถานะ</a>
                                <?php if ($row['status'] == 'completed'): ?>
                                    <a href="admin_update_summary.php?id=<?= htmlspecialchars($row['request_id']) ?>" class="text-green-600 hover:text-green-900">สรุปค่าใช้จ่าย</a>
                                <?php endif; ?>
                                <a href="repair_details.php?id=<?= htmlspecialchars($row['request_id']) ?>" class="text-blue-600 hover:text-blue-900 ml-4">พิมพ์ใบสรุป</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-center text-gray-500 p-6">ไม่พบรายการแจ้งซ่อมตามเงื่อนไขที่ค้นหา</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="mt-10">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">การจัดการพนักงาน/แอดมิน</h2>
            <div class="flex justify-end mb-4">
                <a href="admin_add_employee.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-lg transition duration-300">
                    + เพิ่มพนักงาน/ผู้ใช้งาน
                </a>
            </div>
            <div class="overflow-x-auto bg-white shadow-lg rounded-xl p-4">
                <?php if (!empty($employees)): ?>
                <table class="min-w-full divide-y divide-gray-200 table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อ-นามสกุล</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">อีเมล</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">บทบาท</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($employees as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['employee_id']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['email']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                                <?= getTranslatedRole($row['role']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="admin_edit_employee.php?id=<?= htmlspecialchars($row['employee_id']) ?>" class="text-red-600 hover:text-red-900 mr-4">แก้ไข</a>
                                <a href="admin_delete_employee.php?id=<?= htmlspecialchars($row['employee_id']) ?>" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบพนักงานคนนี้?')" class="text-gray-600 hover:text-gray-900">ลบ</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-center text-gray-500 p-6">ไม่พบข้อมูลพนักงาน/แอดมิน</p>
                <?php endif; ?>
            </div>
        </section>
        
        <section class="mt-10">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">การจัดการข้อมูลลูกค้า</h2>
            <div class="overflow-x-auto bg-white shadow-lg rounded-xl p-4">
                <?php if (!empty($customers)): ?>
                <table class="min-w-full divide-y divide-gray-200 table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อ-นามสกุล</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">อีเมล</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">เบอร์โทร</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">จำนวนรถ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($customers as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['customer_id']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['email']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['total_vehicles']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="admin_edit_customer.php?id=<?= htmlspecialchars($row['customer_id']) ?>" class="text-red-600 hover:text-red-900 mr-4">แก้ไข</a>
                                <a href="admin_delete_customer.php?id=<?= htmlspecialchars($row['customer_id']) ?>" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบข้อมูลลูกค้าคนนี้?')" class="text-gray-600 hover:text-gray-900">ลบ</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-center text-gray-500 p-6">ไม่พบข้อมูลลูกค้า</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy;  2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>
    
</body>
</html>
<?php
$conn->close();
?>