<?php
session_start();
require_once 'config.php';

// تسجيل نشاط الخروج
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $conn->query("INSERT INTO activity_log (user_id, action, ip_address) 
                 VALUES ($user_id, 'تسجيل خروج', '$ip_address')");
}

// حذف جميع بيانات الجلسة
session_unset();
session_destroy();

// إعادة التوجيه
header("Location: login.php?logout=success");
exit();
?>