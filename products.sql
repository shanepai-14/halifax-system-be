-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 19, 2025 at 04:51 AM
-- Server version: 10.11.10-MariaDB
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u191884628_halifax`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `product_category_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `use_bracket_pricing` tinyint(1) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `product_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `product_code`, `product_category_id`, `quantity`, `use_bracket_pricing`, `reorder_level`, `created_at`, `updated_at`, `deleted_at`, `product_image`) VALUES
(1, 'Tempered Glass', 'GLA-R-24-0001', 1, 0.00, 1, 40, '2024-11-25 05:33:32', '2024-12-01 00:49:27', '2024-12-01 00:49:27', 'products/product_1.png'),
(2, 'Aluminum Bars', 'ALU-R-24-0001', 2, 30.00, 1, 20, '2024-11-30 00:31:02', '2025-05-22 03:28:28', NULL, 'products/product_2.jpg'),
(9, 'Alloy', 'ALU-R-24-0002', 2, 2120.00, 1, 22, '2024-11-30 00:45:27', '2025-05-30 09:35:55', NULL, NULL),
(10, 'Alloy 2', 'ALU-R-24-0003', 2, 70.00, 1, 23, '2024-11-30 00:46:40', '2025-03-13 07:07:05', NULL, NULL),
(11, 'Alloy 3', 'ALU-R-24-0004', 2, 23.00, 1, 21, '2024-11-30 00:49:11', '2025-02-27 22:38:06', NULL, 'products/product_11.jpg'),
(12, 'Fabric', 'BRE-R-24-0001', 3, 44.00, 1, 20, '2024-11-30 04:36:50', '2025-02-27 13:22:56', NULL, 'products/product_12.jpg'),
(13, 'Fabric 2', 'BRE-R-24-0002', 3, 8.00, 1, 23, '2024-11-30 04:44:53', '2025-04-29 00:46:51', NULL, 'products/product_13.png'),
(14, 'MESH', 'BRE-R-24-0003', 3, 0.00, 1, 10, '2024-11-30 04:50:29', '2024-12-01 00:48:27', '2024-12-01 00:48:27', 'products/product_14.jpg'),
(15, 'MESH 2', 'BRE-R-24-0004', 3, 22.00, 1, 38, '2024-11-30 04:53:51', '2025-05-30 09:21:39', NULL, 'products/product_15.jpg'),
(16, 'Laminated glass', 'GLA-R-24-0002', 1, 500.00, 1, 20, '2024-11-30 04:56:13', '2025-05-29 03:10:52', NULL, 'products/product_16.jpg'),
(17, 'Laminated glass 2', 'GLA-R-24-0003', 1, 0.00, 1, 99, '2024-11-30 05:05:51', '2024-11-30 05:05:52', NULL, 'products/product_17.jpg'),
(18, 'Jalousie', 'GLA-R-24-0004', 1, 0.00, 1, 23, '2024-11-30 21:18:37', '2025-02-27 22:37:48', NULL, 'products/product_18.jpg'),
(19, 'MUI', 'ALU-R-24-0005', 2, 0.00, 1, 78, '2024-11-30 21:21:18', '2024-11-30 21:21:19', NULL, 'products/product_19.png'),
(20, '48X72 1/4 BRONZE', 'GLA-R-25-0001', 1, 5.00, 1, 0, '2025-03-03 05:20:42', '2025-05-22 03:37:34', NULL, 'products/product_20.jpg'),
(21, '48X84 1/4 BRONZE', 'GLA-R-25-0002', 1, 0.00, 1, 0, '2025-03-03 05:21:29', '2025-03-03 05:21:29', NULL, 'products/product_21.jpg'),
(22, '48X72 3/16 BRONZE', 'GLA-R-25-0003', 1, 0.00, 1, 0, '2025-03-03 05:22:02', '2025-03-03 05:22:02', NULL, 'products/product_22.jpg'),
(23, '60X96 3/16 BRONZE', 'GLA-R-25-0004', 1, 0.00, 1, 0, '2025-03-03 05:22:27', '2025-03-03 05:22:27', NULL, 'products/product_23.jpg'),
(24, '72X96 3/16 BRONZE', 'GLA-R-25-0005', 1, 0.00, 1, 0, '2025-03-03 05:23:00', '2025-03-03 05:23:00', NULL, 'products/product_24.jpg'),
(25, '72X84 1/4 BRONZE', 'GLA-R-25-0006', 1, 0.00, 1, 0, '2025-03-03 05:23:20', '2025-03-03 05:23:20', NULL, 'products/product_25.jpg'),
(26, '72X96 1/4 BRONZE', 'GLA-R-25-0007', 1, 0.00, 1, 0, '2025-03-03 05:23:37', '2025-03-03 05:23:37', NULL, 'products/product_26.jpg'),
(27, '48X72 3/16 LUNINGNING', 'GLA-R-25-0008', 1, 0.00, 1, 0, '2025-03-03 05:26:53', '2025-03-03 05:26:54', NULL, 'products/product_27.jpg'),
(28, '48X72 3/16 DARK GRAY', 'GLA-R-25-0009', 1, 0.00, 1, 0, '2025-03-03 05:27:28', '2025-03-03 05:27:28', NULL, 'products/product_28.jpg'),
(29, '72X96 1/4 REFLECTIVE DARK GRAY', 'GLA-R-25-0010', 1, 0.00, 1, 0, '2025-03-03 05:28:05', '2025-03-03 05:28:06', NULL, 'products/product_29.jpg'),
(30, '48X72 1/4 DARK GRAY', 'GLA-R-25-0011', 1, 0.00, 1, 0, '2025-03-03 05:28:24', '2025-03-03 05:28:25', NULL, 'products/product_30.jpg'),
(31, '48X84 1/4 DARK GRAY', 'GLA-R-25-0012', 1, 0.00, 1, 0, '2025-03-03 05:28:41', '2025-03-03 05:28:41', NULL, 'products/product_31.jpg'),
(32, '72X96 1/4 DARK GRAY', 'GLA-R-25-0013', 1, 0.00, 1, 0, '2025-03-03 05:28:58', '2025-03-03 05:28:58', NULL, 'products/product_32.jpg'),
(33, '84X120 1/4 DARK GRAY', 'GLA-R-25-0014', 1, 0.00, 1, 0, '2025-03-03 05:29:16', '2025-03-03 05:29:16', NULL, 'products/product_33.jpg'),
(34, '84X132 1/4 REFLECTIVE OPTIMUM GRAY', 'GLA-R-25-0015', 1, 0.00, 1, 0, '2025-03-03 05:29:45', '2025-03-03 05:29:45', NULL, 'products/product_34.jpg'),
(35, '48X72 3/16 REFLECTIVE BRONZE', 'GLA-R-25-0016', 1, 0.00, 1, 0, '2025-03-03 05:31:16', '2025-03-03 05:31:17', NULL, 'products/product_35.jpg'),
(36, '72X96 3/16 REFLECTIVE BRONZE', 'GLA-R-25-0017', 1, 0.00, 1, 0, '2025-03-03 05:31:34', '2025-03-03 05:31:34', NULL, 'products/product_36.jpg'),
(37, '72X84 1/4 REFLECTIVE BRONZE', 'GLA-R-25-0018', 1, 0.00, 1, 0, '2025-03-03 05:31:53', '2025-03-03 05:31:53', NULL, 'products/product_37.jpg'),
(38, '72X96 1/4 REFLECTIVE BRONZE', 'GLA-R-25-0019', 1, 0.00, 1, 0, '2025-03-03 05:32:10', '2025-03-03 05:32:10', NULL, 'products/product_38.jpg'),
(39, '72X96 3/16 REFLECTIVE GOLD', 'GLA-R-25-0020', 1, 0.00, 1, 0, '2025-03-03 05:32:29', '2025-03-03 05:32:29', NULL, 'products/product_39.jpg'),
(40, '48X72 3/16 REFLECTIVE GOLD', 'GLA-R-25-0021', 1, 0.00, 1, 0, '2025-03-03 05:32:53', '2025-03-03 05:32:53', NULL, 'products/product_40.jpg'),
(41, '84X130 1/4 REFLECTIVE GOLD', 'GLA-R-25-0022', 1, 0.00, 1, 0, '2025-03-03 05:33:09', '2025-03-03 05:33:10', NULL, 'products/product_41.jpg'),
(42, '48X72 4.7MM REFLECTIVE DARK GREEN', 'GLA-R-25-0023', 1, 0.00, 1, 0, '2025-03-03 05:34:45', '2025-03-03 05:34:45', NULL, 'products/product_42.jpg'),
(43, '48X72 5.7MM REFLECTIVE DARK GREEN', 'GLA-R-25-0024', 1, 0.00, 1, 0, '2025-03-03 05:35:12', '2025-03-03 05:35:12', NULL, 'products/product_43.jpg'),
(44, '48X72 1/4 REFLECTIVE DARK GREEN', 'GLA-R-25-0025', 1, 0.00, 1, 0, '2025-03-03 05:35:32', '2025-03-03 05:35:32', NULL, 'products/product_44.jpg'),
(45, '72X84 1/4 REFLECTIVE DARK GREEN', 'GLA-R-25-0026', 1, 0.00, 1, 0, '2025-03-03 05:35:55', '2025-03-03 05:35:55', NULL, 'products/product_45.jpg'),
(46, '72X96 1/4 REFLECTIVE DARK GREEN', 'GLA-R-25-0027', 1, 0.00, 1, 0, '2025-03-03 05:36:16', '2025-03-03 05:36:17', NULL, 'products/product_46.jpg'),
(47, '84X144 1/4 REFLECTIVE DARK GREEN', 'GLA-R-25-0028', 1, 0.00, 1, 0, '2025-03-03 05:36:54', '2025-03-03 05:36:55', NULL, 'products/product_47.jpg'),
(48, '48X72 3/16 REFLECTIVE OPTIMUM DARK BLUE', 'GLA-R-25-0029', 1, 0.00, 1, 0, '2025-03-03 05:53:01', '2025-03-03 05:53:01', NULL, 'products/product_48.jpg'),
(49, '48X72 1/4 REFLECTIVE OPTIMUM DARK BLUE', 'GLA-R-25-0030', 1, 0.00, 1, 0, '2025-03-03 05:53:24', '2025-03-03 05:53:24', NULL, 'products/product_49.jpg'),
(50, '48X84 1/4 REFLECTIVE OPTIMUM DARK BLUE', 'GLA-R-25-0031', 1, 0.00, 1, 0, '2025-03-03 05:53:51', '2025-03-03 05:53:52', NULL, 'products/product_50.jpg'),
(51, '72X96 1/4 REFLECTIVE OPTIMUM DARK BLUE', 'GLA-R-25-0032', 1, 0.00, 1, 0, '2025-03-03 05:54:09', '2025-03-03 05:54:09', NULL, 'products/product_51.jpg'),
(52, '84X120 1/4 REFLECTIVE OPTIMUM DARK BLUE', 'GLA-R-25-0033', 1, 0.00, 1, 0, '2025-03-03 05:54:30', '2025-03-03 05:54:31', NULL, 'products/product_52.jpg'),
(53, '36X48 1/16 MIRROR', 'GLA-R-25-0034', 1, 0.00, 1, 0, '2025-03-03 05:55:44', '2025-03-03 05:55:44', NULL, 'products/product_53.jpg'),
(54, '48X72 1/8 MIRROR', 'GLA-R-25-0035', 1, 0.00, 1, 0, '2025-03-03 05:56:35', '2025-03-03 05:56:35', NULL, 'products/product_54.jpg'),
(55, '48X72 3/16 MIRROR', 'GLA-R-25-0036', 1, 0.00, 1, 0, '2025-03-03 05:56:50', '2025-03-03 05:56:50', NULL, 'products/product_55.jpg'),
(56, '48X72 1/4 MIRROR', 'GLA-R-25-0037', 1, 0.00, 1, 0, '2025-03-03 05:57:37', '2025-03-03 05:57:37', NULL, 'products/product_56.jpg'),
(57, '72X96 1/4 MIRROR', 'GLA-R-25-0038', 1, 0.00, 1, 0, '2025-03-03 05:57:55', '2025-03-03 05:57:55', NULL, 'products/product_57.jpg'),
(58, '84X120 1/4 MIRROR', 'GLA-R-25-0039', 1, 0.00, 1, 0, '2025-03-03 05:58:08', '2025-03-03 05:58:08', NULL, 'products/product_58.jpg'),
(59, '36X48 1/16 CLEAR', 'GLA-R-25-0040', 1, 0.00, 1, 0, '2025-03-03 05:58:48', '2025-03-03 05:58:48', NULL, 'products/product_59.jpg'),
(60, '48X72 1/8 CLEAR', 'GLA-R-25-0041', 1, 0.00, 1, 0, '2025-03-03 05:59:07', '2025-03-03 05:59:08', NULL, 'products/product_60.jpg'),
(61, '48X72 3/16 CLEAR', 'GLA-R-25-0042', 1, 0.00, 1, 0, '2025-03-03 05:59:25', '2025-03-03 05:59:26', NULL, 'products/product_61.jpg'),
(62, '72X96 3/16 CLEAR', 'GLA-R-25-0043', 1, 0.00, 1, 0, '2025-03-03 05:59:47', '2025-03-03 05:59:47', NULL, 'products/product_62.jpg'),
(63, '48X72 1/4 CLEAR', 'GLA-R-25-0044', 1, 0.00, 1, 0, '2025-03-03 06:00:03', '2025-03-03 06:00:03', NULL, 'products/product_63.jpg'),
(64, '48X84 1/4 CLEAR', 'GLA-R-25-0045', 1, 0.00, 1, 0, '2025-03-03 06:00:20', '2025-03-03 06:00:20', NULL, 'products/product_64.jpg'),
(65, '48X96 1/4 CLEAR', 'GLA-R-25-0046', 1, 0.00, 1, 0, '2025-03-03 06:00:38', '2025-03-03 06:00:38', NULL, 'products/product_65.jpg'),
(66, '72X84 1/4 CLEAR', 'GLA-R-25-0047', 1, 0.00, 1, 0, '2025-03-03 06:00:53', '2025-03-03 06:00:53', NULL, 'products/product_66.jpg'),
(67, '72X96 1/4 CLEAR', 'GLA-R-25-0048', 1, 0.00, 1, 0, '2025-03-03 06:01:08', '2025-03-03 06:01:08', NULL, 'products/product_67.jpg'),
(68, '84X120 1/4 CLEAR', 'GLA-R-25-0049', 1, 0.00, 1, 0, '2025-03-03 06:01:23', '2025-03-03 06:01:23', NULL, 'products/product_68.jpg'),
(69, '72X96 3/8 CLEAR', 'GLA-R-25-0050', 1, 0.00, 1, 0, '2025-03-03 06:01:45', '2025-03-03 06:01:45', NULL, 'products/product_69.jpg'),
(70, '84X120 3/8 CLEAR', 'GLA-R-25-0051', 1, 0.00, 1, 0, '2025-03-03 06:02:01', '2025-03-03 06:02:01', NULL, 'products/product_70.jpg'),
(71, '72X96 1/2 CLEAR', 'GLA-R-25-0052', 1, 0.00, 1, 0, '2025-03-03 06:02:22', '2025-03-03 06:02:22', NULL, 'products/product_71.jpg'),
(72, '84X120 1/2 CLEAR', 'GLA-R-25-0053', 1, 0.00, 1, 0, '2025-03-03 06:02:39', '2025-03-03 06:02:39', NULL, 'products/product_72.jpg'),
(73, '60X84 1/4 CLEAR', 'GLA-R-25-0054', 1, 0.00, 1, 0, '2025-03-03 06:05:06', '2025-03-03 06:05:07', NULL, 'products/product_73.jpg'),
(74, '60X96 1/4 CLEAR', 'GLA-R-25-0055', 1, 0.00, 1, 0, '2025-03-03 06:05:26', '2025-03-03 06:05:27', NULL, 'products/product_74.jpg'),
(75, '84X144 1/4 CLEAR', 'GLA-R-25-0056', 1, 0.00, 1, 0, '2025-03-03 06:05:53', '2025-03-03 06:05:53', NULL, 'products/product_75.jpg'),
(76, '84X120 1/4 CRYSTAL GRAY', 'GLA-R-25-0057', 1, 0.00, 1, 0, '2025-03-03 06:06:56', '2025-03-03 06:06:56', NULL, 'products/product_76.jpg'),
(77, '84X144 1/4 DARK GRAY', 'GLA-R-25-0058', 1, 0.00, 1, 0, '2025-03-03 06:07:11', '2025-03-03 06:07:11', NULL, 'products/product_77.jpg'),
(78, '84X130 1/4 BRONZE MIRROR', 'GLA-R-25-0059', 1, 0.00, 1, 0, '2025-03-03 06:07:28', '2025-03-03 06:07:28', NULL, 'products/product_78.jpg'),
(79, '84X130 1/4 CRYSTAL GRAY', 'GLA-R-25-0060', 1, 0.00, 1, 0, '2025-03-03 06:07:51', '2025-03-03 06:07:51', NULL, 'products/product_79.jpg'),
(80, '798 LOCKSTILE (HA) B.U', 'ALU-R-25-0001', 2, 600.00, 1, 200, '2025-05-15 07:07:56', '2025-05-15 07:17:53', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_product_code_unique` (`product_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
