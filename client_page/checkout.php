<?php
session_start();
include '../db.php';
require_once '../stripe-config.php';

// -------------------------
// Helpers
// -------------------------
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function old($key, $default = '') {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

// -------------------------
// Security: must be logged in
// -------------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: shop.php");
    exit();
}

$product_id = (int)$_GET['id'];
$buyer_id = (int)$_SESSION['user_id'];

// -------------------------
// Check for Cancellation Return
// -------------------------
if (isset($_GET['cancel_order'])) {
    $cancel_order_id = (int)$_GET['cancel_order'];
    $conn->begin_transaction();
    try {
        $cancel_stmt = $conn->prepare("SELECT id, status, amount_paid_wallet FROM orders WHERE id = ? AND buyer_id = ? FOR UPDATE");
        $cancel_stmt->bind_param("ii", $cancel_order_id, $buyer_id);
        $cancel_stmt->execute();
        $cancel_order = $cancel_stmt->get_result()->fetch_assoc();

        if ($cancel_order && $cancel_order['status'] === 'PENDING_PAYMENT') {
            $wallet_refund = (float)$cancel_order['amount_paid_wallet'];
            if ($wallet_refund > 0) {
                $refund_wallet_stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $refund_wallet_stmt->bind_param("di", $wallet_refund, $buyer_id);
                $refund_wallet_stmt->execute();
            }
            
            $update_cancel = $conn->prepare("UPDATE orders SET status = 'CANCELLED' WHERE id = ?");
            $update_cancel->bind_param("i", $cancel_order_id);
            $update_cancel->execute();
            $conn->commit();
            $checkout_msg = "Checkout abandoned. Your wallet funds of $" . number_format($wallet_refund, 2) . " have been safely returned.";
        } else {
            $conn->rollback();
        }
    } catch (Exception $e) {
        $conn->rollback();
    }
}

// -------------------------
// Fetch buyer info for prefilling
// -------------------------
$user_stmt = $conn->prepare("SELECT email, wallet_balance FROM users WHERE id = ?");
$user_stmt->bind_param("i", $buyer_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$buyer_email = $user_data['email'] ?? '';
$wallet_balance = (float)($user_data['wallet_balance'] ?? 0);

// -------------------------
// Fetch product
// -------------------------
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product_result = $stmt->get_result();
$product = $product_result->fetch_assoc();

if (!$product) {
    die("
        <div class='container checkout-status-wrap'>
            <div class='checkout-status-box'>
                <h1 class='glitch-text'>PRODUCT <span class='text-primary'>NOT FOUND</span></h1>
                <p>The item you tried to access does not exist.</p>
                <a href='shop.php' class='btn btn-primary checkout-status-btn'>RETURN TO SHOP</a>
            </div>
        </div>
    ");
}

// -------------------------
// Check availability
// -------------------------
$isAvailable = false;

if ((int)$product['is_marketplace'] === 1) {
    if ((int)$product['is_sold'] === 0) {
        $isAvailable = true;
    }
} else {
    if ((int)$product['quantity'] > 0) {
        $isAvailable = true;
    }
}

if (!$isAvailable) {
    die("
        <div class='container checkout-status-wrap'>
            <div class='checkout-status-box'>
                <h1 class='glitch-text'>ITEM <span class='text-primary'>OUT OF STOCK</span></h1>
                <p>This item has already been snatched up.</p>
                <a href='shop.php' class='btn btn-primary checkout-status-btn'>RETURN TO SHOP</a>
            </div>
        </div>
    ");
}

// -------------------------
// Pricing
// -------------------------
$subtotal = (float)$product['price'];
$shipping_fee = 5.00;
$total = $subtotal + $shipping_fee;

// -------------------------
// Handle form submission
// -------------------------
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_to_payment'])) {
    $first_name      = trim($_POST['first_name'] ?? '');
    $last_name       = trim($_POST['last_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $address_line_1  = trim($_POST['address_line_1'] ?? '');
    $address_line_2  = trim($_POST['address_line_2'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $state_region    = trim($_POST['state_region'] ?? '');
    $postal_code     = trim($_POST['postal_code'] ?? '');
    $country         = trim($_POST['country'] ?? '');
    $delivery_notes  = trim($_POST['delivery_notes'] ?? '');
    $wallet_amount_to_use = isset($_POST['wallet_amount_to_use']) ? (float)$_POST['wallet_amount_to_use'] : 0.00;
    $agree_terms     = isset($_POST['agree_terms']) ? 1 : 0;

    // -------------------------
    // Validation
    // -------------------------
    if ($first_name === '' || mb_strlen($first_name) < 2) {
        $errors[] = "Please enter a valid first name.";
    }

    if ($last_name === '' || mb_strlen($last_name) < 2) {
        $errors[] = "Please enter a valid last name.";
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if ($phone === '' || mb_strlen($phone) < 6) {
        $errors[] = "Please enter a valid phone number.";
    }

    if ($address_line_1 === '' || mb_strlen($address_line_1) < 5) {
        $errors[] = "Please enter a valid address line 1.";
    }

    if ($city === '' || mb_strlen($city) < 2) {
        $errors[] = "Please enter a valid city.";
    }

    if ($state_region === '' || mb_strlen($state_region) < 2) {
        $errors[] = "Please enter a valid state or region.";
    }

    if ($postal_code === '' || mb_strlen($postal_code) < 3) {
        $errors[] = "Please enter a valid postal code.";
    }

    if ($country === '' || mb_strlen($country) < 2) {
        $errors[] = "Please select or enter a valid country.";
    }

    if (!$agree_terms) {
        $errors[] = "You must agree to the Terms and Conditions before continuing.";
    }

    if ($wallet_amount_to_use < 0) {
        $errors[] = "Wallet amount cannot be negative.";
    }
    if ($wallet_amount_to_use > $wallet_balance) {
        $errors[] = "You cannot use more wallet funds than you have available.";
    }
    if ($wallet_amount_to_use > $total) {
        $wallet_amount_to_use = $total; // Cap it securely
    }

    // Re-check availability before creating order
    $stock_stmt = $conn->prepare("SELECT is_marketplace, is_sold, quantity FROM products WHERE id = ?");
    $stock_stmt->bind_param("i", $product_id);
    $stock_stmt->execute();
    $latest_product = $stock_stmt->get_result()->fetch_assoc();

    $still_available = false;
    if ($latest_product) {
        if ((int)$latest_product['is_marketplace'] === 1) {
            $still_available = ((int)$latest_product['is_sold'] === 0);
        } else {
            $still_available = ((int)$latest_product['quantity'] > 0);
        }
    }

    if (!$still_available) {
        $errors[] = "Sorry, this item is no longer available.";
    }

    // -------------------------
    // Save order if valid
    // -------------------------
    if (empty($errors)) {
        $shipping_name = $first_name . ' ' . $last_name;
    
        // Save latest customer details to users table
        $secret_key = $_ENV['APP_DB_ENCRYPTION_KEY'] ?? 'change_this_to_a_real_secret_key';
    
        $update_user_stmt = $conn->prepare("
            UPDATE users 
            SET first_name = ?, 
                last_name = ?, 
                phone = AES_ENCRYPT(?, ?), 
                address_line_1 = AES_ENCRYPT(?, ?), 
                city = ?, 
                postal_code = ?, 
                country = ?
            WHERE id = ?
        ");
    
        if ($update_user_stmt) {
            $update_user_stmt->bind_param(
                "sssssssssi",
                $first_name,
                $last_name,
                $phone,
                $secret_key,
                $address_line_1,
                $secret_key,
                $city,
                $postal_code,
                $country,
                $buyer_id
            );
            $update_user_stmt->execute();
            $update_user_stmt->close();
        }
    
        $shipping_payload = [
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'full_name'      => $shipping_name,
            'email'          => $email,
            'phone'          => $phone,
            'address_line_1' => $address_line_1,
            'address_line_2' => $address_line_2,
            'city'           => $city,
            'state_region'   => $state_region,
            'postal_code'    => $postal_code,
            'country'        => $country,
            'delivery_notes' => $delivery_notes,
            'subtotal'       => $subtotal,
            'shipping_fee'   => $shipping_fee,
            'total'          => $total,
            'created_at'     => date('c')
        ];
    
        $raw_shipping_json = json_encode($shipping_payload, JSON_UNESCAPED_UNICODE);
    
        // IMPORTANT: replace this with a real secret from env/config
        $encryption_key = "YOUR_SUPER_SECRET_KEY_MAKE_IT_LONG";
        $cipher = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);
    
        $encrypted_address = openssl_encrypt(
            $raw_shipping_json,
            $cipher,
            $encryption_key,
            0,
            $iv
        );
    
        $final_address = base64_encode($encrypted_address . '::' . $iv);
    
        $seller_id = ((int)$product['is_marketplace'] === 1) ? (int)$product['seller_id'] : 0;
        $amount = $total;
        $amount_paid_wallet = $wallet_amount_to_use;
        $amount_paid_stripe = $total - $wallet_amount_to_use;
    
        $conn->begin_transaction();
        try {
            // Deduct wallet balance immediately if using wallet funds
            if ($amount_paid_wallet > 0) {
                $deduct_stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?");
                $deduct_stmt->bind_param("did", $amount_paid_wallet, $buyer_id, $amount_paid_wallet);
                $deduct_stmt->execute();
                
                if ($deduct_stmt->affected_rows === 0) {
                    throw new Exception("Insufficient wallet balance. Please refresh and try again.");
                }
            }

            $order_stmt = $conn->prepare("
                INSERT INTO orders (
                    buyer_id,
                    product_id,
                    seller_id,
                    amount,
                    amount_paid_wallet,
                    amount_paid_stripe,
                    shipping_name,
                    shipping_address,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING_PAYMENT')
            ");
        
            $order_stmt->bind_param(
                "iiidddss",
                $buyer_id,
                $product_id,
                $seller_id,
                $amount,
                $amount_paid_wallet,
                $amount_paid_stripe,
                $shipping_name,
                $final_address
            );
        
            if (!$order_stmt->execute()) {
                throw new Exception("Could not create order in database.");
            }
            
            $order_id = $conn->insert_id;
            
            // If fully paid by wallet, complete the order immediately
            if ($amount_paid_stripe <= 0) {
                $update_order = $conn->prepare("UPDATE orders SET status = 'PAID' WHERE id = ?");
                $update_order->bind_param("i", $order_id);
                $update_order->execute();

                if ($seller_id > 0) {
                    $update_prod = $conn->prepare("UPDATE products SET is_sold = 1 WHERE id = ?");
                    $update_prod->bind_param("i", $product_id);
                    $update_prod->execute();
                    
                    $purchase_code = "ORD-" . str_pad($order_id, 6, "0", STR_PAD_LEFT);
                    $ship_addr = "{$shipping_payload['full_name']}, {$shipping_payload['address_line_1']}";
                    if (!empty($shipping_payload['address_line_2'])) $ship_addr .= ", {$shipping_payload['address_line_2']}";
                    $ship_addr .= ", {$shipping_payload['city']}, {$shipping_payload['state_region']} {$shipping_payload['postal_code']}, {$shipping_payload['country']}";
                    $del_notes = !empty($shipping_payload['delivery_notes']) ? $shipping_payload['delivery_notes'] : "None";
                    
                    $seller_notif_msg = "Your marketplace item '{$product['title']}' has been purchased!\nOrder Code: {$purchase_code}\nShip to: {$ship_addr}\nDelivery Notes: {$del_notes}\nFunds are held in escrow pending buyer confirmation.";
                    
                    $seller_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $seller_notif->bind_param("is", $seller_id, $seller_notif_msg);
                    $seller_notif->execute();
                } else {
                    $update_prod = $conn->prepare("UPDATE products SET quantity = quantity - 1 WHERE id = ?");
                    $update_prod->bind_param("i", $product_id);
                    $update_prod->execute();
                }

                $buyer_msg = "Your order for '{$product['title']}' was successful (Paid via Wallet).";
                $buyer_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $buyer_notif->bind_param("is", $buyer_id, $buyer_msg);
                $buyer_notif->execute();

                $conn->commit();
                
                // Redirect directly to success
                header("Location: success.php?session_id=WALLET_PAID_" . $order_id);
                exit();
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
            $order_id = null; // Prevent stripe session
        }

        if (isset($order_id) && $amount_paid_stripe > 0) {
            try {
                $session = \Stripe\Checkout\Session::create([
                    'mode' => 'payment',
                    'success_url' => 'https://kristovskis.lv/4pt/jaunarajs/skate-shop/client_page/success.php?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => 'https://kristovskis.lv/4pt/jaunarajs/skate-shop/client_page/checkout.php?id=' . $product_id . '&cancel_order=' . $order_id,
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => $product['title'],
                            ],
                            'unit_amount' => (int) round($amount_paid_stripe * 100),
                        ],
                        'quantity' => 1,
                    ]],
                    'metadata' => [
                        'order_id' => (string)$order_id
                    ]
                ]);
    
                $stripe_stmt = $conn->prepare("UPDATE orders SET stripe_session_id = ? WHERE id = ?");
                $stripe_stmt->bind_param("si", $session->id, $order_id);
                $stripe_stmt->execute();
    
                header("Location: " . $session->url);
                exit();
            } catch (Exception $e) {
                $errors[] = "Payment gateway error. Please try again in a moment.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | SECURE CHECKOUT</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .checkout-page {
            padding-top: 60px;
            padding-bottom: 80px;
            min-height: 80vh;
        }

        .checkout-header {
            margin-bottom: 2rem;
            border-bottom: 4px solid var(--charcoal);
            padding-bottom: 1rem;
        }

        .checkout-subtext {
            font-family: 'Staatliches', sans-serif;
            font-size: 1.2rem;
            letter-spacing: 1px;
            color: var(--charcoal);
            opacity: 0.8;
            margin-top: 0.5rem;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1.35fr 0.9fr;
            gap: 2rem;
            align-items: start;
        }

        .checkout-panel {
            background: var(--textwhite);
            border: 4px solid var(--charcoal);
            box-shadow: 8px 8px 0px var(--charcoal);
            padding: 2rem;
        }

        .checkout-panel-title {
            margin: 0 0 1.25rem 0;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid var(--charcoal);
            font-family: 'Staatliches', sans-serif;
            font-size: 2rem;
            letter-spacing: 1px;
            font-style: italic;
            color: var(--charcoal);
        }

        .checkout-note {
            margin: -0.25rem 0 1.25rem 0;
            font-family: 'Staatliches', sans-serif;
            letter-spacing: 0.5px;
            color: #666;
            font-size: 0.95rem;
            text-transform: uppercase;
        }

        .checkout-alert {
            margin-bottom: 1.25rem;
            padding: 1rem 1.25rem;
            border: 4px solid var(--charcoal);
            background: #fff2f2;
            box-shadow: 5px 5px 0px var(--charcoal);
        }

        .checkout-alert ul {
            margin: 0;
            padding-left: 1.1rem;
        }

        .checkout-alert li {
            font-family: 'Staatliches', sans-serif;
            letter-spacing: 0.4px;
            color: #b00020;
            margin: 0.25rem 0;
        }

        .checkout-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem 1.25rem;
        }

        .checkout-field {
            display: flex;
            flex-direction: column;
        }

        .checkout-field-full {
            grid-column: 1 / -1;
        }

        .checkout-field label {
            display: block;
            margin-bottom: 0.45rem;
            font-family: 'Staatliches', sans-serif;
            font-size: 1rem;
            letter-spacing: 1px;
            color: var(--charcoal);
        }

        .checkout-input,
        .checkout-select,
        .checkout-textarea {
            width: 100%;
            padding: 1rem 1rem;
            border: 3px solid var(--charcoal);
            background: #fff;
            color: var(--charcoal);
            font-family: 'Staatliches', sans-serif;
            font-size: 1rem;
            letter-spacing: 0.8px;
            transition: 0.2s ease;
            box-sizing: border-box;
        }

        .checkout-input:focus,
        .checkout-select:focus,
        .checkout-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 4px 4px 0px var(--charcoal);
            transform: translate(-1px, -1px);
        }

        .checkout-textarea {
            resize: vertical;
            min-height: 110px;
        }

        .checkout-checkbox-wrap {
            grid-column: 1 / -1;
            margin-top: 0.5rem;
            padding: 1rem;
            border: 3px dashed var(--charcoal);
            background: rgba(0, 0, 0, 0.03);
        }

        .checkout-checkbox-label {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            font-family: 'Staatliches', sans-serif;
            font-size: 1rem;
            letter-spacing: 0.5px;
            color: var(--charcoal);
            cursor: pointer;
        }

        .checkout-checkbox-label input {
            margin-top: 0.2rem;
            transform: scale(1.2);
            accent-color: var(--primary);
        }

        .checkout-submit {
            width: 100%;
            margin-top: 1.25rem;
            font-size: 1.25rem;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
        }

        .checkout-summary-card {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1.25rem;
            border-bottom: 3px solid var(--charcoal);
        }

        .checkout-summary-img {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border: 3px solid var(--charcoal);
            background: #f6f6f6;
            flex-shrink: 0;
        }

        .checkout-summary-title {
            margin: 0 0 0.45rem 0;
            font-family: 'Staatliches', sans-serif;
            font-size: 1.5rem;
            line-height: 1;
            color: var(--charcoal);
        }

        .checkout-summary-brand {
            margin: 0 0 0.6rem 0;
            font-family: 'Staatliches', sans-serif;
            font-size: 1rem;
            letter-spacing: 0.5px;
            color: #666;
            text-transform: uppercase;
        }

        .checkout-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 0;
            border-bottom: 2px solid #ddd;
            font-family: 'Staatliches', sans-serif;
            letter-spacing: 1px;
            color: var(--charcoal);
            font-size: 1.05rem;
        }

        .checkout-summary-total {
            color: var(--primary);
            font-size: 1.4rem;
            border-bottom: none;
            padding-bottom: 0;
        }

        .checkout-security-box {
            margin-top: 1.5rem;
            padding: 1rem;
            background: var(--bg-light);
            border: 3px solid var(--charcoal);
            box-shadow: 5px 5px 0px var(--charcoal);
        }

        .checkout-security-box h4 {
            margin: 0 0 0.5rem 0;
            font-family: 'Staatliches', sans-serif;
            font-size: 1.2rem;
            letter-spacing: 1px;
            color: var(--charcoal);
        }

        .checkout-security-box p {
            margin: 0;
            color: #555;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .checkout-status-wrap {
            padding: 100px 0;
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checkout-status-box {
            max-width: 700px;
            width: 100%;
            text-align: center;
            background: var(--textwhite);
            border: 4px solid var(--charcoal);
            box-shadow: 8px 8px 0px var(--charcoal);
            padding: 2rem;
        }

        .checkout-status-box p {
            margin: 1rem 0 1.5rem 0;
            color: #555;
            font-size: 1rem;
        }

        .checkout-status-btn {
            text-decoration: none;
        }

        @media (max-width: 980px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .checkout-form-grid {
                grid-template-columns: 1fr;
            }

            .checkout-panel {
                padding: 1.25rem;
                box-shadow: 5px 5px 0px var(--charcoal);
            }

            .checkout-summary-card {
                flex-direction: column;
            }

            .checkout-summary-img {
                width: 100%;
                height: auto;
                max-height: 260px;
            }
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<main class="container checkout-page">
    <div class="checkout-header">
        <h2 class="glitch-text">SECURE <span class="text-primary">CHECKOUT</span></h2>
        <p class="checkout-subtext">ENTER SHIPPING DETAILS BEFORE PAYMENT IS PROCESSED</p>
    </div>

    <div class="checkout-grid">
        <section class="checkout-panel">
            <h3 class="checkout-panel-title">SHIPPING DETAILS</h3>
            <p class="checkout-note">YOUR SHIPPING INFORMATION IS STORED IN ENCRYPTED FORM</p>

            <?php if (!empty($errors)): ?>
                <div class="checkout-alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="checkout.php?id=<?php echo $product_id; ?>" novalidate>
                <div class="checkout-form-grid">
                    <div class="checkout-field">
                        <label for="first_name">FIRST NAME</label>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            class="checkout-input"
                            value="<?php echo e(old('first_name')); ?>"
                            required
                        >
                    </div>

                    <div class="checkout-field">
                        <label for="last_name">LAST NAME</label>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            class="checkout-input"
                            value="<?php echo e(old('last_name')); ?>"
                            required
                        >
                    </div>

                    <div class="checkout-field">
                        <label for="email">EMAIL ADDRESS</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="checkout-input"
                            value="<?php echo e(old('email', $buyer_email)); ?>"
                            required
                        >
                    </div>

                    <div class="checkout-field">
                        <label for="phone">PHONE NUMBER</label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            class="checkout-input"
                            value="<?php echo e(old('phone')); ?>"
                            required
                        >
                    </div>

                    <div class="checkout-field checkout-field-full">
                        <label for="address_line_1">ADDRESS LINE 1</label>
                        <input
                            type="text"
                            id="address_line_1"
                            name="address_line_1"
                            class="checkout-input"
                            value="<?php echo e(old('address_line_1')); ?>"
                            placeholder="Street address, house number"
                            required
                        >
                    </div>

                    <div class="checkout-field checkout-field-full">
                        <label for="address_line_2">ADDRESS LINE 2 <span style="opacity:.6;">OPTIONAL</span></label>
                        <input
                            type="text"
                            id="address_line_2"
                            name="address_line_2"
                            class="checkout-input"
                            value="<?php echo e(old('address_line_2')); ?>"
                            placeholder="Apartment, suite, unit, building"
                        >
                    </div>

                    <div class="checkout-field">
                        <label for="city">CITY</label>
                        <input
                            type="text"
                            id="city"
                            name="city"
                            class="checkout-input"
                            value="<?php echo e(old('city')); ?>"
                            required
                        >
                    </div>

                    <div class="checkout-field">
                        <label for="state_region">STATE / REGION</label>
                        <input
                            type="text"
                            id="state_region"
                            name="state_region"
                            class="checkout-input"
                            value="<?php echo e(old('state_region')); ?>"
                            required
                        >
                    </div>

                    <div class="checkout-field">
                        <label for="postal_code">POSTAL CODE</label>
                        <input
                            type="text"
                            id="postal_code"
                            name="postal_code"
                            class="checkout-input"
                            value="<?php echo e(old('postal_code')); ?>"
                            required
                        >
                    </div>

                    <div class="checkout-field">
                        <label for="country">COUNTRY</label>
                        <input
                            type="text"
                            id="country"
                            name="country"
                            class="checkout-input"
                            value="<?php echo e(old('country')); ?>"
                            required
                        >
                    </div>

                    <div class="checkout-field checkout-field-full">
                        <label for="delivery_notes">DELIVERY NOTES <span style="opacity:.6;">OPTIONAL</span></label>
                        <textarea
                            id="delivery_notes"
                            name="delivery_notes"
                            class="checkout-textarea"
                            placeholder="Gate code, delivery instructions, preferred drop-off note"
                        ><?php echo e(old('delivery_notes')); ?></textarea>
                    </div>

                    <div class="checkout-checkbox-wrap">
                        <label class="checkout-checkbox-label" for="agree_terms">
                            <input
                                type="checkbox"
                                id="agree_terms"
                                name="agree_terms"
                                value="1"
                                <?php echo isset($_POST['agree_terms']) ? 'checked' : ''; ?>
                            >
                            <span>
                                I CONFIRM THAT MY SHIPPING INFORMATION IS CORRECT AND I AGREE TO THE STORE TERMS, PAYMENT PROCESSING, AND DELIVERY POLICY.
                            </span>
                        </label>
                    </div>

                    <?php if ($wallet_balance > 0): ?>
                    <div class="checkout-field checkout-field-full" style="background: #fdfdfd; padding: 1.25rem; border: 3px solid var(--charcoal); border-top: 6px solid var(--charcoal); margin-top: 1rem;">
                        <label for="wallet_amount_to_use" style="font-size: 1.2rem;">
                            <i class="fa-solid fa-wallet"></i> USE WALLET BALANCE
                        </label>
                        <p style="margin: 0 0 1rem 0; font-size: 0.95rem; color: #555; font-family: 'Staatliches', sans-serif; letter-spacing: 0.5px;">
                            AVAILABLE BALANCE: <strong style="color: var(--primary);">$<?php echo number_format($wallet_balance, 2); ?></strong>
                        </p>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-family: 'Staatliches', sans-serif; font-size: 1.2rem;">$</span>
                            <input
                                type="number"
                                id="wallet_amount_to_use"
                                name="wallet_amount_to_use"
                                class="checkout-input"
                                value="<?php echo e(old('wallet_amount_to_use', '0.00')); ?>"
                                min="0"
                                max="<?php echo min($wallet_balance, $total); ?>"
                                step="0.01"
                                style="max-width: 150px; padding: 0.5rem 1rem;"
                                oninput="updateCheckoutTotal()"
                            >
                            <button type="button" class="btn btn-outline" style="padding: 0.5rem 1rem;" onclick="document.getElementById('wallet_amount_to_use').value = '<?php echo min($wallet_balance, $total); ?>'; updateCheckoutTotal();">USE MAX</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" name="proceed_to_payment" class="btn btn-primary checkout-submit">
                    PROCEED TO PAYMENT
                    <i class="fa-solid fa-lock"></i>
                </button>
            </form>
        </section>

        <aside class="checkout-panel">
            <h3 class="checkout-panel-title">ORDER SUMMARY</h3>

            <div class="checkout-summary-card">
                <img
                    src="<?php echo e($product['image_url']); ?>"
                    alt="<?php echo e($product['title']); ?>"
                    class="checkout-summary-img"
                >

                <div>
                    <h4 class="checkout-summary-title"><?php echo e($product['title']); ?></h4>
                    <p class="checkout-summary-brand"><?php echo e($product['brand']); ?></p>
                    <span class="condition-badge <?php echo ((int)$product['is_marketplace'] === 1) ? 'marketplace-badge' : 'new-drop'; ?>" style="font-size:0.7rem;">
                        <?php echo ((int)$product['is_marketplace'] === 1) ? 'MARKETPLACE' : 'OFFICIAL'; ?>
                    </span>
                </div>
            </div>

            <div class="checkout-summary-row">
                <span>SUBTOTAL</span>
                <span>$<?php echo number_format($subtotal, 2); ?></span>
            </div>

            <div class="checkout-summary-row">
                <span>STANDARD SHIPPING</span>
                <span>$<?php echo number_format($shipping_fee, 2); ?></span>
            </div>

            <div class="checkout-summary-row checkout-summary-total">
                <span>REMAINING</span>
                <span id="summary_total">$<?php echo number_format($total, 2); ?></span>
            </div>

            <div class="checkout-security-box">
                <h4><i class="fa-solid fa-shield-halved"></i> SECURE ORDER</h4>
                <p>
                    Your shipping details are validated before payment and stored in encrypted form before your order moves to payment processing.
                </p>
            </div>
        </aside>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
    function updateCheckoutTotal() {
        const orderTotal = <?php echo $total; ?>;
        const walletInput = document.getElementById('wallet_amount_to_use');
        const summaryTotal = document.getElementById('summary_total');
        
        if (!walletInput || !summaryTotal) return;
        
        let walletUsed = parseFloat(walletInput.value);
        if (isNaN(walletUsed) || walletUsed < 0) walletUsed = 0;
        
        // Cap at order total visually
        if (walletUsed > orderTotal) walletUsed = orderTotal;
        
        const remaining = orderTotal - walletUsed;
        summaryTotal.innerText = '$' + remaining.toFixed(2);
    }
    
    // Initialize on load
    document.addEventListener('DOMContentLoaded', updateCheckoutTotal);
</script>

</body>
</html>