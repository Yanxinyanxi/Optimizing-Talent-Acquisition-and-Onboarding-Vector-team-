-- Talent Acquisition & Onboarding System Database Schema
-- For XAMPP/MySQL

-- Create Database
CREATE DATABASE IF NOT EXISTS talent_acquisition;
USE talent_acquisition;

-- ====================================
-- 1. USERS TABLE (HR, Candidates, Employees)
-- ====================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('hr', 'candidate', 'employee') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);



-- ====================================
-- 2. JOB POSITIONS TABLE
-- ====================================
CREATE TABLE job_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL,
    description TEXT,
    required_skills TEXT NOT NULL, -- JSON or comma-separated skills
    experience_level ENUM('entry', 'mid', 'senior') DEFAULT 'mid',
    status ENUM('active', 'closed') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ====================================
-- 3. APPLICATIONS/RESUMES TABLE
-- ====================================
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT,
    job_position_id INT,
    resume_filename VARCHAR(255) NOT NULL,
    resume_path VARCHAR(500) NOT NULL,
    
    -- Resume API Response Data
    api_response TEXT, -- Full JSON response from parsing API
    extracted_skills TEXT, -- Skills extracted from resume API
    extracted_experience TEXT, -- Experience details from API
    extracted_education TEXT, -- Education details from API
    extracted_contact JSON, -- Contact info (email, phone) from API
    
    match_percentage DECIMAL(5,2) DEFAULT 0.00, -- Calculated match % with job
    api_processing_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    api_error_message TEXT, -- Store API errors if any
    
    status ENUM('pending', 'selected', 'rejected', 'waiting_interview', 'interview_completed', 'offer_sent', 'offer_accepted', 'offer_rejected', 'hired') DEFAULT 'pending',
    hr_notes TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES users(id),
    FOREIGN KEY (job_position_id) REFERENCES job_positions(id)
);

-- ====================================
-- 4. ONBOARDING TASKS TABLE
-- ====================================
CREATE TABLE onboarding_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(150) NOT NULL,
    description TEXT,
    department VARCHAR(50), -- Tasks specific to departments
    is_mandatory BOOLEAN DEFAULT TRUE,
    order_sequence INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ====================================
-- 5. EMPLOYEE ONBOARDING PROGRESS TABLE
-- ====================================
CREATE TABLE employee_onboarding (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT,
    task_id INT,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    completed_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (employee_id) REFERENCES users(id),
    FOREIGN KEY (task_id) REFERENCES onboarding_tasks(id),
    UNIQUE KEY unique_employee_task (employee_id, task_id)
);

-- ====================================
-- 6. TRAINING MODULES TABLE
-- ====================================
CREATE TABLE training_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_name VARCHAR(100) NOT NULL,
    description TEXT,
    content_url VARCHAR(500), -- Link to training material
    department VARCHAR(50),
    duration_hours INT DEFAULT 1,
    is_mandatory BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ====================================
-- 7. EMPLOYEE TRAINING PROGRESS TABLE
-- ====================================
CREATE TABLE employee_training (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT,
    module_id INT,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    progress_percentage INT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES users(id),
    FOREIGN KEY (module_id) REFERENCES training_modules(id),
    UNIQUE KEY unique_employee_module (employee_id, module_id)
);

-- ====================================
-- 8. CHATBOT DATA TABLES
-- ====================================

-- FAQ/Knowledge Base for Static Chatbot
CREATE TABLE chatbot_faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50), -- 'onboarding', 'hr_policy', 'technical', etc.
    keywords TEXT, -- Keywords for matching user questions
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Chat Conversations (for both static and API chatbot)
CREATE TABLE chat_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(100), -- Unique session identifier
    message TEXT NOT NULL,
    response TEXT NOT NULL,
    message_type ENUM('user', 'bot') NOT NULL,
    api_response_time DECIMAL(8,3), -- Track API response time if using external API
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Chatbot Settings/Configuration
CREATE TABLE chatbot_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Updated parsed_resumes table to match your Extracta.ai extraction fields
-- Run this in phpMyAdmin to update your existing table

-- Drop the existing table if it exists (CAUTION: This will delete existing data)
-- DROP TABLE IF EXISTS `parsed_resumes`;

-- Create new table structure matching your extraction fields
CREATE TABLE IF NOT EXISTS `parsed_resumes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_filename` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `linkedin` varchar(500) DEFAULT NULL,
  `github` varchar(500) DEFAULT NULL,
  `work_experience` longtext,
  `education` longtext,
  `languages` text,
  `skills` text,
  `certificates` text,
  `raw_data` longtext,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ====================================
-- INSERT SAMPLE DATA
-- ====================================

-- Sample HR User (password: password123)
INSERT INTO users (username, email, password, full_name, role) VALUES
('hr_admin', 'hr@kabel.my', '$2y$10$j5b5UnIPEeQlMvN099kpn.4qiZDPLRmGizAEsRZm5wN0W3r2ojz0C', 'HR Administrator', 'hr');

-- Candidates
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('alice123', 'alice@example.com', '$2y$10$abc12345hashed1', 'Alice Tan', 'candidate', 'active'),
('azim_m', 'azim.muhd@example.com', '$2y$10$abc12345hashed2', 'Muhd Azim Bin Ali', 'candidate', 'active'),
('bella_lee', 'bella.lee@example.com', '$2y$10$abc12345hashed3', 'Bella Lee', 'candidate', 'active'),
('carlos_s', 'carlos.smith@example.com', '$2y$10$abc12345hashed4', 'Carlos Smith', 'candidate', 'active'),
('diana_w', 'diana.wong@example.com', '$2y$10$abc12345hashed5', 'Diana Wong', 'candidate', 'active');

-- Employees
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('siti_r', 'siti.rahman@example.com', '$2y$10$abc12345hashed6', 'Siti Rahman', 'employee', 'active'),
('john_d', 'john.doe@example.com', '$2y$10$abc12345hashed7', 'John Doe', 'employee', 'active'),
('kevin_l', 'kevin.lim@example.com', '$2y$10$abc12345hashed8', 'Kevin Lim', 'employee', 'active'),
('linda_t', 'linda.tan@example.com', '$2y$10$abc12345hashed9', 'Linda Tan', 'employee', 'active'),
('michael_c', 'michael.choo@example.com', '$2y$10$abc12345hashed10', 'Michael Choo', 'employee', 'inactive');


-- Sample Job Positions
INSERT INTO job_positions (title, department, description, required_skills, experience_level, created_by) VALUES
('Software Developer', 'IT', 'Full-stack developer position', 'PHP,JavaScript,MySQL,HTML,CSS', 'mid', 1),
('Marketing Specialist', 'Marketing', 'Digital marketing role', 'SEO,Social Media,Analytics,Content Writing', 'entry', 1),
('Project Manager', 'IT', 'Technical project management', 'Agile,Scrum,Leadership,Communication', 'senior', 1);

-- Sample Onboarding Tasks
INSERT INTO onboarding_tasks (task_name, description, department, order_sequence) VALUES
('Complete Personal Information Form', 'Fill out personal details and emergency contacts', 'ALL', 1),
('IT Equipment Setup', 'Receive laptop, access cards, and accounts', 'ALL', 2),
('Company Orientation', 'Attend company culture and policy session', 'ALL', 3),
('Department Introduction', 'Meet team members and understand role', 'ALL', 4),
('Security Training', 'Complete cybersecurity awareness training', 'IT', 5),
('Development Environment Setup', 'Install required software and tools', 'IT', 6);

-- Sample Training Modules
INSERT INTO training_modules (module_name, description, department, duration_hours) VALUES
('Company Policies', 'Learn about company rules and regulations', 'ALL', 2),
('Cybersecurity Awareness', 'Security best practices and protocols', 'ALL', 1),
('PHP Development Basics', 'Introduction to PHP programming', 'IT', 8),
('Database Management', 'MySQL fundamentals', 'IT', 6),
('Marketing Fundamentals', 'Basic marketing principles', 'Marketing', 4);

-- Sample FAQ Data for Chatbot
INSERT INTO chatbot_faq (question, answer, category, keywords) VALUES
('What are my working hours?', 'Standard working hours are 9 AM to 6 PM, Monday to Friday. Flexible timing available after probation period.', 'hr_policy', 'working hours, timing, schedule'),
('How do I reset my password?', 'Contact IT support at it@company.com or call extension 1234 to reset your password.', 'technical', 'password reset, login issue, access'),
('When will I get my laptop?', 'IT equipment including laptop will be provided within 2-3 business days of joining.', 'onboarding', 'laptop, equipment, IT setup'),
('What is the dress code?', 'Business casual is our standard dress code. Formal wear required for client meetings.', 'hr_policy', 'dress code, attire, clothing'),
('How do I submit my timesheet?', 'Use the company portal to submit weekly timesheets by Friday 5 PM each week.', 'hr_policy', 'timesheet, attendance, hours'),
('Who is my reporting manager?', 'Your reporting manager details are available in your employee dashboard under "Team Information".', 'onboarding', 'manager, supervisor, reporting');

-- Sample Chatbot Settings
INSERT INTO chatbot_settings (setting_key, setting_value, description) VALUES
('chatbot_enabled', '1', 'Enable/disable chatbot functionality'),
('api_provider', 'static', 'Chatbot provider: static, openai, dialogflow'),
('api_key', '', 'API key for external chatbot service'),
('default_response', 'I apologize, but I could not understand your question. Please contact HR for further assistance.', 'Default response when no match found'),
('greeting_message', 'Hello! I am here to help you with onboarding questions. How can I assist you today?', 'Initial greeting message');

-- ====================================
-- USEFUL QUERIES FOR DEVELOPMENT
-- ====================================

-- ====================================
-- 8. CHATBOT DATA TABLES
-- ====================================

-- FAQ/Knowledge Base for Static Chatbot
CREATE TABLE chatbot_faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50), -- 'onboarding', 'hr_policy', 'technical', etc.
    keywords TEXT, -- Keywords for matching user questions
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Chat Conversations (for both static and API chatbot)
CREATE TABLE chat_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(100), -- Unique session identifier
    message TEXT NOT NULL,
    response TEXT NOT NULL,
    message_type ENUM('user', 'bot') NOT NULL,
    api_response_time DECIMAL(8,3), -- Track API response time if using external API
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Chatbot Settings/Configuration
CREATE TABLE chatbot_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
