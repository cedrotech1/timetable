-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 16, 2025 at 11:54 PM
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
-- Database: `timetable-v2`
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
(1, 'business department', 10),
(2, 'accounting', 10),
(3, 'statistics', 10);

-- --------------------------------------------------------

--
-- Table structure for table `facility`
--

CREATE TABLE `facility` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL,
  `site` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facility`
--

INSERT INTO `facility` (`id`, `name`, `type`, `capacity`, `campus_id`, `site`) VALUES
(1, 'Main Auditorium', 'Auditorium', 500, 1, 6),
(2, 'Science Lab 1', 'Laboratory', 30, 1, 6),
(3, 'Basketball Court', 'classroom', 100, 1, 5),
(4, 'Computer Room 2', 'Computer Lab', 40, 1, 6),
(5, 'Seminar Room A', 'Classroom', 25, 1, 6),
(6, 'Chemistry Lab', 'Laboratory', 20, 1, 6),
(7, 'boo', 'ty', 44, NULL, 4),
(8, 'Lecture Hall', 'Room', 100, 1, 7),
(9, 'Projector', 'Equipment', 1, 1, 7),
(10, 'C001', 'classroom', 60, NULL, 5);

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
(3, 2025, 7, 40, 3),
(4, 2026, 9, 107, 4),
(5, 2026, 1, 115, 4);

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
(5, 'engglish for accademic purpose', 7, '459888', 4, '1', 2);

-- --------------------------------------------------------

--
-- Table structure for table `program`
--

CREATE TABLE `program` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `school_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program`
--

INSERT INTO `program` (`id`, `name`, `code`, `department_id`, `school_id`) VALUES
(1, 'bit', '112', NULL, 1),
(2, 'account program', '23', 2, 0),
(3, 'statistics in bach statistics in bachstatistics in statistics in bachstatistics in bachstatistics in bachstatistics in bachbachstatistics in bachstatistics in bachstatistic', 'S0B', 3, 0),
(4, 'statistics', '2333', 2, 0),
(6, 'wwdw', '546464', NULL, 10),
(7, 'Introduction to Programming', NULL, NULL, 10),
(8, 'Database Management Systems', NULL, NULL, 10),
(9, 'Web Development', NULL, NULL, 10),
(10, 'Software Engineering', NULL, NULL, 10);

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
(3, 'coeb', 4),
(9, 'economics', 1),
(10, 'business', 1);

-- --------------------------------------------------------

--
-- Table structure for table `site`
--

CREATE TABLE `site` (
  `id` int(11) NOT NULL,
  `name` varchar(40) NOT NULL,
  `campus` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site`
--

INSERT INTO `site` (`id`, `name`, `campus`) VALUES
(4, 'batima', 0),
(5, 'batima', 1),
(6, 'mpambat', 1),
(7, 'kiza', 1),
(9, 'batima-2', 1),
(10, 'batima-2', 1);

-- --------------------------------------------------------

--
-- Table structure for table `site_school`
--

CREATE TABLE `site_school` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_school`
--

INSERT INTO `site_school` (`id`, `site_id`, `school_id`) VALUES
(22, 10, 2),
(23, 10, 2),
(24, 5, 10),
(25, 5, 10),
(26, 9, 10),
(27, 9, 10);

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `id` int(11) NOT NULL,
  `regnumber` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`id`, `regnumber`, `email`, `password`, `group_id`) VALUES
(1, 221017990, 'cedrickhakuzimana@gmail.com', '$2y$10$OHgIjDoVJcEf1yeq3tDK..wX.P8ifd3UQzxiUfo/HqDLa971wNad2', 4),
(2, 221017999, 'cedrickhakuzimana@gmail.com', '$2y$10$UM9hFD5YtwLooYlOR35otupK3zOVUUbY5RPFSVGno4SARsRY51EBC', 6),
(3, 20231001, 'cedrickhakuzimana@gmail.com', '$2y$10$Uli9/Bx78oqmlBuz2F/83eP0CqPxtlXBuYbe9G.hcfYSm0uJ.KNxe', 4),
(4, 221017991, 'cedrickhakuzimana00@gmail.com', '$2y$10$c6XPEUpFZemQwkmbR/A6ne49mD9DmssB2pbNplrGlYWT1boW8EHNO', 4),
(5, 221017992, 'cedrickhakuzimana11@gmail.com', '$2y$10$a9AfUJegx6YPUFUgAntiF.cXWzwAGkm0/w6nlnjoGgFQGfl7dYA2C', 4);

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
(4, 'group 1', 30, 2),
(5, 'Goup1', 40, 3),
(6, 'group 2', 60, 2),
(7, 'group xx', 68, 4),
(8, 'grx', 26, 5),
(9, 'grp y', 89, 5),
(10, 'gf', 39, 4);

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
  `leader_lecturer_id` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `semester` varchar(10) DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `status` varchar(100) NOT NULL DEFAULT 'pending',
  `approvedby` int(11) NOT NULL,
  `createdby` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable`
--

INSERT INTO `timetable` (`id`, `module_id`, `leader_lecturer_id`, `facility_id`, `semester`, `academic_year_id`, `status`, `approvedby`, `createdby`) VALUES
(1, 1, 88, 6, '1', 2, 'Approved', 82, 91),
(6, 1, 87, 3, '1', 2, 'pending', 0, 91),
(7, 4, 89, 10, '1', 2, 'pending', 0, 80);

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
(2, 1, 3),
(3, 2, 9),
(4, 3, 9),
(5, 3, 8),
(6, 4, 9),
(7, 4, 8),
(8, 5, 9),
(9, 5, 8),
(10, 6, 9),
(11, 6, 8),
(12, 7, 4),
(13, 7, 6);

-- --------------------------------------------------------

--
-- Table structure for table `timetable_lecturers`
--

CREATE TABLE `timetable_lecturers` (
  `id` int(11) NOT NULL,
  `timetable_id` int(11) NOT NULL,
  `lect_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable_lecturers`
--

INSERT INTO `timetable_lecturers` (`id`, `timetable_id`, `lect_id`) VALUES
(1, 1, 87),
(2, 1, 90),
(3, 2, 87),
(4, 2, 90),
(5, 3, 87),
(6, 3, 90),
(7, 4, 87),
(8, 4, 90),
(9, 5, 84),
(10, 6, 84),
(11, 7, 88),
(12, 7, 83);

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
(1, 1, 'Monday', '08:00:00', '16:00:00'),
(3, 2, 'Thursday', '08:00:00', '12:00:00'),
(4, 3, 'Friday', '08:00:00', '18:00:00'),
(5, 4, 'Friday', '18:00:00', '19:00:00'),
(6, 5, 'Friday', '18:00:00', '19:00:00'),
(7, 6, 'Friday', '18:00:00', '19:00:00'),
(8, 6, 'Thursday', '12:00:00', '16:00:00'),
(9, 7, 'Friday', '12:00:00', '16:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `names` varchar(30) DEFAULT NULL,
  `email` varchar(30) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `image` varchar(200) DEFAULT NULL,
  `about` varchar(150) DEFAULT NULL,
  `role` varchar(30) DEFAULT NULL,
  `password` varchar(200) DEFAULT NULL,
  `active` int(11) DEFAULT NULL,
  `resetcode` int(11) DEFAULT NULL,
  `campus` int(11) DEFAULT NULL,
  `college` int(11) NOT NULL,
  `school` int(11) NOT NULL,
  `privileges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`privileges`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `names`, `email`, `phone`, `image`, `about`, `role`, `password`, `active`, `resetcode`, `campus`, `college`, `school`, `privileges`) VALUES
(1, 'cedrick', 'cedrickhakuzimana@gmail.com', '0783043021', 'assets/img/av.png', '', 'admin', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, 0, 0, 0, 0, NULL),
(70, 'huye', 'adminhuye@gmail.com', '0784366616', 'assets/img/av.png', NULL, 'campus_admin', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 1, 0, 0, NULL),
(71, 'huye_timetable', 'timetable_officer@gmail.com', '0784366611', 'assets/img/av.png', NULL, 'timetable_officer', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 1, 0, 1, NULL),
(78, 'cedrick hakuzimana1', 'cedrickhakuzimana75@gmail.com', '0784366610', 'assets/img/av.png', NULL, 'dean_office', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 1, 1, 2, NULL),
(80, 'dean business', 'dean@gmail.com', '078436661336', 'assets/img/av.png', NULL, 'dean_office', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 1, 1, 10, NULL),
(81, 'cedrick hakuzimana123', 'dean1@gmail.com', '0784366616', 'assets/img/av.png', NULL, 'dean_office', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 1, 4, 1, NULL),
(82, 'registrar', 'registrar@gmail.com', '0784366616', 'assets/img/av.png', NULL, 'registrar_office', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 1, 0, 0, NULL),
(83, 'John Doe', 'john.doe@example.com', '+1234567890', 'assets/img/av.png', NULL, 'lecturer', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 0, 0, 0, NULL),
(84, 'Jane Smith', 'jane.smith@example.com', '+0987654321', 'assets/img/av.png', NULL, 'lecturer', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 0, 0, 0, NULL),
(85, 'Robert Johnson', 'robert.johnson@example.com', '+1122334455', 'assets/img/av.png', NULL, 'lecturer', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 0, 0, 0, NULL),
(86, 'kalisa', 'kalisa@gmail.com', '+5566778899', 'assets/img/av.png', NULL, 'lecturer', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 0, 0, 0, NULL),
(87, 'silas', 'silas@gmail.com', '0784366617', 'assets/img/default-avatar.png', NULL, 'lecturer', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 65, 1, 1, 0, 0, NULL),
(88, 'byungura', 'byungura@gmail.com', '07843666109', 'assets/img/default-avatar.png', NULL, 'lecturer', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 0, 1, 2, 0, 0, NULL),
(89, 'bugingo', 'bugingo@gmail.com', '0784366616', 'assets/img/default-avatar.png', NULL, 'lecturer', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, 1, 1, 0, 0, NULL),
(90, 'rugema', 'rugema@gmail.com', '07843666111', 'assets/img/default-avatar.png', NULL, 'lecturer', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 0, 1, 1, 0, 0, NULL),
(91, 'benjamin', 'benjamin@gmail.com', '0785301081', 'assets/img/av.png', NULL, 'dean_office', '$2y$10$tmeBIJgUBb6U6ThAdBwhtuiFtmlNGKKmG0xsbIWXZSinvGzNxsD3i', 1, NULL, 1, 1, 1, NULL),
(92, 'digital', 'dtle@gmail.com', '900', 'assets/img/av.png', NULL, 'dtle', '$2y$10$VCKjRfrYEEbNBanA1KTszuIsljAlEy8rFMeNPVM4ocqbru6kBLgu6', 1, NULL, 1, 0, 0, NULL);

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
-- Indexes for table `site`
--
ALTER TABLE `site`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_school`
--
ALTER TABLE `site_school`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
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
-- Indexes for table `timetable_lecturers`
--
ALTER TABLE `timetable_lecturers`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `intake`
--
ALTER TABLE `intake`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `module`
--
ALTER TABLE `module`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `program`
--
ALTER TABLE `program`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `school`
--
ALTER TABLE `school`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `site`
--
ALTER TABLE `site`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `site_school`
--
ALTER TABLE `site_school`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `student_group`
--
ALTER TABLE `student_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `system`
--
ALTER TABLE `system`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `timetable_groups`
--
ALTER TABLE `timetable_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `timetable_lecturers`
--
ALTER TABLE `timetable_lecturers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `timetable_sessions`
--
ALTER TABLE `timetable_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
