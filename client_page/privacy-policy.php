<?php
session_start();
include '../db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | PRIVACY POLICY</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php include 'header.php'; ?>

<main class="legal-container">
    <div class="section-header" style="margin-bottom: 20px;">
        <h3>PRIVACY <span class="header-span">POLICY</span></h3>
        <p style="color: var(--charcoal); font-size: 1.2rem; font-weight: 600; margin: 0; padding-bottom: 5px;">HOW WE PROTECT YOUR DATA IN THE SKATESHOP UNIVERSE.</p>
    </div>

    <div class="legal-card">
        <p style="font-family: 'Inter', sans-serif; font-size: 1rem; color: #666; margin-bottom: 30px; font-style: italic;">
            Last Updated: June 1, 2026<br>
            Note: SkateShop is an academic project created for educational purposes. This website does not operate as a real commercial entity, and no real financial transactions are processed.
        </p>

        <div class="legal-section">
            <h4>1. INTRODUCTION</h4>
            <p>Welcome to SkateShop. We respect your privacy and are committed to protecting your personal data in accordance with the General Data Protection Regulation (EU) 2016/679 ("GDPR") and applicable Latvian data protection laws.</p>
            <p>This Privacy Policy explains what information we collect, how we use it, how long we retain it, and what rights you have regarding your personal data.</p>
            <p><strong>Important Notice:</strong> SkateShop is an academic project created for educational purposes. This website does not operate as a real commercial entity, and no real financial transactions are processed.</p>
        </div>

        <div class="legal-section">
            <h4>2. DATA CONTROLLER</h4>
            <p>The Data Controller responsible for your personal data is:</p>
            <p>
                <strong>SkateShop</strong><br>
                Liepāja, Latvia<br>
                Email: <a href="mailto:privacy@skateshop-universe.com">privacy@skateshop-universe.com</a>
            </p>
            <p>For any questions regarding this Privacy Policy or your personal data, please contact us using the information above.</p>
        </div>

        <div class="legal-section">
            <h4>3. PERSONAL DATA WE COLLECT</h4>
            <p>We may collect the following categories of personal data:</p>
            <ul>
                <li><strong>Identity Data:</strong> Username, first name, last name, profile image.</li>
                <li><strong>Contact Data:</strong> Email address, telephone number, billing address, shipping address.</li>
                <li><strong>Account Data:</strong> Login credentials, password (stored in encrypted form), account preferences.</li>
                <li><strong>Transaction Data:</strong> Products ordered, order history, purchase dates, order amounts.</li>
                <li><strong>Technical Data:</strong> IP address, browser type and version, device information, operating system, time zone settings, website usage statistics.</li>
                <li><strong>Profile Data:</strong> Wishlist items, product ratings, reviews, shopping preferences.</li>
                <li><strong>Communication Data:</strong> Messages sent through contact forms, customer support requests, feedback submissions.</li>
            </ul>
        </div>

        <div class="legal-section">
            <h4>4. HOW WE COLLECT YOUR DATA</h4>
            <p>We collect personal data through:</p>
            <ul>
                <li>User registration forms</li>
                <li>Product purchases</li>
                <li>Contact forms</li>
                <li>Newsletter subscriptions</li>
                <li>Website analytics tools</li>
                <li>Cookies and similar technologies</li>
                <li>Direct communication with users</li>
            </ul>
        </div>

        <div class="legal-section">
            <h4>5. LEGAL BASIS FOR PROCESSING</h4>
            <p>We process personal data under one or more of the following legal bases:</p>
            <ul>
                <li><strong>Contract Performance:</strong> Processing required to fulfill orders, manage accounts, and provide requested services.</li>
                <li><strong>Legal Obligation:</strong> Processing required to comply with applicable laws and regulations.</li>
                <li><strong>Legitimate Interests:</strong> Processing necessary for fraud prevention, website security, service improvements, and statistical analysis.</li>
                <li><strong>Consent:</strong> Processing based on your explicit consent, including marketing communications, newsletter subscriptions, and non-essential cookies. You may withdraw your consent at any time.</li>
            </ul>
        </div>

        <div class="legal-section">
            <h4>6. HOW WE USE YOUR DATA</h4>
            <p>We use your personal data to:</p>
            <ul>
                <li>Create and manage user accounts</li>
                <li>Process orders and transactions</li>
                <li>Deliver purchased products</li>
                <li>Provide customer support</li>
                <li>Improve website performance</li>
                <li>Prevent fraud and abuse</li>
                <li>Maintain website security</li>
                <li>Comply with legal obligations</li>
                <li>Send newsletters and promotional materials (with consent)</li>
            </ul>
        </div>

        <div class="legal-section">
            <h4>7. COOKIES AND TRACKING TECHNOLOGIES</h4>
            <p>Our website uses cookies and similar technologies.</p>
            <ul>
                <li><strong>Essential Cookies:</strong> Required for user authentication, shopping cart functionality, and security purposes.</li>
                <li><strong>Analytics Cookies:</strong> Used to measure website traffic, analyze visitor behavior, and improve website functionality.</li>
                <li><strong>Marketing Cookies:</strong> Used to display relevant advertisements and measure advertising effectiveness.</li>
            </ul>
            <p>Users may manage cookie preferences through their browser settings or cookie consent banner.</p>
        </div>

        <div class="legal-section">
            <h4>8. DATA SHARING</h4>
            <p>We do not sell personal data. However, we may share information with:</p>
            <ul>
                <li><strong>Payment Providers:</strong> To process transactions securely.</li>
                <li><strong>Delivery Services:</strong> To deliver purchased products.</li>
                <li><strong>Hosting Providers:</strong> To operate and maintain the website.</li>
                <li><strong>Analytics Providers:</strong> To understand website usage.</li>
                <li><strong>Government Authorities:</strong> Where required by law.</li>
            </ul>
            <p>All third parties are required to protect your personal data and process it only for specified purposes.</p>
        </div>

        <div class="legal-section">
            <h4>9. INTERNATIONAL DATA TRANSFERS</h4>
            <p>Some service providers may be located outside the European Economic Area (EEA).</p>
            <p>Where such transfers occur, we ensure appropriate safeguards are implemented, including:</p>
            <ul>
                <li>Standard Contractual Clauses (SCCs)</li>
                <li>Adequacy decisions approved by the European Commission</li>
                <li>Other GDPR-compliant mechanisms</li>
            </ul>
        </div>

        <div class="legal-section">
            <h4>10. DATA SECURITY</h4>
            <p>We implement appropriate technical and organizational security measures including:</p>
            <ul>
                <li>SSL/TLS encryption</li>
                <li>Password hashing</li>
                <li>Secure database storage</li>
                <li>Access control restrictions</li>
                <li>Regular security updates</li>
            </ul>
            <p>Despite our efforts, no online system can guarantee absolute security.</p>
        </div>

        <div class="legal-section">
            <h4>11. DATA RETENTION</h4>
            <p>We retain personal data only for as long as necessary. Typical retention periods include:</p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-family: 'Inter', sans-serif;">
                <thead>
                    <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; text-align: left;">
                        <th style="padding: 12px; font-weight: 600;">Data Type</th>
                        <th style="padding: 12px; font-weight: 600;">Retention Period</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px;">User Account Data</td>
                        <td style="padding: 12px;">Until account deletion</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px;">Order Records</td>
                        <td style="padding: 12px;">Up to 5 years</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px;">Customer Support Messages</td>
                        <td style="padding: 12px;">Up to 3 years</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px;">Technical Logs</td>
                        <td style="padding: 12px;">Up to 12 months</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px;">Marketing Consent Records</td>
                        <td style="padding: 12px;">Until consent withdrawal</td>
                    </tr>
                </tbody>
            </table>

            <p>After expiration, data is securely deleted or anonymized.</p>
        </div>

        <div class="legal-section">
            <h4>12. AUTOMATED DECISION-MAKING</h4>
            <p>SkateShop does not use automated decision-making that produces legal or similarly significant effects on users.</p>
            <p>Analytics tools may be used to personalize content and improve user experience.</p>
        </div>

        <div class="legal-section">
            <h4>13. YOUR GDPR RIGHTS</h4>
            <p>Under GDPR, you have the following rights:</p>
            <ul>
                <li><strong>Right of Access:</strong> Request a copy of your personal data.</li>
                <li><strong>Right to Rectification:</strong> Request correction of inaccurate data.</li>
                <li><strong>Right to Erasure:</strong> Request deletion of your personal data.</li>
                <li><strong>Right to Restrict Processing:</strong> Request limited processing of your information.</li>
                <li><strong>Right to Data Portability:</strong> Receive your data in a machine-readable format.</li>
                <li><strong>Right to Object:</strong> Object to processing based on legitimate interests.</li>
                <li><strong>Right to Withdraw Consent:</strong> Withdraw previously given consent at any time.</li>
            </ul>
            <p>To exercise any of these rights, contact us at: <a href="mailto:privacy@skateshop-universe.com">privacy@skateshop-universe.com</a></p>
        </div>

        <div class="legal-section">
            <h4>14. CHILDREN'S PRIVACY</h4>
            <p>Our services are not intended for individuals under the age of 13.</p>
            <p>We do not knowingly collect personal information from children under 13 years of age. If we become aware that such data has been collected, it will be removed promptly.</p>
        </div>

        <div class="legal-section">
            <h4>15. COMPLAINTS</h4>
            <p>If you believe your personal data has been processed unlawfully, you have the right to lodge a complaint with your local data protection authority.</p>
            <p>
                <strong>In Latvia:</strong><br>
                Data State Inspectorate (Datu valsts inspekcija)<br>
                Website: <a href="https://www.dvi.gov.lv" target="_blank">https://www.dvi.gov.lv</a><br>
                Email: <a href="mailto:pasts@dvi.gov.lv">pasts@dvi.gov.lv</a>
            </p>
        </div>

        <div class="legal-section">
            <h4>16. CHANGES TO THIS POLICY</h4>
            <p>We may update this Privacy Policy from time to time.</p>
            <p>Any updates will be published on this page together with a revised "Last Updated" date. We encourage users to review this policy periodically.</p>
        </div>

        <div class="legal-section">
            <h4>17. CONTACT US</h4>
            <p>If you have questions regarding this Privacy Policy, please contact:</p>
            <ul>
                <li><strong>Email:</strong> <a href="mailto:skateshopp2026@gmail.com">skateshopp2026@gmail.com</a></li>
                <li><strong>Location:</strong> Liepāja, Latvia</li>
                <li><strong>Data Protection Contact:</strong> <a href="mailto:skateshopp2026@gmail.com">skateshopp2026@gmail.com</a></li>
            </ul>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

</body>
</html>