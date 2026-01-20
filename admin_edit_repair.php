<?php
session_start();

// Include the database connection file
include 'db_connect.php';

// Check if the user is an admin. If not, redirect to the login page.
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Get the admin's first name from the session for display
$admin_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'ผู้ดูแลระบบ';

// --- ฟังก์ชันการจัดการข้อมูล (UPDATE, DELETE, และ SET QUEUE) ---
$message = '';
$message_type = '';

// ตรวจสอบว่ามีการส่งฟอร์มเพื่อแก้ไขสถานะมาหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $request_id = $conn->real_escape_string($_POST['request_id']);
    $new_status = $conn->real_escape_string($_POST['status']);
    
    // Use a Prepared Statement to prevent SQL Injection
    $stmt = $conn->prepare("UPDATE service_requests SET status = ? WHERE request_id = ?");
    $stmt->bind_param("si", $new_status, $request_id);
    
    if ($stmt->execute()) {
        $message = "สำเร็จ! สถานะการซ่อมได้รับการอัปเดตแล้ว";
        $message_type = "success";
    } else {
        $message = "ผิดพลาด! ไม่สามารถอัปเดตสถานะได้: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Check if a form was submitted to delete an entry
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_request'])) {
    $request_id = $conn->real_escape_string($_POST['request_id']);

    // Begin transaction for data integrity
    $conn->begin_transaction();
    try {
        // Delete from service_requests (repair_summary จะถูกลบตามเพราะมี ON DELETE CASCADE)
        $stmt_main_del = $conn->prepare("DELETE FROM service_requests WHERE request_id = ?");
        $stmt_main_del->bind_param("i", $request_id);
        
        if (!$stmt_main_del->execute()) {
            throw new Exception("Error deleting service request: " . $stmt_main_del->error);
        }
        $stmt_main_del->close();

        $conn->commit();
        $message = "สำเร็จ! รายการซ่อมถูกลบเรียบร้อยแล้ว";
        $message_type = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "ผิดพลาด! ไม่สามารถลบรายการได้: " . $e->getMessage();
        $message_type = "error";
    }
}

// ตรวจสอบว่ามีการส่งฟอร์มเพื่อกำหนดเลขคิวมาหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_queue'])) {
    $request_id = $conn->real_escape_string($_POST['request_id']);
    $queue_number = $conn->real_escape_string($_POST['queue_number']);

    // Use a Prepared Statement to update the queue number
    $stmt = $conn->prepare("UPDATE service_requests SET queue_number = ? WHERE request_id = ?");
    $stmt->bind_param("si", $queue_number, $request_id);

    if ($stmt->execute()) {
        $message = "สำเร็จ! เลขคิว `" . htmlspecialchars($queue_number) . "` ได้รับการกำหนดแล้ว";
        $message_type = "success";
    } else {
        $message = "ผิดพลาด! ไม่สามารถกำหนดเลขคิวได้: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}


// Fetch all repair requests from the database for the main table
$sql_all = "SELECT 
                sr.request_id,
                sr.appointment_date,
                sr.description,
                sr.status,
                sr.created_at,
                sr.queue_number,
                c.first_name,
                c.last_name,
                v.brand, 
                v.model, 
                v.license_plate
            FROM 
                service_requests sr
            JOIN 
                vehicles v ON sr.vehicle_id = v.vehicle_id
            JOIN
                customers c ON v.customer_id = c.customer_id
            ORDER BY 
                sr.created_at DESC";
            
$result_all = $conn->query($sql_all);

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO Admin | จัดการรายการซ่อม</title>
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
            flex-grow: 1;
            padding: 2rem;
            margin: 0 auto;
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            overflow: hidden;
        }
        th, td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        th {
            background-color: #f9fafb;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.875rem;
            color: #4b5563;
        }
        /* Status Badge Styling */
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            text-transform: capitalize;
        }
        .status-pending { background-color: #f59e0b; color: #fff; } /* Amber */
        .status-in_progress { background-color: #3b82f6; color: #fff; } /* Blue */
        .status-completed { background-color: #10b981; color: #fff; } /* Green */
        .status-cancelled { background-color: #6b7280; color: #fff; } /* Gray */
    </style>
</head>
<body>

    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center rounded-md">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>
            <div class="flex items-center space-x-8 text-sm font-semibold">
                <a href="#" onclick="javascript:history.back()" class="text-white hover:text-red-600 transition-colors duration-300">ย้อนกลับ</a>
                
                <div class="text-white">
                    สวัสดี , <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                </div>
                <a href="admin_dashboard.php" class="hover:text-red-600 transition-colors duration-300">แผงควบคุม</a>
                <a href="admin_logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="bg-white p-8 rounded-xl shadow-lg">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-8 border-b pb-4">จัดการรายการซ่อมทั้งหมด</h1>

            <?php if (!empty($message)): ?>
            <div class="p-4 mb-4 rounded-lg border-l-4 font-semibold
                <?php echo ($message_type == 'success') ? 'bg-green-100 text-green-700 border-green-500' : 'bg-red-100 text-red-700 border-red-500'; ?>" role="alert">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            <?php endif; ?>

            <?php
            // Check if there are any repair requests
            if ($result_all->num_rows > 0) {
                echo "<div class='overflow-x-auto'>";
                echo "<table class='min-w-full bg-white rounded-xl shadow-inner'>";
                echo "<thead>";
                echo "<tr class='bg-gray-100 text-gray-600 uppercase text-sm leading-normal'>";
                echo "<th class='py-3 px-6 text-left'>ID</th>";
                echo "<th class='py-3 px-6 text-left'>ลูกค้า</th>";
                echo "<th class='py-3 px-6 text-left'>รถยนต์ / ทะเบียน</th>";
                echo "<th class='py-3 px-6 text-left'>อาการแจ้ง</th>";
                echo "<th class='py-3 px-6 text-center'>วันที่นัดหมาย</th>";
                echo "<th class='py-3 px-6 text-center'>เลขคิว</th>";
                echo "<th class='py-3 px-6 text-center'>สถานะ</th>";
                echo "<th class='py-3 px-6 text-center'>จัดการ</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody class='text-gray-700 text-sm font-light'>";
                
                // Loop through each repair request to display it
                while($row = $result_all->fetch_assoc()) {
                    $status_text = '';
                    $status_class = '';
                    $status_raw = strtolower($row['status']);
                    
                    switch ($status_raw) {
                        case 'pending':
                            $status_text = 'รอดำเนินการ';
                            $status_class = 'status-pending';
                            break;
                        case 'in_progress':
                            $status_text = 'กำลังซ่อม';
                            $status_class = 'status-in_progress';
                            break;
                        case 'completed':
                            $status_text = 'ซ่อมเสร็จแล้ว';
                            $status_class = 'status-completed';
                            break;
                        case 'cancelled':
                            $status_text = 'ยกเลิก';
                            $status_class = 'status-cancelled';
                            break;
                        default:
                            $status_text = $row['status'];
                            $status_class = 'status-cancelled';
                            break;
                    }

                    echo "<tr class='border-b border-gray-200 hover:bg-gray-50'>";
                    echo "<td class='py-3 px-6 text-left whitespace-nowrap font-bold'>" . $row["request_id"] . "</td>";
                    echo "<td class='py-3 px-6 text-left whitespace-nowrap'>" . htmlspecialchars($row["first_name"] . " " . $row["last_name"]) . "</td>";
                    echo "<td class='py-3 px-6 text-left'>
                            <span class='font-medium'>" . htmlspecialchars($row["brand"] . " " . $row["model"]) . "</span><br>
                            <span class='text-xs text-gray-500'>" . htmlspecialchars($row["license_plate"]) . "</span>
                        </td>";
                    echo "<td class='py-3 px-6 text-left max-w-[200px] truncate'>" . htmlspecialchars($row["description"]) . "</td>";
                    echo "<td class='py-3 px-6 text-center whitespace-nowrap'>" . date('d/m/Y H:i', strtotime($row['appointment_date'])) . "</td>";
                    echo "<td class='py-3 px-6 text-center font-bold'>" . (isset($row['queue_number']) ? htmlspecialchars($row['queue_number']) : '-') . "</td>";
                    echo "<td class='py-3 px-6 text-center'>";
                    echo "<span class='status-badge " . $status_class . "'>" . $status_text . "</span>";
                    echo "</td>";
                    
                    // --- Action Column ---
                    echo "<td class='py-3 px-6 text-center whitespace-nowrap'>"; 
                    
                    // 1. Status Update Form 
                    echo "<form method='post' class='flex items-center justify-center space-x-2 mb-2 p-1 border border-gray-200 rounded-md bg-gray-50'>";
                    echo "<input type='hidden' name='request_id' value='" . $row['request_id'] . "'>";
                    echo "<input type='hidden' name='update_status' value='1'>";
                    echo "<select name='status' class='border-gray-300 rounded-md shadow-sm p-1 text-xs focus:ring-blue-500 focus:border-blue-500'>";
                    echo "<option value='pending' " . ($row['status'] == 'pending' ? 'selected' : '') . ">รอการอนุมัติ</option>";
                    echo "<option value='in_progress' " . ($row['status'] == 'in_progress' ? 'selected' : '') . ">กำลังซ่อม</option>";
                    echo "<option value='completed' " . ($row['status'] == 'completed' ? 'selected' : '') . ">ซ่อมเสร็จแล้ว</option>";
                    echo "<option value='cancelled' " . ($row['status'] == 'cancelled' ? 'selected' : '') . ">ยกเลิก</option>";
                    echo "</select>";
                    echo "<button type='submit' class='bg-blue-600 hover:bg-blue-700 text-white font-bold py-1.5 px-3 rounded-md text-xs shadow transition duration-200'>อัปเดต</button>";
                    echo "</form>";

                    // 2. Queue and Delete Buttons
                    echo "<div class='flex flex-wrap justify-center items-center space-x-2 mt-2'>";
                    
                    // Set Queue Form (Conditional display)
                    if ($status_raw == 'in_progress' && empty($row['queue_number'])) {
                        echo "<form method='post' class='inline-flex items-center space-x-1'>";
                        echo "<input type='hidden' name='request_id' value='" . $row['request_id'] . "'>";
                        echo "<input type='hidden' name='set_queue' value='1'>";
                        echo "<input type='text' name='queue_number' placeholder='คิว' class='border-gray-300 rounded-md shadow-sm p-1 w-16 text-xs' required>";
                        echo "<button type='submit' class='bg-purple-600 hover:bg-purple-700 text-white font-bold py-1.5 px-2 rounded-md text-xs shadow transition duration-200'>กำหนดคิว</button>";
                        echo "</form>";
                    } else if ($status_raw == 'in_progress' && !empty($row['queue_number'])) {
                        echo "<span class='bg-purple-200 text-purple-800 text-xs font-bold py-1 px-3 rounded-full'>คิวที่: " . htmlspecialchars($row['queue_number']) . "</span>";
                    }

                    // Delete Form
                    echo "<form method='post' class='inline-block'>";
                    echo "<input type='hidden' name='request_id' value='" . $row['request_id'] . "'>";
                    echo "<input type='hidden' name='delete_request' value='1'>";
                    echo "<button type='submit' class='bg-red-500 hover:bg-red-700 text-white font-bold py-1.5 px-3 rounded-md text-xs shadow transition duration-200' onclick=\"return confirm('คุณแน่ใจหรือไม่ที่จะลบรายการนี้?');\">ลบ</button>";
                    echo "</form>";

                    echo "</div>"; // End of group buttons
                    echo "</td>"; // End of Action Column
                    echo "</tr>";
                }
                echo "</tbody>";
                echo "</table>";
                echo "</div>";
            } else {
                echo "<p class='text-center text-gray-500 p-8 border rounded-lg'>ไม่พบรายการแจ้งซ่อมในขณะนี้</p>";
            }
            ?>
        </div>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy;  2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
<?php $conn->close(); ?>