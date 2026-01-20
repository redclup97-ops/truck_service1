<?php
session_start();
// รวมไฟล์เชื่อมต่อฐานข้อมูล
include 'db_connect.php'; 

// หากมีการส่งฟอร์มเข้าสู่ระบบ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // 1. สร้างคำสั่ง SQL สำหรับดึงข้อมูลผู้ใช้จากฐานข้อมูล
    // *** แก้ไข: เพิ่ม last_name ในคำสั่ง SELECT ***
    $sql = "SELECT customer_id, first_name, last_name, email, password_hash FROM customers WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 2. ตรวจสอบว่าพบผู้ใช้ด้วยอีเมลที่กรอกหรือไม่
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // 3. ใช้ password_verify() เพื่อตรวจสอบรหัสผ่าน
        if (password_verify($password, $user['password_hash'])) {
            // ล็อกอินสำเร็จ: สร้าง session เพื่อจดจำผู้ใช้
            $_SESSION['loggedin'] = true;
            $_SESSION['customer_id'] = $user['customer_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['first_name'];
            // *** แก้ไข: เพิ่ม last_name ใน session ***
            $_SESSION['last_name'] = $user['last_name'];

            // เปลี่ยนเส้นทางไปหน้าแจ้งซ่อม
            header("Location: service_request.php");
            exit;
        } else {
            // รหัสผ่านไม่ถูกต้อง
            $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
        }
    } else {
        // ไม่พบอีเมลในฐานข้อมูล
        $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO | เข้าสู่ระบบ</title>
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

        .login-bg {
            background-image: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('image/แบนเนอร์4.jpg');
            background-position: center;
            background-size: cover;
        }

        .auth-container {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            text-align: center;
            width: 90%;
            max-width: 400px;
        }

        .auth-container h2 {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: #333;
        }

        /* ปรับปรุง CSS สำหรับ input-group เพื่อรองรับไอคอน */
        .input-group {
            margin-bottom: 1.5rem;
            position: relative; /* สำคัญ: เพื่อให้ไอคอนวางตำแหน่งได้ */
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #ddd;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            padding-right: 3rem; /* เพิ่มช่องว่างด้านขวาสำหรับไอคอน */
        }

        .input-group input:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
        }
        
        /* สไตล์สำหรับปุ่ม/ไอคอนรูปตา */
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 0.75rem;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af; /* Gray-400 */
            transition: color 0.2s;
            padding: 0.5rem;
            line-height: 0;
            z-index: 10;
        }

        .toggle-password:hover {
            color: #dc2626; /* Red-600 */
        }

        .btn-primary {
            width: 100%;
            padding: 1rem;
            background-color: #dc2626;
            color: white;
            font-weight: 600;
            border-radius: 0.5rem;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #b91c1c;
        }

        .link-container {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #555;
        }

        .link-container a {
            color: #dc2626;
            font-weight: 600;
            text-decoration: none;
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
                <li><a href="login.php" class="text-red-600 hover:text-red-600 transition-colors duration-300 border-b-2 border-red-600">เข้าสู่ระบบ</a></li>
                <li><a href="service_request.php" class="hover:text-red-600 transition-colors duration-300">นัดหมายเข้าซ่อม</a></li>
                <li><a href="check_status.php" class="hover:text-yellow-400">ตรวจสอบสถานะงานซ่อม</a></li>
                <li><a href="contact.php" class="hover:text-red-600 transition-colors duration-300">ติดต่อเรา</a></li>
            </ul>
        </nav>
    </header>

        <main class="auth-page login-bg flex-grow flex items-center justify-center">
        <div class="auth-container">
            <h2>เข้าสู่ระบบ</h2>
            <?php if(isset($error)) { echo "<p style='color:red; margin-bottom: 1rem;'>$error</p>"; } ?>
            <form action="login.php" method="POST">
                <div class="input-group">
                    <input type="email" name="email" placeholder="อีเมล" required>
                </div>
                
                                <div class="input-group">
                    <input type="password" name="password" id="password_input" placeholder="รหัสผ่าน" required>
                    <span class="toggle-password" id="toggle_password">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.173a1.012 1.012 0 0 1 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.173Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </span>
                </div>
                
                <button type="submit" class="btn btn-primary">เข้าสู่ระบบ</button>
            </form>
            <div class="link-container">
                ยังไม่มีบัญชี? <a href="signup.php">สมัครสมาชิกที่นี่</a>
            </div>
        </div>
    </main>
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy;  2025 Online Appointment System for Hino Truck Service Center. All rights reserved.</p>
        </div>
    </footer>
    
    <script>
        const togglePassword = document.getElementById('toggle_password');
        const passwordInput = document.getElementById('password_input');

        togglePassword.addEventListener('click', function() {
            // สลับประเภทของ input ระหว่าง 'password' และ 'text'
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // สลับไอคอน (ถ้าใช้ Font Awesome หรือ Icon Set ที่ซับซ้อนกว่านี้)
            // สำหรับโค้ดนี้: เราจะสลับ SVG/Path เพื่อให้ดูเหมือนเปลี่ยนไอคอน
            // ถ้าเป็น 'text' (แสดงรหัสผ่าน) ให้เปลี่ยนไอคอนเป็นรูปตามีขีด
            if (type === 'text') {
                togglePassword.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c.241.753.535 1.484.887 2.185M20.02 8.223a10.477 10.477 0 0 1 2.046 3.777c-.241.753-.535 1.484-.887 2.185M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm1.949 3.089a4 4 0 0 0-5.898-4.178M2.28 16.485 2 16.5m20 0-.28-.015M12 18.75a7.5 7.5 0 0 0 7.5-7.5" />
                    </svg>`;
            } else {
                // ถ้าเป็น 'password' (ซ่อนรหัสผ่าน) ให้เปลี่ยนกลับเป็นรูปตาปกติ
                togglePassword.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.173a1.012 1.012 0 0 1 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.173Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>`;
            }
        });
    </script>
</body>
</html>