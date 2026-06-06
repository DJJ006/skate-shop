<?php
$dir = __DIR__;
$files = glob($dir . '/*.php');

foreach ($files as $file) {
    if (basename($file) === 'admin_header.php' || basename($file) === 'admin-login.php') continue;
    
    $content = file_get_contents($file);
    if (strpos($content, '<?php require __DIR__ . '/admin_header.php'; ?> block.
        // Since there is only one header block per file, we can use a regex.
        $new_content = preg_replace('/<header class="main-header">.*?<\/header>/is', "<?php require __DIR__ . '/admin_header.php'; ?>", $content);
        if ($new_content !== null && $new_content !== $content) {
            file_put_contents($file, $new_content);
            echo "Updated " . basename($file) . "\n";
        }
    }
}
?>

