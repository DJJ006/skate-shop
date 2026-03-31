<?php
#Savienojuma izveide ar datubāzi:
$host = "localhost";
$username = "grobina1_jaunarajs";
$password = 'Nej$v3Hw0J7t';
$database = "grobina1_jaunarajs";

$conn = mysqli_connect($host, $username, $password, $database);

if(!$conn){
    die("Nav izveidots savienojums: " . mysqli_connect_error());
}else{
    #echo "Ir izveidots savienojums ar datubāzi";
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $check_user_id = $_SESSION['user_id'];
    
    $current_page = basename($_SERVER['PHP_SELF']);
    $exempt_pages = ['login.php', 'register.php'];
    
    if (!in_array($current_page, $exempt_pages)) {
        
        
        $stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ?");
        $stmt->bind_param("i", $check_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (isset($row['is_blocked']) && $row['is_blocked'] == 1) {
                
                
                session_unset();
                session_destroy();
                
                
                
                exit();
            }
        }
    }
}
?>