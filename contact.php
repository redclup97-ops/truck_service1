<?php
// ไฟล์: contact.php
session_start();
// === CSRF Protection Logic Start ===
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        // Fallback for older PHP versions
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

$csrf_token = $_SESSION['csrf_token'] ?? '';

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ใช้ hash_equals เพื่อป้องกัน Timing Attacks
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        // CSRF attack detected or missing token
        unset($_SESSION['csrf_token']);
        http_response_code(403);
        die("Security Check Failed: Invalid or missing CSRF token. Request blocked.");
    }
    
    // --- NOTE: Backend processing for sending email would go here ---
    // For demonstration, we simulate success
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $inquiry = $_POST['inquiry'] ?? '';

    if (!empty($name) && !empty($email) && !empty($inquiry)) {
        $message = "ขอบคุณสำหรับข้อความ ทีมงานจะติดต่อกลับโดยเร็วที่สุด!";
        $message_type = "success";
    } else {
         $message = "กรุณากรอกข้อมูลให้ครบถ้วน";
         $message_type = "error";
    }

    // Token valid, regenerate for next request
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
// === CSRF Protection Logic End ===
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HINO | ติดต่อเรา</title>
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
        padding: 2rem;
        flex-grow: 1;
    }
    .contact-card {
        background-color: white;
        padding: 2rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .form-group input, .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        transition: all 0.2s;
    }
    .form-group input:focus, .form-group textarea:focus {
        outline: none;
        border-color: #dc2626; /* Red-600 */
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
    }
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
        <li><a href="check_status.php" class="hover:text-yellow-400 transition-colors duration-300">ตรวจสอบสถานะงานซ่อม</a></li>
        <li><a href="contact.php" class="text-red-600 border-b-2 border-red-600 transition-colors duration-300">ติดต่อเรา</a></li>
      </ul>
    </nav>
  </header>

  <main class="main-content container mx-auto">
    <h1 class="text-4xl font-bold text-gray-900 mb-8 text-center">ติดต่อศูนย์บริการ HINO</h1>

    <?php if (!empty($message)): ?>
        <div class="max-w-4xl mx-auto p-4 mb-6 rounded-md 
            <?php echo $message_type === 'success' ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700'; ?>" role="alert">
            <p class="font-bold"><?php echo $message_type === 'success' ? 'ส่งข้อความสำเร็จ' : 'ข้อผิดพลาด'; ?></p>
            <p><?= htmlspecialchars($message) ?></p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-6xl mx-auto">
        
        <div class="contact-card">
            <h2 class="text-2xl font-bold text-red-600 mb-4 border-b pb-2">ข้อมูลติดต่อและเวลาทำการ</h2>
            
            <div class="space-y-4 text-gray-700">
                <p class="flex items-start space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 flex-shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>
                        <strong class="font-semibold text-gray-900 block">ที่อยู่:</strong>
                        ศูนย์บริการ HINO (สาขาหลัก) 99/99 ถนนฮีโน่ หมู่ที่ 10 ต.บริการ อ.ซ่อมบำรุง จ.ประเทศไทย 10110
                    </span>
                </p>
                <p class="flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    <span><strong class="font-semibold text-gray-900">โทร:</strong> 0-2123-4567 (ฝ่ายบริการ)</span>
                </p>
                <p class="flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span><strong class="font-semibold text-gray-900">อีเมล:</strong> service@hino-appointment.co.th</span>
                </p>
                
                <h3 class="text-xl font-semibold text-gray-800 pt-3">เวลาทำการ</h3>
                <p class="ml-9">
                    **งานบริการ:** วันจันทร์ - วันศุกร์ (08:00 - 17:00 น.) <br>
                    **งานบริการ:** วันเสาร์ (08:00 - 12:00 น.) <br>
                    **หยุดทำการ:** วันอาทิตย์และวันหยุดนักขัตฤกษ์
                </p>
            </div>
        </div>

        <!-- <div class="contact-card">
            <h2 class="text-2xl font-bold text-red-600 mb-4 border-b pb-2">แบบฟอร์มส่งข้อความ</h2>
            <form action="contact.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label for="name" class="block text-sm font-medium text-gray-700">ชื่อ-นามสกุล</label>
                    <input type="text" id="name" name="name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>
                <div class="form-group">
                    <label for="email" class="block text-sm font-medium text-gray-700">อีเมล</label>
                    <input type="email" id="email" name="email" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>
                <div class="form-group">
                    <label for="subject" class="block text-sm font-medium text-gray-700">หัวข้อ</label>
                    <input type="text" id="subject" name="subject" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>
                <div class="form-group">
                    <label for="inquiry" class="block text-sm font-medium text-gray-700">ข้อความ/สอบถามรายละเอียด</label>
                    <textarea id="inquiry" name="inquiry" rows="4" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2"></textarea>
                </div>
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md shadow-lg transition duration-300">
                    ส่งข้อความ
                </button>
            </form>
        </div> -->
    </div>
    
    <div class="mt-10 contact-card max-w-6xl mx-auto">
        <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">แผนที่ตั้งศูนย์บริการ</h2>
        <div class="aspect-w-16 aspect-h-9 w-full overflow-hidden rounded-lg">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15502.261234479905!2d100.5694389!3d13.756359!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30e29d6786c12b7d%3A0xea2b72111b1574e4!2sBangkok%2C%20Thailand!5e0!3m2!1sen!2s" 
                    width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </div>
  </main>

  <footer class="bg-gray-800 text-white py-6 mt-auto">
    <div class="container mx-auto px-6 text-center text-sm">
      <p>© 2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
    </div>
  </footer>

</body>
</html>