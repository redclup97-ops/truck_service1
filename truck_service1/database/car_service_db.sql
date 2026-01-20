-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2025 at 04:41 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `car_service_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `created_at`, `reset_token`, `token_expiry`) VALUES
(1, 'ทิวากร', 'โกแสนตอ', 'redclup97@gmail.com', '0972302342', '$2y$10$57MjAlr2Ym1RL13fde7WIe4Vwj0mQTu/uveg4Qmz7seOJstg3uugy', '2025-08-26 10:29:16', '183439e9f4630c4ccbde97edf31b9f13cc460aea6372228c0b9dd6563b60f5dd', '2025-10-08 18:45:12'),
(3, 'ทิวากร', 'ชอบสะอาด', 'lannaoffice01@gmail.com', '0892249689', '$2y$10$Bh.j62FYs.35.6g4xC.HBOk0isHFFS4y6ikXNf8TBWS4.3ry7f3gS', '2025-08-26 10:58:44', NULL, NULL),
(11, 'สุพัตรา', 'อยู่ยิ่ง', 'supattra@gmail.com', '088888888', '$2y$10$.eivzr2NqCAE0M9uRf/PYO95aIfmneeIJr3KPsHWIWhIdZ7DvdyR2', '2025-10-12 05:51:14', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','technician','receptionist') DEFAULT 'receptionist'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `first_name`, `last_name`, `email`, `password_hash`, `role`) VALUES
(1, 'Admin', '', 'admin@example.com', '$2y$10$iFEAs39WeCjo8jEOIPg2mOJNSFk1Ra5Knd5AJm7zy0rOje63.YfHW', 'admin'),
(9, 'ทิวากร', 'โกแสนตอ', 'redclup@gmail.com', '$2y$10$ZEw/S7Qo7xNeb4bfgnxQIO5KhaShjC/vJozYEL0lL39/u4dztP8z2', 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `repair_summary`
--

CREATE TABLE `repair_summary` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `parts_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parts_details`)),
  `labor_cost` decimal(10,2) DEFAULT 0.00,
  `pickup_date` datetime DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'completed',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `repair_summary`
--

INSERT INTO `repair_summary` (`id`, `request_id`, `parts_details`, `labor_cost`, `pickup_date`, `total_cost`, `status`, `updated_at`) VALUES
(5, 48, '[{\"name\":\"น้ำมันเครื่อง\",\"unit_price\":500,\"quantity\":2,\"subtotal\":1000}]', 1000.00, '2025-09-06 10:57:00', 2000.00, 'in_progress', '2025-08-31 03:57:46'),
(15, 52, '[{\"name\":\"น้ำมันเครื่อง\",\"unit_price\":1500,\"quantity\":1,\"subtotal\":1500},{\"name\":\"กืด่หื\",\"unit_price\":150,\"quantity\":1,\"subtotal\":150}]', 500.00, '2025-08-31 10:57:00', 2150.00, 'in_progress', '2025-08-31 03:57:38'),
(16, 53, '[{\"name\":\"น้ำมันเครื่อง\",\"unit_price\":12000,\"quantity\":1,\"subtotal\":12000}]', 400.00, '2025-09-27 10:56:00', 12400.00, 'in_progress', '2025-08-31 03:56:59'),
(17, 54, '[{\"name\":\"แหวน\",\"unit_price\":5000,\"quantity\":10,\"subtotal\":50000}]', 500.00, '2025-08-31 12:01:00', 50500.00, 'in_progress', '2025-08-31 05:01:10'),
(18, 55, '[{\"name\":\"แหวน\",\"unit_price\":10,\"quantity\":1,\"subtotal\":10}]', 200.00, '2025-09-07 11:07:00', 210.00, 'completed', '2025-08-31 04:07:40'),
(31, 62, '[{\"name\":\"น้ำมันเครื่อง\",\"unit_price\":1000,\"quantity\":10,\"subtotal\":10000}]', 500.00, '2025-09-30 23:30:00', 10500.00, 'in_progress', '2025-09-01 16:30:59'),
(32, 63, '[{\"name\":\"น้ำมันเครื่อง\",\"unit_price\":15000,\"quantity\":1,\"subtotal\":15000}]', 500.00, '2025-09-05 11:15:00', 15500.00, 'completed', '2025-09-01 17:15:51'),
(33, 64, '[{\"name\":\"น้ำมันเครื่อง\",\"unit_price\":1500,\"quantity\":1,\"subtotal\":1500}]', 0.00, '2025-09-10 18:56:00', 1500.00, 'completed', '2025-09-06 11:56:32'),
(34, 67, '[{\"name\":\"น้ำมันเครื่อง\",\"unit_price\":500,\"quantity\":1,\"subtotal\":500}]', 500.00, '2025-09-25 20:53:00', 1000.00, 'completed', '2025-09-23 13:53:15'),
(46, 77, '[{\"name\":\"ล้อ\",\"unit_price\":88550,\"quantity\":1,\"subtotal\":88550}]', 500.00, '2025-10-26 00:00:00', 89050.00, 'completed', '2025-10-12 05:55:04');

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `request_id` int(11) NOT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `symptoms` text DEFAULT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `queue_number` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `service_requests`
--

INSERT INTO `service_requests` (`request_id`, `vehicle_id`, `description`, `symptoms`, `appointment_date`, `status`, `notes`, `cost`, `created_at`, `queue_number`) VALUES
(48, 12, 'แอร์ไม่เย็น', NULL, '2025-08-29 00:27:00', 'completed', NULL, 1500.00, '2025-08-27 17:27:23', NULL),
(52, 14, 'เข้าเช็คระยะ', NULL, '2025-11-30 09:12:00', 'completed', NULL, 2150.00, '2025-08-31 02:12:05', NULL),
(53, 15, 'แอร์ดัง', NULL, '2025-08-31 10:55:00', 'completed', NULL, 12400.00, '2025-08-31 03:55:45', NULL),
(54, 16, 'ทดสอบระบบ', NULL, '2025-09-30 11:05:00', 'completed', NULL, 50500.00, '2025-08-31 04:05:23', '5'),
(55, 17, 'ซ่อม', NULL, '2025-08-31 11:06:00', 'completed', NULL, 210.00, '2025-08-31 04:07:00', '1'),
(62, 22, 'ดดห', NULL, '2025-09-27 23:23:00', 'completed', NULL, 10500.00, '2025-09-01 16:23:55', NULL),
(63, 23, 'ฟรพ', NULL, '2025-09-04 09:30:00', 'completed', NULL, 15500.00, '2025-09-01 17:10:56', NULL),
(64, 24, 'cdsac', NULL, '2025-09-08 09:45:00', 'completed', NULL, 1500.00, '2025-09-06 02:45:30', NULL),
(67, 23, 'เข้าเช็คระยะ', NULL, '2025-10-10 14:36:00', 'completed', NULL, 1000.00, '2025-09-21 07:37:10', NULL),
(77, 33, 'ล้อหมุน', NULL, '2025-10-18 12:00:00', 'completed', NULL, 89050.00, '2025-10-12 05:53:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` varchar(10) DEFAULT NULL,
  `license_plate` varchar(20) NOT NULL,
  `vin_number` varchar(17) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `customer_id`, `brand`, `model`, `year`, `license_plate`, `vin_number`) VALUES
(7, 3, 'HINO', 'HINO 500', '2024', 'ทท1599', NULL),
(12, 1, 'HINO', 'HINO 500', '2024', '4ขก-9589', NULL),
(14, 1, 'HINO', 'HINO 500', '2021', '4ขก-9566', NULL),
(15, 1, 'HINO', 'HINO 300', '2022', '4ขก-9500', NULL),
(16, 1, 'HINO', 'HINO 300', '2021', '4ขก-9588', NULL),
(17, 1, 'HINO', 'HINO 300', '2022', '4ขก-9501', NULL),
(18, 1, 'HINO', 'HINO 300', '2022', 'กหด', NULL),
(19, 1, 'HINO', 'HINO 500', '2021', '4ขก-9590', NULL),
(20, 1, 'HINO', 'HINO 300', '2022', '9577', NULL),
(21, 1, 'HINO', 'HINO 300', '2022', '4ขก-9560', NULL),
(22, 1, 'HINO', 'HINO 700', '2023', '4ขก-95005', NULL),
(23, 1, 'HINO', 'HINO 300', '2022', '4ขก-9550', NULL),
(24, 1, 'HINO', 'HINO 300', '2022', '9578', NULL),
(25, 1, 'HINO', 'HINO 300', '2022', '4ขก-9505', NULL),
(26, 1, 'HINO', 'HINO 300', '2022', '4ขก-9556', NULL),
(27, 1, 'HINO', 'HINO 300', '2022', '12345', NULL),
(28, 1, 'HINO', 'HINO 300', '2023', 'ทท1600', NULL),
(29, 1, 'HINO', 'HINO 700', '2023', 'ssssss', NULL),
(30, 1, 'HINO', 'HINO 700', '2020', '5ขก-9577', NULL),
(31, 1, 'HINO', 'HINO 300', '2020', '4ขก-900', NULL),
(32, NULL, 'HINO', 'HINO 300', '2023', '4ขก-9600', NULL),
(33, 11, 'HINO', 'HINO 700', '2020', 'ขย4783', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `repair_summary`
--
ALTER TABLE `repair_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_request_id` (`request_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD UNIQUE KEY `vin_number` (`vin_number`),
  ADD KEY `customer_id` (`customer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `repair_summary`
--
ALTER TABLE `repair_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `repair_summary`
--
ALTER TABLE `repair_summary`
  ADD CONSTRAINT `repair_summary_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`request_id`) ON DELETE CASCADE;

--
-- Constraints for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `service_requests_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`);

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
