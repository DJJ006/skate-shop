<?php require 'db.php'; echo mysqli_num_rows(mysqli_query($conn, 'SELECT * FROM users')); ?>
