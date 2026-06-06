<?php
$dir = __DIR__;
$files = glob($dir . '/*.php');

foreach ($files as $file) {
    if (basename($file) === 'admin_auth.php' || basename($file) === 'fix_sessions.php') continue;
    
    $content = file_get_contents($file);
    // Replace standalone session_start(); with the safe version
    // Regex matches session_start(); that is NOT already inside the if statement
    $pattern = '/(?<!if \(session_status\(\) === PHP_SESSION_NONE\) {\s{4})session_start\(\);/m';
    $safe_session = "if (session_status() === PHP_SESSION_NONE) {\n    session_start();\n}";
    
    $new_content = preg_replace($pattern, $safe_session, $content);
    if ($new_content !== null && $new_content !== $content) {
        file_put_contents($file, $new_content);
        echo "Fixed session in " . basename($file) . "\n";
    }
}
?>

