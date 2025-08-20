// Kabel HR System - Main JavaScript

// Handle form submissions with loading states
document.addEventListener('DOMContentLoaded', function() {
    // Handle all forms with loading states
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                // Add loading state
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                
                // Store original text
                const originalText = submitBtn.textContent || submitBtn.value;
                
                // Change button text
                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
                } else {
                    submitBtn.value = 'Processing...';
                }
                
                // Reset button after 5 seconds (fallback)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    if (submitBtn.tagName === 'BUTTON') {
                        submitBtn.textContent = originalText;
                    } else {
                        submitBtn.value = originalText;
                    }
                }, 5000);
            }
        });
    });
    
    // Handle status update dropdowns
    const statusSelects = document.querySelectorAll('select[name="status"]');
    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) {
                // Add loading indicator to the select
                this.style.opacity = '0.6';
                this.disabled = true;
                
                // Create a loading spinner
                const spinner = document.createElement('span');
                spinner.className = 'spinner';
                spinner.style.marginLeft = '10px';
                this.parentNode.appendChild(spinner);
                
                // Submit the form
                form.submit();
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
    
    // Add smooth scrolling to anchor links
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add hover effects to cards
    const cards = document.querySelectorAll('.card, .stat-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

// File Upload Enhancement
function initFileUpload() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const label = this.nextElementSibling || this.parentElement.querySelector('.file-upload-label');
            
            if (file) {
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
                
                if (label) {
                    label.innerHTML = `
                        <strong>Selected:</strong> ${fileName}<br>
                        <small>Size: ${fileSize} MB</small>
                    `;
                    label.style.backgroundColor = 'rgba(40, 167, 69, 0.1)';
                    label.style.borderColor = '#28a745';
                }
                
                // Validate file
                validateFile(file);
            }
        });
    });
}

// File Validation
function validateFile(file) {
    const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    const maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!allowedTypes.includes(file.type)) {
        showAlert('Please select a PDF, DOC, or DOCX file.', 'danger');
        return false;
    }
    
    if (file.size > maxSize) {
        showAlert('File size must be less than 5MB.', 'danger');
        return false;
    }
    
    return true;
}

// Chatbot Functionality
function initChatbot() {
    const chatbotButton = document.getElementById('chatbot-button');
    const chatbotWindow = document.getElementById('chatbot-window');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotSend = document.getElementById('chatbot-send');
    const chatbotMessages = document.getElementById('chatbot-messages');
    
    if (!chatbotButton) return;
    
    // Toggle chatbot window
    chatbotButton.addEventListener('click', function() {
        const isVisible = chatbotWindow.style.display === 'flex';
        chatbotWindow.style.display = isVisible ? 'none' : 'flex';
        
        if (!isVisible && chatbotMessages.children.length === 0) {
            // Show welcome message
            addChatMessage('Hello! I\'m here to help you with onboarding questions. How can I assist you today?', 'bot');
        }
    });
    
    // Send message on button click
    if (chatbotSend) {
        chatbotSend.addEventListener('click', sendChatMessage);
    }
    
    // Send message on Enter key
    if (chatbotInput) {
        chatbotInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });
    }
}

// Send Chat Message
function sendChatMessage() {
    const input = document.getElementById('chatbot-input');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message to chat
    addChatMessage(message, 'user');
    input.value = '';
    
    // Show typing indicator
    addTypingIndicator();
    
    // Send to server for response
    fetch('includes/chatbot.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ message: message })
    })
    .then(response => response.json())
    .then(data => {
        removeTypingIndicator();
        addChatMessage(data.response || 'Sorry, I couldn\'t understand your question.', 'bot');
    })
    .catch(error => {
        removeTypingIndicator();
        addChatMessage('Sorry, I\'m experiencing technical difficulties. Please contact HR for assistance.', 'bot');
    });
}

// Add Chat Message
function addChatMessage(message, sender) {
    const messagesContainer = document.getElementById('chatbot-messages');
    if (!messagesContainer) return;
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}`;
    
    messageDiv.innerHTML = `
        <div class="message-bubble">
            ${message}
        </div>
    `;
    
    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Typing Indicator
function addTypingIndicator() {
    const messagesContainer = document.getElementById('chatbot-messages');
    if (!messagesContainer) return;
    
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message bot typing-indicator';
    typingDiv.innerHTML = `
        <div class="message-bubble">
            <div class="loading"></div> Typing...
        </div>
    `;
    
    messagesContainer.appendChild(typingDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function removeTypingIndicator() {
    const typingIndicator = document.querySelector('.typing-indicator');
    if (typingIndicator) {
        typingIndicator.remove();
    }
}

// Status Update Functionality
function initStatusUpdates() {
    const statusSelects = document.querySelectorAll('.status-select');
    
    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const applicationId = this.dataset.applicationId;
            const newStatus = this.value;
            const notes = prompt('Add notes (optional):') || '';
            
            updateApplicationStatus(applicationId, newStatus, notes);
        });
    });
}

// Update Application Status
function updateApplicationStatus(applicationId, status, notes) {
    const formData = new FormData();
    formData.append('application_id', applicationId);
    formData.append('status', status);
    formData.append('notes', notes);
    
    fetch('includes/update_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Status updated successfully!', 'success');
            // Refresh the page or update the UI
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('Failed to update status: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error updating status', 'danger');
    });
}

// Task Update Functionality
function initTaskUpdates() {
    const taskCheckboxes = document.querySelectorAll('.task-checkbox');
    
    taskCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const taskId = this.dataset.taskId;
            const employeeId = this.dataset.employeeId;
            const status = this.checked ? 'completed' : 'pending';
            
            updateTaskStatus(taskId, employeeId, status);
        });
    });
}

// Update Task Status
function updateTaskStatus(taskId, employeeId, status) {
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('employee_id', employeeId);
    formData.append('status', status);
    
    fetch('includes/update_task.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Task updated successfully!', 'success');
            updateProgressBar();
        } else {
            showAlert('Failed to update task', 'danger');
        }
    })
    .catch(error => {
        showAlert('Error updating task', 'danger');
    });
}

// Update Progress Bar
function updateProgressBar() {
    const checkboxes = document.querySelectorAll('.task-checkbox');
    const total = checkboxes.length;
    const completed = document.querySelectorAll('.task-checkbox:checked').length;
    const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
    
    const progressBar = document.querySelector('.progress-bar');
    if (progressBar) {
        progressBar.style.width = percentage + '%';
        progressBar.textContent = percentage + '%';
    }
}

// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

// Validate Form
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#dc3545';
            isValid = false;
        } else {
            field.style.borderColor = '#e9ecef';
        }
    });
    
    return isValid;
}

// Utility functions
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <strong>${type.charAt(0).toUpperCase() + type.slice(1)}:</strong> ${message}
        <button type="button" class="close" onclick="this.parentElement.remove()" style="float: right; background: none; border: none; font-size: 1.2rem; cursor: pointer;">&times;</button>
    `;
    
    // Insert at the top of the container
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Progress bar animation
function animateProgressBar(element, targetPercent) {
    let currentPercent = 0;
    const increment = targetPercent / 100;
    
    const timer = setInterval(() => {
        currentPercent += increment;
        element.style.width = currentPercent + '%';
        
        if (currentPercent >= targetPercent) {
            clearInterval(timer);
            element.style.width = targetPercent + '%';
        }
    }, 10);
}

// Table search functionality
function addTableSearch(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    // Create search input
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search table...';
    searchInput.className = 'form-control';
    searchInput.style.marginBottom = '1rem';
    
    // Insert before table
    table.parentNode.insertBefore(searchInput, table);
    
    // Add search functionality
    searchInput.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 1; i < rows.length; i++) { // Skip header row
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            let found = false;
            
            for (let j = 0; j < cells.length; j++) {
                if (cells[j].textContent.toLowerCase().includes(filter)) {
                    found = true;
                    break;
                }
            }
            
            row.style.display = found ? '' : 'none';
        }
    });
}

// Mobile menu toggle
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
    
    if (overlay) {
        overlay.classList.toggle('show');
    }
}

// Print functionality
function printTable(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Table</title>
            <style>
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            ${table.outerHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// Local storage helpers
function saveToLocalStorage(key, data) {
    try {
        localStorage.setItem(key, JSON.stringify(data));
        return true;
    } catch (e) {
        console.error('Error saving to localStorage:', e);
        return false;
    }
}

function getFromLocalStorage(key) {
    try {
        const data = localStorage.getItem(key);
        return data ? JSON.parse(data) : null;
    } catch (e) {
        console.error('Error reading from localStorage:', e);
        return null;
    }
}

function getFromLocalStorage(key) {
    try {
        const data = localStorage.getItem(key);
        return data ? JSON.parse(data) : null;
    } catch (e) {
        console.error('Error reading from localStorage:', e);
        return null;
    }
}

function removeFromLocalStorage(key) {
    try {
        localStorage.removeItem(key);
        return true;
    } catch (e) {
        console.error('Error removing from localStorage:', e);
        return false;
    }
}

// Format File Size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Animate Numbers (for dashboard stats)
function animateNumber(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const current = Math.floor(progress * (end - start) + start);
        element.textContent = current;
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Initialize number animations when elements are visible
function initNumberAnimations() {
    const numbers = document.querySelectorAll('.stat-number');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = parseInt(entry.target.textContent);
                animateNumber(entry.target, 0, target, 1000);
                observer.unobserve(entry.target);
            }
        });
    });
    
    numbers.forEach(number => observer.observe(number));
}

// Utility function to get match percentage class
function getMatchClass(percentage) {
    if (percentage >= 80) return 'match-high';
    if (percentage >= 60) return 'match-medium';
    return 'match-low';
}

// Real-time search functionality
function initSearch() {
    const searchInputs = document.querySelectorAll('.search-input');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const targetTable = document.querySelector(this.dataset.target);
            
            if (targetTable) {
                const rows = targetTable.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            }
        });
    });
}

// HR Dashboard specific functions
function exportApplications() {
    showAlert('Export functionality would be implemented here. This would generate CSV/Excel files of application data.', 'info');
}

function generateReport() {
    showAlert('Reporting functionality would be implemented here. This would show hiring analytics and trends.', 'info');
}

function manageJobs() {
    showAlert('Job management functionality would be implemented here. This would allow adding/editing job positions.', 'info');
}

// Confirm status changes
function confirmStatusChange(selectElement, newStatus) {
    if (newStatus === 'hired') {
        return confirm('Are you sure you want to hire this candidate?\n\nThis will:\n- Create an employee account\n- Assign onboarding tasks\n- Send welcome email (if configured)');
    }
    return true;
}

// Employee Dashboard specific functions
function startTraining(moduleName) {
    showAlert(`Starting training module: ${moduleName}. This would normally open the training content or redirect to the learning platform.`, 'info');
}

function continueTraining(moduleName) {
    showAlert(`Continuing training module: ${moduleName}. This would resume your progress in the training platform.`, 'info');
}

function viewTrainingMaterial(moduleName) {
    showAlert(`Viewing training material for: ${moduleName}. This would open the training resources and documentation.`, 'info');
}

function openEmployeeHandbook() {
    showAlert('Employee Handbook: This would open the company handbook with policies, procedures, and guidelines.', 'info');
}

function openOrgChart() {
    showAlert('Organization Chart: This would display the company structure and help you get to know your colleagues.', 'info');
}

function openITSupport() {
    showAlert('IT Support: This would connect you with IT support for technical assistance and equipment setup.', 'info');
}

function openHRContact() {
    showAlert('HR Contact: This would provide HR contact information and support channels.', 'info');
}