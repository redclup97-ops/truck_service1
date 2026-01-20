<?php
session_start();
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
include 'db_connect.php';

// ตรวจสอบว่ามีการส่งฟอร์มมาด้วยเมธอด POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ดึงข้อมูลจากฟอร์ม
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password']; // เพิ่มการดึงข้อมูลยืนยันรหัสผ่าน

    // 1. ตรวจสอบข้อมูลว่างเปล่า (Empty check)
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
    // 2. ตรวจสอบรูปแบบอีเมล (Email format validation)
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    }
    // *** เพิ่มใหม่: 3. ตรวจสอบว่ารหัสผ่านตรงกันหรือไม่ (Password match check) ***
    elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน";
    }
    else {
        // 4. ตรวจสอบว่าอีเมลนี้ถูกใช้ไปแล้วหรือยัง (Email duplication check)
        $sql_check = "SELECT customer_id FROM customers WHERE email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error = "อีเมลนี้มีผู้ใช้งานแล้ว กรุณาใช้อีเมลอื่น";
        } else {
            // 5. เข้ารหัสรหัสผ่าน (Password hashing)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 6. เตรียมคำสั่ง SQL เพื่อบันทึกข้อมูลลงในฐานข้อมูล
            $sql_insert = "INSERT INTO customers (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);

            if ($stmt_insert->execute()) {
                // สมัครสมาชิกสำเร็จ: เปลี่ยนเส้นทางไปที่หน้า login.php
                header("Location: login.php");
                exit;
            } else {
                // เกิดข้อผิดพลาดในการบันทึกข้อมูล
                $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HINO | สมัครสมาชิก</title>
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

        .signup-bg {
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

        .input-group {
            margin-bottom: 1.5rem;
            position: relative; /* *** เพิ่มใหม่: สำหรับจัดวางไอคอน *** */
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #ddd;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        /* *** เพิ่มใหม่: เพิ่ม padding ด้านขวาสำหรับช่องรหัสผ่าน เพื่อไม่ให้ข้อความทับไอคอน *** */
        .input-group input[type="password"],
        .input-group input[type="text"] {
            padding-right: 2.5rem; 
        }

        .input-group input:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
        }
        
        /* *** เพิ่มใหม่: สไตล์สำหรับไอคอนดวงตา *** */
        .password-toggle-icon {
            position: absolute;
            top: 50%;
            right: 0.75rem;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af; /* สีเทา */
        }
        
        .password-toggle-icon:hover {
            color: #374151; /* สีเทาเข้ม */
        }

        .btn-accent {
            width: 100%;
            padding: 1rem;
            background-color: #dc2626;
            color: white;
            font-weight: 600;
            border-radius: 0.5rem;
            transition: background-color 0.3s ease;
        }

        .btn-accent:hover {
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
                <li><a href="#" class="hover:text-red-600 transition-colors duration-300">ติดต่อเรา</a></li>
            </ul>
        </nav>
    </header>

    <main class="auth-page signup-bg flex-grow flex items-center justify-center">
        <div class="auth-container">
            <h2>สมัครสมาชิก</h2>
            <?php if(isset($error)) { echo "<p class='text-red-600 mb-4'>$error</p>"; } ?>
            <form action="signup.php" method="POST">
                <div class="input-group">
                    <input type="text" name="first_name" placeholder="ชื่อจริง" required>
                </div>
                <div class="input-group">
                    <input type="text" name="last_name" placeholder="นามสกุล" required>
                </div>
                <div class="input-group">
                    <input type="email" name="email" placeholder="อีเมล" required>
                </div>
                <div class="input-group">
                    <input type="password" name="password" id="password" placeholder="รหัสผ่าน" required>
                    <span class="password-toggle-icon" id="togglePassword">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                           <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                           <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.022 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </div>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="ยืนยันรหัสผ่าน" required>
                     <span class="password-toggle-icon" id="toggleConfirmPassword">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                           <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                           <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.022 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </div>
                <button type="submit" class="btn-accent">ยืนยันการสมัคร</button>
            </form>
            <div class="link-container">
                มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-6 text-center text-sm">
            <p>&copy; 2024 Hino Motors Thailand. All rights reserved.</p>
        </div>
    </footer>
    
    <script>
        // ไอคอนสำหรับแสดงและซ่อน
        const eyeIcon = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                           <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                           <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.022 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                        </svg>`;
        const eyeOffIcon = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                              <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.742L2.303 6.546A10.048 10.048 0 00.458 10c1.274 4.057 5.022 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                            </svg>`;

        function setupPasswordToggle(inputId, toggleId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(toggleId);

            toggleIcon.addEventListener('click', function () {
                // สลับประเภทของ input
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // สลับไอคอน
                this.innerHTML = type === 'password' ? eyeIcon : eyeOffIcon;
            });
        }
        
        setupPasswordToggle('password', 'togglePassword');
        setupPasswordToggle('confirm_password', 'toggleConfirmPassword');
    </script>
    
</body>
</html>