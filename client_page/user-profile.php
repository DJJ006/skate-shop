<?php 
session_start();
include '../db.php';

// --- FOLLOW SYSTEM LOGIC (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_follow'])) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Please login to follow.']);
        exit();
    }

    $follower_id = (int)$_SESSION['user_id'];
    $followed_id = (int)$_POST['followed_id'];

    if ($follower_id === $followed_id) {
        echo json_encode(['status' => 'error', 'message' => 'You cannot follow yourself.']);
        exit();
    }

    // Check if already following
    $check_stmt = $conn->prepare("SELECT id FROM user_follows WHERE follower_id = ? AND followed_id = ?");
    $check_stmt->bind_param("ii", $follower_id, $followed_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result();

    if ($existing->num_rows > 0) {
        // Unfollow
        $del_stmt = $conn->prepare("DELETE FROM user_follows WHERE follower_id = ? AND followed_id = ?");
        $del_stmt->bind_param("ii", $follower_id, $followed_id);
        $del_stmt->execute();
        $action = 'unfollowed';
    } else {
        // Follow
        $ins_stmt = $conn->prepare("INSERT INTO user_follows (follower_id, followed_id) VALUES (?, ?)");
        $ins_stmt->bind_param("ii", $follower_id, $followed_id);
        $ins_stmt->execute();
        $action = 'followed';

        // Send Notification to the followed user
        $follower_name = $_SESSION['username'];
        $notif_msg = "@" . $follower_name . " followed you!";
        sendAppNotification($conn, $followed_id, $notif_msg);
    }

    // Get new follower count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as followers FROM user_follows WHERE followed_id = ?");
    $count_stmt->bind_param("i", $followed_id);
    $count_stmt->execute();
    $new_count = $count_stmt->get_result()->fetch_assoc()['followers'] ?? 0;

    echo json_encode([
        'status' => 'success',
        'action' => $action,
        'new_count' => (int)$new_count
    ]);
    exit();
}

// Rating Submission Logic (Intercepts POST requests before outputting HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dummy_rating'])) {
    $seller_id = (int)$_POST['seller_id'];
    $seller_username = $_POST['seller_username'];
    $return_product_id = (int)$_POST['return_product_id'];
    
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $seller_id) {
        $buyer_id = (int)$_SESSION['user_id'];
        $stars = isset($_POST['stars']) ? (int)$_POST['stars'] : 5;
        $stars = max(1, min(5, $stars));

        $insert_rating = $conn->prepare("INSERT INTO seller_ratings (seller_id, buyer_id, product_id, rating) VALUES (?, ?, ?, ?)");
        $insert_rating->bind_param("iiii", $seller_id, $buyer_id, $return_product_id, $stars);
        $insert_rating->execute();

        header("Location: product.php?id=" . $return_product_id);
        exit();
    }
}

if (!isset($_GET['username']) || empty($_GET['username'])) {
    die("<h3 class='user-profile-error-title'>Invalid Request.</h3>");
}

$seller_username = trim($_GET['username']);
$return_product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// Fetch Seller Data
$stmt = $conn->prepare("SELECT id, username, profile_pic, created_at, is_verified FROM users WHERE username = ?");
$stmt->bind_param("s", $seller_username);
$stmt->execute();
$seller_result = $stmt->get_result();

if ($seller_result->num_rows === 0) {
    die("
        <span class='close-modal' onclick='closeSellerModal()'>&times;</span>
        <div class='user-profile-empty-state'>
            <h3 class='user-profile-empty-title'>SELLER NOT FOUND</h3>
            <p class='user-profile-empty-text'>The profile you tried to open doesn't exist.</p>
        </div>
    ");
}

$seller = $seller_result->fetch_assoc();
$seller_id = (int)$seller['id'];

// Active Listings Count
$active_stmt = $conn->prepare("SELECT COUNT(*) as active FROM products WHERE seller_id = ? AND is_marketplace = 1 AND is_approved = 1");
$active_stmt->bind_param("i", $seller_id);
$active_stmt->execute();
$active_count = $active_stmt->get_result()->fetch_assoc()['active'] ?? 0;

// Reels Count
$reels_stmt = $conn->prepare("SELECT COUNT(*) as total_reels FROM reels WHERE user_id = ? AND is_approved = 1");
$reels_stmt->bind_param("i", $seller_id);
$reels_stmt->execute();
$reels_count = $reels_stmt->get_result()->fetch_assoc()['total_reels'] ?? 0;

// QnA Count
$qna_stmt = $conn->prepare("SELECT COUNT(*) as total_qna FROM community_qna WHERE user_id = ?");
$qna_stmt->bind_param("i", $seller_id);
$qna_stmt->execute();
$qna_count = $qna_stmt->get_result()->fetch_assoc()['total_qna'] ?? 0;

// Shoutouts Count
$shoutout_stmt = $conn->prepare("SELECT COUNT(*) as total_shoutouts FROM community_shoutouts WHERE user_id = ? AND status = 'published'");
$shoutout_stmt->bind_param("i", $seller_id);
$shoutout_stmt->execute();
$shoutouts_count = $shoutout_stmt->get_result()->fetch_assoc()['total_shoutouts'] ?? 0;

// Ratings Data
$rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM seller_ratings WHERE seller_id = ?");
$rating_stmt->bind_param("i", $seller_id);
$rating_stmt->execute();
$rating_data = $rating_stmt->get_result()->fetch_assoc();

$avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
$total_ratings = $rating_data['total_ratings'] ?? 0;

// Reviews List
$reviews_stmt = $conn->prepare("
    SELECT sr.rating, sr.created_at, u.username AS buyer_username
    FROM seller_ratings sr
    LEFT JOIN users u ON sr.buyer_id = u.id
    WHERE sr.seller_id = ?
    ORDER BY sr.created_at DESC LIMIT 5
");
$reviews_stmt->bind_param("i", $seller_id);
$reviews_stmt->execute();
$seller_reviews = $reviews_stmt->get_result();

$profile_pic = !empty($seller['profile_pic']) ? $seller['profile_pic'] : '../assets/images/default-avatar.png';

$profile_pic = !empty($seller['profile_pic']) ? $seller['profile_pic'] : '../assets/images/default-avatar.png';

// Fetch Follow Status and Count for initial display
$is_following = false;
if (isset($_SESSION['user_id'])) {
    $status_stmt = $conn->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = ?");
    $status_stmt->bind_param("ii", $_SESSION['user_id'], $seller_id);
    $status_stmt->execute();
    $is_following = $status_stmt->get_result()->num_rows > 0;
}

$follower_stmt = $conn->prepare("SELECT COUNT(*) as followers FROM user_follows WHERE followed_id = ?");
$follower_stmt->bind_param("i", $seller_id);
$follower_stmt->execute();
$follower_count = $follower_stmt->get_result()->fetch_assoc()['followers'] ?? 0;
?>


<span class="close-modal" onclick="closeSellerModal()">&times;</span>

<div class="user-profile-modal">
    <div class="user-profile-header">
        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Avatar" class="user-profile-avatar">

        <div class="user-profile-headings">
            <h2 class="user-profile-name">
                @<?php echo htmlspecialchars($seller['username']); ?>
                <?php if ($seller['is_verified']): ?>
                    <i class="fa-solid fa-circle-check user-profile-verified" title="Verified Trusted Seller"></i>
                <?php endif; ?>
            </h2>

            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $seller_id): ?>
                <div class="user-profile-actions">
                    <button type="button" 
                            id="followBtn" 
                            class="user-profile-follow-btn <?php echo $is_following ? 'is-following' : ''; ?>"
                            onclick="toggleFollow(<?php echo $seller_id; ?>)">
                        <?php echo $is_following ? 'UNFOLLOW' : 'FOLLOW'; ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="user-profile-meta">
                <?php $is_own_profile = (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $seller_id); ?>
                
                <span class="user-profile-meta-chip">
                    JOINED:
                    <span class="user-profile-meta-value">
                        <?php echo date("M Y", strtotime($seller['created_at'])); ?>
                    </span>
                </span>

                <span class="user-profile-meta-chip <?php echo $is_own_profile ? 'is-clickable' : ''; ?>" <?php echo $is_own_profile ? 'onclick="if(typeof openModal === \'function\') { openModal(\'myListings\'); }"' : ''; ?>>
                    ACTIVE GEAR:
                    <span class="user-profile-meta-value user-profile-meta-value-primary">
                        <?php echo (int)$active_count; ?>
                    </span>
                </span>

                <span class="user-profile-meta-chip <?php echo $is_own_profile ? 'is-clickable' : ''; ?>" <?php echo $is_own_profile ? 'onclick="if(typeof openModal === \'function\') { openModal(\'myReels\'); }"' : ''; ?>>
                    REELS:
                    <span class="user-profile-meta-value user-profile-meta-value-primary">
                        <?php echo (int)$reels_count; ?>
                    </span>
                </span>

                <span class="user-profile-meta-chip <?php echo $is_own_profile ? 'is-clickable' : ''; ?>" <?php echo $is_own_profile ? 'onclick="if(typeof openModal === \'function\') { openModal(\'myQnaModal\'); }"' : ''; ?>>
                    QNAS ASKED:
                    <span class="user-profile-meta-value user-profile-meta-value-primary">
                        <?php echo (int)$qna_count; ?>
                    </span>
                </span>

                <span class="user-profile-meta-chip <?php echo $is_own_profile ? 'is-clickable' : ''; ?>" <?php echo $is_own_profile ? 'onclick="if(typeof openModal === \'function\') { openModal(\'myShoutoutModal\'); }"' : ''; ?>>
                    SHOUTOUTS:
                    <span class="user-profile-meta-value user-profile-meta-value-primary">
                        <?php echo (int)$shoutouts_count; ?>
                    </span>
                </span>

                <span class="user-profile-meta-chip <?php echo $is_own_profile ? 'is-clickable' : ''; ?>" <?php echo $is_own_profile ? 'onclick="if(typeof openModal === \'function\') { openModal(\'followersModal\'); }"' : ''; ?>>
                    FOLLOWERS:
                    <span class="user-profile-meta-value user-profile-meta-value-primary" id="followerCountDisplay">
                        <?php echo (int)$follower_count; ?>
                    </span>
                </span>
            </div>
        </div>
    </div>

    <div class="user-profile-score-card">
        <h4 class="user-profile-section-title">REPUTATION SCORE</h4>

        <?php if ($total_ratings > 0): ?>
            <div class="user-profile-stars-row">
                <span class="user-profile-stars">
                    <?php
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= round($avg_rating) ? '★' : '☆';
                        }
                    ?>
                </span>
                <span class="user-profile-score-text">
                    (<?php echo $avg_rating; ?> / 5 from <?php echo $total_ratings; ?> ratings)
                </span>
            </div>
        <?php else: ?>
            <p class="user-profile-empty-text">No ratings dropped yet.</p>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $seller_id): ?>
        <div class="user-profile-rate-box">
            <h4 class="user-profile-section-title">RATE THIS TRANSACTION</h4>

            <form action="user-profile.php" method="POST" class="user-profile-rate-form">
                <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
                <input type="hidden" name="seller_username" value="<?php echo htmlspecialchars($seller_username); ?>">
                <input type="hidden" name="return_product_id" value="<?php echo $return_product_id; ?>">

                <select name="stars" class="user-profile-select">
                    <option value="5">5 STARS - PERFECT</option>
                    <option value="4">4 STARS - GOOD</option>
                    <option value="3">3 STARS - OKAY</option>
                    <option value="2">2 STARS - BAD</option>
                    <option value="1">1 STAR - TERRIBLE</option>
                </select>

                <button type="submit" name="submit_dummy_rating" class="btn btn-primary user-profile-submit">
                    SUBMIT
                </button>
            </form>
        </div>
    <?php endif; ?>

    <h4 class="user-profile-reviews-heading">RECENT REVIEWS</h4>

    <?php if ($seller_reviews->num_rows > 0): ?>
        <div class="user-profile-reviews-list">
            <?php while ($review = $seller_reviews->fetch_assoc()): ?>
                <div class="user-profile-review-card">
                    <div class="user-profile-review-top">
                        <strong class="user-profile-review-user">
                            @<?php echo htmlspecialchars($review['buyer_username'] ?: 'Anonymous'); ?>
                        </strong>

                        <span class="user-profile-review-stars">
                            <?php for ($i = 1; $i <= 5; $i++) echo $i <= (int)$review['rating'] ? '★' : '☆'; ?>
                        </span>
                    </div>

                    <div class="user-profile-review-date">
                        <?php echo date("M d, Y", strtotime($review['created_at'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="user-profile-empty-text">No written feedback available.</p>
    <?php endif; ?>
</div>

