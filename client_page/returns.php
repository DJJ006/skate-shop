<?php
session_start();
include '../db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | RETURNS & REFUNDS</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php include 'header.php'; ?>

<main class="legal-container">
    <div class="section-header" style="margin-bottom: 20px;">
        <h3>RETURNS <span class="header-span">& REFUNDS</span></h3>
        <p style="color: var(--charcoal); font-size: 1.2rem; font-weight: 600; margin: 0; padding-bottom: 5px;">HASSLE-FREE RETURNS BECAUSE WE GOT YOUR BACK.</p>
    </div>

    <div class="legal-card">
        <div class="legal-section">
            <h4>RETURN ELIGIBILITY</h4>
            <p>We have a <strong>30-day return policy</strong>, which means you have 30 days after receiving your item to request a return.</p>
            <p>To be eligible for a return, your item must be in the same condition that you received it, unworn or unused, with tags, and in its original packaging. You’ll also need the receipt or proof of purchase.</p>
        </div>

        <div class="legal-section">
            <h4>HOW TO START A RETURN</h4>
            <p>To start a return, you can contact us via our <a href="contact-us.php" style="color: var(--primary); font-weight: bold; text-decoration: none;">Support Page</a>. Please include your order number and the reason for the return.</p>
            <p>If your return is accepted, we’ll send you a return shipping label, as well as instructions on how and where to send your package. Items sent back to us without first requesting a return will not be accepted.</p>
        </div>

        <div class="legal-section">
            <h4>NON-RETURNABLE ITEMS</h4>
            <p>Certain types of items cannot be returned due to hygiene, safety, or customization reasons. These include:</p>
            <ul>
                <li><strong>Custom Grip Tape:</strong> Decks that have been pre-gripped at your request.</li>
                <li><strong>Used Hardware:</strong> Bearings, wheels, or trucks that show signs of wear or installation.</li>
                <li><strong>Underwear & Socks:</strong> If the original packaging has been opened.</li>
                <li><strong>Sale Items & Gift Cards:</strong> Only regular priced items may be refunded.</li>
            </ul>
        </div>

        <div class="legal-section">
            <h4>EXCHANGES</h4>
            <p>The fastest way to ensure you get what you want is to return the item you have, and once the return is accepted, make a separate purchase for the new item. We do not offer direct item-for-item exchanges to prevent inventory discrepancies.</p>
        </div>

        <div class="legal-section">
            <h4>DAMAGED OR DEFECTIVE ITEMS</h4>
            <p>Please inspect your order upon reception and contact us immediately if the item is defective, damaged, or if you received the wrong item, so that we can evaluate the issue and make it right.</p>
            <p>If your skateboard deck snaps or delaminates within the first 14 days of normal use, please contact us with photos. <em>Note: Focus breaks, tail wear, and water damage are not considered manufacturing defects.</em></p>
        </div>

        <div class="legal-section">
            <h4>REFUNDS & TIMELINES</h4>
            <p>We will notify you once we’ve received and inspected your return, and let you know if the refund was approved or not. If approved, you’ll be automatically refunded on your original payment method within <strong>5-10 business days</strong>.</p>
            <p>Please remember it can take some time for your bank or credit card company to process and post the refund too. If more than 15 business days have passed since we approved your return, please contact us.</p>
        </div>
        
        <div class="legal-section">
            <h4>RETURN SHIPPING COSTS</h4>
            <p>Unless the item is defective or we made a shipping error, <strong>the customer is responsible for return shipping costs</strong>. The cost of return shipping will be deducted from your refund if you use our provided shipping label.</p>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

</body>
</html>
