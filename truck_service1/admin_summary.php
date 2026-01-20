<?php
// Start the session to check if the admin is logged in
session_start();

// Include the database connection file
include 'db_connect.php'; 

// Check if the user is an admin. If not, redirect to the login page.
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and get data from the POST request
    $request_id = $conn->real_escape_string($_POST['request_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $labor_cost = floatval($_POST['labor_cost']);
    $total_cost = floatval($_POST['total_cost']);

    // Process parts data for JSON storage
    $parts = [];
    $parts_total_calculated = 0; // *** เพิ่มตัวแปรคำนวณยอดรวมอะไหล่ ***
    
    if (isset($_POST['parts']) && is_array($_POST['parts'])) {
        foreach ($_POST['parts'] as $part) {
            $part_name = htmlspecialchars($part['name']);
            $unit_price = floatval($part['unit_price']);
            $quantity = intval($part['quantity']);
            if (!empty($part_name) && $unit_price >= 0 && $quantity >= 0) {
                $subtotal = $unit_price * $quantity; // คำนวณ subtotal
                $parts[] = [
                    'name' => $part_name,
                    'unit_price' => $unit_price,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal
                ];
                $parts_total_calculated += $subtotal; // *** รวมยอดอะไหล่ ***
            }
        }
    }
    $parts_json = json_encode($parts, JSON_UNESCAPED_UNICODE);

    // Start a transaction to ensure both updates succeed or fail together
    $conn->begin_transaction();
    $update_success = true;

    try {
        // Step 1: Update the status and total cost in the service_requests table
        $sql_update_request = "UPDATE service_requests SET status = ?, cost = ? WHERE request_id = ?";
        $stmt_update_request = $conn->prepare($sql_update_request);
        $stmt_update_request->bind_param("sdi", $status, $total_cost, $request_id);
        $stmt_update_request->execute();
        $stmt_update_request->close();

        // Step 2: Insert or update the repair_summary table
        $sql_upsert_summary = "INSERT INTO repair_summary (request_id, parts_details, labor_cost, total_cost, status) 
                       VALUES (?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE 
                       parts_details = VALUES(parts_details),
                       labor_cost = VALUES(labor_cost),
                       total_cost = VALUES(total_cost),
                       status = VALUES(status)";
        $stmt_upsert_summary = $conn->prepare($sql_upsert_summary);
        $stmt_upsert_summary->bind_param("isdds", $request_id, $parts_json, $labor_cost, $total_cost, $status);
        $stmt_upsert_summary->execute();
        $stmt_upsert_summary->close();

        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $update_success = false;
        $error_message = $e->getMessage();
    }

    // Now, fetch the full details for the summary page from both tables
    $sql_fetch = "SELECT 
                    sr.request_id,
                    sr.description,
                    sr.appointment_date,
                    sr.status,
                    sr.created_at,
                    v.license_plate,
                    v.brand,
                    v.model,
                    v.year,
                    c.first_name,
                    c.last_name,
                    c.email,
                    c.phone,
                    rs.parts_details,
                    rs.labor_cost,
                    rs.total_cost
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

    $stmt_fetch = $conn->prepare($sql_fetch);
    $stmt_fetch->bind_param("i", $request_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    $repair_details = $result_fetch->fetch_assoc();
    $stmt_fetch->close();
    
    // *** สำคัญ: คำนวณยอดรวมอะไหล่หลังการ Fetch อีกครั้ง (ในกรณีที่เป็น GET request หรือต้องใช้ค่าที่ Fetch มา) ***
    $parts_data_fetched = json_decode($repair_details['parts_details'], true);
    $parts_total_display = 0;
    if (!empty($parts_data_fetched)) {
        foreach ($parts_data_fetched as $part) {
             $parts_total_display += $part['subtotal'];
        }
    }


} else {
    // If not a POST request, redirect back to the dashboard
    header("Location: admin_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO Admin | สรุปรายการซ่อม #<?php echo htmlspecialchars($request_id); ?></title>
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
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
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
                <a href="admin_dashboard.php" class="hover:text-red-600 transition-colors duration-300">แผงควบคุม</a>
                <a href="admin_logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">สรุปรายการซ่อม #<?php echo htmlspecialchars($request_id); ?></h2>
            <a href="admin_dashboard.php" class="text-sm font-semibold text-gray-600 hover:text-red-600 transition-colors">&larr; กลับไปที่หน้าหลัก</a>
        </div>
        
        <div class="card">
            <?php if ($update_success): ?>
                <div class="p-4 mb-4 text-sm rounded-lg bg-green-100 text-green-700">
                    อัพเดทข้อมูลการแจ้งซ่อมเรียบร้อยแล้ว
                </div>
            <?php else: ?>
                <div class="p-4 mb-4 text-sm rounded-lg bg-red-100 text-red-700">
                    เกิดข้อผิดพลาดในการอัพเดทข้อมูล: <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-xl font-bold mb-3">ข้อมูลลูกค้าและรถยนต์</h3>
                    <p><strong>ชื่อ-นามสกุล:</strong> <?php echo htmlspecialchars($repair_details['first_name'] . ' ' . $repair_details['last_name']); ?></p>
                    <p><strong>เบอร์โทรศัพท์:</strong> <?php echo htmlspecialchars($repair_details['phone']); ?></p>
                    <p><strong>ทะเบียนรถ:</strong> <?php echo htmlspecialchars($repair_details['license_plate']); ?></p>
                    <p><strong>ยี่ห้อ / รุ่น:</strong> <?php echo htmlspecialchars($repair_details['brand'] . ' ' . $repair_details['model'] . ' (' . $repair_details['year'] . ')'); ?></p>
                    <p><strong>สถานะ:</strong> <?php echo htmlspecialchars($status); ?></p>
                </div>

                <div>
                    <h3 class="text-xl font-bold mb-3">สรุปค่าใช้จ่าย</h3>
                    
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 mb-2">รายการอะไหล่:</h4>
                        <?php 
                            // ใช้ $parts_data_fetched จากการคำนวณด้านบน
                            if (!empty($parts_data_fetched)): 
                        ?>
                            <table class="w-full text-sm border border-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="py-2 px-4 text-left border-b">รายการ</th>
                                        <th class="py-2 px-4 text-center border-b">จำนวน</th>
                                        <th class="py-2 px-4 text-right border-b">ราคาต่อหน่วย</th>
                                        <th class="py-2 px-4 text-right border-b">รวม</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($parts_data_fetched as $part): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-2 px-4 text-left"><?php echo htmlspecialchars($part['name']); ?></td>
                                            <td class="py-2 px-4 text-center"><?php echo htmlspecialchars(number_format($part['quantity'])); ?></td>
                                            <td class="py-2 px-4 text-right"><?php echo htmlspecialchars(number_format($part['unit_price'], 2)); ?></td>
                                            <td class="py-2 px-4 text-right font-semibold"><?php echo htmlspecialchars(number_format($part['subtotal'], 2)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-gray-500">ไม่มีรายการอะไหล่ที่ถูกบันทึก</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-right mt-6 pt-4 border-t border-gray-200">
                        <p class="text-lg font-bold">ค่าแรง: <span class="text-gray-900"><?php echo htmlspecialchars(number_format($repair_details['labor_cost'], 2)); ?> บาท</span></p>
                        
                        <p class="text-lg font-bold">ยอดรวมอะไหล่: <span class="text-gray-900"><?php echo htmlspecialchars(number_format($parts_total_display, 2)); ?> บาท</span></p>
                        
                        <p class="text-2xl font-bold text-red-600 mt-2">ค่าใช้จ่ายทั้งหมด: 
                            <span class="text-red-600"><?php echo htmlspecialchars(number_format($repair_details['total_cost'], 2)); ?> บาท</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end mt-8">
                <a href="admin_dashboard.php" class="bg-red-600 text-white px-6 py-2 rounded-md font-semibold hover:bg-red-700 transition-colors duration-300">กลับสู่หน้าหลัก</a>
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

<?php
// Close the database connection
$conn->close();
?>