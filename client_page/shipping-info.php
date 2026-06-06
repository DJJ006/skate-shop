<?php
session_start();
include '../db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | SHIPPING INFORMATION</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
    <script src="../assets/script.js" defer></script>
</head>
<body>

<?php include 'header.php'; ?>

<main class="legal-container">
    <div class="section-header" style="margin-bottom: 20px;">
        <h3>SHIPPING <span class="header-span">INFORMATION</span></h3>
        <p style="color: var(--charcoal); font-size: 1.2rem; font-weight: 600; margin: 0; padding-bottom: 5px;">EVERYTHING YOU NEED TO KNOW ABOUT GETTING YOUR GEAR.</p>
    </div>

    <div class="legal-card">
        <div class="legal-section">
            <h4>ORDER PROCESSING TIMES</h4>
            <p>All orders are processed within <strong>1 to 2 business days</strong> (excluding weekends and holidays) after receiving your order confirmation email. You will receive another notification when your order has shipped.</p>
            <p>If we are experiencing a high volume of orders, shipments may be delayed by a few days. Please allow additional days in transit for delivery. If there will be a significant delay in shipment of your order, we will contact you via email or telephone.</p>
        </div>

        <div class="legal-section">
            <h4>SHIPPING METHODS & COSTS</h4>
            <p>Shipping charges for your order will be calculated and displayed at checkout. We offer the following shipping methods:</p>
            <table class="legal-table">
                <thead>
                    <tr>
                        <th>SHIPPING METHOD</th>
                        <th>ESTIMATED DELIVERY TIME</th>
                        <th>COST</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Standard Domestic</td>
                        <td>3-5 Business Days</td>
                        <td>$5.00</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="legal-section">
            <h4>INTERNATIONAL SHIPPING</h4>
            <p>We offer international shipping to most countries worldwide. Your order may be subject to import duties and taxes (including VAT), which are incurred once a shipment reaches your destination country. SkateShop is not responsible for these charges if they are applied and are your responsibility as the customer.</p>
        </div>

        <div class="legal-section">
            <h4>TRACKING YOUR ORDER</h4>
            <p>When your order has shipped, you will receive an email notification from us which will include a tracking number you can use to check its status. Please allow 48 hours for the tracking information to become available.</p>
            <p>If you haven’t received your order within 5 days of receiving your shipping confirmation email, please contact us at <a href="contact-us.php" style="color: var(--primary); font-weight: bold; text-decoration: none;">Support</a> with your name and order number, and we will look into it for you.</p>
        </div>

        <div class="legal-section">
            <h4>LOST OR DELAYED SHIPMENTS</h4>
            <p>While we strive for perfect delivery every time, sometimes issues happen with our carrier partners.</p>
            <ul>
                <li><strong>Delayed Shipments:</strong> If your order is significantly delayed past the estimated delivery date, please contact us.</li>
                <li><strong>Lost/Stolen Packages:</strong> SkateShop is not responsible for lost or stolen packages confirmed to be delivered to the address entered for an order. Upon inquiry, SkateShop will confirm delivery to the provided address, date of delivery, tracking information and shipping carrier information for the customer to investigate.</li>
            </ul>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

</body>
</html>

