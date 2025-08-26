-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 26, 2025 at 07:43 PM
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
CREATE DATABASE IF NOT EXISTS `talent_acquisition` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `talent_acquisition`;

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `hire_candidate` (IN `p_application_id` INT)  BEGIN
    DECLARE v_candidate_id INT;
    DECLARE v_job_position_id INT;
    DECLARE v_department VARCHAR(50);
    DECLARE v_job_title VARCHAR(100);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Get application details
    SELECT candidate_id, job_position_id INTO v_candidate_id, v_job_position_id
    FROM applications 
    WHERE id = p_application_id;
    
    -- Get job details
    SELECT department, title INTO v_department, v_job_title
    FROM job_positions 
    WHERE id = v_job_position_id;
    
    -- Update application status
    UPDATE applications 
    SET status = 'hired', updated_at = CURRENT_TIMESTAMP 
    WHERE id = p_application_id;
    
    -- Update user role and department
    UPDATE users 
    SET 
        role = 'employee',
        department = v_department,
        job_position_id = v_job_position_id,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = v_candidate_id;
    
    -- Create onboarding tasks
    INSERT IGNORE INTO employee_onboarding (employee_id, task_id, status)
    SELECT v_candidate_id, ot.id, 'pending'
    FROM onboarding_tasks ot
    WHERE ot.department = v_department OR ot.department = 'ALL';
    
    -- Create training assignments
    INSERT IGNORE INTO employee_training (employee_id, module_id, status, progress_percentage)
    SELECT v_candidate_id, tm.id, 'not_started', 0
    FROM training_modules tm
    WHERE tm.department = v_department OR tm.department = 'ALL';
    
    -- Create employee documents
    INSERT IGNORE INTO employee_documents (employee_id, document_name, document_type, status, is_required, description)
    VALUES 
    (v_candidate_id, 'Employment Contract', 'contract', 'pending', 1, 'Your employment contract and terms of service'),
    (v_candidate_id, 'Personal Information Form', 'personal_form', 'pending', 1, 'Complete personal details and emergency contacts'),
    (v_candidate_id, 'Bank Details Form', 'bank_form', 'pending', 1, 'Banking information for salary processing'),
    (v_candidate_id, 'ID Copy', 'identification', 'pending', 1, 'Copy of your identification document (IC/Passport)'),
    (v_candidate_id, 'Educational Certificates', 'education', 'pending', 1, 'Copies of your educational qualifications');
    
    COMMIT;
    
END$$

DELIMITER ;

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
-- Triggers `applications`
--
DELIMITER $$
CREATE TRIGGER `update_user_role_on_hire` AFTER UPDATE ON `applications` FOR EACH ROW BEGIN
    -- Check if status changed to 'hired'
    IF NEW.status = 'hired' AND OLD.status != 'hired' THEN
        -- Get job position details
        SET @job_department = NULL;
        SET @job_title = NULL;
        
        SELECT department, title INTO @job_department, @job_title
        FROM job_positions 
        WHERE id = NEW.job_position_id;
        
        -- Update user role and department
        UPDATE users 
        SET 
            role = 'employee',
            department = @job_department,
            job_position_id = NEW.job_position_id,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.candidate_id;
        
        -- Create initial onboarding tasks for the new employee
        INSERT INTO employee_onboarding (employee_id, task_id, status)
        SELECT NEW.candidate_id, ot.id, 'pending'
        FROM onboarding_tasks ot
        WHERE ot.department = @job_department OR ot.department = 'ALL'
        ON DUPLICATE KEY UPDATE status = status; -- Avoid duplicates
        
        -- Create initial training assignments
        INSERT INTO employee_training (employee_id, module_id, status, progress_percentage)
        SELECT NEW.candidate_id, tm.id, 'not_started', 0
        FROM training_modules tm
        WHERE tm.department = @job_department OR tm.department = 'ALL'
        ON DUPLICATE KEY UPDATE status = status; -- Avoid duplicates
        
        -- Create initial employee documents
        INSERT INTO employee_documents (employee_id, document_name, document_type, status, is_required, description)
        VALUES 
        (NEW.candidate_id, 'Employment Contract', 'contract', 'pending', 1, 'Your employment contract and terms of service'),
        (NEW.candidate_id, 'Personal Information Form', 'personal_form', 'pending', 1, 'Complete personal details and emergency contacts'),
        (NEW.candidate_id, 'Bank Details Form', 'bank_form', 'pending', 1, 'Banking information for salary processing'),
        (NEW.candidate_id, 'ID Copy', 'identification', 'pending', 1, 'Copy of your identification document (IC/Passport)'),
        (NEW.candidate_id, 'Educational Certificates', 'education', 'pending', 1, 'Copies of your educational qualifications')
        ON DUPLICATE KEY UPDATE document_name = document_name; -- Avoid duplicates
        
    END IF;
END
$$
DELIMITER ;

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
(7, 'How do I access the training portal?', 'You can access all training modules through the Employee Portal under \"Training Modules\" section.', 'onboarding', 'training, learning, modules', 1, '2025-08-18 15:12:09'),
(8, 'What documents do I need to submit?', 'Required documents include Employment Contract, Personal Information Form, Bank Details, ID Copy, and Educational Certificates.', 'onboarding', 'documents, paperwork, requirements', 1, '2025-08-18 15:12:09'),
(9, 'How long does document review take?', 'Document review typically takes 2-3 business days. You will be notified once your documents are approved or if any changes are needed.', 'onboarding', 'document review, approval time', 1, '2025-08-18 15:12:09'),
(10, 'What is the company leave policy?', 'Full-time employees are entitled to 14 days annual leave, 14 days medical leave, and public holidays. Leave requests should be submitted through the HR portal.', 'hr_policy', 'leave, vacation, sick days', 1, '2025-08-18 15:12:09'),
(11, 'How do I access the company VPN?', 'Contact IT support at it@company.com or extension 1234. They will provide you with VPN credentials and setup instructions.', 'technical', 'vpn, remote access, network', 1, '2025-08-26 16:21:07'),
(12, 'My laptop is not working', 'For hardware issues, create a support ticket or contact IT at extension 1234. For urgent issues, email it@company.com directly.', 'technical', 'laptop, computer, hardware, broken', 1, '2025-08-26 16:21:07'),
(13, 'How do I reset my email password?', 'Contact IT support at it@company.com or call extension 1234. You will need to verify your identity before the password reset.', 'technical', 'email, password, reset, login', 1, '2025-08-26 16:21:07'),
(14, 'When do I get my employee ID card?', 'Employee ID cards are typically issued within 3-5 business days. Check with HR if you have not received yours after one week.', 'onboarding', 'id card, badge, access card', 1, '2025-08-26 16:21:07'),
(15, 'What documents do I need for onboarding?', 'Required documents include: Employment Contract, Personal Information Form, Bank Details Form, ID Copy, and Educational Certificates. Optional documents may also be requested.', 'onboarding', 'documents, paperwork, requirements, forms', 1, '2025-08-26 16:21:07'),
(16, 'How long does onboarding take?', 'The complete onboarding process typically takes 2-4 weeks, depending on your department and role complexity.', 'onboarding', 'duration, timeline, how long', 1, '2025-08-26 16:21:07'),
(17, 'How do I apply for leave?', 'Submit leave requests through the HR portal at least 2 weeks in advance. For emergency leave, contact your manager and HR immediately.', 'hr_policy', 'leave, vacation, time off, apply', 1, '2025-08-26 16:21:07'),
(18, 'What is our sick leave policy?', 'Employees are entitled to 14 days medical leave annually. Medical certificates are required for sick leave exceeding 2 consecutive days.', 'hr_policy', 'sick leave, medical, illness', 1, '2025-08-26 16:21:07'),
(19, 'Do we have health insurance?', 'Yes, comprehensive health insurance is provided. Contact HR at hr@haircare2u.my for details about coverage and claims.', 'hr_policy', 'health insurance, medical coverage, benefits', 1, '2025-08-26 16:21:07'),
(20, 'What is the company mission?', 'HairCare2U is dedicated to providing premium haircare solutions while fostering employee growth and customer satisfaction.', 'company', 'mission, values, purpose', 1, '2025-08-26 16:21:07'),
(21, 'Are there team building activities?', 'Yes, we organize quarterly team building events and monthly social activities. Check with your department head for upcoming events.', 'company', 'team building, events, social', 1, '2025-08-26 16:21:07'),
(22, 'hello', 'Hello! How can I help you today?', 'greeting', 'hi, hey, hello, good morning', 1, '2025-08-26 16:26:34'),
(23, 'thanks', 'You\'re welcome! Is there anything else I can help you with?', 'greeting', 'thank you, thanks, appreciate', 1, '2025-08-26 16:26:34'),
(24, 'help', 'I can assist you with HR policies, onboarding, training, documents, and general company information. What would you like to know?', 'general', 'help, assist, support', 1, '2025-08-26 16:26:34');

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
(2, 'api_provider', 'openai', 'Chatbot provider: static, openai, dialogflow', '2025-08-26 15:58:05'),
(3, 'api_key', '', 'API key for external chatbot service', '2025-08-18 15:12:09'),
(4, 'default_response', 'I apologize, but I couldn\'t find a specific answer to your question. Please contact HR at hr@haircare2u.my for further assistance.', 'Default response when no match found', '2025-08-26 15:58:05'),
(5, 'greeting_message', 'Hello! I\'m your AI-powered HR assistant. How can I help you today?', 'Initial greeting message', '2025-08-26 15:58:05');

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
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewer_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employee_documents`
--

INSERT INTO `employee_documents` (`id`, `employee_id`, `document_name`, `document_type`, `file_path`, `file_size`, `status`, `is_required`, `description`, `uploaded_at`, `reviewed_at`, `reviewer_notes`) VALUES
(0, 9, 'Employment Contract', 'contract', NULL, NULL, 'pending', 1, 'Your employment contract and terms of service', '2025-08-26 17:42:30', NULL, NULL);

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

-- --------------------------------------------------------

--
-- Table structure for table `job_positions`
--

CREATE TABLE `job_positions` (
  `id` int(11) NOT NULL,
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
(7, 'Product Knowledge Training', 'Learn about company products and services', 'Sales & Marketing', 1, 7, '2025-08-24 12:00:00'),
(8, 'Customer Service Training', 'Learn customer interaction protocols', 'Customer Service', 1, 8, '2025-08-24 12:00:00'),
(9, 'Operations Training', 'Understanding operational procedures and workflows', 'Operations', 1, 9, '2025-08-24 12:00:00');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `job_position_id` int(11) DEFAULT NULL,
  `department` varchar(50) DEFAULT 'ALL'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `status`, `created_at`, `updated_at`, `reset_token`, `reset_expires`, `job_position_id`, `department`) VALUES
(1, 'hr_admin', 'hr@haircare2u.my', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'HR Administrator', 'hr', 'active', '2025-08-18 15:11:16', '2025-08-24 12:00:00', NULL, NULL, NULL, 'ALL'),
(3, 'alice123', 'alice@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Alice Tan', 'candidate', 'active', '2025-08-18 16:04:58', '2025-08-24 12:12:10', NULL, NULL, NULL, 'ALL'),
(4, 'azim_m', 'azim.muhd@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Muhd Azim Bin Ali', 'candidate', 'active', '2025-08-18 16:04:58', '2025-08-18 16:14:08', NULL, NULL, NULL, 'ALL'),
(5, 'bella_lee', 'bella.lee@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Bella Lee', 'candidate', 'active', '2025-08-18 16:04:58', '2025-08-18 16:14:08', NULL, NULL, NULL, 'ALL'),
(6, 'carlos_s', 'carlos.smith@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Carlos Smith', 'candidate', 'active', '2025-08-18 16:04:58', '2025-08-18 16:14:08', NULL, NULL, NULL, 'ALL'),
(7, 'diana_w', 'diana.wong@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Diana Wong', 'candidate', 'active', '2025-08-18 16:04:58', '2025-08-18 16:14:08', NULL, NULL, NULL, 'ALL'),
(8, 'siti_r', 'siti.rahman@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Siti Rahman', 'employee', 'active', '2025-08-18 16:05:04', '2025-08-24 13:15:25', NULL, NULL, 2, 'Sales & Marketing'),
(9, 'john_d', 'john.doe@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'John Doe', 'employee', 'active', '2025-08-18 16:05:04', '2025-08-24 13:15:25', NULL, NULL, 1, 'IT'),
(10, 'kevin_l', 'kevin.lim@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Kevin Lim', 'employee', 'active', '2025-08-18 16:05:04', '2025-08-24 13:15:25', NULL, NULL, 9, 'Customer Service'),
(11, 'linda_t', 'linda.tan@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Linda Tan', 'employee', 'active', '2025-08-18 16:05:04', '2025-08-24 13:15:25', NULL, NULL, 7, 'Operations'),
(12, 'michael_c', 'michael.choo@example.com', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'Michael Choo', 'employee', 'inactive', '2025-08-18 16:05:04', '2025-08-18 16:14:08', NULL, NULL, NULL, 'ALL');

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active_question` (`is_active`,`question`),
  ADD KEY `idx_keywords` (`keywords`(768));

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
  ADD UNIQUE KEY `unique_employee_document` (`employee_id`,`document_type`),
  ADD KEY `employee_id` (`employee_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `chatbot_faq`
--
ALTER TABLE `chatbot_faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `chatbot_settings`
--
ALTER TABLE `chatbot_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `job_positions`
--
ALTER TABLE `job_positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `parsed_resumes`
--
ALTER TABLE `parsed_resumes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
