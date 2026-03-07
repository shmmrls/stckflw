<?php
require_once __DIR__ . '/includes/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$pageCss = '';

include __DIR__ . '/includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="policy-page">
  <div class="policy-container">
    <div class="policy-header">
      <h1 class="policy-title">Terms of Service</h1>
      <p class="policy-subtitle">Please read these terms carefully before using our site or placing an order</p>
    </div>

    <section class="policy-section">
      <h2 class="section-title">1. Agreement to Terms</h2>
      <p class="section-text">By accessing or using this website, you agree to be bound by these Terms of Service and our Privacy Policy. If you do not agree, please do not use the site.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">2. Eligibility</h2>
      <p class="section-text">You must be at least the age of majority in your jurisdiction to use this site or place orders.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">3. Accounts</h2>
      <ul class="section-list">
        <li>You are responsible for maintaining the confidentiality of your account credentials.</li>
        <li>You agree to provide accurate, current, and complete information for your store or household inventory.</li>
        <li>You are responsible for all activities under your account, including inventory management and purchase orders.</li>
        <li>Grocery vendors must provide valid business information for store registration.</li>
      </ul>
    </section>

    <section class="policy-section">
      <h2 class="section-title">4. Inventory Management & Purchase Orders</h2>
      <ul class="section-list">
        <li>All inventory data and purchase orders are subject to verification and approval.</li>
        <li>We reserve the right to suspend accounts for fraudulent inventory reporting or purchase order activities.</li>
        <li>Prices and supplier information are provided by vendors and may vary based on market conditions.</li>
        <li>Expiry dates and inventory levels must be maintained accurately for system integrity.</li>
        <li>Barcode generation and product catalog management must comply with industry standards.</li>
      </ul>
    </section>

    <section class="policy-section">
      <h2 class="section-title">5. Supplier & Store Relationships</h2>
      <p class="section-text">Purchase orders and supplier communications are facilitated through our platform. We are not responsible for supplier performance, delivery delays, or product quality issues. Store vendors are responsible for maintaining accurate supplier information and fulfilling purchase order obligations.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">6. Data Accuracy & System Integrity</h2>
      <p class="section-text">Users must maintain accurate inventory data, including quantities, expiry dates, and product information. Intentional manipulation of inventory data, waste tracking, or analytics may result in account suspension. The system relies on accurate data for proper functionality and user insights.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">7. Intellectual Property</h2>
      <p class="section-text">All content on this site, including logos, images, text, and designs, is owned by or licensed to us and protected by applicable laws. You may not use, reproduce, or distribute content without permission.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">8. Prohibited Uses</h2>
      <ul class="section-list">
        <li>Using the site for unlawful purposes or fraudulent inventory management</li>
        <li>Interfering with security or integrity of the inventory management system</li>
        <li>Infringing the rights of others, including supplier and vendor relationships</li>
        <li>Manipulating waste tracking data, expiry alerts, or analytics</li>
        <li>Using barcode generation features for counterfeit or unauthorized products</li>
        <li>Sharing confidential inventory data with unauthorized third parties</li>
      </ul>
    </section>

    <section class="policy-section">
      <h2 class="section-title">9. Disclaimers</h2>
      <p class="section-text">The site and products are provided "as is" without warranties of any kind, to the fullest extent permitted by law.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">10. Limitation of Liability</h2>
      <p class="section-text">To the maximum extent permitted by law, we are not liable for any indirect, incidental, special, consequential, or punitive damages, or lost profits arising from your use of the site or products.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">11. Indemnification</h2>
      <p class="section-text">You agree to indemnify and hold us harmless from any claims arising out of your use of the site or violation of these Terms.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">12. Governing Law</h2>
      <p class="section-text">These Terms are governed by the laws of our principal place of business, without regard to conflict of law principles. Venue for disputes will be in the courts located in that jurisdiction.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">13. Changes to Terms</h2>
      <p class="section-text">We may update these Terms from time to time. Continued use of the site after changes constitutes acceptance of the updated Terms.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">14. Contact</h2>
      <p class="section-text">Questions about these Terms? Contact us at <a href="mailto:support@stockflow.com">support@stockflow.com</a>.</p>
    </section>
  </div>
</main>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Montserrat', sans-serif; color: #1a1a1a; }
.policy-page { min-height: 100vh; padding: 100px 30px 60px; background: linear-gradient(to bottom, #fafafa 0%, #ffffff 100%); }
.policy-container { max-width: 900px; margin: 0 auto; }
.policy-header { text-align: center; margin-bottom: 40px; }
.policy-title { font-family: 'Playfair Display', serif; font-size: 44px; font-weight: 400; color: #0a0a0a; margin-bottom: 10px; }
.policy-subtitle { font-size: 14px; color: rgba(0,0,0,0.55); }
.policy-section { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); padding: 28px; margin-bottom: 18px; }
.section-title { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 400; color: #0a0a0a; margin-bottom: 10px; }
.section-text { font-size: 13px; color: rgba(0,0,0,0.75); line-height: 1.8; letter-spacing: 0.3px; }
.section-list { margin-left: 18px; display: grid; gap: 6px; }
.section-list li { font-size: 13px; color: rgba(0,0,0,0.75); line-height: 1.8; }
@media (max-width: 768px) {
  .policy-page { padding: 80px 20px 50px; }
  .policy-title { font-size: 32px; }
  .policy-section { padding: 22px; }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
