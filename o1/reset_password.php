<?php
require_once 'config.php';

// إعادة تعيين كلمات المرور الافتراضية
// كلمة المرور الافتراضية: "password"
$default_password = password_hash('password', PASSWORD_DEFAULT);

if(isset($_POST['reset_all'])) {
    try {
        // تحديث كلمة المرور لجميع المستخدمين
        $stmt = $pdo->prepare("UPDATE users SET password = ?");
        $stmt->execute([$default_password]);
        
        $success = "تم إعادة تعيين كلمات المرور بنجاح!<br>كلمة المرور الافتراضية: <strong>password</strong>";
    } catch(Exception $e) {
        $error = "خطأ: " . $e->getMessage();
    }
}

// جلب جميع المستخدمين
$users = $pdo->query("SELECT * FROM users ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .reset-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header i {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 20px;
        }
        .user-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-right: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="header">
            <i class="fas fa-key"></i>
            <h2>إعادة تعيين كلمة المرور</h2>
            <p class="text-muted">نظام إدارة العملاء</p>
        </div>

        <?php if(isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning mb-4">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>تحذير:</strong> سيتم إعادة تعيين كلمة المرور لجميع المستخدمين إلى: <code>password</code>
        </div>

        <h5 class="mb-3">المستخدمين الحاليين:</h5>
        <?php foreach($users as $user): ?>
            <div class="user-card">
                <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                <br>
                <small class="text-muted">
                    <i class="fas fa-user"></i> اسم المستخدم: <?= htmlspecialchars($user['username']) ?>
                    | <i class="fas fa-shield-alt"></i> الصلاحية: <?= $user['role'] ?>
                </small>
            </div>
        <?php endforeach; ?>

        <form method="POST" class="mt-4">
            <button type="submit" name="reset_all" class="btn btn-danger w-100 btn-lg" 
                    onclick="return confirm('هل أنت متأكد من إعادة تعيين كلمات المرور لجميع المستخدمين؟')">
                <i class="fas fa-redo"></i> إعادة تعيين الكل
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة لتسجيل الدخول
            </a>
        </div>

        <div class="alert alert-info mt-4 mb-0">
            <small>
                <strong>ملاحظة:</strong> بعد إعادة التعيين، استخدم:<br>
                <code>اسم المستخدم: admin</code><br>
                <code>كلمة المرور: password</code>
            </small>
        </div>
    </div>
</body>
</html>