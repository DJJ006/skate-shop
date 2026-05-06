<?php
session_start();
include '../db.php';

header('Content-Type: application/json');

/**
 * Persists the current session cart to the database for logged-in users.
 */
function saveCartToDb($conn) {
    if (empty($_SESSION['user_id'])) return;
    $user_id = (int)$_SESSION['user_id'];
    $cart_json = json_encode($_SESSION['cart']);
    $stmt = $conn->prepare("UPDATE users SET cart_data = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $cart_json, $user_id);
        $stmt->execute();
    }
}

// Auth guard — guests cannot use the cart
if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'auth_required' => true,
        'redirect' => 'login.php',
        'message' => 'You must be logged in to use the cart.'
    ]);
    exit;
}

// Checkout lock — prevent mutations while a checkout is in progress
$mutation_actions = ['add', 'remove', 'update', 'clear'];
if (!empty($_SESSION['cart_locked']) && in_array($action, $mutation_actions)) {
    echo json_encode([
        'success' => false,
        'cart_locked' => true,
        'message' => 'Cart editing is disabled during checkout. Complete or cancel your order first.'
    ]);
    exit;
}

// Initialize cart: if session cart is empty and user is logged in, restore from DB
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (empty($_SESSION['cart']) && !empty($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $restore_stmt = $conn->prepare("SELECT cart_data FROM users WHERE id = ?");
    if ($restore_stmt) {
        $restore_stmt->bind_param("i", $user_id);
        $restore_stmt->execute();
        $restore_result = $restore_stmt->get_result()->fetch_assoc();
        if (!empty($restore_result['cart_data'])) {
            $restored = json_decode($restore_result['cart_data'], true);
            if (is_array($restored)) {
                $_SESSION['cart'] = $restored;
            }
        }
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 1);

        if ($product_id <= 0 || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, title, price, quantity, is_marketplace, is_sold, image_url, brand FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }

        $product = $res->fetch_assoc();
        $isMarket = (int)$product['is_marketplace'] === 1;

        if ($isMarket) {
            if ((int)$product['is_sold'] === 1) {
                echo json_encode(['success' => false, 'message' => 'This marketplace item is already sold.']);
                exit;
            }
            $qty = 1;
        } else {
            if ((int)$product['quantity'] < $qty) {
                echo json_encode(['success' => false, 'message' => 'Not enough stock available.']);
                exit;
            }
        }

        if (isset($_SESSION['cart'][$product_id])) {
            if ($isMarket) {
                echo json_encode(['success' => false, 'message' => 'This item is already in your cart.']);
                exit;
            }
            $new_qty = $_SESSION['cart'][$product_id]['qty'] + $qty;
            if ($new_qty > (int)$product['quantity']) {
                echo json_encode(['success' => false, 'message' => 'Cannot exceed available stock.']);
                exit;
            }
            $_SESSION['cart'][$product_id]['qty'] = $new_qty;
        } else {
            $_SESSION['cart'][$product_id] = [
                'id' => $product['id'],
                'title' => $product['title'],
                'brand' => $product['brand'],
                'price' => (float)$product['price'],
                'image_url' => $product['image_url'],
                'is_marketplace' => $isMarket,
                'max_qty' => $isMarket ? 1 : (int)$product['quantity'],
                'qty' => $qty
            ];
        }

        saveCartToDb($conn);
        echo json_encode(['success' => true, 'cart_count' => count($_SESSION['cart'])]);
        break;

    case 'remove':
        $product_id = (int)($_POST['product_id'] ?? 0);
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }
        saveCartToDb($conn);
        echo json_encode(['success' => true, 'cart_count' => count($_SESSION['cart'])]);
        break;

    case 'update':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 1);

        if (isset($_SESSION['cart'][$product_id])) {
            if ($_SESSION['cart'][$product_id]['is_marketplace']) {
                $qty = 1;
            }

            $stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();

            if ($qty > (int)$res['quantity'] && !$_SESSION['cart'][$product_id]['is_marketplace']) {
                echo json_encode(['success' => false, 'message' => 'Cannot exceed stock limit.']);
                exit;
            }

            if ($qty <= 0) {
                unset($_SESSION['cart'][$product_id]);
            } else {
                $_SESSION['cart'][$product_id]['qty'] = $qty;
            }
            saveCartToDb($conn);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not in cart.']);
        }
        break;

    case 'clear':
        $_SESSION['cart'] = [];
        saveCartToDb($conn);
        echo json_encode(['success' => true]);
        break;

    case 'get':
        $total = 0;
        $items = [];
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'] * $item['qty'];
            $items[] = $item;
        }
        echo json_encode([
            'success' => true,
            'items' => $items,
            'subtotal' => round($total, 2),
            'count' => count($_SESSION['cart'])
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
