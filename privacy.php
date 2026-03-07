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
      <h1 class="policy-title">Privacy Policy</h1>
      <p class="policy-subtitle">How we collect, use, and protect your information</p>
    </div>

    <section class="policy-section">
      <h2 class="section-title">1. Overview</h2>
      <p class="section-text">This Privacy Policy explains what information we collect when you use our website, how we use and share it, and the choices you have. By using our site, you agree to the practices described here.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">2. Information We Collect</h2>
      <ul class="section-list">
        <li>Account information such as name, email, phone, and store details (for grocery vendors)</li>
        <li>Inventory data including product names, quantities, expiry dates, and pricing information</li>
        <li>Purchase orders, supplier information, and transaction records</li>
        <li>Barcode scanning data and product catalog information</li>
        <li>Waste tracking data for expired/spoiled items and consumption patterns</li>
        <li>Device, log, and usage data to improve site performance and security</li>
        <li>Communications you send to our support team and group members</li>
        <li>Location data for store registration and delivery services (if applicable)</li>
      </ul>
    </section>

    <section class="policy-section">
      <h2 class="section-title">3. How We Use Information</h2>
      <ul class="section-list">
        <li>To manage grocery inventory, track stock levels, and monitor expiry dates</li>
        <li>To facilitate purchase orders between stores and suppliers</li>
        <li>To provide waste tracking analytics and consumption insights</li>
        <li>To generate barcode labels and manage product catalogs</li>
        <li>To send expiry alerts and low-stock notifications</li>
        <li>To enable group collaboration for household inventory management</li>
        <li>To personalize your experience and improve our inventory management services</li>
        <li>To communicate about orders, updates, and customer support</li>
        <li>To prevent fraud, enforce policies, and ensure site security</li>
      </ul>
    </section>

    <section class="policy-section">
      <h2 class="section-title">4. Cookies and Similar Technologies</h2>
      <p class="section-text">We use cookies and similar technologies to remember preferences, analyze traffic, and improve functionality. You can control cookies through your browser settings. Disabling cookies may affect site features.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">5. Sharing of Information</h2>
      <p class="section-text">We may share information with service providers who help operate our business (such as payment processors, analytics services, and hosting providers). For grocery vendors, supplier information and purchase order details may be shared with relevant suppliers to fulfill orders. Group members may have access to shared inventory data within their designated groups. These providers are authorized to use your information only as necessary to provide services to us.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">6. Data Retention</h2>
      <p class="section-text">We retain information for as long as necessary to fulfill the purposes outlined in this policy unless a longer retention period is required or permitted by law.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">7. Your Rights</h2>
      <p class="section-text">Depending on your location, you may have rights to access, correct, or delete your personal information, or to object to or restrict certain processing. To exercise these rights, contact us using the details below.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">8. Children's Privacy</h2>
      <p class="section-text">Our services are not directed to children. We do not knowingly collect personal information from individuals under the age of 13 (or the applicable age in your jurisdiction).</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">9. Changes to This Policy</h2>
      <p class="section-text">We may update this Privacy Policy from time to time. Changes will be posted on this page with an updated effective date.</p>
    </section>

    <section class="policy-section">
      <h2 class="section-title">10. Contact Us</h2>
      <p class="section-text">If you have questions about this Privacy Policy or your information, please contact us at <a href="mailto:support@stockflow.com">support@stockflow.com</a>.</p>
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
