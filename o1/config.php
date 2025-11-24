<?php
// config.php - ملف الإعدادات الرئيسي

// بدء الجلسة فقط إذا لم تكن موجودة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Riyadh');

// إعدادات قاعدة البيانات
$host = 'localhost';
$dbname = 'customers_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// إعدادات عامة
define('SITE_NAME', 'نظام إدارة العملاء');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

/**
 * دالة التحقق من تسجيل الدخول
 */
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * دالة التحقق من صلاحيات المدير
 */
function checkAdmin() {
    checkLogin();
    if ($_SESSION['role'] != 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * دالة تسجيل الأنشطة
 */
function logActivity($action, $details = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) 
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch(Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * دالة تحديث السندات المنتهية
 */
function updateExpiredDocuments() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE official_documents 
                               SET document_status = 'expired' 
                               WHERE document_status = 'active' 
                               AND expiry_date < CURDATE()");
        $stmt->execute();
        return $stmt->rowCount();
    } catch(Exception $e) {
        return 0;
    }
}

// تحديث السندات المنتهية تلقائياً
if(isset($_SESSION['user_id'])) {
    updateExpiredDocuments();
}
?>