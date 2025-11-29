<?php
require_once 'config.php';

echo "<h3>إعادة تعيين كلمات المرور</h3>";

// كلمات المرور الجديدة
$users = [
    ['admin', 'admin123'],
    ['manager', 'manager123'],
    ['employee', 'employee123']
];

foreach ($users as $user) {
    $username = $user[0];
    $new_password = $user[1];
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE users SET password = ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashed_password, $username);
    
    if ($stmt->execute()) {
        echo "✅ تم تحديث كلمة المرور للمستخدم: <strong>$username</strong> - كلمة المرور: <strong>$new_password</strong><br>";
    } else {
        echo "❌ خطأ في تحديث كلمة المرور للمستخدم: $username<br>";
    }
    
    $stmt->close();
}

echo "<hr><h4>يمكنك الآن تسجيل الدخول باستخدام:</h4>";
echo "<ul>";
echo "<li><strong>admin</strong> / <strong>admin123</strong> (مدير النظام)</li>";
echo "<li><strong>manager</strong> / <strong>manager123</strong> (مدير المبيعات)</li>";
echo "<li><strong>employee</strong> / <strong>employee123</strong> (موظف مبيعات)</li>";
echo "</ul>";

$conn->close();
?>