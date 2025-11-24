<?php
// ملف اختبار للتأكد من عمل جميع الدوال
require_once 'config.php';
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار الدوال</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="card" style="margin-top: 50px;">
            <h1 style="text-align: center; color: #667eea;">🔍 اختبار الدوال</h1>
            
            <div class="alert alert-info">
                <h3>اختبار الاتصال بقاعدة البيانات:</h3>
                <?php if ($conn): ?>
                    <p>✅ الاتصال ناجح!</p>
                    <p><strong>الخادم:</strong> <?php echo DB_HOST; ?></p>
                    <p><strong>قاعدة البيانات:</strong> <?php echo DB_NAME; ?></p>
                <?php else: ?>
                    <p>❌ فشل الاتصال</p>
                <?php endif; ?>
            </div>

            <div class="alert alert-success">
                <h3>اختبار الدوال المتاحة:</h3>
                <?php
                $functions = [
                    'clean_input',
                    'show_message',
                    'log_activity',
                    'display_user_bar',
                    'check_login',
                    'check_permission',
                    'get_current_user',
                    'get_user_role',
                    'validate_session'
                ];
                
                foreach ($functions as $func) {
                    if (function_exists($func)) {
                        echo "<p>✅ الدالة <strong>$func()</strong> موجودة</p>";
                    } else {
                        echo "<p>❌ الدالة <strong>$func()</strong> غير موجودة</p>";
                    }
                }
                ?>
            </div>

            <div class="alert alert-info">
                <h3>اختبار الجداول:</h3>
                <?php
                $tables = [
                    'customers',
                    'official_documents',
                    'products',
                    'sizes',
                    'visits',
                    'invoices',
                    'invoice_items',
                    'payments',
                    'payment_invoice_link',
                    'customer_details',
                    'users',
                    'activity_log',
                    'notifications',
                    'notification_settings',
                    'uploaded_documents'
                ];
                
                echo "<p><strong>الجداول الموجودة:</strong></p>";
                foreach ($tables as $table) {
                    $result = $conn->query("SHOW TABLES LIKE '$table'");
                    if ($result && $result->num_rows > 0) {
                        echo "<p>✅ الجدول <strong>$table</strong> موجود</p>";
                    } else {
                        echo "<p>❌ الجدول <strong>$table</strong> غير موجود</p>";
                    }
                }
                ?>
            </div>

            <div class="alert alert-warning">
                <h3>اختبار المستخدمين:</h3>
                <?php
                $users = $conn->query("SELECT username, role, status FROM users");
                if ($users && $users->num_rows > 0) {
                    echo "<p>✅ عدد المستخدمين: " . $users->num_rows . "</p>";
                    echo "<table style='width: 100%; margin-top: 10px;'>";
                    echo "<tr><th>اسم المستخدم</th><th>الصلاحية</th><th>الحالة</th></tr>";
                    while ($user = $users->fetch_assoc()) {
                        $status_color = $user['status'] == 'active' ? 'green' : 'red';
                        echo "<tr>";
                        echo "<td>{$user['username']}</td>";
                        echo "<td>{$user['role']}</td>";
                        echo "<td style='color: $status_color;'>{$user['status']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>❌ لا توجد مستخدمين</p>";
                }
                ?>
            </div>

            <div class="alert alert-success">
                <h3>اختبار دالة clean_input:</h3>
                <?php
                $test_input = "<script>alert('test')</script>";
                $cleaned = clean_input($test_input);
                echo "<p><strong>المدخل:</strong> " . htmlspecialchars($test_input) . "</p>";
                echo "<p><strong>بعد التنظيف:</strong> $cleaned</p>";
                echo "<p>✅ الدالة تعمل بشكل صحيح!</p>";
                ?>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="login.php" class="btn btn-primary">الانتقال لتسجيل الدخول</a>
                <a href="index.php" class="btn btn-info">الصفحة الرئيسية</a>
            </div>

            <div class="alert alert-danger" style="margin-top: 20px;">
                <h3>⚠️ تحذير أمني:</h3>
                <p><strong>احذف هذا الملف بعد الاختبار!</strong></p>
                <p>هذا الملف يعرض معلومات حساسة عن النظام</p>
            </div>
        </div>
    </div>
</body>
</html>