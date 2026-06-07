<?php

function checkRateLimit($conn, $ip) {
    $stmt = $conn->prepare("SELECT failed_attempts, lockout_until FROM login_rate_limits WHERE ip_address = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    if ($res) {
        if ($res['lockout_until'] && $res['lockout_until'] > time()) {
            return $res['lockout_until'] - time();
        } elseif ($res['lockout_until'] && $res['lockout_until'] <= time()) {
            // Lockout expired, reset attempts to allow a fresh start
            resetRateLimit($conn, $ip);
        }
    }
    return 0;
}

function recordFailedLogin($conn, $ip, $email) {
    // Insert or update failed attempts counter
    $stmt = $conn->prepare("INSERT INTO login_rate_limits (ip_address, failed_attempts, lockout_until) VALUES (?, 1, NULL) ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    
    // Check if lockout threshold is reached
    $stmt2 = $conn->prepare("SELECT failed_attempts FROM login_rate_limits WHERE ip_address = ?");
    $stmt2->bind_param("s", $ip);
    $stmt2->execute();
    $res2 = $stmt2->get_result()->fetch_assoc();
    
    if ($res2 && $res2['failed_attempts'] == 5) {
        // Apply exactly 5 minutes lockout
        $lockout = time() + 300; 
        $upd = $conn->prepare("UPDATE login_rate_limits SET lockout_until = ? WHERE ip_address = ?");
        $upd->bind_param("is", $lockout, $ip);
        $upd->execute();

        // Log lockout event
        $log_stmt = $conn->prepare("INSERT INTO login_lockout_logs (ip_address, email_attempted) VALUES (?, ?)");
        $log_stmt->bind_param("ss", $ip, $email);
        $log_stmt->execute();
    }
}

function resetRateLimit($conn, $ip) {
    $stmt = $conn->prepare("DELETE FROM login_rate_limits WHERE ip_address = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
}
