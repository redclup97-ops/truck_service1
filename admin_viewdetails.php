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

// SQL query เพื่อดึงข้อมูลการแจ้งซ่อมทั้งหมด, ข้อมูลลูกค้า, ทะเบียนรถ และค่าใช้จ่ายรวมจากตาราง repair_summary
// ใช้ LEFT JOIN เพื่อให้แสดงรายการแจ้งซ่อมทั้งหมด แม้ว่ายังไม่มีการบันทึกสรุปค่าใช้จ่ายก็ตาม
$sql = "SELECT 
            sr.request_id,
            sr.status,
            sr.description,
            sr.created_at,
            v.license_plate,
            c.first_name,
            c.last_name,
            c.customer_id,
            IFNULL(rs.total_cost, 0) AS total_cost,
            IFNULL(rs.labor_cost, 0) AS labor_cost,
            rs.parts_details
        FROM 
            service_requests sr
        JOIN 
            vehicles v ON sr.vehicle_id = v.vehicle_id
        JOIN 
            customers c ON v.customer_id = c.customer_id
        LEFT JOIN
            repair_summary rs ON sr.request_id = rs.request_id
        ORDER BY
            sr.created_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO Admin | รายการแจ้งซ่อมและค่าใช้จ่าย</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Kanit Font -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
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
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            color: white;
            text-transform: capitalize;
        }
        /* กำหนดสีสำหรับสถานะต่างๆ */
        .status-badge.pending { background-color: #f59e0b; }
        .status-badge.in_progress { background-color: #3b82f6; }
        .status-badge.completed { background-color: #10b981; }
        .status-badge.cancelled { background-color: #ef4444; }
        .table-responsive {
            overflow-x: auto;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 2rem;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
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
                <a href="admin_dashboard.php" class="hover:text-red-600 transition-colors duration-300">แผงควบคุม</a>
                <a href="admin_logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">รายการแจ้งซ่อมและค่าใช้จ่ายทั้งหมด</h2>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="table-responsive">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                รหัสลูกค้า
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                รหัสแจ้งซ่อม
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ลูกค้า
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ทะเบียนรถ
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                รายละเอียด
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                สถานะ
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ค่าใช้จ่ายรวม
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ดูรายละเอียด
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                การจัดการ
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) { 
                                // เตรียมข้อมูลสำหรับ JavaScript
                                $parts_details = json_decode($row['parts_details'], true) ?? [];
                                $labor_cost = (float)$row['labor_cost'];
                                $total_cost = (float)$row['total_cost'];
                                $data_for_js = [
                                    'parts_details' => $parts_details,
                                    'labor_cost' => $labor_cost,
                                    'total_cost' => $total_cost
                                ];
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($row['customer_id']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($row['request_id']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($row['license_plate']); ?>
                                </td>
                                <td class="px-6 py-4 max-w-xs overflow-hidden truncate text-sm text-gray-600">
                                    <?php echo htmlspecialchars($row['description']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $status_class = str_replace(' ', '_', strtolower(htmlspecialchars($row['status'])));
                                        $display_status = '';
                                        switch ($status_class) {
                                            case 'pending': $display_status = 'รอดำเนินการ'; break;
                                            case 'in_progress': $display_status = 'กำลังดำเนินการ'; break;
                                            case 'completed': $display_status = 'เสร็จสิ้น'; break;
                                            case 'cancelled': $display_status = 'ยกเลิก'; break;
                                            default: $display_status = htmlspecialchars($row['status']); break;
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo $display_status; ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-medium">
                                    <?php echo number_format($row['total_cost'], 2); ?> บาท
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <?php if (!empty($row['parts_details'])) { ?>
                                        <button onclick="showDetails('<?php echo htmlspecialchars(json_encode($data_for_js), ENT_QUOTES, 'UTF-8'); ?>')" class="text-blue-600 hover:text-blue-900">ดูรายการ</button>
                                    <?php } else { ?>
                                        <span class="text-gray-400">ไม่มี</span>
                                    <?php } ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <a href="admin_edit_repair.php?id=<?php echo htmlspecialchars($row['request_id']); ?>" class="text-indigo-600 hover:text-indigo-900">แก้ไข</a>
                                        <a href="repair_details.php?id=<?php echo htmlspecialchars($row['request_id']); ?>"  class="bg-red-600 text-white px-3 py-1 rounded-md text-xs hover:bg-red-700 transition-colors duration-200">ออกรายงาน</a>
                                    </div>
                                </td>
                            </tr>
                        <?php }
                        } else { ?>
                            <tr>
                                <td colspan="9" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    ไม่พบรายการแจ้งซ่อมในระบบ
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy;  2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>

    <!-- The Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 class="text-xl font-bold mb-4">รายละเอียดค่าใช้จ่าย</h3>
            <div id="detailsContent">
                <!-- Details will be displayed here -->
            </div>
        </div>
    </div>

    <script>
        // Get the modal element
        const modal = document.getElementById("detailsModal");

        // Function to show all details in the modal
        function showDetails(dataJson) {
            const detailsContent = document.getElementById("detailsContent");
            detailsContent.innerHTML = ''; // Clear previous content

            try {
                const data = JSON.parse(dataJson);
                const parts = data.parts_details;
                const laborCost = data.labor_cost;
                const totalCost = data.total_cost;
                let partsTotalCost = 0;

                let html = '<div class="mb-4">';
                html += '<h4 class="text-lg font-semibold mb-2">รายการอะไหล่</h4>';
                
                if (parts && parts.length > 0) {
                    html += '<table class="min-w-full divide-y divide-gray-200 mb-2">';
                    html += '<thead class="bg-gray-50"><tr>';
                    html += '<th class="px-4 py-2 text-left text-sm font-medium text-gray-500">รายการอะไหล่</th>';
                    html += '<th class="px-4 py-2 text-center text-sm font-medium text-gray-500">ราคา/หน่วย</th>';
                    html += '<th class="px-4 py-2 text-center text-sm font-medium text-gray-500">จำนวน</th>';
                    html += '<th class="px-4 py-2 text-right text-sm font-medium text-gray-500">ยอดรวม</th>';
                    html += '</tr></thead><tbody>';

                    parts.forEach(part => {
                        const subtotal = parseFloat(part.subtotal);
                        partsTotalCost += subtotal;
                        html += '<tr>';
                        html += `<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">${part.name || 'ไม่ระบุชื่อ'}</td>`;
                        html += `<td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-600">${parseFloat(part.unit_price).toFixed(2)}</td>`;
                        html += `<td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-600">${parseInt(part.quantity, 10)}</td>`;
                        html += `<td class="px-4 py-2 whitespace-nowrap text-sm text-right text-gray-600">${subtotal.toFixed(2)}</td>`;
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                } else {
                    html += '<p class="text-gray-500">ไม่มีรายการอะไหล่</p>';
                }

                html += '</div>';

                html += '<div class="flex flex-col items-end mt-4 text-sm font-bold space-y-1">';
                html += `<p>ค่าแรง: <span class="text-gray-900">${laborCost.toFixed(2)} บาท</span></p>`;
                html += `<p>รวมค่าอะไหล่: <span class="text-gray-900">${partsTotalCost.toFixed(2)} บาท</span></p>`;
                html += `<p class="text-lg text-red-600 mt-2">ยอดรวมทั้งหมด: <span class="text-red-600">${totalCost.toFixed(2)} บาท</span></p>`;
                html += '</div>';

                detailsContent.innerHTML = html;
            } catch (e) {
                console.error("Failed to parse JSON:", e);
                detailsContent.innerHTML = '<p class="text-center text-red-500">ข้อมูลผิดพลาด ไม่สามารถแสดงผลได้</p>';
            }

            modal.style.display = "block";
        }

        // Function to close the modal
        function closeModal() {
            modal.style.display = "none";
        }

        // Close the modal when the user clicks anywhere outside of it
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
