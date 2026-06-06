<?php
$content = file_get_contents('review-sellers.php');
$content = str_replace("['user_id']", "['buyer_id']", $content);
file_put_contents('review-sellers.php', $content);

