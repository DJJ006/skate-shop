<?php
$usernames = [
    'asphaltorbit', 'neoncarve', 'driftalchemy', 'railnomad', 'voidollie', 
    'kineticdeck', 'urbanmythskate', 'chromegrind', 'asphaltpulse', 'skynosedrift', 
    'brokenboardclub', 'frostflip', 'midnightkick', 'gravelskate', 'railsyndicate', 
    'vectorollie', 'staticshred', 'echoasphalt', 'plankrebel', 'darksideboards', 
    'motionfreaks', 'sk8paradox', 'streetlumen', 'concretehalo', 'driftdistrict', 
    'asphaltphantom', 'boardalchemy', 'railvortex', 'skatecipher', 'urbanfluxx', 
    'ollieforge', 'grindhaven', 'skatewraith', 'brokenrails', 'velocitydeck', 
    'nightcarver', 'steelmotion', 'asphaltdrifters', 'voidriders', 'neonrailsociety', 
    'skatelattice', 'grindatlas', 'shadowkick', 'concreteorbit', 'voidshredder', 
    'railphantom', 'driftpulse', 'streetvortex', 'asphaltcipher', 'midnightskater'
];

$conditions = [
    'MINT / WALL HANGER' => 25,
    'LIGHTLY SCUFFED' => 45,
    'BEAT UP / SKATEABLE' => 30
];

function getRandomCondition() {
    global $conditions;
    $rand = rand(1, 100);
    $cumulative = 0;
    foreach ($conditions as $cond => $prob) {
        $cumulative += $prob;
        if ($rand <= $cumulative) {
            return $cond;
        }
    }
    return 'LIGHTLY SCUFFED';
}

$categories = [
    'Decks' => ['Baker', 'Girl', 'Chocolate', 'Zero', 'DGK', 'Primitive', 'Santa Cruz', 'Powell Peralta', 'Element', 'Anti-Hero', 'Krooked', 'Real', 'Creature', 'Plan B', 'Almost'],
    'Trucks' => ['Independent', 'Thunder', 'Venture', 'Ace Trucks', 'Krux', 'Tensor', 'Royal', 'Silver', 'Destructo', 'Theeve'],
    'Wheels' => ['Spitfire', 'Bones', 'Ricta', 'OJ', 'Snot Wheels', 'Dial Tone', 'Satori', 'Pig Wheels', 'Autobahn', 'Wayward'],
    'Bearings' => ['Bones', 'Bronson Speed Co', 'Independent', 'Andale', 'Spitfire', 'Shake Junt', 'Cortina'],
    'Apparel' => ['Thrasher', 'Vans', 'Nike SB', 'Adidas', 'Santa Cruz', 'Spitfire', 'Independent', 'Baker', 'Primitive', 'Girl', 'Volcom', 'Dickies', 'Levis', 'Polar Skate Co', 'HUF'],
    'Accesories' => ['Mob Grip', 'Jessup', 'Shake Junt', 'Independent', 'Shortys', 'Spitfire', 'Bones', 'Shoe Goo', 'Ninja'],
    'Other' => ['Dakine', 'Nixon', 'Vans', 'Nike SB', 'Emerica']
];

$deck_titles = ["Brand Logo Deck", "OG Deck", "Pro Model", "Team Edition", "Classic Graphic", "Limited Edition Deck", "Cruiser Deck", "Custom Shape", "Popsicle Deck", "Wide Boy Deck"];
$truck_titles = ["Stage 11 Standard", "Hollow Lights", "Titanium", "Classic", "AF1", "DLK", "Mag Light", "Aluminum", "Inverted", "M-Class"];
$wheel_titles = ["Formula 4 Classic", "Conical Full", "Bighead", "STF Retros", "SPF Clear", "100s V4 Wide", "Clouds 78a", "Rapido", "Super Juice", "Elite Hardline"];
$bearing_titles = ["Reds Bearings", "Swiss Bearings", "Swiss Ceramics", "G3", "RAW", "Genuine Bearings", "Pro Rated Bearings", "Burners", "Precision Bearings"];
$apparel_titles = ["Flame Hoodie", "Classic Tee", "Icon Pullover", "Track Jacket", "Dot Longsleeve", "Logo Tee", "Cross Hoodie", "Dirty P Hoodie", "Vitals Pants", "874 Work Pants", "Plantlife Socks"];
$accessory_titles = ["Grip Tape Black", "Grip Tape Clear", "7/8 Allen Hardware", "Silverados Hardware", "T3 Skate Tool", "Genuine Parts Tool", "Speed Cream", "Shoe Goo Clear", "Bearing Press"];
$other_titles = ["Skate Backpack", "Time Teller Watch", "Old Skool Pro", "Dunk Low Pro", "Dickson Shoes", "Skate Wax", "Sticker Pack", "Keychain", "Wallet"];

$descriptions = [
    "Used for a few sessions. Still has a lot of pop.",
    "Minor scratches from normal use. Wheels spin smooth.",
    "Graphic has storage wear but it's never been skated.",
    "Bearings cleaned regularly. Runs fast.",
    "Selling because I switched setups and don't need this anymore.",
    "Bought the wrong size, literally skated it once.",
    "A bit beat up but definitely still skateable.",
    "Mint condition. Kept it as a wallhanger but need the cash.",
    "Trucks are broken in perfectly.",
    "Wheels have no flatspots. Good to go.",
    "Slight razor tail, otherwise solid.",
    "Grip tape is a bit dirty but still grippy.",
    "Hardware is fully intact, no stripped bolts.",
    "Washed a few times, graphic slightly faded but looks vintage.",
    "Got this in a mystery box, not my style.",
    "Solid backup piece for anyone needing gear.",
    "Skated mostly transition with these, very little street wear.",
    "Priced to sell quickly.",
    "Classic piece. Hard to find in this condition."
];

$sql_content = "-- Marketplace 100 Products SQL Import\n";
$sql_content .= "-- Compatible with existing 'products' table structure\n\n";

for ($i = 1; $i <= 100; $i++) {
    $category_key = array_rand($categories);
    $brands = $categories[$category_key];
    $brand = $brands[array_rand($brands)];
    
    $title = "";
    if ($category_key == 'Decks') $title = $brand . " " . $deck_titles[array_rand($deck_titles)];
    elseif ($category_key == 'Trucks') $title = $brand . " " . $truck_titles[array_rand($truck_titles)];
    elseif ($category_key == 'Wheels') $title = $brand . " " . $wheel_titles[array_rand($wheel_titles)];
    elseif ($category_key == 'Bearings') $title = $brand . " " . $bearing_titles[array_rand($bearing_titles)];
    elseif ($category_key == 'Apparel') $title = $brand . " " . $apparel_titles[array_rand($apparel_titles)];
    elseif ($category_key == 'Accesories') $title = $brand . " " . $accessory_titles[array_rand($accessory_titles)];
    else $title = $brand . " " . $other_titles[array_rand($other_titles)];

    $condition = getRandomCondition();
    
    // Pricing logic based on condition
    $base_price = rand(20, 80);
    if ($condition == 'MINT / WALL HANGER') $price = $base_price + rand(10, 30);
    elseif ($condition == 'LIGHTLY SCUFFED') $price = $base_price;
    else $price = $base_price - rand(5, 15);
    
    if ($price < 5) $price = 5;
    
    $desc1 = $descriptions[array_rand($descriptions)];
    $desc2 = $descriptions[array_rand($descriptions)];
    while ($desc1 == $desc2) {
        $desc2 = $descriptions[array_rand($descriptions)];
    }
    $description = addslashes($desc1 . " " . $desc2);
    $seller = $usernames[array_rand($usernames)];
    
    // Generate dates within the last 60 days
    $days_ago = rand(1, 60);
    $hours_ago = rand(0, 23);
    $created_at = date('Y-m-d H:i:s', strtotime("-$days_ago days -$hours_ago hours"));
    
    // We add hover_image_url as an empty string to be safe if it exists, wait, let's just insert standard columns we know are required.
    // If we skip image_url and hover_image_url, they might not have default values. It's safer to provide empty strings.
    // Based on shop_products_import.sql:
    // INSERT INTO products (title, brand, price, discount_price, quantity, category, description, image_url, is_marketplace) VALUES ...
    // Let's explicitly specify columns to match exactly what is needed for a marketplace item.
    
    $sql_content .= "INSERT IGNORE INTO `products` (`title`, `brand`, `category`, `description`, `price`, `quantity`, `is_marketplace`, `seller_id`, `seller_name`, `condition_badge`, `is_approved`, `image_url`, `created_at`) VALUES ";
    $sql_content .= "('" . addslashes($title) . "', '" . addslashes($brand) . "', '" . addslashes($category_key) . "', '" . $description . "', " . $price . ", 1, 1, ";
    $sql_content .= "(SELECT id FROM users WHERE username = '" . $seller . "'), '" . $seller . "', '" . $condition . "', 1, '', '" . $created_at . "');\n";
}

file_put_contents('marketplace_100.sql', $sql_content);
echo "SQL script generated successfully.\n";
?>
