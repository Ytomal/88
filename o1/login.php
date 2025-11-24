<?php
// بدء الجلسة فقط إذا لم تكن موجودة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Riyadh');

// الاتصال بقاعدة البيانات
$host = 'localhost';
$dbname = 'customers_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("خطأ في الاتصال: " . $e->getMessage());
}

// إذا كان المستخدم مسجل دخول بالفعل
if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// معالجة تسجيل الدخول
if(isset($_POST['login'])) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    if(empty($user) || empty($pass)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$user]);
            $userData = $stmt->fetch();
            
            if($userData && password_verify($pass, $userData['password'])) {
                // تسجيل الدخول ناجح
                $_SESSION['user_id'] = $userData['id'];
                $_SESSION['username'] = $userData['username'];
                $_SESSION['full_name'] = $userData['full_name'];
                $_SESSION['role'] = $userData['role'];
                
                // تحديث آخر دخول
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$userData['id']]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
            }
        } catch(Exception $e) {
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام إدارة العملاء</title>
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
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        .login-left {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 40px;
            text-align: center;
        }
        .login-right {
            padding: 60px 40px;
        }
        .login-logo {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="row g-0">
                <div class="col-md-5 login-left">
                    <div class="login-logo">
                        <i class="fas fa-building"></i>
                    </div>
                    <h2>نظام إدارة العملاء</h2>
                    <p class="mt-3">إدارة شاملة لعملائك وفروعهم</p>
                    <div class="mt-5">
                        <i class="fas fa-check-circle me-2"></i> إدارة المناطق والفروع<br>
                        <i class="fas fa-check-circle me-2"></i> السندات الرسمية<br>
                        <i class="fas fa-check-circle me-2"></i> الفواتير والمدفوعات<br>
                        <i class="fas fa-check-circle me-2"></i> تقارير وإحصائيات
                    </div>
                </div>
                
                <div class="col-md-7 login-right">
                    <h3 class="mb-4">تسجيل الدخول</h3>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label">اسم المستخدم</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" name="username" class="form-control form-control-lg" 
                                       placeholder="أدخل اسم المستخدم" required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">كلمة المرور</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" name="password" class="form-control form-control-lg" 
                                       placeholder="أدخل كلمة المرور" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="login" class="btn btn-primary btn-login w-100">
                            <i class="fas fa-sign-in-alt"></i> دخول
                        </button>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <small class="text-muted">
                            الحسابات الافتراضية:<br>
                            <strong>admin</strong> / <strong>123456</strong> (مدير)<br>
                            <strong>manager</strong> / <strong>123456</strong> (مدير مبيعات)
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>