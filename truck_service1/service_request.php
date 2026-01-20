<?php
session_start();
include 'db_connect.php'; 

// Check login status
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

// *** โค้ดที่เพิ่ม: บังคับให้ลูกค้าล็อกอินก่อนทำรายการ ***
if (!$is_logged_in) {
    header("Location: login.php");
    exit;
}
// *** สิ้นสุดการเพิ่ม ***

$customer_id = $_SESSION['customer_id'];
$user_email = $_SESSION['email'] ?? ""; 

// Variable to hold error message
$error_message = "";

// Fetch user data from the database to pre-fill the form
$user_full_name = "";
$user_phone = "";
$customer_vehicles = [];
if ($customer_id) { // ตรวจสอบ $customer_id แทน $is_logged_in ซ้ำ
    // Fetch customer's personal info
    $sql_get_user_info = "SELECT first_name, last_name, phone FROM customers WHERE customer_id = ?";
    // ... (โค้ดดึงข้อมูลส่วนตัวที่เหลือ)
    $stmt_get_user_info = $conn->prepare($sql_get_user_info);
    $stmt_get_user_info->bind_param("i", $customer_id);
    $stmt_get_user_info->execute();
    $result_user_info = $stmt_get_user_info->get_result();
    if ($result_user_info->num_rows > 0) {
        $user_data = $result_user_info->fetch_assoc();
        $user_full_name = htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); 
        $user_phone = htmlspecialchars($user_data['phone']);
    }
    $stmt_get_user_info->close();

    // Fetch customer's vehicles
    $sql_get_vehicles = "SELECT license_plate, brand, model, year FROM vehicles WHERE customer_id = ?";
    $stmt_get_vehicles = $conn->prepare($sql_get_vehicles);
    $stmt_get_vehicles->bind_param("i", $customer_id);
    $stmt_get_vehicles->execute();
    $result_vehicles = $stmt_get_vehicles->get_result();
    $customer_vehicles = $result_vehicles->fetch_all(MYSQLI_ASSOC);
    $stmt_get_vehicles->close();
}

// ... (โค้ด PHP ที่เหลือทั้งหมด)

// Code for processing the service request form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data from the form, using session data for name and email
    $phone = $_POST['phone'];
    $brand = 'HINO'; 
    $model_year = $_POST['model_year'];
    // ตรวจสอบรูปแบบของ model_year ก่อน explode
    if (strpos($model_year, ' - ') !== false) {
        list($model, $year) = explode(' - ', $model_year);
    } else {
        $model = $model_year;
        $year = '';
    }
    
    $license_plate = $_POST['license_plate'];
    $description = $_POST['description'];
    $appointment_date = $_POST['appointment_date'];

    // --- Start Server-Side Validation for Appointment Date ---
    $min_appointment_timestamp = strtotime('+2 days');
    $submitted_timestamp = strtotime($appointment_date);
    $submitted_hour = date('H', $submitted_timestamp);

    if ($submitted_timestamp < $min_appointment_timestamp) {
        $error_message = "วันที่นัดหมายต้องล่วงหน้าอย่างน้อย 2 วัน กรุณาเลือกวันและเวลาใหม่";
    } elseif ($submitted_hour < 9 || $submitted_hour > 16) {
        $error_message = "เวลาที่นัดหมายต้องอยู่ในช่วง 09:00 - 16:00 เท่านั้น";
    } else {
        // --- End Server-Side Validation for Appointment Date ---
        $conn->begin_transaction();
        try {
            // Update the customer's phone number in the `customers` table
            if ($customer_id) {
                $sql_update_customer = "UPDATE customers SET phone = ? WHERE customer_id = ?";
                $stmt_update_customer = $conn->prepare($sql_update_customer);
                $stmt_update_customer->bind_param("si", $phone, $customer_id);
                $stmt_update_customer->execute();
                $stmt_update_customer->close();
            }

            // Check if vehicle exists and get its ID
            $sql_check_vehicle = "SELECT vehicle_id FROM vehicles WHERE license_plate = ?";
            $stmt_check_vehicle = $conn->prepare($sql_check_vehicle);
            $stmt_check_vehicle->bind_param("s", $license_plate);
            $stmt_check_vehicle->execute();
            $result_vehicle = $stmt_check_vehicle->get_result();

            if ($result_vehicle->num_rows > 0) {
                $vehicle_data = $result_vehicle->fetch_assoc();
                $vehicle_id = $vehicle_data['vehicle_id'];
                // SQL UPDATE statement to change the model and year
                $sql_update_vehicle = "UPDATE vehicles SET brand = ?, model = ?, year = ?, customer_id = ? WHERE vehicle_id = ?";
                $stmt_update_vehicle = $conn->prepare($sql_update_vehicle);
                $stmt_update_vehicle->bind_param("sssii", $brand, $model, $year, $customer_id, $vehicle_id);
                $stmt_update_vehicle->execute();
                $stmt_update_vehicle->close();
            } else {
                $sql_insert_vehicle = "INSERT INTO vehicles (customer_id, brand, model, year, license_plate) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert_vehicle = $conn->prepare($sql_insert_vehicle);
                $stmt_insert_vehicle->bind_param("issss", $customer_id, $brand, $model, $year, $license_plate);
                $stmt_insert_vehicle->execute();
                $vehicle_id = $conn->insert_id;
                $stmt_insert_vehicle->close();
            }
            $stmt_check_vehicle->close();

            // Save the service request data into the `service_requests` table
            $sql_insert_request = "INSERT INTO service_requests (vehicle_id, description, appointment_date) VALUES (?, ?, ?)";
            $stmt_insert_request = $conn->prepare($sql_insert_request);
            $stmt_insert_request->bind_param("iss", $vehicle_id, $description, $appointment_date);
            $stmt_insert_request->execute();
            $stmt_insert_request->close();

            $conn->commit();

            header("Location: summary.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
        }
    }
}

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO | นัดหมายเข้าซ่อม</title>
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
            align-items: flex-start; /* ปรับให้ฟอร์มอยู่ด้านบน */
            flex-grow: 1;
            padding: 2rem;
        }
        .form-container {
            width: 100%;
            max-width: 900px; 
            background-color: white;
            padding: 3rem; /* เพิ่ม padding ให้ดูโปร่งขึ้น */
            border-radius: 0.75rem; /* เพิ่มโค้งมน */
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #1f2937;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem; /* เพิ่มโค้งมนที่ input */
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #dc2626; /* Red-600 */
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 700; /* Bold */
            text-align: center;
            cursor: pointer;
        }
        .btn-accent {
            background-color: #dc2626; 
            color: white;
            font-size: 1.125rem;
        }
        .btn-accent:hover {
            background-color: #b91c1c; 
        }
        .form-section {
            margin-top: 1.5rem;
        }
        .form-section h3 {
            font-size: 1.5rem; /* ใหญ่ขึ้น */
            font-weight: 700;
            margin-bottom: 1rem;
            color: #dc2626; /* สีแดง */
            border-bottom: 2px solid #fca5a5; /* สีแดงอ่อน */
            padding-bottom: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <header class="bg-black text-white shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="h-10 bg-red-600 px-5 flex items-center">
                <span class="text-white text-xl font-bold">HINO LOGO</span>
            </div>
            <div class="flex items-center space-x-8 text-sm font-semibold">
                <ul class="flex space-x-8">
                </ul>
                <div class="flex items-center space-x-4">
                    <?php if ($is_logged_in): ?>
                        <div class="text-white">
                            สวัสดี , <strong>คุณ <?php echo htmlspecialchars($user_full_name); ?></strong>
                        </div>
                        
                        <a href="edit_profile.php" class="hover:text-yellow-400 transition-colors duration-300">แก้ไขข้อมูล</a>
                        <!-- <a href="vehicle_management.php" class="hover:text-yellow-400 transition-colors duration-300">จัดการรถยนต์</a> -->
                        <a href="service_request.php" class="text-red-600 hover:text-red-600 transition-colors duration-300 border-b-2 border-red-600">นัดหมายเข้าซ่อม</a>
                        <a href="summary.php" class="hover:text-red-600 transition-colors duration-300">รายการซ่อม</a>
                        <a href="logout.php" class="hover:text-red-600 transition-colors duration-300">ออกจากระบบ</a>
                    <?php else: ?>
                        <a href="login.php" class="bg-red-600 text-white font-bold py-2 px-4 rounded-full shadow hover:bg-red-700 transition-colors duration-300">เข้าสู่ระบบ</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="form-container">
            <h2 class="text-3xl font-extrabold text-gray-900 mb-8 text-center">แบบฟอร์มนัดหมายเข้าซ่อม</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 font-semibold" role="alert">
                    <span class="block sm:inline"><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>
            
            <form id="serviceForm" action="service_request.php" method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    
                    <div>
                        <div class="form-section">
                            <h3>ข้อมูลผู้ติดต่อ</h3>
                            <div class="form-group">
                                <label for="name">ชื่อ-นามสกุล</label>
                                <input type="text" id="name" name="name" placeholder="ชื่อ-นามสกุล" value="<?php echo htmlspecialchars($user_full_name); ?>" required readonly class="bg-gray-100 cursor-not-allowed">
                            </div>
                            <div class="form-group">
                                <label for="phone">เบอร์โทรศัพท์</label>
                                <input type="tel" id="phone" name="phone" placeholder="0xx-xxxxxxx" value="<?php echo htmlspecialchars($user_phone); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">อีเมล</label>
                                <input type="email" id="email" name="email" placeholder="email@example.com" value="<?php echo htmlspecialchars($user_email); ?>" required readonly class="bg-gray-100 cursor-not-allowed">
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="form-section">
                            <h3>ข้อมูลรถยนต์</h3>
                            <div class="form-group">
                                <label for="license_plate">ทะเบียนรถ</label>
                                <input type="text" id="license_plate" name="license_plate" placeholder="กข-1234" required>
                            </div>
                            <div class="form-group">
                                <label for="model_year">รุ่นรถ</label>
                                <input 
                                    list="model_list" 
                                    id="model_year" 
                                    name="model_year" 
                                    placeholder="เลือกรุ่นรถหรือกรอกเอง" 
                                    required
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
                        
                        <div class="form-section">
                            <h3>รายละเอียดการแจ้งซ่อมและนัดหมาย</h3>
                            <div class="form-group">
                                <label for="description">อาการที่แจ้งซ่อม</label>
                                <textarea id="description" name="description" rows="4" placeholder="อธิบายอาการผิดปกติของรถโดยละเอียด เช่น เสียงดัง, แอร์ไม่เย็น" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="appointment_date">วันที่ต้องการเข้ารับบริการ</label>
                                <input type="datetime-local" id="appointment_date" name="appointment_date" required>
                                <p class="text-xs text-gray-500 mt-1">
                                    *ต้องแจ้งล่วงหน้าอย่างน้อย 2 วันทำการ และอยู่ในช่วงเวลา 09:00 - 16:00 น.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <button type="submit" class="btn btn-accent w-full shadow-lg hover:shadow-xl transition">ส่งรายการแจ้งซ่อม</button>
                </div>
            </form>
        </div>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy; 2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            const appointmentInput = document.getElementById('appointment_date');
            
            // Function to format date to YYYY-MM-DDTHH:mm
            function formatDateTime(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const hour = String(date.getHours()).padStart(2, '0');
                const minute = String(date.getMinutes()).padStart(2, '0');
                return `${year}-${month}-${day}T${hour}:${minute}`;
            }

            // Get current date and time
            const now = new Date();
            
            // Add 2 days to the current date and set the time to 09:00
            now.setDate(now.getDate() + 2);
            now.setHours(9, 0, 0, 0);

            // Set the minimum value for the input field to be 2 days from now
            const minDateTime = formatDateTime(now);
            appointmentInput.setAttribute('min', minDateTime);

            // Add an event listener to adjust the time if it's outside the valid range
            appointmentInput.addEventListener('input', () => {
                const selectedDate = new Date(appointmentInput.value);
                const selectedHour = selectedDate.getHours();

                if (selectedDate < now) {
                    appointmentInput.value = minDateTime;
                    return;
                }
                
                if (selectedHour < 9) {
                    selectedDate.setHours(9);
                    selectedDate.setMinutes(0);
                } else if (selectedHour > 16) {
                    selectedDate.setHours(16);
                    selectedDate.setMinutes(0);
                }
                
                appointmentInput.value = formatDateTime(selectedDate);
            });

            // Dynamic vehicle lookup
            const licensePlateInput = document.getElementById('license_plate');
            const modelYearSelect = document.getElementById('model_year');
            const customerVehicles = <?php echo json_encode($customer_vehicles); ?>;

            licensePlateInput.addEventListener('input', () => {
                const enteredPlate = licensePlateInput.value.trim().toUpperCase();
                const foundVehicle = customerVehicles.find(vehicle => vehicle.license_plate.trim().toUpperCase() === enteredPlate);

                if (foundVehicle) {
                    // Pre-fill the model and year if a match is found
                    const modelYearValue = `${foundVehicle.model} - ${foundVehicle.year}`;
                    modelYearSelect.value = modelYearValue;
                }
                // If no match, we don't clear the input to allow manual entry, but the JS logic for pre-filling is done.
            });
        });
    </script>
</body>
</html>