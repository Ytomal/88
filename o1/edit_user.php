<?php
require_once 'config.php';
checkAdmin();

$user_id = $_GET['id'] ?? 0;

// جلب بيانات المستخدم
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if(!$user) {
    header('Location: users.php');
    exit;
}

// جلب إحصائيات المستخدم
$activities = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ?")->execute([$user_id]) ? 
              $pdo->query("SELECT COUNT(*) FROM activity_log WHERE user_id = $user_id")->fetchColumn() : 0;
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المستخدم - <?= htmlspecialchars($user['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="professional_style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-user-edit text-primary"></i> تعديل المستخدم</h2>
                    <p class="text-muted">تحديث بيانات <?= htmlspecialchars($user['full_name']) ?></p>
                </div>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i> العودة
                </a>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <form action="users_action.php" method="POST">
                                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">اسم المستخدم</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                    <small class="text-muted">لا يمكن تعديل اسم المستخدم</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">الاسم الكامل *</label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">البريد الإلكتروني</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">الهاتف</label>
                                        <input type="text" name="phone" class="form-control" 
                                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">الصلاحية *</label>
                                        <select name="role" class="form-control" required>
                                            <option value="employee" <?= $user['role'] == 'employee' ? 'selected' : '' ?>>موظف</option>
                                            <option value="manager" <?= $user['role'] == 'manager' ? 'selected' : '' ?>>مدير مبيعات</option>
                                            <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>مدير</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">الحالة</label>
                                        <select name="status" class="form-control">
                                            <option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>نشط</option>
                                            <option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : '' ?>>معطل</option>
                                        </select>
                                    </div>
                                </div>

                                <hr>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="update_user" class="btn btn-primary">
                                        <i class="fas fa-save"></i> حفظ التعديلات
                                    </button>
                                    <a href="users.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> إلغاء
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-info-circle"></i> معلومات إضافية</h5>
                            <hr>
                            <p><strong>تاريخ الإضافة:</strong><br>
                               <?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></p>
                            <p><strong>آخر تحديث:</strong><br>
                               <?= date('Y-m-d H:i', strtotime($user['updated_at'])) ?></p>
                            <p><strong>آخر دخول:</strong><br>
                               <?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'لم يسجل دخول' ?></p>
                            <p><strong>عدد الأنشطة:</strong> <?= $activities ?></p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-key"></i> إدارة كلمة المرور</h5>
                            <hr>
                            <button class="btn btn-warning w-100" onclick="resetPassword()">
                                <i class="fas fa-redo"></i> إعادة تعيين كلمة المرور
                            </button>
                            <small class="text-muted d-block mt-2">كلمة المرور الجديدة: 123456</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetPassword() {
            if(confirm('هل تريد إعادة تعيين كلمة المرور إلى 123456؟')) {
                window.location.href = 'users_action.php?reset_password=<?= $user_id ?>';
            }
        }
    </script>
</body>
</html>