<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HINO | เว็บไซต์หลัก</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
  <link rel="shortcut icon" href="image/logo.png"/>

  <style>
    body {
      font-family: 'Kanit', sans-serif;
      background-color: #f3f4f6;
    }
    .hero-bg {
      background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('image/แบนเนอร์4.jpg');
      background-position: center;
      background-size: cover;
      width: 100%;
      flex: 1; /* ดัน footer ลงไปข้างล่าง */
    }
  </style>
</head>

<body class="bg-gray-100 text-gray-800 flex flex-col min-h-screen">

  <!-- Header -->
  <header class="bg-black text-white shadow-lg">
    <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
      <div class="h-10 bg-red-600 px-5 flex items-center">
        <span class="text-white text-xl font-bold">HINO LOGO</span>
      </div>
      <ul class="flex space-x-8 text-sm font-semibold">
        <li><a href="#" class="text-red-600 border-b-2 border-red-600">หน้าหลัก</a></li>
        <li><a href="login.php" class="hover:text-red-600">เข้าสู่ระบบ</a></li>
        <li><a href="service_request.php" class="hover:text-red-600">นัดหมายเข้าซ่อม</a></li>
            <li><a href="check_status.php" class="hover:text-yellow-400">ตรวจสอบสถานะงานซ่อม</a></li>
        <li><a href="contact.php" class="hover:text-red-600">ติดต่อเรา</a></li>
      </ul>
    </nav>
  </header>

  <!-- Hero -->
  <section class="hero-bg flex items-center justify-center text-center text-white">
    <div class="bg-black bg-opacity-50 p-10 rounded-xl max-w-2xl mx-auto backdrop-blur-sm">
      <h1 class="text-5xl md:text-6xl font-extrabold leading-tight tracking-wide mb-4">
        พลังที่ขับเคลื่อนธุรกิจ<br>ทุกเส้นทาง
      </h1>
      <p class="text-lg md:text-xl mb-8 opacity-90">
        เราพร้อมให้บริการและโซลูชั่นที่ดีที่สุดสำหรับรถบรรทุกและรถบัสของคุณ
      </p>
      <!-- <a href="#" class="bg-red-600 text-white font-bold py-3 px-8 rounded-full shadow-lg hover:bg-red-700 transition transform hover:scale-105">
        ดูรุ่นรถทั้งหมด
      </a> -->
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-gray-800 text-white py-6">
    <div class="container mx-auto px-6 text-center text-sm">
      <p>&copy; 2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
    </div>
  </footer>

</body>
</html>
