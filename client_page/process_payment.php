<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SkateShop | PAYMENT STATUS</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<main class="container" style="padding-top: 150px; text-align: center; min-height: 70vh;">
    <i class="fa-solid fa-hourglass-half" style="font-size: 5rem; color: #f2b600; margin-bottom: 20px;"></i>
    <h1 class="glitch-text" style="font-size: 3rem;">PAYMENT <span class="text-primary">PROCESSING</span></h1>
    <p style="color: #555; font-size: 1.2rem; margin-bottom: 40px;">
        We are waiting for payment confirmation from Stripe.
    </p>

    <div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
        <a href="shop.php" class="btn btn-primary" style="text-decoration: none;">BACK TO SHOP</a>
        <a href="client-profile.php" class="btn btn-outline" style="text-decoration: none;">VIEW DASHBOARD</a>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>