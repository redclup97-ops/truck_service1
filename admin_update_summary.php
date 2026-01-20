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

// Check if a request ID is provided in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // ใช้ header redirect กลับไปหน้า dashboard หากไม่มี ID แทนการ echo
    header("Location: admin_dashboard.php");
    exit;
}

// Sanitize the input to prevent SQL injection
$request_id = $_GET['id']; // เตรียมไว้ bind_param

// SQL query to fetch detailed information for a specific service request
$sql = "SELECT 
            sr.request_id,
            sr.description,
            sr.appointment_date,
            sr.status,
            sr.cost,
            sr.created_at,
            v.license_plate,
            v.brand,
            v.model,
            v.year,
            c.first_name,
            c.last_name,
            c.email,
            c.phone
        FROM 
            service_requests sr
        JOIN 
            vehicles v ON sr.vehicle_id = v.vehicle_id
        JOIN 
            customers c ON v.customer_id = c.customer_id
        WHERE
            sr.request_id = ?";

// Use a prepared statement to prevent SQL injection
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id); // 'i' indicates integer type for the ID
$stmt->execute();
$result = $stmt->get_result();
$repair = $result->fetch_assoc();
$stmt->close();

// Check if a repair request with the given ID exists
if (!$repair) {
    echo "<p class='text-center text-red-600 mt-8'>ไม่พบรายการแจ้งซ่อมที่ระบุ</p>";
    exit;
}

// *** ส่วนที่เพิ่ม/แก้ไข: ดึงข้อมูลสรุปการซ่อมและอะไหล่ที่เคยบันทึกไว้ใน repair_summary ***
$sql_summary = "SELECT parts_details, labor_cost FROM repair_summary WHERE request_id = ?";
$stmt_summary = $conn->prepare($sql_summary);
$stmt_summary->bind_param("i", $request_id);
$stmt_summary->execute();
$result_summary = $stmt_summary->get_result();
$summary_data = $result_summary->fetch_assoc();
$stmt_summary->close();

// เตรียมค่าเริ่มต้นสำหรับ JavaScript และ HTML
$initial_parts_json = '[]';
$initial_labor_cost = '0.00'; 

if ($summary_data) {
    // parts_details อาจเป็น NULL หรือ JSON ว่าง ถ้าเป็นไปได้
    $initial_parts_json = $summary_data['parts_details'] ?: '[]';
    // ใช้ number_format เพื่อให้แสดงผลเป็นทศนิยมสองตำแหน่งใน Input
    $initial_labor_cost = number_format($summary_data['labor_cost'], 2, '.', '');
}
// *** สิ้นสุดส่วนที่เพิ่ม/แก้ไข ***
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO Admin | อัพเดทข้อมูลการแจ้งซ่อม #<?php echo htmlspecialchars($repair['request_id']); ?></title>
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
        .detail-item {
            margin-bottom: 1.5rem;
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
            <h2 class="text-2xl font-bold text-gray-900">อัพเดทข้อมูลการแจ้งซ่อม #<?php echo htmlspecialchars($repair['request_id']); ?></h2>
            <a href="admin_dashboard.php" class="text-sm font-semibold text-gray-600 hover:text-red-600 transition-colors">&larr; กลับไปหน้าแผงควบคุม</a>
        </div>
        
        <div class="card">
            <form action="admin_summary.php" method="POST">
                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($repair['request_id']); ?>">
                
                <div class="mb-6">
                    <label for="status" class="block text-gray-700 font-bold mb-2">สถานะการซ่อม</label>
                    <select id="status" name="status" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500" required>
                        <option value="pending" <?php echo $repair['status'] == 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                        <option value="in_progress" <?php echo $repair['status'] == 'in_progress' ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                        <option value="completed" <?php echo $repair['status'] == 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                        <option value="cancelled" <?php echo $repair['status'] == 'cancelled' ? 'selected' : ''; ?>>ยกเลิกแล้ว</option>
                    </select>
                </div>

                <h3 class="text-lg font-bold mb-4">รายการอะไหล่และค่าใช้จ่าย</h3>
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-left table-auto">
                        <thead>
                            <tr class="bg-gray-200 text-gray-700">
                                <th class="p-2 w-1/2">รายการอะไหล่</th>
                                <th class="p-2 w-1/6">ราคา/หน่วย</th>
                                <th class="p-2 w-1/6">จำนวน</th>
                                <th class="p-2 w-1/6 text-right">ยอดรวม</th>
                                <th class="p-2"></th>
                            </tr>
                        </thead>
                        <tbody id="parts-table-body">
                            </tbody>
                    </table>
                </div>

                <div class="flex justify-start space-x-2 mb-6">
                    <button type="button" id="add-part-btn" class="bg-blue-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-blue-700 transition-colors duration-300">
                        + เพิ่มรายการอะไหล่
                    </button>
                </div>

                <div class="flex flex-col items-end space-y-2 mt-6">
                    <div class="flex justify-between w-full md:w-1/2">
                        <span class="font-bold text-gray-700">ค่าแรง:</span>
                        <input type="number" id="labor-cost" name="labor_cost" value="<?php echo htmlspecialchars($initial_labor_cost); ?>" min="0" step="0.01" class="w-1/2 px-2 py-1 text-right border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500" oninput="calculateTotal()" required>
                    </div>
                    <div class="flex justify-between w-full md:w-1/2">
                        <span class="font-bold text-gray-700">ยอดรวมอะไหล่:</span>
                        <span id="parts-total" class="font-semibold text-gray-900">0.00 บาท</span>
                        <input type="hidden" name="parts_cost" id="parts-cost-input">
                    </div>
                    <div class="flex justify-between w-full md:w-1/2 border-t pt-2">
                        <span class="font-bold text-red-600 text-xl">ค่าใช้จ่ายทั้งหมด:</span>
                        <span id="grand-total" class="font-bold text-red-600 text-xl">0.00 บาท</span>
                        <input type="hidden" name="total_cost" id="total-cost-input">
                    </div>
                </div>

                <div class="flex justify-end mt-8">
                    <button type="submit" class="bg-red-600 text-white px-8 py-3 rounded-md font-bold text-lg hover:bg-red-700 transition-colors duration-300">
                        สรุปค่าใช้จ่าย
                    </button>
                </div>
            </form>
        </div>
    </main>

   <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy;  2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // *** ส่วนที่เพิ่ม/แก้ไข: รับข้อมูลอะไหล่ที่เคยบันทึกไว้ และตั้งค่า step="0.01" ***
        const partsTableBody = document.getElementById('parts-table-body');
        const addPartBtn = document.getElementById('add-part-btn');
        const laborCostInput = document.getElementById('labor-cost');
        
        // รับข้อมูลอะไหล่ที่เคยบันทึกไว้จาก PHP (JSON String)
        const initialPartsData = JSON.parse('<?php echo addslashes($initial_parts_json); ?>');
        
        let rowCounter = 0;

        function addPartRow(part = null) {
            rowCounter++;
            
            // กำหนดค่าเริ่มต้นจากข้อมูลเก่า หรือค่าว่าง/0 ถ้าไม่มีข้อมูลเก่า
            const partName = part ? part.name : '';
            // ใช้ parseFloat เพื่อให้แน่ใจว่าเป็นตัวเลข
            const unitPrice = part ? parseFloat(part.unit_price).toFixed(2) : '0.00'; 
            const quantity = part ? parseInt(part.quantity) : 1;
            const subtotal = part ? parseFloat(part.subtotal).toFixed(2) : '0.00';
            
            const newRow = document.createElement('tr');
            newRow.classList.add('border-b');
            newRow.innerHTML = `
                <td class="p-2">
                    <input type="text" name="parts[${rowCounter}][name]" placeholder="ชื่ออะไหล่" class="w-full px-2 py-1 border rounded-md" value="${partName}" required>
                </td>
                <td class="p-2">
                    <input type="number" name="parts[${rowCounter}][unit_price]" placeholder="ราคา" class="w-full px-2 py-1 border rounded-md text-right" step="0.01" min="0" value="${unitPrice}" oninput="calculateTotal()">
                </td>
                <td class="p-2">
                    <input type="number" name="parts[${rowCounter}][quantity]" placeholder="จำนวน" class="w-full px-2 py-1 border rounded-md text-right" min="1" value="${quantity}" oninput="calculateTotal()">
                </td>
                <td class="p-2 text-right">
                    <span id="subtotal-${rowCounter}">${subtotal}</span> บาท
                </td>
                <td class="p-2 text-center">
                    <button type="button" class="text-red-500 hover:text-red-700" onclick="removePartRow(this)">
                        ลบ
                    </button>
                </td>
            `;
            partsTableBody.appendChild(newRow);
        }

        function removePartRow(button) {
            const row = button.closest('tr');
            row.remove();
            calculateTotal();
        }

        function calculateTotal() {
            let partsTotal = 0;
            // ใช้ querySelectorAll เพื่อดึงเฉพาะ input ที่มี name ขึ้นต้นด้วย 'parts'
            const partRows = partsTableBody.querySelectorAll('tr');
            
            partRows.forEach(row => {
                const unitPriceInput = row.querySelector('input[name*="unit_price"]');
                const quantityInput = row.querySelector('input[name*="quantity"]');
                const subtotalSpan = row.querySelector('span[id*="subtotal"]');

                const unitPrice = parseFloat(unitPriceInput.value) || 0;
                const quantity = parseInt(quantityInput.value) || 0;
                const subtotal = unitPrice * quantity;
                
                subtotalSpan.textContent = subtotal.toFixed(2);
                partsTotal += subtotal;
            });
            
            const laborCost = parseFloat(laborCostInput.value) || 0;
            const grandTotal = partsTotal + laborCost;
            
            document.getElementById('parts-total').textContent = partsTotal.toFixed(2) + ' บาท';
            document.getElementById('grand-total').textContent = grandTotal.toFixed(2) + ' บาท';
            
            // Update hidden inputs for form submission
            document.getElementById('parts-cost-input').value = partsTotal.toFixed(2);
            document.getElementById('total-cost-input').value = grandTotal.toFixed(2);
        }

        addPartBtn.addEventListener('click', () => addPartRow());

        // แก้ไข DOMContentLoaded Listener: โหลดข้อมูลอะไหล่เก่าเมื่อหน้าเว็บโหลด
        document.addEventListener('DOMContentLoaded', () => {
            if (initialPartsData.length > 0) {
                // โหลดข้อมูลอะไหล่เก่า
                initialPartsData.forEach(part => addPartRow(part));
            } else {
                // ถ้าไม่มีข้อมูลเก่า ให้เพิ่มแถวว่างเริ่มต้น 1 แถว
                addPartRow();
            }
            // เรียกคำนวณครั้งแรกเพื่อให้ยอดรวมแสดงผลถูกต้อง
            calculateTotal(); 
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>