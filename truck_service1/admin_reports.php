<?php
// ไฟล์: admin_reports.php
session_start();
include 'db_connect.php'; 

// ตรวจสอบว่าผู้ใช้เป็นแอดมินหรือไม่ ถ้าไม่ใช่ ให้ส่งกลับไปหน้า login
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$admin_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'ผู้ดูแลระบบ';

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

// === Filtering Logic ===
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

$where_clauses = [];
$param_types = '';
$param_values = [];

if (!empty($start_date)) {
    // กรองตั้งแต่ต้นวันที่ระบุ
    $where_clauses[] = "DATE(sr.created_at) >= DATE(?)"; // ใช้ DATE() เพื่อเทียบเฉพาะส่วนวันที่
    $param_types .= 's';
    $param_values[] = $start_date; 
}

if (!empty($end_date)) {
    // กรองจนถึงสิ้นสุดวันที่ระบุ
    $where_clauses[] = "DATE(sr.created_at) <= DATE(?)"; // ใช้ DATE() เพื่อเทียบเฉพาะส่วนวันที่
    $param_types .= 's';
    $param_values[] = $end_date;
}

if (!empty($status_filter)) {
    $where_clauses[] = "sr.status = ?";
    $param_types .= 's';
    $param_values[] = $status_filter;
}

// Base SQL query to fetch detailed report data
$sql = "
    SELECT 
        sr.request_id, sr.created_at, sr.status, sr.description, sr.appointment_date,
        v.license_plate, v.brand, v.model, v.year,
        c.first_name, c.last_name, c.phone, c.email,
        IFNULL(rs.total_cost, 0.00) AS total_cost,
        IFNULL(rs.labor_cost, 0.00) AS labor_cost,
        rs.parts_details, rs.pickup_date
    FROM service_requests sr
    JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
    JOIN customers c ON v.customer_id = c.customer_id
    LEFT JOIN repair_summary rs ON sr.request_id = rs.request_id
";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY sr.created_at DESC";

// Helper function for dynamic bind_param
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) //PHP 5.3+
    {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

// === Export Logic (CSV) ===
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Re-prepare and execute the query for export
    $stmt_export = $conn->prepare($sql);
    if (!empty($param_values)) {
        // Bind parameters dynamically
        $bind_params = array_merge([$param_types], $param_values);
        call_user_func_array([$stmt_export, 'bind_param'], refValues($bind_params));
    }
    $stmt_export->execute();
    $result_export = $stmt_export->get_result();
    
    if ($result_export->num_rows > 0) {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="repair_report_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write UTF-8 BOM to ensure Thai characters display correctly in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Define CSV Headers
        $csv_headers = [
            'รหัสแจ้งซ่อม', 'วันที่แจ้งซ่อม', 'สถานะ', 'ทะเบียนรถ', 'ยี่ห้อ/รุ่น/ปี',
            'ชื่อลูกค้า', 'เบอร์โทร', 'อาการแจ้ง', 
            'ยอดรวมค่าอะไหล่', 'ค่าแรง', 'ค่าใช้จ่ายรวม', 'วันนัดรับรถ'
        ];
        fputcsv($output, $csv_headers);

        // Loop through results and write rows
        while ($row = $result_export->fetch_assoc()) {
            $parts_total = (float)$row['total_cost'] - (float)$row['labor_cost'];
            
            $csv_row = [
                $row['request_id'],
                date('Y-m-d H:i', strtotime($row['created_at'])),
                getThaiStatus($row['status']),
                $row['license_plate'],
                $row['brand'] . ' ' . $row['model'] . ' (' . $row['year'] . ')',
                $row['first_name'] . ' ' . $row['last_name'],
                $row['phone'],
                str_replace(["\r", "\n"], ' ', $row['description']), // Remove newlines
                number_format($parts_total, 2, '.', ''),
                number_format($row['labor_cost'], 2, '.', ''),
                number_format($row['total_cost'], 2, '.', ''),
                $row['pickup_date'] ? date('Y-m-d', strtotime($row['pickup_date'])) : '-'
            ];
            fputcsv($output, $csv_row);
        }

        fclose($output);
        $stmt_export->close();
        $conn->close();
        exit;
    } else {
         // Handle case where no data is found for export
         die("ไม่พบข้อมูลสำหรับสร้างรายงาน");
    }
}


// === Data Fetching for HTML Display ===
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die('SQL error during prepare: ' . htmlspecialchars($conn->error));
}

if (!empty($param_values)) {
    // Bind parameters dynamically
    $bind_params = array_merge([$param_types], $param_values);
    call_user_func_array([$stmt, 'bind_param'], refValues($bind_params));
}

$stmt->execute();
$result = $stmt->get_result();
$report_data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO Admin | รายงานสรุปงานซ่อม</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="image/logo.png"/>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f3f4f6;
            /* เปลี่ยนจาก display: flex; เป็นการวาง layout แบบปกติ และใช้ margin-left ชดเชย */
        }
        .sidebar {
            width: 250px;
            min-height: 100vh;
            /* ทำให้ Sidebar อยู่กับที่ */
            position: fixed; 
            top: 0;
            left: 0;
        }
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            /* ชดเชยความกว้างของ Sidebar */
            margin-left: 250px; 
            min-height: calc(100vh - 80px); /* Adjust based on expected footer height */
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            color: white;
        }
        .status-pending { background-color: #f59e0b; }
        .status-in_progress { background-color: #3b82f6; }
        .status-completed { background-color: #10b981; }
        .status-cancelled { background-color: #ef4444; }
        .table-auto {
            width: 100%;
        }
        /* *** แก้ไข: CSS สำหรับ Footer ให้ถูกต้อง *** */
        .footer-container {
            /* ชดเชยความกว้างของ Sidebar */
            margin-left: 250px; 
            width: calc(100% - 250px);
            position: relative; /* เพื่อให้สามารถใช้ z-index ได้หากจำเป็น */
        }
        /* CSS สำหรับการพิมพ์ */
        @media print {
            .sidebar, header, footer, .filter-section, .footer-container {
                display: none !important;
            }
            body {
                background-color: white !important;
                margin: 0;
            }
            /* บังคับให้เนื้อหาหลักเต็มจอ */
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            table {
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <aside class="sidebar bg-gray-800 text-white p-6 space-y-6">
        <h1 class="text-3xl font-bold text-red-600 border-b border-gray-700 pb-4 mb-6">ADMIN PANEL</h1>
        <nav class="space-y-3">
            <a href="admin_dashboard.php" class="flex items-center space-x-2 p-3 rounded-lg hover:bg-gray-700 transition duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>หน้าหลัก</span>
            </a>
            
            <a href="admin_reports.php" class="flex items-center space-x-2 p-3 rounded-lg bg-gray-700 text-red-400 font-semibold transition duration-200">
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

    <main class="main-content">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">รายงานสรุปงานซ่อม</h1>
        
        <div class="bg-white shadow-md rounded-xl p-6 mb-8 filter-section">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">ตัวกรองรายงาน</h2>
            <form method="GET" action="admin_reports.php" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end">
                
                <div class="form-group">
                    <label for="start_date" class="block text-sm font-medium text-gray-700">จากวันที่ (แจ้งซ่อม)</label>
                    <input type="date" id="start_date" name="start_date" 
                           value="<?php echo htmlspecialchars($start_date); ?>"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                
                <div class="form-group">
                    <label for="end_date" class="block text-sm font-medium text-gray-700">ถึงวันที่ (แจ้งซ่อม)</label>
                    <input type="date" id="end_date" name="end_date" 
                           value="<?php echo htmlspecialchars($end_date); ?>"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>

                <div class="form-group">
                    <label for="status_filter" class="block text-sm font-medium text-gray-700">สถานะ</label>
                    <select id="status_filter" name="status_filter" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                        <option value="">-- สถานะทั้งหมด --</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                        <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>ซ่อมเสร็จแล้ว</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>ยกเลิกแล้ว</option>
                    </select>
                </div>

                <div class="flex space-x-3">
                    <button type="submit" 
                            class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md shadow-lg transition duration-200">
                        กรองข้อมูล
                    </button>
                    
                    <button type="button" onclick="window.print()"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-lg transition duration-200">
                        พิมพ์รายงาน
                    </button>

                    <a href="admin_reports.php?export=csv&<?php echo http_build_query($_GET); ?>" 
                       class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md shadow-lg transition duration-200 text-center">
                        Export CSV
                    </a>
                </div>
            </form>
        </div>

        <h2 class="text-2xl font-bold text-gray-800 mb-4">ผลลัพธ์รายงาน (<?php echo count($report_data); ?> รายการ)</h2>
        
        <div class="overflow-x-auto bg-white shadow-lg rounded-xl p-4">
            <?php if (!empty($report_data)): ?>
            <table class="min-w-full divide-y divide-gray-200 table-auto">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รหัส</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วันที่แจ้ง</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ลูกค้า/เบอร์โทร</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ทะเบียนรถ</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">อาการแจ้ง</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ค่าใช้จ่ายรวม (บาท)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                        $grand_total_cost = 0;
                        foreach ($report_data as $row): 
                            $status_class = 'status-' . $row['status'];
                            $total_cost = (float)$row['total_cost'];
                            $grand_total_cost += $total_cost;
                        ?>
                    <tr>
                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['request_id']) ?></td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?><br>
                            <span class="text-xs text-gray-500"><?= htmlspecialchars($row['phone'] ?? '-') ?></span>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($row['license_plate']) ?>
                        </td>
                        <td class="px-3 py-4 max-w-xs overflow-hidden truncate text-sm text-gray-500">
                            <?= htmlspecialchars($row['description']) ?>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-center">
                            <span class="status-badge <?= $status_class ?>">
                                <?= getThaiStatus($row['status']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-bold text-red-600">
                            <?= number_format($total_cost, 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-right text-base text-gray-800">ยอดรวมค่าใช้จ่ายทั้งหมดในรายงาน:</td>
                        <td class="px-3 py-4 text-right text-lg text-red-700"><?= number_format($grand_total_cost, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
                <p class="text-center text-gray-500 p-6">ไม่พบรายการงานซ่อมตามเงื่อนไขที่กรอง</p>
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