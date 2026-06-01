<?php
session_start();
include '../db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | TERMS OF SERVICE</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php include 'header.php'; ?>

<main class="legal-container">
    <div class="section-header" style="margin-bottom: 20px;">
        <h3>TERMS <span class="header-span">OF SERVICE</span></h3>
        <p style="color: var(--charcoal); font-size: 1.2rem; font-weight: 600; margin: 0; padding-bottom: 5px;">THE RULES OF THE STREET MARKET.</p>
    </div>

    <div class="legal-card">
        <p style="font-family: 'Inter', sans-serif; font-size: 1rem; color: #666; margin-bottom: 30px; font-style: italic;">
            Last Updated: June 1, 2026<br>
            Note: This is an academic project demonstration. While written to emulate real Terms of Service, this platform does not operate as a real commercial entity.
        </p>

        <div class="legal-section">
            <h4>1. ACCEPTANCE OF TERMS</h4>
            <p>By accessing and using the SkateShop platform ("the Service"), you agree to be bound by these Terms of Service. If you do not agree to these terms, you may not use the Service. These terms apply to all visitors, users, and others who access or use the Service.</p>
        </div>

        <div class="legal-section">
            <h4>2. USER ACCOUNTS & RESPONSIBILITIES</h4>
            <p>When you create an account with us, you must provide information that is accurate, complete, and current at all times. Failure to do so constitutes a breach of the Terms, which may result in immediate termination of your account on our Service.</p>
            <ul>
                <li>You are responsible for safeguarding the password that you use to access the Service.</li>
                <li>You agree not to disclose your password to any third party.</li>
                <li>You must notify us immediately upon becoming aware of any breach of security or unauthorized use of your account.</li>
                <li>You may not use as a username the name of another person or entity or that is not lawfully available for use, a name or trademark that is subject to any rights of another person or entity other than you without appropriate authorization, or a name that is otherwise offensive, vulgar or obscene.</li>
            </ul>
        </div>

        <div class="legal-section">
            <h4>3. MARKETPLACE RULES & ACCEPTABLE USE</h4>
            <p>The SkateShop platform includes a community marketplace where users can sell gear. By using the marketplace, you agree to the following:</p>
            <ul>
                <li><strong>Accurate Listings:</strong> All product listings must accurately describe the condition of the item. Using misleading photos or descriptions is prohibited.</li>
                <li><strong>Prohibited Items:</strong> You may not sell stolen goods, counterfeit items, or items entirely unrelated to skateboarding and streetwear culture.</li>
                <li><strong>Admin Approval:</strong> All marketplace items are subject to review by SkateShop administrators before going live. We reserve the right to reject any listing without explanation.</li>
                <li><strong>Transaction Integrity:</strong> Attempting to bypass the platform's purchasing system to avoid fees or tracking is strictly prohibited.</li>
            </ul>
        </div>

        <div class="legal-section">
            <h4>4. CONTENT OWNERSHIP & LICENSING</h4>
            <p>Our Service allows you to post, link, store, share and otherwise make available certain information, text, graphics, videos ("Reels"), or other material ("Content"). You are responsible for the Content that you post to the Service, including its legality, reliability, and appropriateness.</p>
            <p>By posting Content to the Service, you grant us the right and license to use, modify, publicly perform, publicly display, reproduce, and distribute such Content on and through the Service. You retain any and all of your rights to any Content you submit, post or display on or through the Service and you are responsible for protecting those rights.</p>
        </div>

        <div class="legal-section">
            <h4>5. PROHIBITED ACTIVITIES</h4>
            <p>You agree not to engage in any of the following prohibited activities:</p>
            <ul>
                <li>Using the Service for any illegal purpose or in violation of any local, state, national, or international law.</li>
                <li>Harassing, abusing, or harming another person on the platform, including in Q&A sections or Shoutouts.</li>
                <li>Attempting to interfere with, compromise the system integrity or security, or decipher any transmissions to or from the servers running the Service.</li>
                <li>Uploading invalid data, viruses, worms, or other software agents through the Service.</li>
                <li>Automated use of the system, such as using scripts to send comments or messages, or using any data mining, robots, or similar data gathering and extraction tools.</li>
            </ul>
        </div>

        <div class="legal-section">
            <h4>6. ACCOUNT SUSPENSION & TERMINATION</h4>
            <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms. Upon termination, your right to use the Service will immediately cease. If you wish to terminate your account, you may simply discontinue using the Service or contact support for account deletion.</p>
        </div>
        
        <div class="legal-section">
            <h4>7. INTELLECTUAL PROPERTY</h4>
            <p>The Service and its original content (excluding Content provided by users), features, and functionality are and will remain the exclusive property of SkateShop and its licensors. The Service is protected by copyright, trademark, and other laws of both the country of operation and foreign countries. Our trademarks and trade dress may not be used in connection with any product or service without the prior written consent of SkateShop.</p>
        </div>

        <div class="legal-section">
            <h4>8. LIMITATION OF LIABILITY</h4>
            <p>In no event shall SkateShop, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from (i) your access to or use of or inability to access or use the Service; (ii) any conduct or content of any third party on the Service; (iii) any content obtained from the Service; and (iv) unauthorized access, use or alteration of your transmissions or content, whether based on warranty, contract, tort (including negligence) or any other legal theory.</p>
        </div>
        
        <div class="legal-section">
            <h4>9. CHANGES TO TERMS</h4>
            <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. If a revision is material, we will try to provide at least 30 days' notice prior to any new terms taking effect. What constitutes a material change will be determined at our sole discretion.</p>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

</body>
</html>
