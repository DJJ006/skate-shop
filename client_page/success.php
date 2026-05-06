<?php
session_start();
$session_id = isset($_GET['session_id']) ? $_GET['session_id'] : 'unknown_session';
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
            transition: all 0.4s ease;
        }

        .success-title {
            margin: 0 0 1rem 0;
            font-size: clamp(2rem, 6vw, 3.5rem); /* Reduced to prevent overflow */
            transition: opacity 0.4s ease;
        }

        .success-text {
            color: #555;
            font-size: 1.15rem;
            margin-bottom: 2rem;
            transition: opacity 0.4s ease;
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

        /* Fix button color */
        .success-actions .btn-outline {
            color: var(--charcoal);
            border-color: var(--charcoal);
        }
        
        .success-actions .btn-outline:hover {
            background: var(--charcoal);
            color: var(--textwhite);
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container success-page">
    <div class="success-box">
        <i id="status-icon" class="fa-solid fa-hourglass-half success-icon"></i>
        <h1 id="status-heading" class="glitch-text success-title">
            PAYMENT <span class="text-primary">PROCESSING</span>
        </h1>
        <p id="status-text" class="success-text">We're confirming your payment...</p>

        <div class="success-actions">
            <a href="shop.php" class="btn btn-primary">BACK TO SHOP</a>
            <a href="client-profile.php" class="btn btn-outline">VIEW DASHBOARD</a>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
    const sessionId = "<?php echo htmlspecialchars($session_id, ENT_QUOTES, 'UTF-8'); ?>";
    const storageKey = 'payment_confirmed_' + sessionId;

    const icon = document.getElementById('status-icon');
    const heading = document.getElementById('status-heading');
    const text = document.getElementById('status-text');

    function setConfirmedState() {
        if (icon && heading && text) {
            icon.className = 'fa-solid fa-circle-check success-icon';
            icon.style.color = 'var(--green)'; // Assuming --green is defined in style.css or we can use #22c55e
            heading.innerHTML = 'PAYMENT <span class="text-primary">PROCESSED</span>';
            text.innerText = 'Payment confirmed!';
            
            // Ensure opacity is 1 in case of refresh
            icon.style.opacity = '1';
            heading.style.opacity = '1';
            text.style.opacity = '1';
        }
    }

    if (localStorage.getItem(storageKey) === 'true') {
        // Page was refreshed, show final state immediately
        setConfirmedState();
    } else {
        // Smoothly update the text after 2.5 seconds to show payment confirmed
        setTimeout(() => {
            if (icon && heading && text) {
                // Fade out
                icon.style.opacity = '0';
                heading.style.opacity = '0';
                text.style.opacity = '0';

                setTimeout(() => {
                    // Update content
                    setConfirmedState();
                    localStorage.setItem(storageKey, 'true');
                }, 400); // Wait for the fade-out transition
            }
        }, 2500);
    }
</script>

</body>
</html>