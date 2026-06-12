<?php
$usernames = [
    "asphaltorbit", "neoncarve", "driftalchemy", "railnomad", "voidollie", 
    "kineticdeck", "urbanmythskate", "chromegrind", "asphaltpulse", "skynosedrift", 
    "brokenboardclub", "frostflip", "midnightkick", "gravelskate", "railsyndicate", 
    "vectorollie", "staticshred", "echoasphalt", "plankrebel", "darksideboards", 
    "motionfreaks", "sk8paradox", "streetlumen", "concretehalo", "driftdistrict", 
    "asphaltphantom", "boardalchemy", "railvortex", "skatecipher", "urbanfluxx", 
    "ollieforge", "grindhaven", "skatewraith", "brokenrails", "velocitydeck", 
    "nightcarver", "steelmotion", "asphaltdrifters", "voidriders", "neonrailsociety", 
    "skatelattice", "grindatlas", "shadowkickflip", "boardnomics", "streetentropy", 
    "rampnomads", "frictionzone", "concreteorbit", "sk8relic", "darkrollworks"
];

$sql = "-- Community Activity Generation Script\n";
$sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
$sql .= "START TRANSACTION;\n\n";

// Helper function to get random users
function get_users($count = 1) {
    global $usernames;
    $keys = array_rand($usernames, $count);
    if (!is_array($keys)) $keys = [$keys];
    $res = [];
    foreach ($keys as $k) $res[] = $usernames[$k];
    return $res;
}

// 1. Q&A
$qna_topics = [
    ['title' => 'Best wheels for rough asphalt?', 'body' => 'I mostly skate street and the roads here are terrible. Are Spitfire 80HDs or Bones Rough Riders better?'],
    ['title' => 'Thunder vs Indy trucks?', 'body' => 'Getting a new setup. I hear Indys turn better but Thunders are lighter. Thoughts?'],
    ['title' => 'How to stop razor tail?', 'body' => 'I keep wearing down my tail super fast. Is there any trick to prevent this or do I just need to stop dragging?'],
    ['title' => 'Shoe recommendations for wide feet?', 'body' => 'Every skate shoe I buy pinches my toes. Anyone know brands that fit wider?'],
    ['title' => 'First time at a skatepark, any advice?', 'body' => 'Going to my local park tomorrow morning. Kind of nervous. What are the unwritten rules?'],
    ['title' => 'Deck sizes for street skating', 'body' => 'Is 8.25 too wide for technical street tricks? I currently ride an 8.0.'],
    ['title' => 'Cleaning bearings', 'body' => 'Can I use WD-40 to clean my bearings or will that ruin them?']
];

$sql .= "-- Community Q&A\n";
foreach ($qna_topics as $topic) {
    $author = get_users(1)[0];
    $title = addslashes($topic['title']);
    $body = addslashes($topic['body']);
    
    $days_ago = rand(5, 60);
    $created_at = date('Y-m-d H:i:s', strtotime("-$days_ago days"));

    $sql .= "INSERT INTO `community_qna` (`user_id`, `username`, `title`, `body`, `status`, `created_at`) VALUES ";
    $sql .= "((SELECT id FROM users WHERE username = '$author'), '$author', '$title', '$body', 'approved', '$created_at');\n";
}
$sql .= "\n";

// 2. Shoutouts
$shoutouts = [
    ['title' => 'Landed my first kickflip!', 'body' => 'Took me 3 months but I finally got it rolling. So hyped right now!'],
    ['title' => 'New gear day', 'body' => 'Just picked up a fresh Baker deck and some Indys. Setup feels amazing.'],
    ['title' => 'Local park is finally open', 'body' => 'They finished the repairs at the district park. The new bowl is buttery smooth.'],
    ['title' => 'Sprained ankle gang', 'body' => 'Rolled my ankle on a 5 stair. Out for at least 2 weeks. Send good vibes.'],
    ['title' => 'Go Skate Day was insane', 'body' => 'Huge turnout downtown today. So sick seeing everyone out pushing.'],
    ['title' => 'Anyone skating downtown tonight?', 'body' => 'Me and a few guys are hitting the ledges by the library around 8.'],
    ['title' => 'Just hit my first handrail', 'body' => 'Scariest thing ever but I managed a boardslide down a 5-stair rail.']
];

$sql .= "-- Community Shoutouts\n";
foreach ($shoutouts as $s) {
    $author = get_users(1)[0];
    $title = addslashes($s['title']);
    $body = addslashes($s['body']);
    $days_ago = rand(1, 30);
    $created_at = date('Y-m-d H:i:s', strtotime("-$days_ago days"));

    $sql .= "INSERT INTO `community_shoutouts` (`user_id`, `username`, `title`, `body`, `status`, `created_at`) VALUES ";
    $sql .= "((SELECT id FROM users WHERE username = '$author'), '$author', '$title', '$body', 'approved', '$created_at');\n";
}
$sql .= "\n";

// 3. Product Reviews
$sql .= "-- Product Reviews (Assuming products exist)\n";
$review_comments = [
    [5, 'Absolutely love this. High quality and exactly as described.'],
    [5, 'Perfect. Fits great and performs even better. Highly recommend.'],
    [4, 'Really solid product, but shipping took a little longer than expected.'],
    [4, 'Good stuff. Does exactly what I need it to do.'],
    [5, 'Best gear I\'ve bought all year. 10/10.'],
    [3, 'It\'s okay. Not the best quality but it gets the job done for the price.'],
    [5, 'Insane pop, shape feels great under my feet.'],
    [4, 'Very durable so far. We\'ll see how long it lasts.']
];

// Generate 40 reviews across random product IDs between 1 and 50 (assuming they exist)
// We will use a subquery to pick a product, since we don't know IDs.
// MySQL doesn't support LIMIT in IN/ALL subqueries easily in INSERTs without a wrapping select.
// So we just pick random numbers and hope products exist, or better, we do:
// INSERT INTO product_reviews (product_id, ...) SELECT id, ... FROM products WHERE is_marketplace=0 ORDER BY RAND() LIMIT 1;
for ($i=0; $i<40; $i++) {
    $author = get_users(1)[0];
    $rc = $review_comments[array_rand($review_comments)];
    $rating = $rc[0];
    $comment = addslashes($rc[1]);
    $days_ago = rand(1, 80);
    $created_at = date('Y-m-d H:i:s', strtotime("-$days_ago days"));

    // We select a random product ID that is not a marketplace item
    $sql .= "INSERT IGNORE INTO `product_reviews` (`product_id`, `user_id`, `rating`, `comment`, `status`, `created_at`) ";
    $sql .= "SELECT id, (SELECT id FROM users WHERE username = '$author'), $rating, '$comment', 'Approved', '$created_at' ";
    $sql .= "FROM `products` WHERE is_marketplace = 0 ORDER BY RAND() LIMIT 1;\n";
}
$sql .= "\n";

// 4. Reel Engagement (Likes and Comments)
$sql .= "-- Reel Engagement (Assuming reels exist)\n";
$reel_comments = [
    'Clean catch! 🔥',
    'Bro that was insane.',
    'Steezy.',
    'What park is this?',
    'Smooth.',
    'Wow you made that look too easy.',
    'Proper form.'
];

for ($i=0; $i<30; $i++) {
    $author = get_users(1)[0];
    $sql .= "INSERT IGNORE INTO `reel_likes` (`reel_id`, `user_id`) ";
    $sql .= "SELECT id, (SELECT id FROM users WHERE username = '$author') ";
    $sql .= "FROM `reels` ORDER BY RAND() LIMIT 1;\n";
}

for ($i=0; $i<25; $i++) {
    $author = get_users(1)[0];
    $comment = addslashes($reel_comments[array_rand($reel_comments)]);
    $days_ago = rand(1, 20);
    $created_at = date('Y-m-d H:i:s', strtotime("-$days_ago days"));
    
    $sql .= "INSERT IGNORE INTO `reel_comments` (`reel_id`, `user_id`, `username`, `comment`, `created_at`) ";
    $sql .= "SELECT id, (SELECT id FROM users WHERE username = '$author'), '$author', '$comment', '$created_at' ";
    $sql .= "FROM `reels` ORDER BY RAND() LIMIT 1;\n";
}
$sql .= "\n";

// 5. Followers
$sql .= "-- User Followers\n";
for ($i=0; $i<80; $i++) {
    $pair = get_users(2);
    $follower = $pair[0];
    $followed = $pair[1];
    
    $sql .= "INSERT IGNORE INTO `user_follows` (`follower_id`, `followed_id`) VALUES ";
    $sql .= "((SELECT id FROM users WHERE username = '$follower'), (SELECT id FROM users WHERE username = '$followed'));\n";
}
$sql .= "\n";

// 6. Notifications
$sql .= "-- Notifications\n";
$notif_messages = [
    'Someone just replied to your Q&A post.',
    'Your product review has been approved!',
    'Someone liked your reel.',
    'You have a new follower.',
    'Your shoutout was approved.'
];

for ($i=0; $i<50; $i++) {
    $user = get_users(1)[0];
    $msg = addslashes($notif_messages[array_rand($notif_messages)]);
    $is_read = rand(0, 1);
    $days_ago = rand(0, 10);
    $created_at = date('Y-m-d H:i:s', strtotime("-$days_ago days"));

    $sql .= "INSERT INTO `notifications` (`user_id`, `message`, `is_read`, `created_at`) VALUES ";
    $sql .= "((SELECT id FROM users WHERE username = '$user'), '$msg', $is_read, '$created_at');\n";
}

$sql .= "\nCOMMIT;\n";

file_put_contents('community_activity.sql', $sql);
echo "SQL script generated successfully as community_activity.sql\n";
?>
