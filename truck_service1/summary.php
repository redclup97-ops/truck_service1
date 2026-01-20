<?php
session_start();
// Include the database connection file
include 'db_connect.php'; 

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$customer_id = $_SESSION['customer_id'];

// Function to map status to Thai text
function getThaiStatus($status) {
    $status_map = [
        'pending' => 'รอการตรวจสอบ', 
        'in_progress' => 'กำลังดำเนินการ', 
        'completed' => 'ซ่อมเสร็จแล้ว', 
        'cancelled' => 'ยกเลิกแล้ว'
    ];
    return $status_map[$status] ?? htmlspecialchars($status);
}

// Fetch the user's full name directly from the database for accuracy
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

// Code to handle the pickup date update
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_pickup_date') {
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $new_pickup_date = isset($_POST['pickup_date']) ? $_POST['pickup_date'] : null;

    if ($request_id > 0 && $new_pickup_date) {
        // Check if new pickup date is after the service date
        $sql_get_appointment_date = "SELECT appointment_date FROM service_requests WHERE request_id = ?";
        $stmt_get_date = $conn->prepare($sql_get_appointment_date);
        $stmt_get_date->bind_param("i", $request_id);
        $stmt_get_date->execute();
        $result_date = $stmt_get_date->get_result();
        $appointment_date = null;
        if ($result_date->num_rows > 0) {
            $row = $result_date->fetch_assoc();
            $appointment_date = $row['appointment_date'];
        }
        $stmt_get_date->close();
        
        $is_valid = false;
        if ($appointment_date) {
            $earliest_pickup_date = date('Y-m-d', strtotime($appointment_date . ' +1 day')); 
            if ($new_pickup_date >= $earliest_pickup_date) {
                $is_valid = true;
            } else {
                $message = "วันนัดรับรถต้องเป็นวันที่ " . date('d/m/Y', strtotime($earliest_pickup_date)) . " หรือหลังจากนั้น";
            }
        } else {
            $message = "ไม่พบวันที่นัดหมายเดิม";
        }
        
        if ($is_valid) {
            // Update pickup date in repair_summary
            $sql_update_date = "UPDATE repair_summary SET pickup_date = ? WHERE request_id = ?";
            $stmt_update_date = $conn->prepare($sql_update_date);
            $stmt_update_date->bind_param("si", $new_pickup_date, $request_id); 
            if ($stmt_update_date->execute()) {
                $message = "บันทึกวันนัดรับรถเรียบร้อยแล้ว! ✅";
            } else {
                $message = "เกิดข้อผิดพลาดในการบันทึกวันนัดรับรถ";
            }
            $stmt_update_date->close();
        }
    }
}

// Code to fetch the customer's repair request data
$sql = "SELECT 
            sr.*, 
            v.brand, 
            v.model, 
            v.license_plate,
            rs.parts_details,
            rs.labor_cost,
            rs.pickup_date
        FROM 
            service_requests sr
        JOIN 
            vehicles v ON sr.vehicle_id = v.vehicle_id
        LEFT JOIN
            repair_summary rs ON sr.request_id = rs.request_id
        WHERE 
            v.customer_id = ?
        ORDER BY 
            sr.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$service_requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปรายการซ่อม</title>
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
            align-items: flex-start;
            flex-grow: 1;
            padding: 2rem;
        }
        .summary-container {
            width: 100%;
            max-width: 900px; /* เพิ่มความกว้าง */
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .status {
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            color: white;
            white-space: nowrap;
        }
        .status.pending { background-color: #f59e0b; } /* Amber-500 */
        .status.in_progress { background-color: #3b82f6; } /* Blue-500 */
        .status.completed { background-color: #10b981; } /* Emerald-500 */
        .status.cancelled { background-color: #6b7280; } /* Gray-500 */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-view-summary {
            background-color: #4a5568; /* Gray-700 */
            color: white;
        }
        .btn-view-summary:hover {
            background-color: #2d3748; /* Gray-800 */
        }
        .details-section {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .details-section.active {
            max-height: 1000px; /* Adjust based on max content height */
            transition: max-height 0.5s ease-in;
        }
        /* *** แก้ไข CSS สำหรับ Modal: ให้มั่นใจว่าซ่อนอยู่แต่แรก *** */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center rounded-lg">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>

            <div class="flex items-center space-x-8 text-sm font-semibold">
                <div class="flex items-center space-x-4">
                    <?php if ($is_logged_in): ?>
                        <div class="text-white">
                            สวัสดี , <strong>คุณ <?php echo $user_full_name; ?></strong>
                        </div>
                        <a href="edit_profile.php" class="hover:text-yellow-400 transition-colors duration-300">
                            แก้ไขข้อมูล
                        </a>
                        <!-- <a href="vehicle_management.php" class="hover:text-yellow-400 transition-colors duration-300">จัดการรถยนต์</a> -->
                        <a href="service_request.php" class="hover:text-red-600 transition-colors duration-300">นัดหมายเข้าซ่อม</a>
                        <a href="summary.php" class="text-red-600 hover:text-red-600 transition-colors duration-300 border-b-2 border-red-600">รายการซ่อม</a>
                        <a href="logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <div class="main-content">
        <div class="summary-container">
            <h2 class="text-3xl font-bold mb-6 text-gray-900 border-b pb-3">สรุปรายการซ่อมของคุณ</h2>
            
            <?php if (!empty($message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6" role="alert">
                    <p class="font-bold">แจ้งเตือน:</p>
                    <span class="block sm:inline"><?php echo $message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (empty($service_requests)): ?>
                <p class="text-center text-gray-500 p-8 border rounded-lg">ยังไม่มีรายการแจ้งซ่อม</p>
                <div class="flex justify-center mt-6">
                    <a href="service_request.php" class="bg-red-600 text-white font-bold py-3 px-6 rounded-lg shadow hover:bg-red-700 transition">แจ้งซ่อมใหม่</a>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($service_requests as $request): ?>
                        <?php 
                            $status_class = htmlspecialchars($request['status']);
                            $appointment_date_only = date('Y-m-d', strtotime($request['appointment_date']));
                            $is_editable = $status_class == 'pending';
                            $has_summary = !empty($request['parts_details']); // มีการสรุปค่าใช้จ่ายแล้ว
                        ?>
                        <div class="bg-white shadow-lg border-l-4 
                            <?php 
                                if ($status_class == 'completed') echo 'border-green-500';
                                elseif ($status_class == 'in_progress') echo 'border-blue-500';
                                elseif ($status_class == 'pending') echo 'border-yellow-500';
                                else echo 'border-gray-500';
                            ?> 
                            rounded-lg overflow-hidden border border-gray-100">
                            
                            <div class="px-6 py-4 flex justify-between items-center bg-gray-50">
                                <h4 class="text-lg font-bold text-gray-800">
                                    <span class="text-red-600 mr-2">#<?php echo htmlspecialchars($request['request_id']); ?></span>
                                    <?php echo htmlspecialchars($request['brand'] . ' ' . $request['model']); ?>
                                    <span class="text-sm font-normal text-gray-500">(<?php echo htmlspecialchars($request['license_plate']); ?>)</span>
                                </h4>
                                <span class="status <?php echo $status_class; ?>">
                                    <?php echo getThaiStatus($request['status']); ?>
                                </span>
                            </div>

                            <div class="p-6">
                                <div class="grid grid-cols-2 gap-y-3 gap-x-6 text-sm text-gray-700 mb-4">
                                    <div>
                                        <p class="font-semibold">วันที่นัดหมาย:</p> 
                                        <span class="font-medium"><?php echo date('d M Y H:i', strtotime($request['appointment_date'])); ?></span>
                                    </div>
                                    <div>
                                        <p class="font-semibold">ค่าใช้จ่าย (โดยประมาณ/สุดท้าย):</p> 
                                        <span class="font-bold text-lg text-red-700">
                                            <?php echo $request['cost'] ? number_format($request['cost'], 2) . ' บาท' : 'อยู่ระหว่างประเมิน'; ?>
                                        </span>
                                    </div>
                                    <div class="col-span-2">
                                        <p class="font-semibold">อาการที่แจ้ง:</p>
                                        <p class="text-gray-600 border-l-2 border-gray-200 pl-3 italic"><?php echo htmlspecialchars(substr($request['description'], 0, 100)) . (strlen($request['description']) > 100 ? '...' : ''); ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($status_class == 'completed'): ?>
                                    
                                    <?php if (!empty($request['pickup_date'])): ?>
                                        <div class="p-4 bg-green-100 border-l-4 border-green-500 rounded-md mt-4">
                                            <p class="text-lg font-bold text-green-800">
                                                รถพร้อมรับ: <span class="text-green-900"><?php echo date('d M Y', strtotime($request['pickup_date'])); ?></span>
                                            </p>
                                        </div>
                                    <?php elseif ($has_summary): ?>
                                        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md flex flex-col sm:flex-row items-center justify-between">
                                            <p class="font-semibold text-yellow-800 mb-2 sm:mb-0">ช่างได้ประเมินค่าใช้จ่ายเสร็จแล้ว</p>
                                            <button onclick="showPickupDateForm('form-<?php echo $request['request_id']; ?>', '<?php echo date('Y-m-d', strtotime($request['appointment_date'] . ' +1 day')); ?>')" 
                                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-md shadow transition whitespace-nowrap">ยืนยันวันนัดรับรถ</button>
                                        </div>
                                    <?php endif; ?>

                                <?php endif; ?>

                                <div id="form-<?php echo $request['request_id']; ?>" class="details-section">
                                    <form action="summary.php" method="POST" class="flex flex-col md:flex-row items-center gap-2 pt-4">
                                        <input type="hidden" name="action" value="update_pickup_date">
                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                        <div class="w-full md:w-auto flex-grow">
                                            <label for="pickup_date_<?php echo $request['request_id']; ?>" class="sr-only">วันที่นัดรับรถ</label>
                                            <input type="date" id="pickup_date_<?php echo $request['request_id']; ?>" name="pickup_date" required class="w-full px-4 py-2 border rounded-md focus:ring focus:ring-emerald-200">
                                        </div>
                                        <button type="submit" class="w-full md:w-auto px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-md shadow transition">บันทึกวันรับรถ</button>
                                    </form>
                                </div>
                                
                                <div class="flex justify-end space-x-2 mt-4">
                                    <?php if ($is_editable): ?>
                                        <a href="edit_request.php?request_id=<?php echo $request['request_id']; ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-md transition">แก้ไข</a>
                                        <button onclick="showCancelModal(<?php echo $request['request_id']; ?>)" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-md transition">ยกเลิก</button>
                                    <?php endif; ?>
                                    
                                    <?php if ($status_class == 'completed' && $has_summary): ?>
                                        <!-- <a href="repair_details.php?id=<?php echo $request['request_id']; ?>" target="_blank" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white font-semibold rounded-md transition">
                                            พิมพ์ใบสรุป/ใบรับรถ
                                        </a> -->
                                        
                                        <button onclick="toggleDetails('details-<?php echo $request['request_id']; ?>')" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-md transition">ดูสรุปค่าใช้จ่าย</button>
                                    <?php endif; ?>
                                </div>


                                <?php if ($status_class == 'completed' && $has_summary): ?>
                                    <?php $parts_data = json_decode($request['parts_details'], true); ?>
                                    <div id="details-<?php echo $request['request_id']; ?>" class="details-section bg-gray-50 p-4 rounded-md mt-4">
                                        <h5 class="text-md font-bold mb-3 border-b pb-2">รายละเอียดค่าใช้จ่าย (โดยช่าง)</h5>
                                        
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left table-auto text-sm">
                                                <thead>
                                                    <tr class="bg-gray-200 text-gray-700">
                                                        <th class="p-2 w-1/2">รายการ</th>
                                                        <th class="p-2 w-1/6 text-right">ราคา/หน่วย</th>
                                                        <th class="p-2 w-1/6 text-center">จำนวน</th>
                                                        <th class="p-2 w-1/6 text-right">รวม</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($parts_data as $part): ?>
                                                    <tr class="border-b">
                                                        <td class="p-2"><?php echo htmlspecialchars($part['name']); ?></td>
                                                        <td class="p-2 text-right"><?php echo number_format($part['unit_price'], 2); ?></td>
                                                        <td class="p-2 text-center"><?php echo number_format($part['quantity']); ?></td>
                                                        <td class="p-2 text-right font-semibold"><?php echo number_format($part['subtotal'], 2); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="flex flex-col items-end mt-4 text-sm font-bold space-y-1">
                                            <p>ค่าแรงช่าง: <span class="text-gray-900"><?php echo number_format($request['labor_cost'], 2); ?> บาท</span></p>
                                            <p>ยอดรวมอะไหล่: <span class="text-gray-900"><?php echo number_format($request['cost'] - $request['labor_cost'], 2); ?> บาท</span></p>
                                            <p class="text-xl text-red-600 mt-3 border-t pt-2 w-full text-right">ยอดรวมทั้งหมด: <span class="text-red-600"><?php echo number_format($request['cost'], 2); ?> บาท</span></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">ยืนยันการยกเลิก</h3>
            <p class="mb-6">คุณแน่ใจหรือไม่ที่จะยกเลิกรายการแจ้งซ่อมนี้?</p>
            <div class="flex justify-center space-x-4">
                <button id="cancel-confirm-btn" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-md transition">ยืนยัน</button>
                <button onclick="closeModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-md transition">ปิด</button>
            </div>
        </div>
    </div>

    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy;  2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>
    
    <script>
        // Toggles the visibility of a details section
        function toggleDetails(elementId) {
            const detailsSection = document.getElementById(elementId);
            if (detailsSection) {
                detailsSection.classList.toggle('active');
            }
        }
        
        // Shows the pickup date form and sets the minimum date
        function showPickupDateForm(formId, minDate) {
            const formSection = document.getElementById(formId);
            if (formSection) {
                // Find the date input inside the form and set its min attribute
                const dateInput = formSection.querySelector('input[type="date"]');
                if (dateInput) {
                    dateInput.min = minDate;
                }
                // Hide other forms that might be open
                document.querySelectorAll('.details-section').forEach(section => {
                    if (section.id !== formId) {
                        section.classList.remove('active');
                    }
                });
                
                // Toggle this form section
                formSection.classList.toggle('active');
            }
        }
        
        // Shows the cancellation confirmation modal
        function showCancelModal(requestId) {
            const modal = document.getElementById('cancelModal');
            const confirmBtn = document.getElementById('cancel-confirm-btn');
            confirmBtn.onclick = function() {
                window.location.href = `cancel_request.php?request_id=${requestId}`;
            };
            modal.style.display = 'flex';
        }

        // Hides the modal
        function closeModal() {
            const modal = document.getElementById('cancelModal');
            modal.style.display = 'none';
        }

        // *** โค้ดที่เพิ่ม: ปิด Modal เมื่อโหลดหน้าเว็บ ***
        document.addEventListener('DOMContentLoaded', () => {
             closeModal(); 
        });
        
        // Close the modal when the user clicks anywhere outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>