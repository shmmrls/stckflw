<?php
require_once __DIR__ . '/includes/config.php';

$pageCss = '<link rel="stylesheet" href="./includes/style/contact.css">';

require_once __DIR__ . '/includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="contact-page">
    <div class="landing-container">

        <!-- Hero -->
        <section class="contact-hero">
            <h1 class="hero-tagline">Get In Touch</h1>
            <p class="hero-subtitle">Have questions, feedback, or need support? We'd love to hear from you.</p>
        </section>

        <!-- Main Contact Grid -->
        <section class="contact-section">
            <div class="contact-grid">

                <!-- Left: Contact Form -->
                <div class="contact-form-card">
                    <h2 class="contact-card-title">Send Us a Message</h2>
                    <p class="contact-card-subtitle">We typically respond within 24–48 hours on business days.</p>

                    <?php if (isset($_GET['sent'])): ?>
                    <div class="form-alert form-alert-success">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span>Message sent! We'll get back to you soon.</span>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['error']) && isset($_SESSION['form_errors'])): ?>
                    <div class="form-alert form-alert-error">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <div>
                            <strong>Please fix the following errors:</strong>
                            <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                                <?php foreach ($_SESSION['form_errors'] as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php 
                        // Clear errors after displaying
                        unset($_SESSION['form_errors']);
                    endif; ?>

                    <form class="contact-form" action="contact_submit.php" method="POST" enctype="multipart/form-data" novalidate>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="contact_name">
                                    Full Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="contact_name"
                                    name="name"
                                    class="form-input"
                                    placeholder="Your full name"
                                    required
                                    maxlength="100"
                                    value="<?php echo isset($_SESSION['form_data']['name']) ? htmlspecialchars($_SESSION['form_data']['name']) : ''; ?>"
                                >
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="contact_email">
                                    Email Address <span class="required">*</span>
                                </label>
                                <input
                                    type="email"
                                    id="contact_email"
                                    name="email"
                                    class="form-input"
                                    placeholder="you@example.com"
                                    required
                                    maxlength="150"
                                    value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact_subject">
                                Subject <span class="required">*</span>
                            </label>
                            <select id="contact_subject" name="subject" class="form-input form-select" required>
                                <option value="" disabled <?php echo !isset($_SESSION['form_data']['subject']) ? 'selected' : ''; ?>>Select a topic…</option>
                                <option value="General Inquiry" <?php echo (isset($_SESSION['form_data']['subject']) && $_SESSION['form_data']['subject'] === 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="Technical Support" <?php echo (isset($_SESSION['form_data']['subject']) && $_SESSION['form_data']['subject'] === 'Technical Support') ? 'selected' : ''; ?>>Technical Support</option>
                                <option value="Account Issues" <?php echo (isset($_SESSION['form_data']['subject']) && $_SESSION['form_data']['subject'] === 'Account Issues') ? 'selected' : ''; ?>>Account Issues</option>
                                <option value="Feature Request" <?php echo (isset($_SESSION['form_data']['subject']) && $_SESSION['form_data']['subject'] === 'Feature Request') ? 'selected' : ''; ?>>Feature Request</option>
                                <option value="Partnership / Business" <?php echo (isset($_SESSION['form_data']['subject']) && $_SESSION['form_data']['subject'] === 'Partnership / Business') ? 'selected' : ''; ?>>Partnership / Business</option>
                                <option value="Other" <?php echo (isset($_SESSION['form_data']['subject']) && $_SESSION['form_data']['subject'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact_message">
                                Message <span class="required">*</span>
                            </label>
                            <textarea
                                id="contact_message"
                                name="message"
                                class="form-input form-textarea"
                                placeholder="Tell us how we can help…"
                                required
                                minlength="10"
                                maxlength="2000"
                                rows="6"
                            ><?php echo isset($_SESSION['form_data']['message']) ? htmlspecialchars($_SESSION['form_data']['message']) : ''; ?></textarea>
                            <span class="char-count" id="charCount">0 / 2000</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact_attachment">
                                Screenshot / Attachment (Optional)
                            </label>
                            <input
                                type="file"
                                id="contact_attachment"
                                name="attachment"
                                class="form-input"
                                accept="image/*,.pdf,.doc,.docx,.txt"
                                style="padding: 8px;"
                            >
                            <small style="color: rgba(0,0,0,0.55); font-size: 12px; margin-top: 4px; display: block;">
                                Supported formats: Images (JPG, PNG, GIF), PDF, Word documents, Text files. Max size: 5MB
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-submit">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            Send Message
                        </button>
                    </form>
                </div>

                <!-- Right: Info Cards -->
                <div class="contact-info-col">

                    <!-- Email -->
                    <div class="info-card">
                        <div class="info-card-icon">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </div>
                        <div class="info-card-body">
                            <h3 class="info-card-title">Email Us Directly</h3>
                            <p class="info-card-desc">Reach us anytime at our official support address.</p>
                            <a href="mailto:stockflowg6@gmail.com" class="info-card-link">
                                stockflowg6@gmail.com
                            </a>
                        </div>
                    </div>

                    <!-- Response Time -->
                    <div class="info-card">
                        <div class="info-card-icon">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div class="info-card-body">
                            <h3 class="info-card-title">Response Time</h3>
                            <p class="info-card-desc">We aim to respond to all inquiries within <strong>24–48 hours</strong> on business days.</p>
                        </div>
                    </div>

                    <!-- What to expect -->
                    <div class="info-card">
                        <div class="info-card-icon">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                        </div>
                        <div class="info-card-body">
                            <h3 class="info-card-title">What to Include</h3>
                            <div class="info-feature-list">
                                <div class="info-feature-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <span>Your account email (if applicable)</span>
                                </div>
                                <div class="info-feature-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <span>A clear description of your issue</span>
                                </div>
                                <div class="info-feature-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <span>Steps to reproduce (for bugs)</span>
                                </div>
                                <div class="info-feature-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <span>Screenshots if relevant</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- CTA Back to Home -->
        <section class="cta-section">
            <h2 class="cta-title">Not Sure Where to Start?</h2>
            <p class="cta-description">Explore StockFlow and find the right plan for you — whether you're a household consumer or running a grocery store.</p>
            <div class="cta-buttons">
                <a href="index.php" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Back to Home
                </a>
                <a href="about.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    Learn More
                </a>
            </div>
        </section>

    </div>
</main>

<script>
    // Live character counter for textarea
    const textarea = document.getElementById('contact_message');
    const counter  = document.getElementById('charCount');
    if (textarea && counter) {
        // Initialize counter with existing content
        const initialLength = textarea.value.length;
        counter.textContent = initialLength + ' / 2000';
        counter.style.color = initialLength >= 1800 ? '#dc2626' : 'rgba(0,0,0,0.35)';
        
        textarea.addEventListener('input', function () {
            const len = this.value.length;
            counter.textContent = len + ' / 2000';
            counter.style.color = len >= 1800 ? '#dc2626' : 'rgba(0,0,0,0.35)';
        });
    }

    // Minimal client-side validation feedback
    const form = document.querySelector('.contact-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            const inputs = form.querySelectorAll('[required]');
            let valid = true;
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('input-error');
                    valid = false;
                } else {
                    input.classList.remove('input-error');
                }
            });
            if (!valid) {
                e.preventDefault();
            }
        });

        form.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('input', function () {
                this.classList.remove('input-error');
            });
        });
    }
</script>

<?php 
    // Clear form data after displaying
    if (isset($_SESSION['form_data'])) {
        unset($_SESSION['form_data']);
    }
    require_once __DIR__ . '/includes/footer.php'; 
?>