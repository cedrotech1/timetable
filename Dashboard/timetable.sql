-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 05, 2025 at 02:08 PM
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
(2, '2025-2026');

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
(2, 'kigali');

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
(2, 'cass', 1);

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
(1, 'bit', 1);

-- --------------------------------------------------------

--
-- Table structure for table `facility`
--

CREATE TABLE `facility` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facility`
--

INSERT INTO `facility` (`id`, `name`, `type`, `capacity`, `campus_id`) VALUES
(1, 'c001', 'classroom', 10, 0),
(2, 'c002', 'classroom', 40, 0),
(3, '1', 'NULL', 2025, 1),
(4, 'Lecture Hall 1', 'Lecture Hall', 100, 1),
(5, 'Computer Lab 1', 'Laboratory', 30, 1),
(6, 'Seminar Room 1', 'Seminar Room', 50, 1),
(7, 'Conference Room 1', 'Conference Room', 20, 1),
(8, 'Lecture Hall 1', 'Lecture Hall', 100, 5),
(9, 'Computer Lab 1', 'Laboratory', 30, 5),
(10, 'Seminar Room 1', 'Seminar Room', 50, 5),
(11, 'Conference Room 1', 'Conference Room', 20, 5),
(12, 'Lecture Hall 1', 'Lecture Hall', 100, 4),
(13, 'Computer Lab 1', 'Laboratory', 30, 4),
(14, 'Seminar Room 1', 'Seminar Room', 50, 4),
(15, 'Conference Room 1', 'Conference Room', 20, 4),
(16, 'Lecture Hall 1', 'Lecture Hall', 100, 2),
(17, 'Computer Lab 1', 'Laboratory', 30, 2),
(18, 'Seminar Room 1', 'Seminar Room', 50, 2),
(19, 'Conference Room 1', 'Conference Room', 20, 2);

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
(1, 2025, 6, NULL, 1);

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
(4, 'Software Engineering', 4, 'CS401', 2, '2', 2);

-- --------------------------------------------------------

--
-- Table structure for table `program`
--

CREATE TABLE `program` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program`
--

INSERT INTO `program` (`id`, `name`, `code`, `department_id`) VALUES
(1, 'bachour in bisness and it', 'bit001', 1);

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
(2, 'economics', 1);

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
(1, 'group 1', 76, 1),
(2, 'group 2', 89, 1);

-- --------------------------------------------------------

--
-- Table structure for table `system`
--

CREATE TABLE `system` (
  `id` int(11) NOT NULL,
  `status` varchar(100) NOT NULL,
  `exp_date` varchar(20) NOT NULL,
  `exam_validity` varchar(20) NOT NULL,
  `accademic_year` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `allow_message` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `system`
--

INSERT INTO `system` (`id`, `status`, `exp_date`, `exam_validity`, `accademic_year`, `semester`, `allow_message`) VALUES
(1, 'live', '', '', '', '', '');

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

-- --------------------------------------------------------

--
-- Table structure for table `timetable_groups`
--

CREATE TABLE `timetable_groups` (
  `id` int(11) NOT NULL,
  `timetable_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'cedro_tech', 'cedrotech1@gmail.com', '0788308413', 'upload/icon1.png', '                                                                                                                                                      ', 'admin', '$2y$10$jiLGHmEwqW0ARK0pDQqdreaADev6mkf/pfO0ZkFx0uBqsHImhmwNG', 1, 0, 0, NULL),
(29, 'cedrick', 'cedrickhakuzimana@gmail.com', '0783043021', 'upload/av.png', '', 'information_modifier', '$2y$10$5OhGuQPwsrHkVzq9b91vO.KowcpwDdbpM2ZAogWii.xZf4ya0sLSK', 1, 0, 0, NULL),
(41, 'Ange', 'a.nduwera@ur.ac.rw', '', 'upload/av.png', '', 'admin', '$2y$10$xIc8QDddoo7PWKT2Ejtd1O2n77W.elUtKlnL9nNcTyeamddekY4o6', 1, 660120, 0, NULL),
(44, 'cedrick hakuzimana', 'cedrickhakuzimana75@gmail.com', '0784366616', 'assets/img/av.png', '', 'warefare', '$2y$10$G4MfaQibRn0UVkrJPJvEIOQ33re6/7Wzx10XA/Gas5g1Q7KTuiXRK', 1, 0, 1, NULL),
(45, 'akimana', 'akimana@gmail.com', '0784366616', 'assets/img/av.png', '', 'warefare', '$2y$10$oetnF5JR/F/4d8o57UPPfek9ogQo3nckbODaeEZdc0cG6OgthJ.su', 1, 0, 4, NULL),
(46, 'John Doe', 'john.doe@example.com', '+1234567890', NULL, 'Computer Science Professor', 'lecturer', '$2y$10$nn5IFOKELCUC9u8YBu.91.8AXMHP5f4oGISWBaLAJBBT8NwZD5aK2', 1, NULL, NULL, NULL),
(47, 'Jane Smith', 'jane.smith@example.com', '+0987654321', NULL, 'Mathematics Lecturer', 'lecturer', '$2y$10$Vsu0yibQECagl7BN7ZgYkeRwetw9syTwBKOHuJQy8JZWbufuThDeK', 1, NULL, NULL, NULL),
(48, 'Robert Johnson', 'robert.johnson@example.com', '+1122334455', NULL, 'Physics Professor', 'lecturer', '$2y$10$bPP0WMz3rDWHzh.KT5y8L.5d/AiSM516lzjfIgOpTXOfUvEbXi9Ou', 1, NULL, NULL, NULL),
(50, 'Mary Williams', 'mary.williams@example.com', '+5566778899', 'upload/icon1.png', NULL, 'lecturer', '$2y$10$SSY7MY3htkgQ7EUGck0l/.XQ59yQ9GJC/HscG.dh07MsRR3njYIAC', 1, NULL, 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_year`
--
ALTER TABLE `academic_year`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `campus`
--
ALTER TABLE `campus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `college`
--
ALTER TABLE `college`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `facility`
--
ALTER TABLE `facility`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `intake`
--
ALTER TABLE `intake`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `module`
--
ALTER TABLE `module`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `program`
--
ALTER TABLE `program`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `school`
--
ALTER TABLE `school`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_group`
--
ALTER TABLE `student_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `system`
--
ALTER TABLE `system`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timetable_groups`
--
ALTER TABLE `timetable_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
