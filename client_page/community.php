<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | Community</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <script src="../assets/script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .community-header {
            text-align: center;
            padding: 4rem 1rem 2rem 1rem;
            border-bottom: 4px solid var(--charcoal);
            background-color: var(--textwhite);
            background-image: radial-gradient(var(--charcoal) 1px, transparent 1px);
            background-size: 20px 20px;
            background-position: 0 0;
        }

        .community-header h1 {
            font-size: 5rem;
            margin-bottom: 1rem;
            color: var(--charcoal);
            text-shadow: 4px 4px 0px var(--primary);
        }

        .community-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            padding: 4rem 0;
        }

        .community-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 3rem;
            text-decoration: none;
            color: var(--charcoal);
            background: var(--textwhite);
            border: 4px solid var(--charcoal);
            box-shadow: 8px 8px 0px var(--charcoal);
            transition: all 0.2s ease-in-out;
            position: relative;
            overflow: hidden;
        }

        .community-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('https://www.transparenttextures.com/patterns/stardust.png');
            opacity: 0.3;
            pointer-events: none;
        }

        .community-card:hover {
            transform: translate(-4px, -4px);
            box-shadow: 12px 12px 0px var(--primary);
            border-color: var(--primary);
        }

        .community-icon {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
            text-shadow: 3px 3px 0px var(--charcoal);
            transition: transform 0.2s ease-in-out;
        }

        .community-card:hover .community-icon {
            transform: scale(1.1) rotate(-5deg);
        }

        .community-card h2 {
            font-family: 'Staatliches', sans-serif;
            font-size: 3rem;
            margin-bottom: 1rem;
            letter-spacing: 2px;
        }

        .community-card p {
            font-family: 'Inter', sans-serif;
            font-size: 1.2rem;
            opacity: 0.8;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .community-grid {
                grid-template-columns: 1fr;
            }
            .community-header h1 {
                font-size: 3.5rem;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="community-header noise-bg">
    <div class="container">
        <h1 class="glitch-text">THE <span class="text-primary">COMMUNITY</span> HUB</h1>
        <p style="font-family: 'Staatliches', sans-serif; font-size: 1.5rem; letter-spacing: 2px;">CONNECT / WATCH / READ / SHARE</p>
    </div>
</div>

<main class="container">
    <div class="community-grid">
        
        <!-- THE REELS -->
        <a href="reels.php" class="community-card">
            <span class="material-icons community-icon">movie_filter</span>
            <h2>THE REELS</h2>
            <p>Watch clips, skate edits, and community videos.</p>
        </a>

        <!-- THE MAG -->
        <a href="the-mag.php" class="community-card">
            <span class="material-icons community-icon">auto_stories</span>
            <h2>THE MAG</h2>
            <p>Posts, stories, skate culture, and shop updates.</p>
        </a>

        <!-- GUESTBOOK SHOUTOUTS -->
        <a href="#" class="community-card">
            <span class="material-icons community-icon">rate_review</span>
            <h2>GUESTBOOK SHOUTOUTS</h2>
            <p>Leave messages, reviews, and shoutouts for the community.</p>
        </a>

        <!-- QnA -->
        <a href="#" class="community-card">
            <span class="material-icons community-icon">forum</span>
            <h2>Q&A</h2>
            <p>Ask questions about the shop, products, or website.</p>
        </a>

    </div>
</main>

<?php include 'footer.php'; ?>

</body>
</html>
