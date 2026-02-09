// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.luxury-tab');
    const forms = document.querySelectorAll('.luxury-form-wrapper');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');

            // Hide all forms
            forms.forEach(form => form.classList.remove('active'));
            // Show the corresponding form
            const targetForm = document.getElementById(this.dataset.tab + '-form');
            if (targetForm) {
                targetForm.classList.add('active');
            }
        });
    });

    // Check URL hash for initial tab
    if (window.location.hash === '#register') {
        document.querySelector('.luxury-tab[data-tab="register"]').click();
    }

    // Password toggle functionality
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling;
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        
        // Update button icon (you can add SVG icons here if needed)
        const icon = button.querySelector('svg');
        if (type === 'password') {
            icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
        } else {
            icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
        }
    }

    // Attach toggle functions
    window.toggleLoginPassword = function() {
        togglePassword('login-password');
    };

    window.toggleRegisterPassword = function() {
        togglePassword('register-password');
    };

    window.toggleConfirmPassword = function() {
        togglePassword('register-confirm');
    };

    // Profile picture preview
    window.previewRegisterImage = function(input) {
        const file = input.files[0];
        const preview = document.getElementById('register-avatar-preview');
        const fileName = document.getElementById('register-file-name');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
            };
            reader.readAsDataURL(file);
            fileName.textContent = file.name;
        } else {
            preview.src = '<?php echo htmlspecialchars($baseUrl); ?>/user/images/profile_pictures/nopfp.jpg';
            fileName.textContent = '';
        }
    };

    // Password requirements checking
    const passwordInput = document.getElementById('register-password');
    const requirements = document.getElementById('passwordRequirements');
    const reqItems = requirements.querySelectorAll('.req');

    function checkPassword() {
        const password = passwordInput.value;
        const length = password.length >= 8 && password.length <= 12;
        const uppercase = /[A-Z]/.test(password);
        const lowercase = /[a-z]/.test(password);
        const number = /\d/.test(password);
        const special = /[!@#$%^&*]/.test(password);

        const checks = [length, uppercase, lowercase, number, special];
        reqItems.forEach((item, index) => {
            if (checks[index]) {
                item.classList.add('met');
            } else {
                item.classList.remove('met');
            }
        });

        return length && uppercase && lowercase && number && special;
    }

    if (passwordInput) {
        passwordInput.addEventListener('focus', function() {
            requirements.classList.add('visible');
        });

        passwordInput.addEventListener('blur', function() {
            setTimeout(() => {
                requirements.classList.remove('visible');
            }, 150);
        });

        passwordInput.addEventListener('input', checkPassword);
    }

    // Confirm password matching
    const confirmInput = document.getElementById('register-confirm');
    const matchIndicator = document.querySelector('.password-match-indicator');

    if (confirmInput) {
        confirmInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirm = this.value;
            
            if (confirm.length > 0) {
                matchIndicator.classList.add('visible');
                if (password === confirm) {
                    matchIndicator.classList.add('matched');
                    matchIndicator.textContent = '✓';
                } else {
                    matchIndicator.classList.remove('matched');
                    matchIndicator.textContent = '✗';
                }
            } else {
                matchIndicator.classList.remove('visible', 'matched');
            }
        });
    }

    // Form validation
    function validateLoginForm() {
        const email = document.getElementById('login-email').value.trim();
        const password = document.getElementById('login-password').value;
        let isValid = true;

        // Clear previous errors
        document.querySelectorAll('.error-message').forEach(el => el.textContent = '');

        if (!email) {
            document.getElementById('login-email-error').textContent = 'Email is required';
            isValid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            document.getElementById('login-email-error').textContent = 'Please enter a valid email';
            isValid = false;
        }

        if (!password) {
            document.getElementById('login-password-error').textContent = 'Password is required';
            isValid = false;
        }

        return isValid;
    }

    function validateRegisterForm() {
        let isValid = true;

        // Clear previous errors
        document.querySelectorAll('.error-message').forEach(el => el.textContent = '');

        const name = document.getElementById('register-name').value.trim();
        const email = document.getElementById('register-email').value.trim();
        const password = document.getElementById('register-password').value;
        const confirm = document.getElementById('register-confirm').value;

        if (!name) {
            document.getElementById('register-name-error').textContent = 'Name is required';
            isValid = false;
        } else if (name.length < 2) {
            document.getElementById('register-name-error').textContent = 'Name must be at least 2 characters';
            isValid = false;
        }

        if (!email) {
            document.getElementById('register-email-error').textContent = 'Email is required';
            isValid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            document.getElementById('register-email-error').textContent = 'Please enter a valid email';
            isValid = false;
        }

        if (!checkPassword()) {
            document.getElementById('register-password-error').textContent = 'Password does not meet requirements';
            isValid = false;
        }

        if (!confirm) {
            document.getElementById('register-confirm-error').textContent = 'Please confirm your password';
            isValid = false;
        } else if (password !== confirm) {
            document.getElementById('register-confirm-error').textContent = 'Passwords do not match';
            isValid = false;
        }

        return isValid;
    }

    // Attach validation functions
    window.validateLoginForm = validateLoginForm;
    window.validateRegisterForm = validateRegisterForm;
});