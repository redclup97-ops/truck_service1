<?php
// Start the session to check if the admin is logged in
session_start();
include 'db_connect.php'; 

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<p class='text-center text-red-600 mt-8'>ไม่พบ ID การแจ้งซ่อมที่ต้องการ</p>";
    exit;
}

$request_id = $conn->real_escape_string($_GET['id']);

$sql = "SELECT 
            sr.request_id, sr.description, sr.appointment_date, sr.status, sr.cost, sr.created_at,
            v.license_plate, v.brand, v.model, v.year,
            c.first_name, c.last_name, c.email, c.phone
        FROM service_requests sr
        JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
        JOIN customers c ON v.customer_id = c.customer_id
        WHERE sr.request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$repair = $result->fetch_assoc();

if (!$repair) {
    echo "<p class='text-center text-red-600 mt-8'>ไม่พบรายการแจ้งซ่อมที่ระบุ</p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>รายละเอียดการแจ้งซ่อม #<?php echo htmlspecialchars($repair['request_id']); ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
  <link rel="shortcut icon" href="image/logo.png"/>
  <style>
    body { font-family: 'Kanit', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
    .status-badge { padding: 0.4rem 0.8rem; border-radius: 9999px; font-weight: 600; font-size: 0.875rem; color: white; }
    .pending { background-color: #f59e0b; }
    .in_progress { background-color: #3b82f6; }
    .completed { background-color: #10b981; }
    .cancelled { background-color: #ef4444; }
  </style>
</head>
<body class="bg-gray-100 text-gray-800">

  <!-- Header -->
  <header class="bg-black text-white shadow sticky top-0 z-50">
    <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
      <div class="h-10 bg-red-600 px-5 flex items-center rounded-md">
        <span class="text-white text-xl font-bold">HINO LOGO</span>
      </div>
      <div class="flex items-center space-x-6 text-sm font-semibold">
        <a href="admin_dashboard.php" class="hover:text-red-500">แผงควบคุม</a>
        <a href="admin_logout.php" class="hover:text-red-500">ออกจากระบบ</a>
      </div>
    </nav>
  </header>

  <!-- Main Content -->
  <main class="flex-grow container mx-auto px-6 py-8">
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
        รายละเอียดการแจ้งซ่อม #<?php echo htmlspecialchars($repair['request_id']); ?>
      </h1>
      <a href="admin_dashboard.php" class="text-sm font-semibold text-gray-600 hover:text-red-600">&larr; กลับ</a>
    </div>

    <!-- Card -->
    <div class="bg-white shadow rounded-lg p-6 space-y-6">

      <!-- Status + Dates -->
      <div class="grid md:grid-cols-3 gap-6">
        <div>
          <p class="text-sm text-gray-500 font-semibold">วันที่แจ้งซ่อม</p>
          <p class="text-gray-800 mt-1"><?php echo htmlspecialchars(date('d/m/Y', strtotime($repair['created_at']))); ?></p>
        </div>
        <div>
          <p class="text-sm text-gray-500 font-semibold">วันที่นัดหมาย</p>
          <p class="text-gray-800 mt-1">
            <?php echo $repair['appointment_date'] ? date('d/m/Y H:i', strtotime($repair['appointment_date'])) : 'ยังไม่ได้กำหนด'; ?>
          </p>
        </div>
        <div>
          <p class="text-sm text-gray-500 font-semibold">สถานะ</p>
          <?php 
            $status_class = str_replace(' ', '_', strtolower($repair['status']));
            $status_text = match($status_class) {
                'pending' => 'รอดำเนินการ',
                'in_progress' => 'กำลังดำเนินการ',
                'completed' => 'เสร็จสิ้น',
                'cancelled' => 'ยกเลิก',
                default => $repair['status']
            };
          ?>
          <span class="status-badge <?php echo $status_class; ?> mt-1 inline-block">
            <?php echo $status_text; ?>
          </span>
        </div>
      </div>

      <!-- Cost -->
      <?php if ($repair['cost']): ?>
      <div>
        <p class="text-sm text-gray-500 font-semibold">ค่าใช้จ่าย</p>
        <p class="text-lg font-bold text-green-600 mt-1"><?php echo number_format($repair['cost'], 2); ?> บาท</p>
      </div>
      <?php endif; ?>

      <!-- Customer Info -->
      <hr>
      <h2 class="text-lg font-semibold">ข้อมูลลูกค้า</h2>
      <div class="grid md:grid-cols-2 gap-6 mt-4">
        <div>
          <p class="text-sm text-gray-500">ชื่อ - นามสกุล</p>
          <p class="text-gray-800 font-medium"><?php echo $repair['first_name'] . ' ' . $repair['last_name']; ?></p>
        </div>
        <div>
          <p class="text-sm text-gray-500">เบอร์โทรศัพท์</p>
          <p class="text-gray-800 font-medium"><?php echo $repair['phone']; ?></p>
        </div>
        <div>
          <p class="text-sm text-gray-500">อีเมล</p>
          <p class="text-gray-800 font-medium"><?php echo $repair['email']; ?></p>
        </div>
      </div>

      <!-- Vehicle Info -->
      <hr>
      <h2 class="text-lg font-semibold">ข้อมูลรถยนต์</h2>
      <div class="grid md:grid-cols-2 gap-6 mt-4">
        <div>
          <p class="text-sm text-gray-500">ทะเบียนรถ</p>
          <p class="text-gray-800 font-medium"><?php echo $repair['license_plate']; ?></p>
        </div>
        <div>
          <p class="text-sm text-gray-500">ยี่ห้อ / รุ่น</p>
          <p class="text-gray-800 font-medium"><?php echo $repair['brand'] . ' ' . $repair['model'] . ' (' . $repair['year'] . ')'; ?></p>
        </div>
      </div>

      <!-- Problem Description -->
      <hr>
      <h2 class="text-lg font-semibold">รายละเอียดปัญหา</h2>
      <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 leading-relaxed">
        <?php echo nl2br(htmlspecialchars($repair['description'])); ?>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-4">
        <a href="admin_edit_repair.php?id=<?php echo $repair['request_id']; ?>" 
           class="bg-red-600 text-white px-5 py-2 rounded-md font-semibold hover:bg-red-700">
           แก้ไขสถานะ
        </a>
        <a href="admin_update_summary.php?id=<?php echo $repair['request_id']; ?>" 
           class="bg-green-600 text-white px-5 py-2 rounded-md font-semibold hover:bg-green-700">
           อัพเดทค่าใช้จ่าย
        </a>
      </div>

    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-gray-800 text-white py-8 mt-auto">
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
