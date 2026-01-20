<style>
/* Reset และตั้งค่าพื้นฐาน */
body {
    margin: 0;
    font-family: 'Kanit', sans-serif;
}

/* Navbar container */
.hino-navbar {
    background-color: #000000;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 50px;
    height: 70px;
}

/* Hino Logo */
.hino-logo {
    height: 100%;
    background-color: #ff0000;
    padding: 0 20px;
    display: flex;
    align-items: center;
}

.hino-logo img {
    height: 30px;
}

/* รายการเมนู */
.nav-menu {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    gap: 30px;
}

.nav-menu li a {
    color: #ffffff;
    text-decoration: none;
    font-size: 16px;
    font-weight: bold;
    padding: 20px 0;
    transition: color 0.3s ease;
}

.nav-menu li a:hover {
    color: #ff0000;
}
</style>

<nav class="hino-navbar">
    <div class="hino-logo">
        <img src="https://via.placeholder.com/200x60/FF0000/FFFFFF?text=HINO+LOGO" alt="Hino Logo">
    </div>
    <ul class="nav-menu">
        <li><a href="#">รุ่นรถ</a></li>
        <li><a href="#">HINO JOURNEY</a></li>
        <li><a href="#">เช็คระยะ</a></li>
        <li><a href="#">ข่าวสาร</a></li>
        <li><a href="#">ตัวแทนจำหน่าย</a></li>
        <li><a href="#">เกี่ยวกับฮีโน่</a></li>
        <li><a href="#">ฮีโน่ลิสซิ่ง</a></li>
        <li><a href="#">ติดต่อเรา</a></li>
    </ul>
</nav>