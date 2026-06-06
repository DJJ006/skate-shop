<?php
session_start();
include '../db.php';

// Fetch all users for client-side search
$users_query = $conn->prepare("
    SELECT u.id, u.username, u.profile_pic, u.is_verified, u.created_at,
           (SELECT COUNT(*) FROM reels WHERE user_id = u.id AND is_approved = 1) as reels_count,
           (SELECT COUNT(*) FROM community_qna WHERE user_id = u.id) as qna_count
    FROM users u
    ORDER BY u.username ASC
");
$users_query->execute();
$all_users = $users_query->get_result();
$users_array = [];

while ($user = $all_users->fetch_assoc()) {
    $users_array[] = $user;
}

// Convert to JSON for JavaScript
$users_json = json_encode($users_array);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | Community</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/user-profile.css">
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

        /* Community User Search Styling */
        .community-search-section {
            padding: 3rem 1rem;
            background-color: var(--textwhite);
            border-bottom: 4px solid var(--charcoal);
            position: relative;
            z-index: 50;
        }

        .community-search-wrapper {
            display: flex;
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            border: 3px solid var(--charcoal);
            background: var(--textwhite);
            transition: all 0.2s ease-in-out;
            box-shadow: 6px 6px 0px var(--charcoal);
        }

        .community-search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 6px 6px 0px var(--primary);
            transform: translate(-2px, -2px);
        }

        .community-search-wrapper input {
            flex: 1;
            border: none;
            padding: 1.1rem 1.4rem;
            font-family: 'Staatliches', sans-serif;
            font-size: 1.2rem;
            letter-spacing: 1px;
            outline: none;
            background: transparent;
            color: var(--charcoal);
        }

        .community-search-wrapper input::placeholder {
            color: var(--charcoal);
            opacity: 0.5;
        }

        .community-search-icon {
            padding: 0 1.2rem;
            display: flex;
            align-items: center;
            color: var(--charcoal);
            opacity: 0.5;
            pointer-events: none;
        }

        .community-search-wrapper:focus-within .community-search-icon {
            color: var(--primary);
            opacity: 1;
        }

        /* Results Dropdown */
        .community-user-results {
            position: absolute;
            background: var(--textwhite);
            border: 3px solid var(--charcoal);
            border-top: none;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 6px 6px 0px var(--charcoal);
            display: none;
            z-index: 2500;
            min-width: 250px;
            width: auto;
            max-width: 600px;
            top: 100%;
            left: -3px;
            right: -3px;
        }

        .community-user-results.active {
            display: block;
        }

        /* Scrollbar styling for dropdown */
        .community-user-results::-webkit-scrollbar {
            width: 8px;
        }

        .community-user-results::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }

        .community-user-results::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        .community-user-results::-webkit-scrollbar-thumb:hover {
            background: var(--charcoal);
        }

        .community-user-result-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.4rem;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            color: inherit;
        }

        .community-user-result-item:last-child {
            border-bottom: none;
        }

        .community-user-result-item:hover {
            background-color: rgba(255, 75, 43, 0.05);
            padding-left: 1.8rem;
            color: var(--primary);
        }

        .community-user-result-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 1rem;
            object-fit: cover;
            border: 2px solid var(--charcoal);
        }

        .community-user-result-info {
            flex: 1;
        }

        .community-user-result-name {
            font-family: 'Staatliches', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.3rem;
        }

        .community-user-verified {
            color: var(--primary);
            font-size: 0.9rem;
        }

        .community-user-result-meta {
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            opacity: 0.7;
            display: flex;
            gap: 1rem;
        }

        .community-user-result-meta span {
            display: flex;
            gap: 0.3rem;
        }

        .community-search-empty {
            padding: 2rem;
            text-align: center;
            color: var(--charcoal);
            opacity: 0.6;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .community-search-section {
                padding: 2rem 0.5rem;
            }

            .community-search-wrapper {
                max-width: 100%;
            }
        }

        /* User Profile Modal Styling - Must match reels.php */
        .reel-modal-overlay {
            display: flex;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .reel-modal-overlay.active {
            opacity: 1;
            visibility: visible;
            pointer-events: all;
        }

        /* User Profile Modal Loading Styling */
        .user-profile-loading {
            text-align: center;
            padding: 5rem 2rem;
            color: var(--charcoal);
        }

        .user-profile-loading {
            text-align: center;
            padding: 3rem;
        }

        .user-profile-loading h2 {
            font-family: 'Staatliches', sans-serif;
            font-size: 2rem;
        }

        .close-modal {
            position: absolute; 
            top: 10px; 
            right: 20px; 
            font-size: 3rem; 
            color: var(--charcoal); 
            cursor: pointer; 
            font-family: 'Staatliches', sans-serif;
            line-height: 1;
        }

        .close-modal:hover {
            color: var(--primary); 
            transform: scale(1.20);
            transition: var(--transition);
        }
    </style>
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body>

<?php include 'header.php'; ?>

<div class="community-header noise-bg">
    <div class="container">
        <h1 class="glitch-text">THE <span class="text-primary">COMMUNITY</span> HUB</h1>
        <p style="font-family: 'Staatliches', sans-serif; font-size: 1.5rem; letter-spacing: 2px;">CONNECT / WATCH / READ / SHARE</p>
    </div>
</div>

<div class="community-search-section">
    <div class="container">
        <div class="community-search-wrapper">
            <span class="material-icons community-search-icon">search</span>
            <input 
                type="text" 
                id="community-user-search" 
                placeholder="SEARCH MEMBERS BY USERNAME..."
                autocomplete="off"
                maxlength="100"
            >
            <div class="community-user-results" id="community-user-results"></div>
        </div>
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

        <!-- SHOUTOUTS -->
        <a href="shoutouts.php" class="community-card">
            <span class="material-icons community-icon">rate_review</span>
            <h2>SHOUTOUTS</h2>
            <p>Leave messages, reviews, and shoutouts for the community.</p>
        </a>

        <!-- QnA -->
        <a href="qna.php" class="community-card">
            <span class="material-icons community-icon">forum</span>
            <h2>Q&A</h2>
            <p>Ask questions about the shop, products, or website.</p>
        </a>

    </div>
</main>

<?php include 'footer.php'; ?>

<!-- User Profile Modal (reused from reels.php) -->
<div id="userProfileModal" class="reel-modal-overlay">
    <div class="modal-content seller-profile-modal-shell" id="userProfileContent" 
         style="padding: 30px; width: 90%; max-width: 1100px; max-height: 90vh; overflow-y: auto;">
    </div>
</div>

<script>
    // User data from PHP
    const allCommunityUsers = <?php echo $users_json; ?> || [];

    // Wait for DOM to be ready and initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCommunitySearch);
    } else {
        initializeCommunitySearch();
    }

    function initializeCommunitySearch() {
        // Get DOM elements
        const communitySearchInput = document.getElementById('community-user-search');
        const communitySearchWrapper = document.querySelector('.community-search-wrapper');
        const communityResultsContainer = document.getElementById('community-user-results');
        const userProfileModal = document.getElementById('userProfileModal');
        const userProfileContent = document.getElementById('userProfileContent');

        // Verify all elements exist before proceeding
        if (!communitySearchInput || !communitySearchWrapper || !communityResultsContainer || !userProfileModal || !userProfileContent) {
            console.error('Required DOM elements not found for community search');
            return;
        }

        // Make modal functions globally accessible
        window.openUserProfile = function(username) {
            const modal = document.getElementById('userProfileModal');
            const contentDiv = document.getElementById('userProfileContent');
            
            // Show loading state
            contentDiv.innerHTML = `<span class="close-modal" onclick="window.closeSellerModal()">&times;</span>
                                   <div class="user-profile-loading">
                                       <h2 class="glitch-text" style="color: var(--primary); font-size: 2.5rem;">LOADING RECORD...</h2>
                                       <p style="font-family: 'Staatliches', sans-serif; letter-spacing: 2px; margin-top: 1rem;">RETRIEVING COMMUNITY DATA</p>
                                   </div>`;
            modal.classList.add('active');

            // Fetch HTML fragment from user-profile.php
            fetch(`user-profile.php?username=${encodeURIComponent(username)}`)
                .then(response => response.text())
                .then(html => { 
                    contentDiv.innerHTML = html; 
                })
                .catch(err => { 
                    console.error('Failed to load user profile:', err);
                    contentDiv.innerHTML = '<span class="close-modal" onclick="window.closeSellerModal()"></span>' +
                                          '<h3 style="color:#ff4b2b;">FAILED TO LOAD USER DATA.</h3>'; 
                });
        };

        window.closeSellerModal = function() {
            document.getElementById('userProfileModal').classList.remove('active');
        };

        // Click outside modal to close
        userProfileModal.addEventListener('click', (e) => { 
            if (e.target === userProfileModal) {
                window.closeSellerModal();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            // Close dropdown if clicking outside search wrapper and results
            if (!communitySearchWrapper.contains(e.target) && 
                !communityResultsContainer.contains(e.target)) {
                communityResultsContainer.classList.remove('active');
            }
        });

        // Event delegation for result item clicks
        communityResultsContainer.addEventListener('click', (e) => {
            const resultItem = e.target.closest('.community-user-result-item');
            if (resultItem) {
                e.preventDefault();
                const username = resultItem.getAttribute('data-username');
                // Close dropdown
                communityResultsContainer.classList.remove('active');
                communitySearchInput.value = '';
                // Open modal
                window.openUserProfile(username);
            }
        });

        // Escape key to close dropdown
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                communityResultsContainer.classList.remove('active');
                communitySearchInput.blur();
            }
        });



        // Live search function
        function filterCommunityUsers() {
            const searchTerm = communitySearchInput.value.toLowerCase().trim();

            // If empty, hide results
            if (searchTerm.length === 0) {
                communityResultsContainer.classList.remove('active');
                return;
            }

            // Filter users by username (case-insensitive, partial match)
            const matchedUsers = allCommunityUsers.filter(user => 
                user.username.toLowerCase().includes(searchTerm)
            );

            // Render results
            if (matchedUsers.length === 0) {
                communityResultsContainer.innerHTML = '<div class="community-search-empty">No community members found</div>';
            } else {
                communityResultsContainer.innerHTML = matchedUsers.map(user => {
                    const profilePic = user.profile_pic ? user.profile_pic : '../assets/images/default-avatar.png';
                    const verifiedBadge = user.is_verified ? '<i class="fa-solid fa-circle-check community-user-verified" title="Verified"></i>' : '';
                    
                    return `
                        <a href="#" class="community-user-result-item" data-username="${escapeHtml(user.username)}">
                            <img src="${escapeHtml(profilePic)}" alt="${escapeHtml(user.username)}" class="community-user-result-avatar">
                            <div class="community-user-result-info">
                                <div class="community-user-result-name">
                                    @${escapeHtml(user.username)}
                                    ${verifiedBadge}
                                </div>
                                <div class="community-user-result-meta">
                                    <span title="Active Reels"><i class="fas fa-film"></i> ${user.reels_count}</span>
                                    <span title="Q&As Asked"><i class="fas fa-question-circle"></i> ${user.qna_count}</span>
                                </div>
                            </div>
                        </a>
                    `;
                }).join('');
            }

            communityResultsContainer.classList.add('active');
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Attach input event listener for live search
        communitySearchInput.addEventListener('input', filterCommunityUsers);


    }
</script>

</body>
</html>

