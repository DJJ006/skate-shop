<?php

// email-config.php
// Configuration for PHPMailer to send emails via Gmail

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'skateshopp2026@gmail.com');   // <-- your Gmail address
define('SMTP_PASS', 'eobl svhp cvua xlyd');    // <-- your 16-char App Password
define('SMTP_SECURE', 'tls');

define('EMAIL_FROM_ADDRESS', 'skateshopp2026@gmail.com');  // must match SMTP_USER for Gmail
define('EMAIL_FROM_NAME', 'SkateShop Notifications');

define('SMTP_DEBUG', false);
