<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Get job positions
$job_positions = getJobPositions();

$success = '';
$error = '';

// Handle resume upload
if ($_POST && isset($_POST['submit_application'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $job_position_id = $_POST['job_position_id'] ?? '';
    
    // Validation
    if (empty($full_name) || empty($email) || empty($job_position_id)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($_FILES['resume']['name'])) {
        $error = 'Please upload your resume.';
    } else {
        try {
            // Check if candidate already exists, if not create account
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user) {
                $candidate_id = $existing_user['id'];
            } else {
                // Create new candidate account
                $username = strtolower(str_replace(' ', '_', $full_name)) . '_' . time();
                $temp_password = generatePassword();
                
                if (createUser($username, $email, $temp_password, $full_name, 'candidate')) {
                    $candidate_id = $pdo->lastInsertId();
                } else {
                    throw new Exception('Failed to create candidate account.');
                }
            }
            
            // Upload resume file
            $upload_result = uploadResume($_FILES['resume'], $candidate_id, $job_position_id);
            
            if ($upload_result['success']) {
                // Parse resume (mock implementation)
                $parsed_data = parseResume($upload_result['path']);
                
                // Get job requirements for match calculation
                $stmt = $pdo->prepare("SELECT required_skills FROM job_positions WHERE id = ?");
                $stmt->execute([$job_position_id]);
                $job = $stmt->fetch();
                
                // Calculate match percentage
                $match_percentage = calculateMatchPercentage($parsed_data['skills'], $job['required_skills']);
                
                // Save application
                $stmt = $pdo->prepare("
                    INSERT INTO applications 
                    (candidate_id, job_position_id, resume_filename, resume_path, extracted_skills, 
                     extracted_experience, extracted_education, extracted_contact, match_percentage, api_processing_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
                ");
                
                $result = $stmt->execute([
                    $candidate_id,
                    $job_position_id,
                    $upload_result['filename'],
                    $upload_result['path'],
                    $parsed_data['skills'],
                    $parsed_data['experience'],
                    $parsed_data['education'],
                    $parsed_data['contact'],
                    $match_percentage
                ]);
                
                if ($result) {
                    $success = "Application submitted successfully! Your match score is {$match_percentage}%. You will be contacted if selected for an interview.";
                    
                    // Clear form data
                    $_POST = [];
                } else {
                    $error = 'Failed to save application. Please try again.';
                }
                
            } else {
                $error = $upload_result['message'];
            }
            
        } catch(Exception $e) {
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}

// If user is already logged in as candidate, pre-fill their info
$user_info = [];
if (isLoggedIn() && hasRole('candidate')) {
    $user_info = [
        'full_name' => $_SESSION['full_name'],
        'email' => $_SESSION['email']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Position - Kabel HR System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .application-hero {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.9), rgba(43, 76, 140, 0.9));
            color: white;
            padding: 3rem 0;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .job-listing {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        .job-listing:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow);
        }
        
        .job-listing.selected {
            border-color: var(--primary-color);
            background: rgba(255, 107, 53, 0.05);
        }
        
        .job-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .job-department {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .job-skills {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .upload-area {
            border: 3px dashed var(--primary-color);
            border-radius: var(--border-radius);
            padding: 3rem;
            text-align: center;
            background: rgba(255, 107, 53, 0.05);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .upload-area:hover {
            background: rgba(255, 107, 53, 0.1);
            border-color: var(--secondary-color);
        }
        
        .upload-area.drag-over {
            background: rgba(255, 107, 53, 0.15);
            border-color: var(--secondary-color);
            transform: scale(1.02);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .file-info {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(40, 167, 69, 0.1);
            border-radius: var(--border-radius);
            border: 1px solid var(--success-color);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <div class="logo-icon">K</div>
                <div class="logo-text">Kabel HR</div>
            </a>
            <div class="user-info">
                <?php if (isLoggedIn()): ?>
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="includes/logout.php" class="logout-btn">Logout</a>
                <?php else: ?>
                    <a href="index.php" class="btn btn-outline">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <div class="application-hero">
        <div class="container">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Join Our Team</h1>
            <p style="font-size: 1.2rem; opacity: 0.9;">
                Start your journey with Kabel - Where Excellence Meets Innovation
            </p>
            <p style="opacity: 0.8;">
                Upload your resume and let our AI-powered system find the perfect match for your skills
            </p>
        </div>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="application-form">
            <!-- Available Positions -->
            <div class="card">
                <div class="card-header">
                    <i>💼</i> Available Positions
                </div>
                <div class="card-body">
                    <?php if (empty($job_positions)): ?>
                        <div style="text-align: center; padding: 2rem; color: #6c757d;">
                            <h3>No positions available</h3>
                            <p>Please check back later for new opportunities.</p>
                        </div>
                    <?php else: ?>
                        <p style="margin-bottom: 1.5rem; color: #6c757d;">
                            Select the position you're interested in applying for:
                        </p>
                        
                        <div id="job-listings">
                            <?php foreach ($job_positions as $job): ?>
                                <div class="job-listing" data-job-id="<?php echo $job['id']; ?>" onclick="selectJob(<?php echo $job['id']; ?>)">
                                    <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <div class="job-department"><?php echo htmlspecialchars($job['department']); ?> Department</div>
                                    <div class="job-skills">
                                        <strong>Required Skills:</strong> <?php echo htmlspecialchars($job['required_skills']); ?>
                                    </div>
                                    <?php if (!empty($job['description'])): ?>
                                        <div style="margin-top: 0.5rem; color: #6c757d; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars(substr($job['description'], 0, 150)); ?>
                                            <?php if (strlen($job['description']) > 150) echo '...'; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <input type="hidden" name="job_position_id" id="selected-job-id" required>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="card">
                <div class="card-header">
                    <i>👤</i> Personal Information
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($user_info['full_name'] ?? $_POST['full_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" required
                                   value="<?php echo htmlspecialchars($user_info['email'] ?? $_POST['email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resume Upload -->
            <div class="card">
                <div class="card-header">
                    <i>📄</i> Resume Upload
                </div>
                <div class="card-body">
                    <div class="upload-area" id="upload-area">
                        <div class="upload-icon">📤</div>
                        <h3 style="margin-bottom: 0.5rem; color: var(--secondary-color);">Upload Your Resume</h3>
                        <p style="color: #6c757d; margin-bottom: 1rem;">
                            Drag and drop your resume here, or click to browse
                        </p>
                        <p style="color: #6c757d; font-size: 0.9rem;">
                            Supported formats: PDF, DOC, DOCX (Max 5MB)
                        </p>
                        <input type="file" name="resume" id="resume-input" accept=".pdf,.doc,.docx" required style="display: none;">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('resume-input').click()">
                            Choose File
                        </button>
                    </div>
                    
                    <div id="file-info" class="file-info" style="display: none;">
                        <h4 style="margin-bottom: 0.5rem; color: var(--success-color);">✅ File Selected</h4>
                        <div id="file-details"></div>
                    </div>
                </div>
            </div>

            <!-- AI Processing Info -->
            <div class="card">
                <div class="card-header">
                    <i>🤖</i> AI-Powered Analysis
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔍</div>
                            <strong>Skill Extraction</strong>
                            <p style="color: #6c757d; font-size: 0.9rem; margin: 0;">
                                AI analyzes your resume to identify technical and soft skills
                            </p>
                        </div>
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                            <strong>Match Scoring</strong>
                            <p style="color: #6c757d; font-size: 0.9rem; margin: 0;">
                                Calculate compatibility percentage with job requirements
                            </p>
                        </div>
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">⚡</div>
                            <strong>Instant Results</strong>
                            <p style="color: #6c757d; font-size: 0.9rem; margin: 0;">
                                Get immediate feedback on your application status
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div style="text-align: center; margin: 2rem 0;">
                <button type="submit" name="submit_application" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;" id="submit-btn" disabled>
                    🚀 Submit Application
                </button>
                <p style="color: #6c757d; margin-top: 1rem; font-size: 0.9rem;">
                    By submitting this application, you agree to our privacy policy and terms of service.
                </p>
            </div>
        </form>

        <!-- FAQ Section -->
        <div class="card">
            <div class="card-header">
                <i>❓</i> Frequently Asked Questions
            </div>
            <div class="card-body">
                <div style="display: grid; gap: 1rem;">
                    <div>
                        <strong style="color: var(--secondary-color);">What file formats are accepted?</strong>
                        <p style="margin: 0.5rem 0 0 0; color: #6c757d;">We accept PDF, DOC, and DOCX files up to 5MB in size.</p>
                    </div>
                    <div>
                        <strong style="color: var(--secondary-color);">How long does the review process take?</strong>
                        <p style="margin: 0.5rem 0 0 0; color: #6c757d;">Our AI system provides instant analysis. HR team reviews applications within 3-5 business days.</p>
                    </div>
                    <div>
                        <strong style="color: var(--secondary-color);">What happens after I submit my application?</strong>
                        <p style="margin: 0.5rem 0 0 0; color: #6c757d;">You'll receive an immediate match score. If selected, our HR team will contact you for the next steps.</p>
                    </div>
                    <div>
                        <strong style="color: var(--secondary-color);">Can I apply for multiple positions?</strong>
                        <p style="margin: 0.5rem 0 0 0; color: #6c757d;">Yes! You can submit separate applications for different positions that match your skills.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Candidate Upload Page - Specific Functionality
        let selectedJobId = null;

        // Job selection functionality
        function selectJob(jobId) {
            // Remove previous selection
            document.querySelectorAll('.job-listing').forEach(listing => {
                listing.classList.remove('selected');
            });

            // Add selection to clicked job
            const selectedListing = document.querySelector(`[data-job-id="${jobId}"]`);
            selectedListing.classList.add('selected');

            // Update hidden input
            document.getElementById('selected-job-id').value = jobId;
            selectedJobId = jobId;

            // Enable submit button if file is also selected
            checkFormValidity();
        }

        // Initialize page functionality
        document.addEventListener('DOMContentLoaded', function() {
            // File upload handling
            document.getElementById('resume-input').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    displayFileInfo(file);
                    checkFormValidity();
                }
            });

            // Drag and drop functionality
            const uploadArea = document.getElementById('upload-area');
            
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('drag-over');
            });

            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    document.getElementById('resume-input').files = files;
                    displayFileInfo(file);
                    checkFormValidity();
                }
            });

            // Click to upload
            uploadArea.addEventListener('click', function(e) {
                if (e.target.tagName !== 'BUTTON') {
                    document.getElementById('resume-input').click();
                }
            });

            // Form validation
            document.getElementById('application-form').addEventListener('submit', function(e) {
                if (!selectedJobId) {
                    e.preventDefault();
                    alert('Please select a job position.');
                    return;
                }
                
                const file = document.getElementById('resume-input').files[0];
                if (!file) {
                    e.preventDefault();
                    alert('Please upload your resume.');
                    return;
                }
                
                // Show loading state
                const submitBtn = document.getElementById('submit-btn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<div class="loading"></div> Processing Application...';
            });

            // Add event listeners for form fields
            document.getElementById('full_name').addEventListener('input', checkFormValidity);
            document.getElementById('email').addEventListener('input', checkFormValidity);

            // Initialize form validation
            checkFormValidity();
        });

        // Display file information
        function displayFileInfo(file) {
            const fileInfo = document.getElementById('file-info');
            const fileDetails = document.getElementById('file-details');
            
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            const fileName = file.name;
            
            fileDetails.innerHTML = `
                <p><strong>File:</strong> ${fileName}</p>
                <p><strong>Size:</strong> ${fileSize} MB</p>
                <p><strong>Type:</strong> ${file.type}</p>
            `;
            
            fileInfo.style.display = 'block';
            
            // Update upload area
            const uploadArea = document.getElementById('upload-area');
            uploadArea.innerHTML = `
                <div class="upload-icon" style="color: var(--success-color);">✅</div>
                <h3 style="margin-bottom: 0.5rem; color: var(--success-color);">File Ready</h3>
                <p style="color: #6c757d; margin-bottom: 1rem;">${fileName}</p>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('resume-input').click()">
                    Change File
                </button>
            `;
        }

        // Check form validity
        function checkFormValidity() {
            const submitBtn = document.getElementById('submit-btn');
            const hasJob = selectedJobId !== null;
            const hasFile = document.getElementById('resume-input').files.length > 0;
            const hasName = document.getElementById('full_name').value.trim() !== '';
            const hasEmail = document.getElementById('email').value.trim() !== '';
            
            if (hasJob && hasFile && hasName && hasEmail) {
                submitBtn.disabled = false;
                submitBtn.textContent = '🚀 Submit Application';
            } else {
                submitBtn.disabled = true;
                submitBtn.textContent = '🚀 Submit Application (Complete all fields)';
            }
        }
    </script>
</body>
</html>