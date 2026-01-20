<?php
// ไฟล์: vehicle_management.php
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

// --- 1. ส่วนประมวลผลการส่งฟอร์ม (เพิ่ม/ลบ) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'add_vehicle') {
        // โค้ดสำหรับเพิ่มรถยนต์ใหม่
        $brand = 'HINO'; // กำหนดให้เป็น HINO ตามการออกแบบระบบ
        $model_year = trim($_POST['model_year']);
        $license_plate = trim($_POST['license_plate']);
        
        if (strpos($model_year, ' - ') !== false) {
            list($model, $year) = explode(' - ', $model_year);
        } else {
            $model = $model_year;
            $year = '';
        }
        
        if (empty($license_plate) || empty($model)) {
            $message = "โปรดกรอกทะเบียนรถและรุ่นรถให้ครบถ้วน";
            $message_type = "error";
        } else {
            $sql_check = "SELECT vehicle_id FROM vehicles WHERE license_plate = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("s", $license_plate);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $message = "ผิดพลาด! ทะเบียนรถนี้ถูกลงทะเบียนแล้ว";
                $message_type = "error";
            } else {
                $sql_insert = "INSERT INTO vehicles (customer_id, brand, model, year, license_plate) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("issss", $customer_id, $brand, $model, $year, $license_plate);

                if ($stmt_insert->execute()) {
                    $message = "เพิ่มรถยนต์ใหม่สำเร็จแล้ว!";
                    $message_type = "success";
                } else {
                    $message = "เกิดข้อผิดพลาดในการเพิ่มรถยนต์: " . htmlspecialchars($stmt_insert->error);
                    $message_type = "error";
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
        }
    } 
    
    // *** ส่วนที่แก้ไข: โค้ดสำหรับลบรถยนต์ด้วย Transaction ***
    elseif ($action == 'delete_vehicle') {
        $vehicle_id_to_delete = intval($_POST['vehicle_id']);
        $license_plate_display = $_POST['license_plate_display'] ?? 'Unknown Vehicle';
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // 1. Delete all associated service requests first (to satisfy FOREIGN KEY constraint).
            // หมายเหตุ: การลบ Service Requests จะลบ Repair Summary ที่เกี่ยวข้องด้วย (เนื่องจากมี ON DELETE CASCADE บนตาราง repair_summary)
            $sql_del_requests = "DELETE FROM service_requests WHERE vehicle_id = ?";
            $stmt_del_requests = $conn->prepare($sql_del_requests);
            $stmt_del_requests->bind_param("i", $vehicle_id_to_delete);
            $stmt_del_requests->execute();
            $stmt_del_requests->close();
            
            // 2. Now delete the vehicle itself, ensuring the customer owns it (Authorization check).
            $sql_delete_vehicle = "DELETE FROM vehicles WHERE vehicle_id = ? AND customer_id = ?";
            $stmt_delete_vehicle = $conn->prepare($sql_delete_vehicle);
            $stmt_delete_vehicle->bind_param("ii", $vehicle_id_to_delete, $customer_id);

            if ($stmt_delete_vehicle->execute()) {
                if ($stmt_delete_vehicle->affected_rows > 0) {
                    $conn->commit(); // Commit if deletion successful
                    $message = "ลบรถยนต์ทะเบียน " . htmlspecialchars($license_plate_display) . " และรายการซ่อมที่เกี่ยวข้องทั้งหมดเรียบร้อยแล้ว!";
                    $message_type = "success";
                } else {
                    $conn->rollback(); // Rollback if no vehicle was deleted (e.g., wrong customer_id)
                    $message = "ผิดพลาด! ไม่พบรถยนต์ทะเบียน " . htmlspecialchars($license_plate_display) . " หรือคุณไม่มีสิทธิ์ลบ";
                    $message_type = "error";
                }
            } else {
                throw new Exception("Error executing vehicle deletion: " . $stmt_delete_vehicle->error);
            }
            $stmt_delete_vehicle->close();
            
        } catch (Exception $e) {
            $conn->rollback(); // Rollback on any failure
            $message = "เกิดข้อผิดพลาดในการลบข้อมูล: " . htmlspecialchars($e->getMessage());
            $message_type = "error";
        }
    }
}

// --- 2. ส่วนดึงข้อมูลรถยนต์ปัจจุบันเพื่อแสดงรายการ (Fetch Current Vehicles) ---
$sql_fetch_vehicles = "SELECT * FROM vehicles WHERE customer_id = ?";
$stmt_fetch_vehicles = $conn->prepare($sql_fetch_vehicles);
$stmt_fetch_vehicles->bind_param("i", $customer_id);
$stmt_fetch_vehicles->execute();
$result_vehicles = $stmt_fetch_vehicles->get_result();
$customer_vehicles = $result_vehicles->fetch_all(MYSQLI_ASSOC);
$stmt_fetch_vehicles->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรถยนต์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="image/logo.png"/>
    <style>
        body { font-family: 'Kanit', sans-serif; }
        .btn-primary { 
            background-color: #ef4444; /* red-500 */
            color: white; 
            padding: 0.75rem 1.5rem; 
            border-radius: 0.375rem; 
            font-weight: 600; 
            transition: background-color 0.2s;
        }
        .btn-primary:hover { background-color: #dc2626; } /* red-600 */
        .btn-green { 
            background-color: #10b981; /* green-500 */
            color: white; 
            padding: 0.75rem 1rem; 
            border-radius: 0.375rem; 
            font-weight: 600; 
            transition: background-color 0.2s;
        }
        .btn-green:hover { background-color: #059669; } /* green-600 */
        .btn-red-outline {
             border: 2px solid #ef4444;
             color: #ef4444;
             padding: 0.5rem 1rem; 
             border-radius: 0.375rem; 
             font-weight: 600;
             transition: all 0.2s;
        }
        .btn-red-outline:hover {
            background-color: #ef4444;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex flex-col min-h-screen">

    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center rounded-lg">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>

            <div class="flex items-center space-x-8 text-sm font-semibold">
                <div class="flex items-center space-x-4">
                    <div class="text-white">
                        สวัสดี , <strong>คุณ <?php echo $user_full_name; ?></strong>
                    </div>
                    
                    <a href="edit_profile.php" class="hover:text-yellow-400 transition-colors duration-300">แก้ไขข้อมูลสมาชิก</a>
                    
                    <a href="vehicle_management.php" class="text-yellow-400 hover:text-red-600 transition-colors duration-300 border-b-2 border-yellow-400">จัดการรถยนต์</a>

                    <a href="service_request.php" class="hover:text-red-600 transition-colors duration-300">นัดหมายเข้าซ่อม</a>
                    <a href="summary.php" class="hover:text-red-600 transition-colors duration-300">รายการซ่อม</a>
                    <a href="logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
                </div>
            </div>
        </nav>
    </header>

    <main class="flex-grow container mx-auto px-6 py-10">
        <div class="max-w-4xl mx-auto bg-white p-8 rounded-xl shadow-2xl">
            <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-3">จัดการรถยนต์ของคุณ</h1>
            
            <?php if (!empty($message)): ?>
                <div class="p-4 mb-4 text-sm rounded-lg 
                    <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <h2 class="text-2xl font-bold text-gray-700 mb-4 mt-6">เพิ่มรถยนต์ใหม่</h2>
            <form action="vehicle_management.php" method="POST" class="p-6 border border-gray-200 rounded-lg bg-gray-50 space-y-4">
                <input type="hidden" name="action" value="add_vehicle">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="license_plate" class="block text-sm font-medium text-gray-700">ทะเบียนรถ</label>
                        <input type="text" id="license_plate" name="license_plate" placeholder="กข 1234" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border">
                    </div>
                    
                    <div>
                        <label for="model_year" class="block text-sm font-medium text-gray-700">รุ่นรถ</label>
                        <input 
                            list="model_list" 
                            id="model_year" 
                            name="model_year" 
                            placeholder="เลือกรุ่นรถหรือกรอกเอง" 
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2 border"
                        >
                        <datalist id="model_list">
                            <option value="HINO 300 - 2020">
                            <option value="HINO 300 - 2021">
                            <option value="HINO 300 - 2022">
                            <option value="HINO 300 - 2023">
                            <option value="HINO 300 - 2024">
                            <option value="HINO 500 - 2020">
                            <option value="HINO 500 - 2021">
                            <option value="HINO 500 - 2022">
                            <option value="HINO 500 - 2023">
                            <option value="HINO 500 - 2024">
                            <option value="HINO 700 - 2020">
                            <option value="HINO 700 - 2021">
                            <option value="HINO 700 - 2022">
                            <option value="HINO 700 - 2023">
                            <option value="HINO 700 - 2024">
                        </datalist>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-green">เพิ่มรถยนต์</button>
                </div>
            </form>

            <h2 class="text-2xl font-bold text-gray-700 mb-4 mt-8">รายการรถยนต์ที่ลงทะเบียนแล้ว (<?php echo count($customer_vehicles); ?> คัน)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 shadow-lg rounded-lg overflow-hidden">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ทะเบียนรถ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ยี่ห้อ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">รุ่น / ปี</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($customer_vehicles) > 0): ?>
                            <?php foreach ($customer_vehicles as $vehicle): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-lg font-bold text-red-600"><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($vehicle['brand']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($vehicle['model'] . ' (' . $vehicle['year'] . ')'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                        <form method="POST" action="vehicle_management.php" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบรถยนต์ทะเบียน <?php echo htmlspecialchars($vehicle['license_plate']); ?> ? รายการแจ้งซ่อมและใบสรุปค่าใช้จ่ายที่เกี่ยวข้องทั้งหมดจะถูกลบออกถาวร');">
                                            <input type="hidden" name="action" value="delete_vehicle">
                                            <input type="hidden" name="vehicle_id" value="<?php echo htmlspecialchars($vehicle['vehicle_id']); ?>">
                                            <input type="hidden" name="license_plate_display" value="<?php echo htmlspecialchars($vehicle['license_plate']); ?>">
                                            <button type="submit" class="btn-red-outline text-sm">
                                                ลบรถยนต์
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">คุณยังไม่ได้ลงทะเบียนรถยนต์ใดๆ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="flex justify-end space-x-4 mt-8">
                <a href="summary.php" class="btn btn-primary">กลับสู่รายการซ่อม</a>
            </div>
        </div>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy; 2024 Hino Motors Thailand. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>