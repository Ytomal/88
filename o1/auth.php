<?php
// ملف الحماية والصلاحيات

// التحقق من تسجيل الدخول
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// التحقق من الصلاحيات
function check_permission($required_role) {
    if (!isset($_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }
    
    $roles_hierarchy = [
        'admin' => 3,
        'manager' => 2,
        'employee' => 1
    ];
    
    $user_level = $roles_hierarchy[$_SESSION['role']] ?? 0;
    $required_level = $roles_hierarchy[$required_role] ?? 0;
    
    if ($user_level < $required_level) {
        die("
        <html dir='rtl'>
        <head>
            <meta charset='UTF-8'>
            <link rel='stylesheet' href='style.css'>
        </head>
        <body>
            <div class='container'>
                <div class='card' style='margin-top: 50px; text-align: center;'>
                    <h1 style='color: #dc3545;'>⛔ خطأ في الصلاحيات</h1>
                    <p style='font-size: 1.2em; margin: 20px 0;'>ليس لديك صلاحية للوصول إلى هذه الصفحة</p>
                    <a href='index.php' class='btn btn-primary'>العودة للصفحة الرئيسية</a>
                </div>
            </div>
        </body>
        </html>
        ");
    }
}

// الحصول على اسم المستخدم الحالي


// الحصول على صلاحية المستخدم
function get_user_role() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';
}

// التحقق من جلسة المستخدم
function validate_session() {
    // التحقق من انتهاء الجلسة (30 دقيقة)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();
}
?>