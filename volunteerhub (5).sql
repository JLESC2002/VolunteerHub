-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 07, 2026 at 03:28 AM
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
-- Database: `volunteerhub`
--

-- --------------------------------------------------------

--
-- Table structure for table `bank_payments`
--

CREATE TABLE `bank_payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `uploaded_proof` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_payments`
--

INSERT INTO `bank_payments` (`id`, `user_id`, `organization_id`, `bank_name`, `reference_number`, `amount`, `uploaded_proof`, `status`, `created_at`) VALUES
(1, 16, 1, NULL, '12eqwds2134q1231231', 123123.00, 'bank_transfer_1760859507_68f495735ee78.jpg', '', '2025-10-19 07:38:27'),
(2, 16, 1, NULL, 'adawdawd123123', 12311.00, 'bank_transfer_1760859708_68f4963ced245.jpg', '', '2025-10-19 07:41:48'),
(3, 16, 1, NULL, '12312312', 122.00, NULL, '', '2025-10-19 08:33:57');

-- --------------------------------------------------------

--
-- Table structure for table `dropoff_donations`
--

CREATE TABLE `dropoff_donations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `item_category` varchar(100) DEFAULT NULL,
  `item_description` text DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `dropoff_date` date DEFAULT NULL,
  `status` enum('Pending','Received','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dropoff_donations`
--

INSERT INTO `dropoff_donations` (`id`, `user_id`, `organization_id`, `item_category`, `item_description`, `quantity`, `dropoff_date`, `status`, `created_at`) VALUES
(1, 16, 1, 'Books', 'awdq1EQWD123V24B', 123, '2025-10-22', 'Received', '2025-10-19 08:41:07');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Open',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `date`, `location`, `created_by`, `status`, `latitude`, `longitude`) VALUES
(2, 'Tree Planting Drive', 'Help us plant trees to create a greener city.', '2025-10-22', 'Riverside Park', 1, 'Completed', NULL, NULL),
(3, 'Food Drive for Families in Need', 'Assist in collecting and distributing food supplies.', '2025-05-05', 'Community Center', 2, 'Open', NULL, NULL),
(4, 'Clothing Donation & Distribution', 'Sort and distribute donated clothes to underprivileged families.', '2025-05-15', 'Volunteer Hub Warehouse', 2, 'Open', NULL, NULL),
(5, 'Blood Donation Camp', 'Organized in collaboration with the city hospital. Donate blood and save lives.', '2025-06-01', 'Red Cross Center', 3, 'Open', NULL, NULL),
(6, 'Elderly Care Home Visit', 'Spend time with the elderly, play games, and share stories.', '2025-06-20', 'Sunrise Elderly Home', 3, 'Open', NULL, NULL),
(7, 'Street clean up ', 'cleaning the streets of iloilo', '2025-05-11', 'iloilo', 1, 'Completed', NULL, NULL),
(8, 'dawdasdaw', 'dasdawdasdaw', '2025-03-17', 'awdasdawd', 19, 'Open', NULL, NULL),
(9, 'Team Tress', 'plating 1 million trees', '2025-07-28', 'iloilo', 1, 'Completed', NULL, NULL),
(10, 'Team Trees', 'plant 1 million treee', '2025-06-22', 'iloilo', 1, 'Completed', NULL, NULL),
(11, 'Gift Giving', 'Giving Gifts to Homeless People', '2025-08-17', 'Iloilo City', 1, 'Completed', NULL, NULL),
(12, 'Food Donation', 'donate food for the needs', '2025-06-24', 'iloilo', 1, 'Completed', NULL, NULL),
(15, 'Team Water', 'Help impoverish countries have access to water', '0000-00-00', 'Syria', 1, 'Open', NULL, NULL),
(16, 'Treee Planting', 'planting trees', '2025-08-30', 'iloilo', 1, 'Open', NULL, NULL),
(17, 'wsdehkfgajklsdhgfjkha', 'jsdfgliouewhrfgiuahsdikjvh', '2025-11-08', 'ergtsdfgsergtasdfg', 1, 'Open', NULL, NULL),
(18, 'gift giving ', 'giving gifts to people of mandurriao', '2025-10-30', 'mandurriao iloilo city', 1, 'Open', NULL, NULL),
(20, 'h235l3hfknsdfhjk', 'nweijk;thjklwerfnkljshtpou234h', '0000-00-00', '0', 1, 'Open', 10.71906800, 122.53570100),
(21, 'testing', 'testing lng', '2025-11-08', 'Iloilo CIty', 1, 'Open', 10.71883900, 122.53572700);

-- --------------------------------------------------------

--
-- Table structure for table `event_attendance`
--

CREATE TABLE `event_attendance` (
  `event_id` int(11) NOT NULL,
  `volunteer_id` int(11) NOT NULL,
  `attended` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = Not Checked In, 1 = Checked In, 2 = Checked Out',
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `check_in` datetime DEFAULT NULL,
  `check_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_attendance`
--

INSERT INTO `event_attendance` (`event_id`, `volunteer_id`, `attended`, `recorded_at`, `check_in`, `check_out`) VALUES
(2, 16, 0, '2025-08-17 03:48:01', NULL, NULL),
(2, 20, 0, '2025-08-17 03:48:01', NULL, NULL),
(7, 16, 2, '2025-08-17 04:44:56', '2025-08-17 12:44:56', '2025-08-17 12:44:58'),
(9, 14, 0, '2025-08-13 11:01:13', NULL, NULL),
(9, 16, 2, '2025-08-13 11:01:14', '2025-08-18 08:26:16', '2025-08-18 08:26:31'),
(9, 20, 0, '2025-08-13 11:01:12', NULL, NULL),
(10, 16, 2, '2025-08-18 00:26:25', '2025-08-18 08:26:25', '2025-08-18 08:26:42'),
(11, 16, 2, '2025-08-18 00:26:23', '2025-08-18 08:26:23', '2025-08-18 08:26:29'),
(16, 16, 2, '2025-10-18 08:29:04', '2025-10-18 16:29:04', '2025-10-18 16:29:08'),
(17, 16, 0, '2025-10-18 09:15:14', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `gcash_donations`
--

CREATE TABLE `gcash_donations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_number` varchar(100) NOT NULL,
  `proof_image` varchar(255) NOT NULL,
  `status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gcash_donations`
--

INSERT INTO `gcash_donations` (`id`, `user_id`, `organization_id`, `amount`, `reference_number`, `proof_image`, `status`, `created_at`) VALUES
(1, 16, 1, 1231231.00, '1231231', 'gcash_1760859003_68f4937bc7d50.jpg', '', '2025-10-19 07:30:03');

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) NOT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `gcash_name` varchar(255) DEFAULT NULL,
  `gcash_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `logo` varchar(255) DEFAULT NULL,
  `gcash_qr` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_account_name` varchar(255) DEFAULT NULL,
  `bank_account_number` varchar(100) DEFAULT NULL,
  `dropoff_location` varchar(255) DEFAULT NULL,
  `dropoff_instructions` text DEFAULT NULL,
  `facebook_link` varchar(512) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `admin_id`, `name`, `description`, `location`, `contact_email`, `contact_phone`, `gcash_name`, `gcash_number`, `created_at`, `logo`, `gcash_qr`, `bank_name`, `bank_account_name`, `bank_account_number`, `dropoff_location`, `dropoff_instructions`, `facebook_link`) VALUES
(1, 1, 'scholar ng bayan', 'Free scholarship for students', 'iloilo', 'scholar@gmail.com', '12312312313', 'John lester cha', '09283416054', '2025-03-06 08:54:47', 'org_logo_1_1761622899_69003b73684a2.jpg', 'gcash_qr_1760863128_68f4a3986ef0d.png', '238947289347238947', 'iwehfwhdfiuahweruhy', '12948012890', 'iloilo City', 'please knock on the front door', 'https://www.facebook.com/johnlester.chua.5'),
(2, 15, 'Team Trees', 'plant 1 miilion trees', 'Iloilo', 'Teamtrees@gmail.com', '123123123123', NULL, NULL, '2025-03-08 02:31:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 12, 'Org of Benjamin Clark', 'An organization dedicated to community volunteering.', 'City 9', 'contact_12@volunteerhub.org', '09614574362', NULL, NULL, '2025-06-07 02:49:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 13, 'Org of Charlotte Lewis', 'An organization dedicated to community volunteering.', 'City 5', 'contact_13@volunteerhub.org', '09348615786', NULL, NULL, '2025-06-07 02:49:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 17, 'Org of john lester chau', 'An organization dedicated to community volunteering.', 'City 5', 'contact_17@volunteerhub.org', '0992530958', NULL, NULL, '2025-06-07 02:49:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 18, 'Org of lester', 'An organization dedicated to community volunteering.', 'City 2', 'contact_18@volunteerhub.org', '09620546409', NULL, NULL, '2025-06-07 02:49:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 19, 'Org of outa', 'An organization dedicated to community volunteering.', 'City 6', 'contact_19@volunteerhub.org', '09957603431', NULL, NULL, '2025-06-07 02:49:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 21, 'Helping Filipino', 'helps filipinos in need', 'iloilo', 'awd@gmail.cpom', '1231231312', NULL, NULL, '2025-08-14 04:01:40', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `volunteer_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  `assigned_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `event_id`, `volunteer_id`, `description`, `status`, `assigned_by`) VALUES
(6, 2, 6, 'Dig holes for tree planting.', 'Pending', 4),
(7, 2, 7, 'Water newly planted trees.', 'Completed', 5),
(8, 2, 8, 'Distribute saplings to volunteers.', 'Pending', 4),
(9, 2, 9, 'Prepare compost for planting.', 'Completed', 5),
(10, 2, 10, 'Clean up the area after planting.', 'Pending', 4),
(11, 3, 6, 'Sort and pack food items.', 'Completed', 5),
(12, 3, 7, 'Distribute food packages.', 'Pending', 4),
(13, 3, 8, 'Assist with event registration.', 'Pending', 5),
(14, 3, 9, 'Deliver food to elderly homes.', 'Completed', 4),
(15, 3, 10, 'Manage crowd control.', 'Pending', 5),
(16, 4, 6, 'Sort clothes by size and category.', 'Pending', 5),
(17, 4, 7, 'Help distribute clothes to families.', 'Completed', 4),
(18, 4, 8, 'Fold and organize clothing racks.', 'Pending', 5),
(19, 4, 9, 'Assist elderly individuals with selection.', 'Completed', 4),
(20, 4, 10, 'Clean up after the distribution.', 'Pending', 5),
(21, 5, 6, 'Register blood donors at the entrance.', 'Completed', 4),
(22, 5, 7, 'Assist medical staff with paperwork.', 'Pending', 5),
(23, 5, 8, 'Provide refreshments to donors.', 'Completed', 4),
(24, 5, 9, 'Distribute educational pamphlets.', 'Pending', 5),
(25, 5, 10, 'Ensure donors rest after donation.', 'Pending', 4),
(26, 6, 6, 'Help elderly residents with mobility.', 'Pending', 4),
(27, 6, 7, 'Read books to elderly residents.', 'Completed', 5),
(28, 6, 8, 'Assist with serving food.', 'Pending', 4),
(29, 6, 9, 'Play board games with seniors.', 'Completed', 5),
(30, 6, 10, 'Clean common areas after event.', 'Pending', 4),
(36, 2, NULL, 'dshshf', 'Pending', NULL),
(37, 8, NULL, 'dawdasdawdasdawd', 'Pending', NULL),
(38, 9, NULL, 'fetch saplings', 'Pending', NULL),
(39, 9, NULL, 'kuha samplings123123', 'Pending', NULL),
(41, 10, NULL, 'aqwdawdaqwda', 'Pending', NULL),
(42, 7, NULL, 'Street Sweeping\r\n', 'Pending', NULL),
(44, 7, NULL, 'picking Garbage Cans', 'Pending', NULL),
(47, 17, NULL, 'adawdafaerhysefghdfghsdhgsdert', 'Pending', NULL),
(48, 18, NULL, 'kuha bugas', 'Pending', NULL),
(49, 18, NULL, 'edaqwda', 'Pending', NULL),
(50, 2, NULL, 'dawdzsxf et', 'Pending', NULL),
(52, 21, 16, 'test task', 'Pending', 1);

-- --------------------------------------------------------

--
-- Table structure for table `task_assignments`
--

CREATE TABLE `task_assignments` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `volunteer_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `progress` enum('Not Started','In Progress','Completed') DEFAULT 'Not Started'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_assignments`
--

INSERT INTO `task_assignments` (`id`, `task_id`, `volunteer_id`, `assigned_by`, `assigned_at`, `progress`) VALUES
(1, 1, 14, 1, '2025-04-12 08:51:37', 'Not Started'),
(2, 38, 16, 1, '2025-04-12 08:56:36', 'Completed'),
(3, 39, 16, 1, '2025-04-12 08:59:00', 'Completed'),
(4, 0, 14, 1, '2025-04-14 02:03:26', 'Not Started'),
(5, 0, 16, 1, '2025-04-15 02:20:56', 'Not Started'),
(6, 0, 20, 1, '2025-04-23 04:54:35', 'Not Started'),
(7, 1, 6, 0, '2025-06-07 05:02:42', 'Not Started'),
(8, 3, 8, 0, '2025-06-07 05:02:42', 'Not Started'),
(9, 7, 7, 0, '2025-06-07 05:02:42', 'Not Started'),
(10, 9, 9, 0, '2025-06-07 05:02:42', 'Not Started'),
(11, 11, 6, 0, '2025-06-07 05:02:42', 'Not Started'),
(12, 14, 9, 0, '2025-06-07 05:02:42', 'Not Started'),
(13, 17, 7, 0, '2025-06-07 05:02:42', 'Not Started'),
(14, 19, 9, 0, '2025-06-07 05:02:42', 'Not Started'),
(15, 21, 6, 0, '2025-06-07 05:02:42', 'Not Started'),
(16, 23, 8, 0, '2025-06-07 05:02:42', 'Not Started'),
(17, 27, 7, 0, '2025-06-07 05:02:42', 'Not Started'),
(18, 29, 9, 0, '2025-06-07 05:02:42', 'Not Started'),
(19, 7, 20, 1, '2025-08-13 11:40:52', 'Not Started'),
(20, 46, 22, 1, '2025-08-18 03:32:30', 'Completed'),
(21, 47, 16, 1, '2025-10-18 09:12:53', 'Not Started'),
(22, 41, 16, 1, '2025-10-19 04:34:21', 'Not Started'),
(23, 42, 14, 1, '2025-10-19 08:30:02', 'Not Started'),
(24, 38, 20, 1, '2025-10-19 14:00:20', 'Not Started'),
(25, 52, 16, 1, '2025-11-05 14:06:42', 'Not Started');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','volunteer') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_token` varchar(64) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `session_token`, `profile_pic`) VALUES
(1, 'CHUA, JOHN LESTER, H.', 'admin@example.com', '$2y$10$BW.3cnu3hwddKYRXJK.dEu/v7ZID4oT5jatWoN.fRV2jKWFR1u7Wu', 'admin', '2025-03-06 07:37:38', '79f204b422de538ae6f79691c0c14887', 'profile_1_1761622372_6900396444099.jpg'),
(2, 'Michael Green', 'michael@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'volunteer', '2025-03-06 07:49:18', NULL, NULL),
(3, 'Emily Davis', 'emily@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'volunteer', '2025-03-06 07:49:18', NULL, NULL),
(4, 'David Wilson', 'david@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'volunteer', '2025-03-06 07:49:18', NULL, NULL),
(5, 'Sophia Miller', 'sophia@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'volunteer', '2025-03-06 07:49:18', NULL, NULL),
(6, 'Daniel Martinez', 'daniel@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'volunteer', '2025-03-06 07:49:18', NULL, NULL),
(7, 'Olivia Anderson', 'olivia@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'staff', '2025-03-06 07:49:18', NULL, NULL),
(8, 'William Thomas', 'william@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'staff', '2025-03-06 07:49:18', NULL, NULL),
(9, 'Emma Garcia', 'emma@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'staff', '2025-03-06 07:49:18', NULL, NULL),
(10, 'Liam Rodriguez', 'liam@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'staff', '2025-03-06 07:49:18', NULL, NULL),
(11, 'Isabella Lee', 'isabella@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'staff', '2025-03-06 07:49:18', NULL, NULL),
(12, 'Benjamin Clark', 'benjamin@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'admin', '2025-03-06 07:49:18', NULL, NULL),
(13, 'Charlotte Lewis', 'charlotte@example.com', 'a1ddceb73d86e5ad202615095280ac88fdb8c4bd80877afbf363b3b0f47a2ad9', 'admin', '2025-03-06 07:49:18', NULL, NULL),
(14, 'sample', 'sample@gmail.com', '$2y$10$21DlUxHAv1xDNzEgd4g.nehuiOZus/aguJTFUZLcmwbJgtKUrmPvm', 'volunteer', '2025-03-06 08:24:15', NULL, NULL),
(15, 'chua', 'chua@gmail.com', '$2y$10$gX2dFimyW4j3q1A5ZgSQCO7WeAPbisnz4e/fnn3N5Lw8MaNb59N/C', 'admin', '2025-03-08 02:30:00', NULL, NULL),
(16, 'CHUA, JOHN LESTER, H.', 'volunteer@example.com', '$2y$10$NdweccHGVzR3PDaNwzld6OikQqgOllAjclxKX549BxJ31HX7WhQ16', 'volunteer', '2025-03-17 09:34:00', '2ddda4d280c659c3ea3303e46663d199', 'volunteer_16_1761629220_69005424a61b2.jpg'),
(17, 'john lester chau', 'chua@sample.com', '$2y$10$epeAYxCEWz/WSOket5/VpeDLhSnscl1m2KxRK7FVZetedFXo4sHbq', 'admin', '2025-03-17 10:43:28', 'e4a5f51ac053c488a60f13c22ff5b3e4', NULL),
(18, 'lester', 'lnte@lnte.com', '$2y$10$.LhporIbu6wEz48.1bHXcuGmEM2A4ieIjBjjCEtrXCiVRJwkCNrnO', 'admin', '2025-03-17 12:51:38', NULL, NULL),
(19, 'outa', 'outa@outa.com', '$2y$10$AVp5bP0vebYuq7M/k7ariuescP1ykHl7GMg3.2KtNRIUsNAk6ihf2', 'admin', '2025-03-17 13:11:19', '18dff6cb8ea84103db0a5cf424289141', NULL),
(20, 'chua', 'chua@chua.com', '$2y$10$dpyJ2ON8cVzoadzcPa9p.uUvxi9QRfF5snhfEgUsdGHou45ipnRlK', 'volunteer', '2025-03-19 10:25:29', '091688abc70cf1c9669853c08f8e7bcd', NULL),
(21, 'Lara Chua', 'larachua@gmail.com', '$2y$10$b1O4TqpkNHAX8Te0g4zfguzWK9lbWC1WSN/OKec8t246T86u2xfuG', 'admin', '2025-08-14 04:00:57', 'c00ec02279ef3c8d1b998c3b203a6d5f', NULL),
(22, 'Joshua porras', 'joshua@gmail.com', '$2y$10$iGFhQws1SolAizK2DFbs5esazcfi9ubZnmqaFIGD0OsyfrWKQZXcC', 'volunteer', '2025-08-18 03:31:45', '3752775558e9b0992342b1db5945c40a', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_applications`
--

CREATE TABLE `volunteer_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `volunteer_applications`
--

INSERT INTO `volunteer_applications` (`id`, `user_id`, `event_id`, `status`, `applied_at`) VALUES
(4, 20, 2, 'approved', '2025-04-11 02:30:20'),
(5, 20, 9, 'approved', '2025-04-11 02:30:22'),
(6, 14, 7, 'approved', '2025-04-12 08:53:56'),
(7, 14, 8, 'pending', '2025-04-12 08:54:01'),
(8, 14, 9, 'approved', '2025-04-12 08:54:02'),
(9, 14, 10, 'approved', '2025-04-12 08:54:03'),
(10, 16, 7, 'approved', '2025-04-12 08:55:20'),
(11, 16, 2, 'rejected', '2025-04-12 08:55:22'),
(12, 16, 6, 'pending', '2025-04-12 08:55:27'),
(13, 16, 5, 'pending', '2025-04-12 08:55:30'),
(15, 16, 11, 'approved', '2025-04-12 08:55:33'),
(16, 16, 9, 'approved', '2025-04-12 08:55:37'),
(17, 16, 10, 'approved', '2025-04-12 08:55:38'),
(19, 16, 8, 'pending', '2025-08-13 10:01:22'),
(21, 16, 15, 'pending', '2025-08-18 00:31:50'),
(22, 16, 3, 'pending', '2025-08-18 00:33:19'),
(23, 16, 16, 'approved', '2025-08-18 03:30:32'),
(24, 22, 16, 'approved', '2025-08-18 03:32:04'),
(25, 16, 17, 'approved', '2025-10-18 09:12:27'),
(26, 16, 4, 'pending', '2025-10-18 09:53:41'),
(27, 16, 20, 'pending', '2025-10-28 05:10:39'),
(28, 16, 21, 'approved', '2025-11-05 14:06:10');

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_details`
--

CREATE TABLE `volunteer_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `hobbies` text DEFAULT NULL,
  `skills` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `volunteer_details`
--

INSERT INTO `volunteer_details` (`id`, `user_id`, `gender`, `age`, `address`, `phone_number`, `hobbies`, `skills`) VALUES
(1, 16, 'Male', 22, '12 Guzman Street Mandurriao Iloilo CIty', '09283416054', 'Gaming', 'IT'),
(2, 2, 'Female', 40, 'Street 22, City 9', '09394949612', 'Music', 'Communication'),
(3, 3, 'Male', 41, 'Street 65, City 9', '09432912823', 'Music', 'Communication'),
(4, 4, 'Female', 44, 'Street 42, City 5', '09930607146', 'Sports', 'Event Planning'),
(5, 5, 'Female', 30, 'Street 50, City 2', '09448563261', 'Photography', 'Leadership'),
(6, 6, 'Male', 38, 'Street 18, City 9', '09782794354', 'Sports', 'Teamwork'),
(7, 14, 'Female', 22, 'Street 84, City 8', '09192762957', 'Photography', 'Leadership'),
(8, 20, 'Male', 36, 'Street 22, City 3', '09422063669', 'Music', 'Leadership');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bank_payments`
--
ALTER TABLE `bank_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `organization_id` (`organization_id`);

--
-- Indexes for table `dropoff_donations`
--
ALTER TABLE `dropoff_donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `organization_id` (`organization_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `event_attendance`
--
ALTER TABLE `event_attendance`
  ADD PRIMARY KEY (`event_id`,`volunteer_id`),
  ADD KEY `idx_event` (`event_id`),
  ADD KEY `idx_volunteer` (`volunteer_id`);

--
-- Indexes for table `gcash_donations`
--
ALTER TABLE `gcash_donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `organization_id` (`organization_id`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_id` (`admin_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `volunteer_id` (`volunteer_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `task_assignments`
--
ALTER TABLE `task_assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `volunteer_applications`
--
ALTER TABLE `volunteer_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `volunteer_details`
--
ALTER TABLE `volunteer_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bank_payments`
--
ALTER TABLE `bank_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `dropoff_donations`
--
ALTER TABLE `dropoff_donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `gcash_donations`
--
ALTER TABLE `gcash_donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `task_assignments`
--
ALTER TABLE `task_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `volunteer_applications`
--
ALTER TABLE `volunteer_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `volunteer_details`
--
ALTER TABLE `volunteer_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bank_payments`
--
ALTER TABLE `bank_payments`
  ADD CONSTRAINT `bank_payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bank_payments_ibfk_2` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);

--
-- Constraints for table `dropoff_donations`
--
ALTER TABLE `dropoff_donations`
  ADD CONSTRAINT `dropoff_donations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `dropoff_donations_ibfk_2` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_attendance`
--
ALTER TABLE `event_attendance`
  ADD CONSTRAINT `fk_ea_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ea_user` FOREIGN KEY (`volunteer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `gcash_donations`
--
ALTER TABLE `gcash_donations`
  ADD CONSTRAINT `gcash_donations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `gcash_donations_ibfk_2` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);

--
-- Constraints for table `organizations`
--
ALTER TABLE `organizations`
  ADD CONSTRAINT `organizations_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`volunteer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `volunteer_applications`
--
ALTER TABLE `volunteer_applications`
  ADD CONSTRAINT `volunteer_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `volunteer_applications_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `volunteer_details`
--
ALTER TABLE `volunteer_details`
  ADD CONSTRAINT `volunteer_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
