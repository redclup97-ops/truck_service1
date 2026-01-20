<?php
// เริ่ม session เพื่อตรวจสอบการเข้าสู่ระบบของแอดมิน
session_start();

// ดึงไฟล์เชื่อมต่อฐานข้อมูล
include 'db_connect.php'; 

// ตรวจสอบว่าผู้ใช้เป็นแอดมินหรือไม่ ถ้าไม่ใช่ ให้ส่งกลับไปหน้า login
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// ตรวจสอบว่ามี request_id ส่งมาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$request_id = $_GET['id'];

// SQL query เพื่อดึงข้อมูลทั้งหมด
$sql = "SELECT 
            sr.request_id,
            sr.status,
            sr.description,
            sr.created_at,
            v.license_plate,
            v.brand, /* เพิ่ม brand เพื่อให้ข้อมูลสมบูรณ์ */
            v.model,
            v.year,
            c.first_name,
            c.last_name,
            c.phone,
            c.email,
            rs.pickup_date, /* ดึงวันนัดรับรถมาแสดงในเอกสาร */
            rs.total_cost,
            rs.labor_cost,
            rs.parts_details
        FROM 
            service_requests sr
        JOIN 
            vehicles v ON sr.vehicle_id = v.vehicle_id
        JOIN 
            customers c ON v.customer_id = c.customer_id
        LEFT JOIN
            repair_summary rs ON sr.request_id = rs.request_id
        WHERE
            sr.request_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "ไม่พบข้อมูลการแจ้งซ่อม";
    exit;
}

$repair = $result->fetch_assoc();

// แปลงสถานะเป็นภาษาไทย
$status_map = [
    'pending' => 'รอดำเนินการ',
    'in_progress' => 'กำลังดำเนินการ',
    'completed' => 'เสร็จสิ้น',
    'cancelled' => 'ยกเลิก'
];
$display_status = $status_map[$repair['status']] ?? htmlspecialchars($repair['status']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO Admin | รายละเอียดการซ่อม #<?php echo htmlspecialchars($repair['request_id']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="image/logo.png"/>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .main-content {
            flex-grow: 1;
            padding: 2rem;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: white;
                padding: 0;
            }
            .print-container {
                width: 100%;
                margin: 0;
                box-shadow: none;
                border: none;
            }
            /* จัดการ Margin สำหรับการพิมพ์ */
            .signature-area {
                width: 100%;
                max-width: 100%; /* ไม่จำกัดความกว้างในการพิมพ์ */
                margin: 0 auto;
            }
            .signature-box {
                width: 45%; /* ให้แต่ละกล่องกว้าง 45% */
            }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <header class="bg-black text-white shadow-lg sticky top-0 z-50 no-print">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center rounded-md">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>
            <div class="flex items-center space-x-8 text-sm font-semibold">
                <a href="admin_dashboard.php" class="hover:text-red-600 transition-colors duration-300">แผงควบคุม</a>
                <a href="admin_logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="container mx-auto p-8 print-container bg-white shadow-lg rounded-lg">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">ใบสรุปและรับมอบงานซ่อม</h1>
                    <p class="text-gray-500">รหัสอ้างอิง: #<?php echo htmlspecialchars($repair['request_id']); ?></p>
                    <p class="text-sm text-gray-500">วันที่แจ้งซ่อม: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($repair['created_at']))); ?></p>
                </div>
                <div class="text-right">
                    <p class="font-semibold">สถานะงานซ่อม:</p>
                    <span class="text-lg font-bold text-blue-600"><?php echo $display_status; ?></span>
                    <?php if (!empty($repair['pickup_date'])): ?>
                       <p class="text-sm text-gray-500 mt-2">กำหนดรับรถ: <span class="font-bold text-green-700"><?php echo date('d/m/Y', strtotime($repair['pickup_date'])); ?></span></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 border-t border-b py-6 border-gray-200">
                <div>
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">ข้อมูลลูกค้า</h2>
                    <p><strong>ชื่อ-สกุล:</strong> <?php echo htmlspecialchars($repair['first_name'] . ' ' . $repair['last_name']); ?></p>
                    <p><strong>เบอร์โทรศัพท์:</strong> <?php echo htmlspecialchars($repair['phone']); ?></p>
                    <p><strong>อีเมล:</strong> <?php echo htmlspecialchars($repair['email']); ?></p>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">ข้อมูลรถยนต์</h2>
                    <p><strong>ทะเบียนรถ:</strong> <?php echo htmlspecialchars($repair['license_plate']); ?></p>
                    <p><strong>ยี่ห้อ / รุ่น:</strong> <?php echo htmlspecialchars($repair['brand'] . ' ' . $repair['model']); ?></p>
                    <p><strong>ปี:</strong> <?php echo htmlspecialchars($repair['year']); ?></p>
                </div>
            </div>

            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-700 mb-3">รายละเอียดปัญหาที่แจ้ง</h2>
                <p class="bg-gray-100 p-4 rounded-lg"><?php echo nl2br(htmlspecialchars($repair['description'])); ?></p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-gray-700 mb-4">สรุปค่าใช้จ่าย</h2>

                <?php if (isset($repair['total_cost'])): ?>
                    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">รายการ</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">จำนวน</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ราคา/หน่วย</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ยอดรวม</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                    $parts = json_decode($repair['parts_details'] ?? '[]', true);
                                    $labor_cost = (float)($repair['labor_cost'] ?? 0);
                                    $total_cost = (float)($repair['total_cost'] ?? 0);
                                
                                    if (!empty($parts)) {
                                        foreach ($parts as $part) { ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($part['name'] ?? 'N/A'); ?></td>
                                            <td class="px-6 py-4 text-center text-sm text-gray-600"><?php echo htmlspecialchars($part['quantity'] ?? 'N/A'); ?></td>
                                            <td class="px-6 py-4 text-right text-sm text-gray-600"><?php echo number_format($part['unit_price'] ?? 0, 2); ?></td>
                                            <td class="px-6 py-4 text-right text-sm text-gray-600"><?php echo number_format($part['subtotal'] ?? 0, 2); ?></td>
                                        </tr>
                                    <?php }
                                    }
                                ?>
                                <tr>
                                   <td class="px-6 py-4 text-sm text-gray-800 font-semibold">ค่าแรงช่าง</td>
                                   <td colspan="2"></td>
                                   <td class="px-6 py-4 text-right text-sm text-gray-600"><?php echo number_format($labor_cost, 2); ?></td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-100">
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-right text-lg font-bold text-gray-800">ยอดรวมทั้งสิ้น</td>
                                    <td class="px-6 py-4 text-right text-lg font-bold text-red-600"><?php echo number_format($total_cost, 2); ?> บาท</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-gray-500 bg-gray-100 p-4 rounded-lg">ยังไม่มีการสรุปค่าใช้จ่ายสำหรับรายการนี้</p>
                <?php endif; ?>
            </div>
            

            <div class="signature-area mt-16 max-w-4xl mx-auto flex justify-around">
                
                <div class="signature-box text-center w-full max-w-xs">
                    <p class="mb-2 pt-8 border-t border-gray-400 w-full"></p> 
                    <p class="font-semibold text-sm">ลายเซ็นลูกค้า (ผู้รับมอบรถ)</p>
                    <p class="text-xs text-gray-600">(<?php echo htmlspecialchars($repair['first_name'] . ' ' . $repair['last_name']); ?>)</p>
                </div>
                
                <div class="signature-box text-center w-full max-w-xs">
                    <p class="mb-2 pt-8 border-t border-gray-400 w-full"></p> 
                    <p class="font-semibold text-sm">ลายเซ็นพนักงาน (ผู้ส่งมอบรถ)</p>
                    <p class="text-xs text-gray-600">(ชื่อ-สกุล พนักงาน)</p>
                </div>
            </div>
            <div class="mt-10 text-center no-print">
                <a href="#" onclick="history.back()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg mr-4 transition-colors">
                    กลับไป
                </a>
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                    พิมพ์หน้านี้ (ใบสรุป)
                </button>
            </div>
        </div>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-auto no-print">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy;  2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>