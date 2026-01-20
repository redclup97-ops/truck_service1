<?php
// ไฟล์: check_status.php
include 'db_connect.php'; 

$search_results = null;
$error_message = '';
$search_input = '';

// Function แปลสถานะเป็นภาษาไทย
function getTranslatedStatus($status) {
    switch($status) {
        case 'pending': return 'รอดำเนินการ';
        case 'in_progress': return 'กำลังดำเนินการ';
        case 'completed': return 'ซ่อมเสร็จแล้ว';
        case 'cancelled': return 'ยกเลิกแล้ว';
        default: return $status;
    }
}

// Function กำหนด class สีสำหรับสถานะ
function getStatusClass($status) {
    switch($status) {
        case 'pending': return 'bg-yellow-500';
        case 'in_progress': return 'bg-blue-500';
        case 'completed': return 'bg-green-500';
        case 'cancelled': return 'bg-gray-500';
        default: return 'bg-gray-500';
    }
}

// ประมวลผลการค้นหา
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_input'])) {
    $search_input = trim($_POST['search_input']);

    if (!empty($search_input)) {
        // ใช้ prepared statement เพื่อค้นหาด้วย รหัสแจ้งซ่อม (numeric) หรือ ทะเบียนรถ (string)
        $sql = "
            SELECT 
                sr.request_id, sr.status, sr.description, sr.created_at, sr.appointment_date,
                v.brand, v.model, v.license_plate,
                c.first_name, c.last_name,
                rs.pickup_date
            FROM 
                service_requests sr
            JOIN 
                vehicles v ON sr.vehicle_id = v.vehicle_id
            JOIN
                customers c ON v.customer_id = c.customer_id
            LEFT JOIN
                repair_summary rs ON sr.request_id = rs.request_id
            WHERE 
                sr.request_id = ? OR v.license_plate LIKE ?
            ORDER BY sr.created_at DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        
        // กำหนดเงื่อนไข v.license_plate LIKE ? สำหรับการค้นหาด้วยทะเบียนรถ
        $license_plate_like = "%" . $search_input . "%";
        
        // พยายาม bind request_id เป็น integer, และ v.license_plate เป็น string
        // แม้จะ bind ทั้งสองอย่าง แต่ SQL จะเลือกเงื่อนไขใดเงื่อนไขหนึ่งที่ตรง
        $stmt->bind_param("is", $search_input, $license_plate_like); 
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $search_results = $result->fetch_assoc();
        } else {
            $error_message = "ไม่พบรายการงานซ่อมสำหรับข้อมูลที่ระบุ";
        }
        $stmt->close();

    } else {
        $error_message = "กรุณากรอกรหัสแจ้งซ่อมหรือทะเบียนรถ";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO | ตรวจสอบสถานะงานซ่อม</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
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
        .search-container {
            width: 100%;
            max-width: 600px;
            background-color: white;
            padding: 2.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .status-badge {
            font-weight: 700;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 1rem;
            color: white;
            display: inline-block;
        }
        /* Status colors are defined by the PHP function getStatusClass() */
    </style>
</head>

<body class="bg-gray-100 text-gray-800">
    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>
            <ul class="flex space-x-8 text-sm font-semibold">
                <li><a href="home.php" class="hover:text-red-600 transition-colors duration-300">หน้าหลัก</a></li>
                <li><a href="login.php" class="hover:text-red-600 transition-colors duration-300">เข้าสู่ระบบ</a></li>
                <li><a href="service_request.php" class="hover:text-red-600 transition-colors duration-300">นัดหมายเข้าซ่อม</a></li>
                <li><a href="check_status.php" class="text-yellow-400 border-b-2 border-yellow-400 transition-colors duration-300">ตรวจสอบสถานะงานซ่อม</a></li>
                <li><a href="contact.php" class="hover:text-red-600 transition-colors duration-300">ติดต่อเรา</a></li>
            </ul>
        </nav>
    </header>

    <main class="main-content">
        <div class="search-container">
            <h1 class="text-3xl font-bold text-center mb-6 text-gray-900">ตรวจสอบสถานะงานซ่อม</h1>
            <p class="text-center text-gray-600 mb-6">กรุณากรอก **รหัสแจ้งซ่อม** หรือ **ทะเบียนรถ** เพื่อตรวจสอบ</p>

            <form action="check_status.php" method="POST" class="space-y-4">
                <input type="text" name="search_input" placeholder="ตัวอย่าง: 48 หรือ 4ขก-9589" 
                       value="<?php echo htmlspecialchars($search_input); ?>" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg shadow transition duration-300">
                    ตรวจสอบสถานะ
                </button>
            </form>

            <div class="mt-8">
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php elseif ($search_results): ?>
                    <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                        <h2 class="text-xl font-bold mb-4 text-gray-900">งานซ่อม #<?php echo htmlspecialchars($search_results['request_id']); ?></h2>
                        
                        <div class="space-y-3">
                            <p><strong>ลูกค้า:</strong> <?php echo htmlspecialchars($search_results['first_name'] . ' ' . $search_results['last_name']); ?></p>
                            <p><strong>รถยนต์:</strong> <?php echo htmlspecialchars($search_results['brand'] . ' ' . $search_results['model']); ?></p>
                            <p><strong>ทะเบียนรถ:</strong> <?php echo htmlspecialchars($search_results['license_plate']); ?></p>
                            <p><strong>อาการแจ้ง:</strong> <?php echo htmlspecialchars(substr($search_results['description'], 0, 50)) . '...'; ?></p>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between items-center">
                            <div>
                                <p class="text-sm text-gray-500 font-semibold mb-1">สถานะปัจจุบัน:</p>
                                <span class="status-badge <?php echo getStatusClass($search_results['status']); ?>">
                                    <?php echo getTranslatedStatus($search_results['status']); ?>
                                </span>
                            </div>
                            <?php if ($search_results['status'] == 'completed' && !empty($search_results['pickup_date'])): ?>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-green-700">รถพร้อมรับได้ตั้งแต่วันที่:</p>
                                    <p class="text-lg font-bold text-green-900"><?php echo htmlspecialchars(date('d/m/Y', strtotime($search_results['pickup_date']))); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy; 2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>