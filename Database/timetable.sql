-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 07, 2025 at 10:46 PM
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
-- Database: `timetable`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_year`
--

CREATE TABLE `academic_year` (
  `id` int(11) NOT NULL,
  `year_label` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_year`
--

INSERT INTO `academic_year` (`id`, `year_label`) VALUES
(1, '2024-2025'),
(2, '2025-2026'),
(3, '2023-2024');

-- --------------------------------------------------------

--
-- Table structure for table `all_resources`
--

CREATE TABLE `all_resources` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `code` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `all_resources`
--

INSERT INTO `all_resources` (`id`, `name`, `type`, `code`, `created_at`) VALUES
(1, 'huye', 'campus', '', '2025-08-02 14:29:10'),
(2, 'nyarugenge', 'campus', '', '2025-08-02 14:29:10'),
(3, 'gikondo', 'campus', '', '2025-08-02 14:29:10'),
(4, 'rukara', 'campus', '', '2025-08-02 14:29:10'),
(5, 'College of Science and Technology', 'college', '', '2025-08-02 14:29:10'),
(6, 'College of Business and Economics', 'college', '', '2025-08-02 14:29:10'),
(7, 'College of Education', 'college', '', '2025-08-02 14:29:10'),
(8, 'College of Medicine and Health Sciences', 'college', '', '2025-08-02 14:29:10'),
(9, 'School of Computing and Information Technology', 'school', '', '2025-08-02 14:29:10'),
(10, 'School of Engineering', 'school', '', '2025-08-02 14:29:10'),
(11, 'School of Business', 'school', '', '2025-08-02 14:29:10'),
(12, 'School of Health Sciences', 'school', '', '2025-08-02 14:29:10'),
(13, 'Department of Computer Science', 'department', '', '2025-08-02 14:29:10'),
(14, 'Department of Civil Engineering', 'department', '', '2025-08-02 14:29:10'),
(15, 'Department of Finance', 'department', '', '2025-08-02 14:29:10'),
(16, 'Department of Nursing', 'department', '', '2025-08-02 14:29:10'),
(17, 'Bachelor of Science in Computer Science', 'program', 'BSC-CS', '2025-08-02 14:29:10'),
(18, 'Bachelor of Science in Civil Engineering', 'program', 'BSC-CE', '2025-08-02 14:29:10'),
(19, 'Bachelor of Business Administration in Finance', 'program', 'BBA-FIN', '2025-08-02 14:29:10'),
(20, 'Bachelor of Science in General Nursing', 'program', 'BSC-GN', '2025-08-02 14:29:10');

-- --------------------------------------------------------

--
-- Table structure for table `campus`
--

CREATE TABLE `campus` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campus`
--

INSERT INTO `campus` (`id`, `name`) VALUES
(1, 'huye'),
(2, 'gikondo'),
(3, 'remera');

-- --------------------------------------------------------

--
-- Table structure for table `college`
--

CREATE TABLE `college` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `college`
--

INSERT INTO `college` (`id`, `name`, `campus_id`) VALUES
(1, 'cbe', 1),
(2, 'cass', 1),
(3, 'cmhs', 1),
(4, 'cst', 1);

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `school_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`id`, `name`, `school_id`) VALUES
(1, 'business department', 1),
(2, 'accounting', 1),
(3, 'statistics', 2);

-- --------------------------------------------------------

--
-- Table structure for table `facility`
--

CREATE TABLE `facility` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `location` varchar(30) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facility`
--

INSERT INTO `facility` (`id`, `name`, `location`, `type`, `capacity`, `campus_id`) VALUES
(1, 'BOO1', 'batima', 'classroom', 400, 1),
(2, 'clab001', 'batima', 'computer lab', 25, 1),
(3, 'mlab001', 'batima', 'medicine lab', 267, 1),
(5, 'clab00299', 'mpamba', 'computer lab', 30, 1),
(6, 'mlab0023', 'kkkkkkkkk', 'Classroom', 100, 1),
(8, 'clab003', 'nyarugenge', 'computer lab', 35, 1),
(9, 'mlab003', 'nyarugenge', 'medicine lab', 30, 1),
(10, 'cl004', 'nyarugenge', 'classroom', 45, 1),
(11, 'clab004', 'kicukiro', 'computer lab', 40, 1),
(12, 'mlab004', 'kicukiroo', 'Classroom', 79, 1),
(13, '001', 'kakire', 'Classroom', 20, 1),
(14, 'munezero', 's', 'Classroom', 23, 1),
(15, 'munezero', '23', 'Classroom', 34, 1),
(16, 'munezero', 'dd', 'Classroom', 33, 1),
(17, 'cl001', 'batima', 'classroom', 30, 1),
(18, 'cl002', 'mpamba', 'classroom', 35, 1),
(19, 'clab002', 'mpamba', 'computer lab', 30, 1),
(20, 'mlab002', 'mpamba', 'medicine lab', 25, 1),
(21, 'cl003', 'nyarugenge', 'classroom', 40, 1),
(22, 'cl004', 'kicukiro', 'classroom', 45, 1),
(23, 'mlab004', 'kicukiro', 'medicine lab', 35, 1);

-- --------------------------------------------------------

--
-- Table structure for table `intake`
--

CREATE TABLE `intake` (
  `id` int(11) NOT NULL,
  `year` int(11) DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `intake`
--

INSERT INTO `intake` (`id`, `year`, `month`, `size`, `program_id`) VALUES
(1, 2024, 1, 80, 1),
(2, 2025, 3, 90, 2),
(3, 2025, 7, 40, 3);

-- --------------------------------------------------------

--
-- Table structure for table `module`
--

CREATE TABLE `module` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `credits` int(11) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `year` int(11) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `program_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `module`
--

INSERT INTO `module` (`id`, `name`, `credits`, `code`, `year`, `semester`, `program_id`) VALUES
(1, 'Introduction to Programming', 3, 'CS101', 1, '1', 1),
(2, 'Database Management Systems', 4, 'CS201', 1, '2', 2),
(3, 'Web Development', 3, 'CS301', 2, '1', 1),
(4, 'Software Engineering', 4, 'CS401', 2, '2', 2),
(5, 'Software Engineering', 20, '491874', 1, '1', 1);

-- --------------------------------------------------------

--
-- Table structure for table `program`
--

CREATE TABLE `program` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program`
--

INSERT INTO `program` (`id`, `name`, `code`, `department_id`) VALUES
(1, 'bit', '112', 1),
(2, 'account', '23', 2),
(3, 'statistics in bach statistics in bachstatistics in statistics in bachstatistics in bachstatistics in bachstatistics in bachbachstatistics in bachstatistics in bachstatistic', 'S0B', 3);

-- --------------------------------------------------------

--
-- Table structure for table `school`
--

CREATE TABLE `school` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `college_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school`
--

INSERT INTO `school` (`id`, `name`, `college_id`) VALUES
(1, 'business', 1),
(2, 'economics', 1),
(3, 'coeb', 4);

-- --------------------------------------------------------

--
-- Table structure for table `student_group`
--

CREATE TABLE `student_group` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `intake_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_group`
--

INSERT INTO `student_group` (`id`, `name`, `size`, `intake_id`) VALUES
(2, 'bit group 1', 55, 1),
(3, 'bit group 2', 25, 1),
(4, 'g1', 30, 2),
(5, 'Goup1', 40, 3),
(6, 'g2', 60, 2);

-- --------------------------------------------------------

--
-- Table structure for table `system`
--

CREATE TABLE `system` (
  `id` int(11) NOT NULL,
  `status` varchar(100) NOT NULL,
  `accademic_year_id` int(11) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `userid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `system`
--

INSERT INTO `system` (`id`, `status`, `accademic_year_id`, `semester`, `userid`) VALUES
(1, 'live', 2, '1', 71);

-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `lecturer_id` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `semester` varchar(10) DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable`
--

INSERT INTO `timetable` (`id`, `module_id`, `lecturer_id`, `facility_id`, `semester`, `academic_year_id`) VALUES
(1, 5, 75, 1, '1', 2),
(2, 2, 74, 6, '1', 2),
(3, 2, 74, 3, '1', 2),
(4, 4, 75, 3, '1', 2),
(5, 2, 73, 6, '1', 2);

-- --------------------------------------------------------

--
-- Table structure for table `timetable_groups`
--

CREATE TABLE `timetable_groups` (
  `id` int(11) NOT NULL,
  `timetable_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable_groups`
--

INSERT INTO `timetable_groups` (`id`, `timetable_id`, `group_id`) VALUES
(1, 1, 4),
(2, 1, 6),
(3, 2, 6),
(4, 3, 2),
(5, 3, 3),
(6, 3, 4),
(7, 3, 6),
(8, 4, 5),
(9, 5, 4);

-- --------------------------------------------------------

--
-- Table structure for table `timetable_sessions`
--

CREATE TABLE `timetable_sessions` (
  `id` int(11) NOT NULL,
  `timetable_id` int(11) NOT NULL,
  `day` varchar(10) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable_sessions`
--

INSERT INTO `timetable_sessions` (`id`, `timetable_id`, `day`, `start_time`, `end_time`) VALUES
(1, 1, 'Monday', '08:00:00', '12:00:00'),
(2, 1, 'Tuesday', '08:00:00', '12:00:00'),
(4, 2, 'Monday', '14:00:00', '17:00:00'),
(5, 3, 'Friday', '08:00:00', '12:00:00'),
(6, 4, 'Friday', '17:00:00', '20:00:00'),
(7, 5, 'Wednesday', '00:05:00', '22:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `names` varchar(30) DEFAULT NULL,
  `email` varchar(30) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `image` varchar(200) DEFAULT NULL,
  `about` varchar(150) DEFAULT NULL,
  `role` varchar(30) DEFAULT NULL,
  `password` varchar(200) DEFAULT NULL,
  `active` int(11) DEFAULT NULL,
  `resetcode` int(11) DEFAULT NULL,
  `campus` int(11) DEFAULT NULL,
  `privileges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`privileges`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `names`, `email`, `phone`, `image`, `about`, `role`, `password`, `active`, `resetcode`, `campus`, `privileges`) VALUES
(1, 'cedrick', 'cedrickhakuzimana@gmail.com', '0783043021', 'assets/img/av.png', '', 'admin', '$2y$10$5OhGuQPwsrHkVzq9b91vO.KowcpwDdbpM2ZAogWii.xZf4ya0sLSK', 1, 0, 0, NULL),
(70, 'huye', 'adminhuye@gmail.com', '0784366616', 'assets/img/av.png', NULL, 'campus_admin', '$2y$10$.jd0GXmKIIyZKGmesfBBv.6e4dSowlLMgVTCzVs.kNTPTOY4kIlNG', 1, NULL, 1, NULL),
(71, 'huye_timetable', 'timetable_officer@gmail.com', '0784366611', 'assets/img/av.png', NULL, 'timetable_officer', '$2y$10$HQ6iIo1q8IaiOL3eOEQg.uUcvzpNU5Hd6MHZXRLGunLP5Do.VnpA2', 1, NULL, 1, NULL),
(72, 'John Doe', 'john.doe@example.com', '+1234567890', 'assets/img/av.png', NULL, 'lecturer', '$2y$10$9sDL3wNgKgNA6GXi77w20e6R6wIUMoM6YEIdmmB6HgwGmaN3lVDUq', 1, NULL, 0, NULL),
(73, 'Jane Smith', 'jane.smith@example.com', '+0987654321', 'assets/img/av.png', NULL, 'lecturer', '$2y$10$Z/0l/y18mb5azOEqOQUd2.96EnQRrjmkFcSx1Avwr9knAaVUaHWbW', 1, NULL, 0, NULL),
(74, 'Robert Johnson', 'robert.johnson@example.com', '+1122334455', 'assets/img/av.png', NULL, 'lecturer', '$2y$10$cxafpfvizg8FGNR/os86buOubYx0OUCmk/kKyWDKwaD5EEeDonS/G', 1, NULL, 0, NULL),
(75, 'Mary Williams', 'mary.williams@example.com', '+5566778899', 'assets/img/av.png', NULL, 'lecturer', '$2y$10$dwdycB0.a2Hzka7D5kSIfu99ItmaInPWrnm/AjjA/82AJAh6Ck5tG', 1, NULL, 0, NULL),
(76, 'chris', 'chris@gmail.com', '0784366619', 'assets/img/av.png', NULL, 'timetable_officer', '$2y$10$MtVEGcqqwH4yKKcHJY68iO3NnHyDPHI1TS8uaG7DhbpjaxlqYaYrS', 1, NULL, 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_year`
--
ALTER TABLE `academic_year`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `all_resources`
--
ALTER TABLE `all_resources`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `campus`
--
ALTER TABLE `campus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `college`
--
ALTER TABLE `college`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facility`
--
ALTER TABLE `facility`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `intake`
--
ALTER TABLE `intake`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `module`
--
ALTER TABLE `module`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `program`
--
ALTER TABLE `program`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `school`
--
ALTER TABLE `school`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_group`
--
ALTER TABLE `student_group`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system`
--
ALTER TABLE `system`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `timetable_groups`
--
ALTER TABLE `timetable_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `timetable_sessions`
--
ALTER TABLE `timetable_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_year`
--
ALTER TABLE `academic_year`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `all_resources`
--
ALTER TABLE `all_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `campus`
--
ALTER TABLE `campus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `college`
--
ALTER TABLE `college`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `facility`
--
ALTER TABLE `facility`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `intake`
--
ALTER TABLE `intake`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `module`
--
ALTER TABLE `module`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `program`
--
ALTER TABLE `program`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `school`
--
ALTER TABLE `school`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_group`
--
ALTER TABLE `student_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `system`
--
ALTER TABLE `system`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `timetable_groups`
--
ALTER TABLE `timetable_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `timetable_sessions`
--
ALTER TABLE `timetable_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
