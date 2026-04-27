<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | PAYMENT STATUS</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .success-page {
            min-height: 70vh;
            padding: 120px 20px 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-box {
            max-width: 760px;
            width: 100%;
            text-align: center;
            background: var(--textwhite);
            border: 4px solid var(--charcoal);
            box-shadow: 8px 8px 0px var(--charcoal);
            padding: 3rem 2rem;
        }

        .success-icon {
            font-size: 5rem;
            color: #f2b600;
            margin-bottom: 1rem;
        }

        .success-title {
            margin: 0 0 1rem 0;
        }

        .success-text {
            color: #555;
            font-size: 1.15rem;
            margin-bottom: 2rem;
        }

        .success-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .success-actions a {
            text-decoration: none;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container success-page">
    <div class="success-box">
        <i class="fa-solid fa-hourglass-half success-icon"></i>
        <h1 class="glitch-text success-title">
            PAYMENT <span class="text-primary">PROCESSING</span>
        </h1>
        <p class="success-text">We're confirming your payment...</p>

        <div class="success-actions">
            <a href="shop.php" class="btn btn-primary">BACK TO SHOP</a>
            <a href="client-profile.php" class="btn btn-outline">VIEW DASHBOARD</a>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

</body>
</html>