-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 24, 2025 at 10:52 AM
-- Server version: 10.4.22-MariaDB
-- PHP Version: 8.0.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `talent_acquisition`
--
--
-- Create database if it doesn't exist
--
CREATE DATABASE IF NOT EXISTS `talent_acquisition` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `talent_acquisition`;
-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) DEFAULT NULL,
  `job_position_id` int(11) DEFAULT NULL,
  `resume_filename` varchar(255) NOT NULL,
  `resume_path` varchar(500) NOT NULL,
  `api_response` text DEFAULT NULL,
  `extracted_skills` text DEFAULT NULL,
  `extracted_experience` text DEFAULT NULL,
  `extracted_education` text DEFAULT NULL,
  `extracted_contact` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extracted_contact`)),
  `match_percentage` decimal(5,2) DEFAULT 0.00,
  `api_processing_status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `api_error_message` text DEFAULT NULL,
  `status` enum('pending','selected','rejected','waiting_interview','interview_completed','offer_sent','offer_accepted','offer_rejected','hired') DEFAULT 'pending',
  `hr_notes` text DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `candidate_id`, `job_position_id`, `resume_filename`, `resume_path`, `api_response`, `extracted_skills`, `extracted_experience`, `extracted_education`, `extracted_contact`, `match_percentage`, `api_processing_status`, `api_error_message`, `status`, `hr_notes`, `applied_at`, `updated_at`) VALUES
(3, 3, 1, 'for_hackathon_sample_resume.pdf', 'uploads/resumes/68a55b23eca82_1755667235.pdf', '{\"education\":[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}],\"languages\":[\"Mandarin\",\"English\",\"Malay\"],\"personal_info\":{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"https:\\/\\/github.com\\/johndoe\",\"linkedin\":\"https:\\/\\/www.linkedin.com\\/in\\/john-doe\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"},\"skills\":[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"],\"work_experience\":[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]}', '[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"]', '[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]', '[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}]', '{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"https:\\/\\/github.com\\/johndoe\",\"linkedin\":\"https:\\/\\/www.linkedin.com\\/in\\/john-doe\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"}', '85.50', 'completed', NULL, 'pending', NULL, '2025-08-20 05:20:50', '2025-08-23 16:53:47'),
(4, 3, 3, 'v2.0_simplied_for_hackathon_sample_resume 副本.pdf', 'uploads/resumes/68a55c42c8354_1755667522.pdf', '{\"education\":[{\"description\":\"cgpa 4.0, Dean\'s List, Semester 3, 2021\\/2022, Dean\'s List, Semester 1, 2022\\/2023\",\"end_date\":\"2023\",\"institute\":\"uni abc\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"degree\"},{\"description\":\"cgpa 3.97\",\"end_date\":\"2023\",\"institute\":\"uni def\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}],\"languages\":[\"Mandarin\",\"English\",\"Malay\"],\"personal_info\":{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"https:\\/\\/github.com\\/johndoe\",\"linkedin\":\"https:\\/\\/www.linkedin.com\\/in\\/john-doe\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"},\"skills\":[\"tech skills 1\",\"tech skills 2\",\"tech skills 3\",\"tech skills 4\"],\"work_experience\":[{\"company\":\"company abc\",\"description\":\"did this that, balhblah, did this\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"intern\"}]}', '[\"tech skills 1\",\"tech skills 2\",\"tech skills 3\",\"tech skills 4\"]', '[{\"company\":\"company abc\",\"description\":\"did this that, balhblah, did this\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"intern\"}]', '[{\"description\":\"cgpa 4.0, Dean\'s List, Semester 3, 2021\\/2022, Dean\'s List, Semester 1, 2022\\/2023\",\"end_date\":\"2023\",\"institute\":\"uni abc\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"degree\"},{\"description\":\"cgpa 3.97\",\"end_date\":\"2023\",\"institute\":\"uni def\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}]', '{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"https:\\/\\/github.com\\/johndoe\",\"linkedin\":\"https:\\/\\/www.linkedin.com\\/in\\/john-doe\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"}', '72.30', 'completed', NULL, 'rejected', '', '2025-08-20 05:25:40', '2025-08-23 16:53:47');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_faq`
--

CREATE TABLE `chatbot_faq` (
  `id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `chatbot_faq`
--

INSERT INTO `chatbot_faq` (`id`, `question`, `answer`, `category`, `keywords`, `is_active`, `created_at`) VALUES
(1, 'What are my working hours?', 'Standard working hours are 9 AM to 6 PM, Monday to Friday. Flexible timing available after probation period.', 'hr_policy', 'working hours, timing, schedule', 1, '2025-08-18 15:12:09'),
(2, 'How do I reset my password?', 'Contact IT support at it@company.com or call extension 1234 to reset your password.', 'technical', 'password reset, login issue, access', 1, '2025-08-18 15:12:09'),
(3, 'When will I get my laptop?', 'IT equipment including laptop will be provided within 2-3 business days of joining.', 'onboarding', 'laptop, equipment, IT setup', 1, '2025-08-18 15:12:09'),
(4, 'What is the dress code?', 'Business casual is our standard dress code. Formal wear required for client meetings.', 'hr_policy', 'dress code, attire, clothing', 1, '2025-08-18 15:12:09'),
(5, 'How do I submit my timesheet?', 'Use the company portal to submit weekly timesheets by Friday 5 PM each week.', 'hr_policy', 'timesheet, attendance, hours', 1, '2025-08-18 15:12:09'),
(6, 'Who is my reporting manager?', 'Your reporting manager information will be provided by HR during your first week. Please contact HR at hr@haircare2u.my for this information.', 'onboarding', 'manager, supervisor, reporting', 1, '2025-08-18 15:12:09'),
(7, 'How do I access the training portal?', 'You can access all training modules through the Employee Portal under "Training Modules" section.', 'onboarding', 'training, learning, modules', 1, '2025-08-18 15:12:09'),
(8, 'What documents do I need to submit?', 'Required documents include Employment Contract, Personal Information Form, Bank Details, ID Copy, and Educational Certificates.', 'onboarding', 'documents, paperwork, requirements', 1, '2025-08-18 15:12:09'),
(9, 'How long does document review take?', 'Document review typically takes 2-3 business days. You will be notified once your documents are approved or if any changes are needed.', 'onboarding', 'document review, approval time', 1, '2025-08-18 15:12:09'),
(10, 'What is the company leave policy?', 'Full-time employees are entitled to 14 days annual leave, 14 days medical leave, and public holidays. Leave requests should be submitted through the HR portal.', 'hr_policy', 'leave, vacation, sick days', 1, '2025-08-18 15:12:09');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_settings`
--

CREATE TABLE `chatbot_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `chatbot_settings`
--

INSERT INTO `chatbot_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'chatbot_enabled', '1', 'Enable/disable chatbot functionality', '2025-08-18 15:12:09'),
(2, 'api_provider', 'static', 'Chatbot provider: static, openai, dialogflow', '2025-08-18 15:12:09'),
(3, 'api_key', '', 'API key for external chatbot service', '2025-08-18 15:12:09'),
(4, 'default_response', 'I apologize, but I could not understand your question. Please contact HR for further assistance.', 'Default response when no match found', '2025-08-18 15:12:09'),
(5, 'greeting_message', 'Hello! I am here to help you with onboarding questions. How can I assist you today?', 'Initial greeting message', '2025-08-18 15:12:09');

-- --------------------------------------------------------

--
-- Table structure for table `chat_conversations`
--

CREATE TABLE `chat_conversations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `response` text NOT NULL,
  `message_type` enum('user','bot') NOT NULL,
  `api_response_time` decimal(8,3) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `status` enum('pending','submitted','approved','rejected') DEFAULT 'pending',
  `is_required` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewer_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  UNIQUE KEY `unique_employee_document` (`employee_id`, `document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employee_documents`
--

INSERT INTO `employee_documents` (`id`, `employee_id`, `document_name`, `document_type`, `file_path`, `file_size`, `status`, `is_required`, `description`, `uploaded_at`, `reviewed_at`, `reviewer_notes`) VALUES
(1, 9, 'Employment Contract', 'contract', NULL, NULL, 'pending', 1, 'Your employment contract and terms of service', '2025-08-24 10:00:00', NULL, NULL),
(2, 9, 'Personal Information Form', 'personal_form', 'uploads/documents/employee_9/1756036140_personal_info.pdf', 245760, 'submitted', 1, 'Complete personal details and emergency contacts', '2025-08-24 10:15:00', NULL, NULL),
(3, 9, 'Bank Details Form', 'bank_form', 'uploads/documents/employee_9/1756036140_bank_details.pdf', 187392, 'approved', 1, 'Banking information for salary processing', '2025-08-24 10:20:00', '2025-08-24 11:30:00', 'Document approved - bank details verified.'),
(4, 9, 'ID Copy', 'identification', NULL, NULL, 'pending', 1, 'Copy of your identification document (IC/Passport)', '2025-08-24 10:00:00', NULL, NULL),
(5, 9, 'Educational Certificates', 'education', NULL, NULL, 'pending', 1, 'Copies of your educational qualifications', '2025-08-24 10:00:00', NULL, NULL),
(6, 9, 'Medical Certificate', 'medical', NULL, NULL, 'pending', 0, 'Health clearance certificate', '2025-08-24 10:00:00', NULL, NULL),
(7, 9, 'Previous Employment Letter', 'employment_history', NULL, NULL, 'pending', 0, 'Letter from previous employer (if applicable)', '2025-08-24 10:00:00', NULL, NULL),
(8, 8, 'Employment Contract', 'contract', NULL, NULL, 'pending', 1, 'Your employment contract and terms of service', '2025-08-24 10:00:00', NULL, NULL),
(9, 8, 'Personal Information Form', 'personal_form', NULL, NULL, 'pending', 1, 'Complete personal details and emergency contacts', '2025-08-24 10:00:00', NULL, NULL),
(10, 8, 'Bank Details Form', 'bank_form', NULL, NULL, 'pending', 1, 'Banking information for salary processing', '2025-08-24 10:00:00', NULL, NULL),
(11, 8, 'ID Copy', 'identification', NULL, NULL, 'pending', 1, 'Copy of your identification document (IC/Passport)', '2025-08-24 10:00:00', NULL, NULL),
(12, 8, 'Educational Certificates', 'education', NULL, NULL, 'pending', 1, 'Copies of your educational qualifications', '2025-08-24 10:00:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee_onboarding`
--

CREATE TABLE `employee_onboarding` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employee_onboarding`
--

INSERT INTO `employee_onboarding` (`id`, `employee_id`, `task_id`, `status`, `completed_at`, `notes`) VALUES
(1, 9, 1, 'completed', '2025-08-24 08:44:23', 'Completed personal information form with all required details.'),
(2, 9, 2, 'completed', '2025-08-24 08:45:17', 'Laptop and access credentials received from IT department.'),
(3, 9, 3, 'completed', '2025-08-24 08:45:21', 'Attended company orientation session and understood company policies.'),
(4, 9, 4, 'completed', '2025-08-24 08:45:24', 'Met with team members and got introduction to my role and responsibilities.'),
(5, 9, 5, 'completed', '2025-08-24 08:45:27', 'Completed cybersecurity training and understood security protocols.'),
(6, 9, 6, 'completed', '2025-08-24 08:45:29', 'Development environment setup completed with all required tools and software.'),
(7, 8, 1, 'completed', '2025-08-24 09:30:00', 'Personal information form completed'),
(8, 8, 2, 'in_progress', NULL, NULL),
(9, 8, 3, 'pending', NULL, NULL),
(10, 8, 4, 'pending', NULL, NULL),
(11, 10, 1, 'completed', '2025-08-24 10:15:00', 'Form completed successfully'),
(12, 10, 2, 'completed', '2025-08-24 10:30:00', 'Equipment received'),
(13, 10, 3, 'in_progress', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee_training`
--

CREATE TABLE `employee_training` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `status` enum('not_started','in_progress','completed') DEFAULT 'not_started',
  `progress_percentage` int(11) DEFAULT 0,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employee_training`
--

INSERT INTO `employee_training` (`id`, `employee_id`, `module_id`, `status`, `progress_percentage`, `started_at`, `completed_at`) VALUES
(1, 9, 1, 'completed', 100, '2025-08-24 08:00:00', '2025-08-24 09:30:00'),
(2, 9, 2, 'completed', 100, '2025-08-24 08:30:00', '2025-08-24 09:00:00'),
(3, 9, 3, 'in_progress', 75, '2025-08-24 10:00:00', NULL),
(4, 9, 4, 'in_progress', 50, '2025-08-24 11:00:00', NULL),
(5, 8, 1, 'completed', 100, '2025-08-24 08:00:00', '2025-08-24 09:00:00'),
(6, 8, 2, 'in_progress', 60, '2025-08-24 09:30:00', NULL),
(7, 10, 1, 'in_progress', 25, '2025-08-24 10:00:00', NULL),
(8, 10, 2, 'not_started', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `job_positions`
--

CREATE TABLE `job_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `department` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `required_skills` text NOT NULL,
  `experience_level` enum('entry','mid','senior') DEFAULT 'mid',
  `status` enum('active','closed') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `job_positions`
--

INSERT INTO `job_positions` (`id`, `title`, `department`, `description`, `required_skills`, `experience_level`, `status`, `created_by`, `created_at`) VALUES
(1, 'Software Developer', 'IT', 'Full-stack developer position', 'PHP,JavaScript,MySQL,HTML,CSS', 'mid', 'active', 1, '2025-08-18 15:11:16'),
(2, 'Marketing Specialist', 'Sales & Marketing', 'Digital marketing role', 'SEO,Social Media,Analytics,Content Writing', 'entry', 'active', 1, '2025-08-18 15:11:16'),
(3, 'Project Manager', 'IT', 'Technical project management', 'Agile,Scrum,Leadership,Communication', 'senior', 'active', 1, '2025-08-18 15:11:16'),
(4, 'Product Specialist', 'Sales & Marketing', 'Responsible for promoting and demonstrating haircare products to clients and partners.', 'Product Knowledge,Sales Techniques,Presentation Skills,Customer Engagement', 'entry', 'active', 1, '2025-08-23 15:49:43'),
(5, 'Digital Marketer', 'Sales & Marketing', 'Manage online marketing campaigns for haircare products.', 'SEO,Social Media Ads,Content Creation,Analytics', 'mid', 'active', 1, '2025-08-23 15:49:43'),
(6, 'Brand Ambassador', 'Sales & Marketing', 'Represent the company brand at events and campaigns.', 'Public Speaking,Influencing Skills,Networking,Product Knowledge', 'entry', 'active', 1, '2025-08-23 15:49:43'),
(7, 'Customer Support Representative', 'Customer Service', 'Assist customers with inquiries, complaints, and product guidance.', 'Communication,Problem Solving,CRM Tools,Patience', 'entry', 'active', 1, '2025-08-23 15:49:43'),
(8, 'Beauty Consultant', 'Customer Service', 'Provide personalized haircare consultations to customers.', 'Haircare Knowledge,Customer Service,Product Recommendation,Sales Skills', 'mid', 'active', 1, '2025-08-23 15:49:43'),
(9, 'Logistics Coordinator', 'Operations', 'Manage supply chain, deliveries, and shipping of haircare products.', 'Logistics Management,Excel,Inventory Tracking,Vendor Coordination', 'mid', 'active', 1, '2025-08-23 15:49:43'),
(10, 'Inventory Specialist', 'Operations', 'Monitor and maintain stock levels for haircare products.', 'Inventory Management,Attention to Detail,ERP Systems,Excel', 'entry', 'active', 1, '2025-08-23 15:49:43'),
(11, 'Quality Control Officer', 'Operations', 'Ensure haircare products meet quality and safety standards.', 'Quality Assurance,Detail Orientation,Analytical Thinking,Reporting', 'mid', 'active', 1, '2025-08-23 15:49:43'),
(12, 'E-commerce Developer', 'IT', 'Develop and maintain the company e-commerce website.', 'PHP,JavaScript,MySQL,Shopify/WordPress', 'mid', 'active', 1, '2025-08-23 15:49:43'),
(13, 'Data Analyst', 'IT', 'Analyze sales and customer data to improve business decisions.', 'SQL,Excel,Data Visualization,Python/R', 'mid', 'active', 1, '2025-08-23 15:49:43'),
(14, 'Team Leader', 'Management', 'Lead a small team to achieve sales and operational targets.', 'Leadership,Communication,Reporting,Coaching', 'mid', 'active', 1, '2025-08-23 15:49:43'),
(15, 'Department Head', 'Management', 'Oversee departmental operations and strategy execution.', 'Strategic Planning,Leadership,Budget Management,Decision Making', 'senior', 'active', 1, '2025-08-23 15:49:43'),
(16, 'Regional Manager', 'Management', 'Manage business operations across multiple regions.', 'Leadership,Market Knowledge,Negotiation,People Management', 'senior', 'active', 1, '2025-08-23 15:49:43');
--
-- Indexes for table `job_positions`
--
ALTER TABLE `job_positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- AUTO_INCREMENT for table `job_positions`
--
ALTER TABLE `job_positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
-- --------------------------------------------------------

--
-- Table structure for table `onboarding_tasks`
--

CREATE TABLE `onboarding_tasks` (
  `id` int(11) NOT NULL,
  `task_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `order_sequence` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `onboarding_tasks`
--

INSERT INTO `onboarding_tasks` (`id`, `task_name`, `description`, `department`, `is_mandatory`, `order_sequence`, `created_at`) VALUES
(1, 'Complete Personal Information Form', 'Fill out personal details and emergency contacts', 'ALL', 1, 1, '2025-08-18 15:11:16'),
(2, 'IT Equipment Setup', 'Receive laptop, access cards, and accounts', 'ALL', 1, 2, '2025-08-18 15:11:16'),
(3, 'Company Orientation', 'Attend company culture and policy session', 'ALL', 1, 3, '2025-08-18 15:11:16'),
(4, 'Department Introduction', 'Meet team members and understand role', 'ALL', 1, 4, '2025-08-18 15:11:16'),
(5, 'Security Training', 'Complete cybersecurity awareness training', 'IT', 1, 5, '2025-08-18 15:11:16'),
(6, 'Development Environment Setup', 'Install required software and tools', 'IT', 1, 6, '2025-08-18 15:11:16'),
(7, 'Product Knowledge Training', 'Learn about company products and services', 'Sales & Marketing', 1, 5, '2025-08-24 12:00:00'),
(8, 'Customer Service Training', 'Learn customer interaction protocols', 'Customer Service', 1, 5, '2025-08-24 12:00:00'),
(9, 'Operations Training', 'Understanding operational procedures and workflows', 'Operations', 1, 5, '2025-08-24 12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `parsed_resumes`
--

CREATE TABLE `parsed_resumes` (
  `id` int(11) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `linkedin` varchar(500) DEFAULT NULL,
  `github` varchar(500) DEFAULT NULL,
  `work_experience` longtext DEFAULT NULL,
  `education` longtext DEFAULT NULL,
  `languages` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `certificates` text DEFAULT NULL,
  `raw_data` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `parsed_resumes`
--

INSERT INTO `parsed_resumes` (`id`, `original_filename`, `name`, `email`, `phone`, `address`, `linkedin`, `github`, `work_experience`, `education`, `languages`, `skills`, `certificates`, `raw_data`, `created_at`, `updated_at`) VALUES
(1, 'sample_resume.pdf', 'KEH BAN NING', 'banningkeh@gmail.com', '0172230741', 'Rembau, Negeri Sembilan.', '', '', '[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]', '[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}]', '[\"Mandarin\",\"English\",\"Malay\"]', '[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"]', '[]', '{\"education\":[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}],\"languages\":[\"Mandarin\",\"English\",\"Malay\"],\"personal_info\":{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"\",\"linkedin\":\"\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"},\"skills\":[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"],\"work_experience\":[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]}', '2025-08-19 12:33:19', '2025-08-19 12:33:19'),
(2, 'sample_resume.pdf', 'KEH BAN NING', 'banningkeh@gmail.com', '0172230741', 'Rembau, Negeri Sembilan.', '', '', '[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]', '[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}]', '[\"Mandarin\",\"English\",\"Malay\"]', '[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"]', '[]', '{\"education\":[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}],\"languages\":[\"Mandarin\",\"English\",\"Malay\"],\"personal_info\":{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"\",\"linkedin\":\"\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"},\"skills\":[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"],\"work_experience\":[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]}', '2025-08-19 12:33:23', '2025-08-19 12:33:23'),
(3, 'sample_resume.pdf', 'KEH BAN NING', 'banningkeh@gmail.com', '0172230741', 'Rembau, Negeri Sembilan.', '', '', '[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]', '[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}]', '[\"Mandarin\",\"English\",\"Malay\"]', '[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"]', '[]', '{\"education\":[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahung Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}],\"languages\":[\"Mandarin\",\"English\",\"Malay\"],\"personal_info\":{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"\",\"linkedin\":\"\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"},\"skills\":[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"],\"work_experience\":[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]}', '2025-08-19 12:44:40', '2025-08-19 12:44:40'),
(4, 'sample_resume.pdf', 'KEH BAN NING', 'banningkeh@gmail.com', '0172230741', 'Rembau, Negeri Sembilan.', '', '', '[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]', '[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}]', '[\"Mandarin\",\"English\",\"Malay\"]', '[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"]', '[]', '{\"education\":[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}],\"languages\":[\"Mandarin\",\"English\",\"Malay\"],\"personal_info\":{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"\",\"linkedin\":\"\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"},\"skills\":[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"],\"work_experience\":[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]}', '2025-08-19 12:45:37', '2025-08-19 12:45:37'),
(5, 'sample_resume.pdf', 'KEH BAN NING', 'banningkeh@gmail.com', '0172230741', 'Rembau, Negeri Sembilan.', '', '', '[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]', '[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}]', '[\"Mandarin\",\"English\",\"Malay\"]', '[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"]', '[]', '{\"education\":[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}],\"languages\":[\"Mandarin\",\"English\",\"Malay\"],\"personal_info\":{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"\",\"linkedin\":\"\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"},\"skills\":[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"],\"work_experience\":[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]}', '2025-08-19 12:46:33', '2025-08-19 12:46:33'),
(7, 'for_hackathon_sample_resume.pdf', 'KEH BAN NING', 'banningkeh@gmail.com', '0172230741', 'Rembau, Negeri Sembilan.', 'https://www.linkedin.com/in/john-doe', 'https://github.com/johndoe', '[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]', '[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}]', '[\"Mandarin\",\"English\",\"Malay\"]', '[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"]', '[]', '{\"education\":[{\"description\":\"Current CGPA: 3.50\",\"end_date\":\"2026\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2023\",\"title\":\"Bachelor of Computer Science (Software Engineering) With Honours\"},{\"description\":\"3.71 CGPA. Dean\'s List, Semester 1, 2021\\/2022. Dean\'s List, Semester 3, 2021\\/2022. Dean\'s List, Semester 2, 2021\\/2022. Dean\'s List, Semester 1, 2022\\/2023.\",\"end_date\":\"2023\",\"institute\":\"Universiti Malaysia Pahang Al-Sultan Abdullah\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}],\"languages\":[\"Mandarin\",\"English\",\"Malay\"],\"personal_info\":{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"https:\\/\\/github.com\\/johndoe\",\"linkedin\":\"https:\\/\\/www.linkedin.com\\/in\\/john-doe\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"},\"skills\":[\"HTML\\/CSS\",\"JavaScript\",\"PHP\",\"Java\",\"C\\/C++\",\"Dart\",\"SQL\",\"Flutter\",\"Firebase\",\"Laravel\",\"Bootstrap\",\"MySQL\",\"Github\",\"Android Studio\",\"VS Code\",\"Figma\",\"XAMPP\",\"Postman\",\"Notion\",\"Microsoft Word\",\"Microsoft Excel\",\"Google Workspace\",\"Discord\",\"Teams\"],\"work_experience\":[{\"company\":\"Adaptive Netpoleon Malaysia Sdn Bhd\",\"description\":\"Documented technical issues and solutions to enable tracking history and maintain accurate logs. Improved team performance by collaborating with coworkers and engineers to develop best practices for issue resolution. Acquired comprehensive knowledge of the company\'s distributed products and services. Mastered the customer communication system for addressing technical issues. Supported the validation process for support case contracts. Provided initial responses and follow-ups on support cases, collaborating closely with engineers until resolution.\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"Technical Helpdesk Support Intern\"}]}', '2025-08-20 05:20:50', '2025-08-20 05:20:50'),
(8, 'v2.0_simplied_for_hackathon_sample_resume 副本.pdf', 'KEH BAN NING', 'banningkeh@gmail.com', '0172230741', 'Rembau, Negeri Sembilan.', 'https://www.linkedin.com/in/john-doe', 'https://github.com/johndoe', '[{\"company\":\"company abc\",\"description\":\"did this that, balhblah, did this\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"intern\"}]', '[{\"description\":\"cgpa 4.0, Dean\'s List, Semester 3, 2021\\/2022, Dean\'s List, Semester 1, 2022\\/2023\",\"end_date\":\"2023\",\"institute\":\"uni abc\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"degree\"},{\"description\":\"cgpa 3.97\",\"end_date\":\"2023\",\"institute\":\"uni def\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}]', '[\"Mandarin\",\"English\",\"Malay\"]', '[\"tech skills 1\",\"tech skills 2\",\"tech skills 3\",\"tech skills 4\"]', '[]', '{\"education\":[{\"description\":\"cgpa 4.0, Dean\'s List, Semester 3, 2021\\/2022, Dean\'s List, Semester 1, 2022\\/2023\",\"end_date\":\"2023\",\"institute\":\"uni abc\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"degree\"},{\"description\":\"cgpa 3.97\",\"end_date\":\"2023\",\"institute\":\"uni def\",\"location\":\"\",\"start_date\":\"2021\",\"title\":\"Diploma in Computer Science\"}],\"languages\":[\"Mandarin\",\"English\",\"Malay\"],\"personal_info\":{\"address\":\"Rembau, Negeri Sembilan.\",\"email\":\"banningkeh@gmail.com\",\"github\":\"https:\\/\\/github.com\\/johndoe\",\"linkedin\":\"https:\\/\\/www.linkedin.com\\/in\\/john-doe\",\"name\":\"KEH BAN NING\",\"phone\":\"0172230741\"},\"skills\":[\"tech skills 1\",\"tech skills 2\",\"tech skills 3\",\"tech skills 4\"],\"work_experience\":[{\"company\":\"company abc\",\"description\":\"did this that, balhblah, did this\",\"end_date\":\"2023-08\",\"start_date\":\"2023-03\",\"title\":\"intern\"}]}', '2025-08-20 05:25:40', '2025-08-20 05:25:40');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'general',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `description` text NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `employee_id`, `subject`, `category`, `priority`, `status`, `description`, `created_at`, `updated_at`, `resolved_at`) VALUES
(1, 9, 'Unable to access company email', 'technical', 'high', 'resolved', 'I am unable to access my company email account. Getting authentication error when trying to login through Outlook.', '2025-08-24 09:00:00', '2025-08-24 10:30:00', '2025-08-24 10:30:00'),
(2, 9, 'Question about leave policy', 'hr_policy', 'medium', 'open', 'I would like to understand the process for requesting annual leave and what the advance notice requirements are.', '2025-08-24 11:00:00', '2025-08-24 11:00:00', NULL),
(3, 8, 'Laptop setup issues', 'technical', 'medium', 'in_progress', 'Having trouble installing the required development software on my laptop. Some installations are failing.', '2025-08-24 08:30:00', '2025-08-24 09:15:00', NULL),
(4, 10, 'Onboarding task clarification', 'onboarding', 'low', 'resolved', 'Need clarification on what exactly needs to be completed for the "Department Introduction" task.', '2025-08-24 10:45:00', '2025-08-24 11:20:00', '2025-08-24 11:20:00'),
(5, 9, 'Training module not loading', 'technical', 'medium', 'open', 'The PHP Development Basics training module is not loading properly. Page shows blank after clicking continue.', '2025-08-24 12:00:00', '2025-08-24 12:00:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `training_modules`
--

CREATE TABLE `training_modules` (
  `id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `content_url` varchar(500) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `duration_hours` int(11) DEFAULT 1,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `training_modules`
--

INSERT INTO `training_modules` (`id`, `module_name`, `description`, `content_url`, `department`, `duration_hours`, `is_mandatory`, `created_at`) VALUES
(1, 'Company Policies', 'Learn about company rules and regulations', 'https://learning.haircare2u.my/policies', 'ALL', 2, 1, '2025-08-18 15:11:16'),
(2, 'Cybersecurity Awareness', 'Security best practices and protocols', 'https://learning.haircare2u.my/security', 'ALL', 1, 1, '2025-08-18 15:11:16'),
(3, 'PHP Development Basics', 'Introduction to PHP programming', 'https://learning.haircare2u.my/php-basics', 'IT', 8, 1, '2025-08-18 15:11:16'),
(4, 'Database Management', 'MySQL fundamentals', 'https://learning.haircare2u.my/mysql', 'IT', 6, 1, '2025-08-18 15:11:16'),
(5, 'Marketing Fundamentals', 'Basic marketing principles', 'https://learning.haircare2u.my/marketing', 'Sales & Marketing', 4, 1, '2025-08-18 15:11:16'),
(6, 'Customer Service Excellence', 'Providing outstanding customer service', 'https://learning.haircare2u.my/customer-service', 'Customer Service', 3, 1, '2025-08-24 12:00:00'),
(7, 'Product Knowledge Training', 'Understanding our haircare products', 'https://learning.haircare2u.my/products', 'Sales & Marketing', 4, 1, '2025-08-24 12:00:00'),
(8, 'Quality Control Procedures', 'Standards and testing procedures', 'https://learning.haircare2u.my/quality', 'Operations', 3, 1, '2025-08-24 12:00:00'),
(9, 'Communication Skills', 'Effective workplace communication', NULL, 'ALL', 2, 0, '2025-08-24 12:00:00'),
(10, 'Time Management', 'Productivity and time management techniques', NULL, 'ALL', 2, 0, '2025-08-24 12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('hr','candidate','employee') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `job_position_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `status`, `created_at`, `updated_at`, `reset_token`, `reset_expires`, `job_position_id`) VALUES
(1, 'hr_admin', 'hr@haircare2u.my', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'HR Administrator', 'hr', 'active', '2025-08-18 15:11:16', '2025-08-24 12:00:00', NULL, NULL, NULL),
(3, 'alice123', 'alice@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C.', 'Alice Tan', 'candidate', 'active', '2025-08-18 16:04:58', '2025-08-20 05:31:29', NULL, NULL, NULL),
(4, 'azim_m', 'azim.muhd@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Muhd Azim Bin Ali', 'candidate', 'active', '2025-08-18 16:04:58', '2025-08-18 16:14:08', NULL, NULL, NULL),
(5, 'bella_lee', 'bella.lee@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Bella Lee', 'candidate', 'active', '2025-08-18 16:04:58', '2025-08-18 16:14:08', NULL, NULL, NULL),
(6, 'carlos_s', 'carlos.smith@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Carlos Smith', 'candidate', 'active', '2025-08-18 16:04:58', '2025-08-18 16:14:08', NULL, NULL, NULL),
(7, 'diana_w', 'diana.wong@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Diana Wong', 'candidate', 'active', '2025-08-18 16:04:58', '2025-08-18 16:14:08', NULL, NULL, NULL),
(8, 'siti_r', 'siti.rahman@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Siti Rahman', 'employee', 'active', '2025-08-18 16:05:04', '2025-08-24 12:00:00', NULL, NULL, 2),
(9, 'john_d', 'john.doe@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'John Doe', 'employee', 'active', '2025-08-18 16:05:04', '2025-08-24 08:49:58', NULL, NULL, 1),
(10, 'kevin_l', 'kevin.lim@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Kevin Lim', 'employee', 'active', '2025-08-18 16:05:04', '2025-08-24 12:00:00', NULL, NULL, 9),
(11, 'linda_t', 'linda.tan@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Linda Tan', 'employee', 'active', '2025-08-18 16:05:04', '2025-08-24 12:00:00', NULL, NULL, 7),
(12, 'michael_c', 'michael.choo@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Michael Choo', 'employee', 'inactive', '2025-08-18 16:05:04', '2025-08-18 16:14:08', NULL, NULL, NULL),
(14, 'mn_n', 'banning1212@gmail.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Lee shy woei', 'candidate', 'active', '2025-08-21 07:03:13', '2025-08-21 07:21:09', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_id` (`candidate_id`),
  ADD KEY `job_position_id` (`job_position_id`);

--
-- Indexes for table `chatbot_faq`
--
ALTER TABLE `chatbot_faq`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chatbot_settings`
--
ALTER TABLE `chatbot_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `unique_employee_document` (`employee_id`, `document_type`);

--
-- Indexes for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_task` (`employee_id`,`task_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `employee_training`
--
ALTER TABLE `employee_training`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_module` (`employee_id`,`module_id`),
  ADD KEY `module_id` (`module_id`);

--
-- Indexes for table `job_positions`
--
ALTER TABLE `job_positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `onboarding_tasks`
--
ALTER TABLE `onboarding_tasks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parsed_resumes`
--
ALTER TABLE `parsed_resumes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `training_modules`
--
ALTER TABLE `training_modules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chatbot_faq`
--
ALTER TABLE `chatbot_faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `chatbot_settings`
--
ALTER TABLE `chatbot_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `employee_training`
--
ALTER TABLE `employee_training`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `job_positions`
--
ALTER TABLE `job_positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `onboarding_tasks`
--
ALTER TABLE `onboarding_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `parsed_resumes`
--
ALTER TABLE `parsed_resumes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `training_modules`
--
ALTER TABLE `training_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`job_position_id`) REFERENCES `job_positions` (`id`);

--
-- Constraints for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD CONSTRAINT `chat_conversations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  ADD CONSTRAINT `employee_onboarding_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `employee_onboarding_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `onboarding_tasks` (`id`);

--
-- Constraints for table `employee_training`
--
ALTER TABLE `employee_training`
  ADD CONSTRAINT `employee_training_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `employee_training_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`);

--
-- Constraints for table `job_positions`
--
ALTER TABLE `job_positions`
  ADD CONSTRAINT `job_positions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;