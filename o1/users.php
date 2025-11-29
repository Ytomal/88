<?php
require_once 'config.php';
checkAdmin(); // فقط المدير يستطيع الوصول

// جلب المستخدمين مع إحصائياتهم
$stmt = $pdo->query("SELECT u.*,
                     (SELECT COUNT(*) FROM activity_log WHERE user_id = u.id) as activities_count
                     FROM users u
                     ORDER BY u.created_at DESC");
$users = $stmt->fetchAll();

// إحصائيات
$stats = [
    'total' => count($users),
    'active' => count(array_filter($users, fn($u) => $u['status'] == 'active')),
    'admins' => count(array_filter($users, fn($u) => $u['role'] == 'admin')),
    'managers' => count(array_filter($users, fn($u) => $u['role'] == 'manager')),
    'employees' => count(array_filter($users, fn($u) => $u['role'] == 'employee')),
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="professional_style.css">
    <style>
        .user-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: all 0.3s;
            border-left: 4px solid #667eea;
        }
        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-card i {
            font-size: 36px;
            margin-bottom: 10px;
        }
        .stat-card h3 {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-users-cog text-primary"></i> إدارة المستخدمين</h2>
                    <p class="text-muted">عرض وإدارة مستخدمي النظام</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus"></i> إضافة مستخدم جديد
                </button>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php
                    $messages = [
                        'added' => 'تم إضافة المستخدم بنجاح',
                        'updated' => 'تم تحديث بيانات المستخدم بنجاح',
                        'deleted' => 'تم حذف المستخدم بنجاح',
                        'password_reset' => 'تم إعادة تعيين كلمة المرور بنجاح. كلمة المرور الجديدة: ' . ($_GET['password'] ?? '123456')
                    ];
                    echo $messages[$_GET['success']] ?? 'تمت العملية بنجاح';
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php
                    $errors = [
                        'username_exists' => 'اسم المستخدم موجود مسبقاً',
                        'cannot_delete_self' => 'لا يمكنك حذف حسابك الخاص'
                    ];
                    echo $errors[$_GET['error']] ?? htmlspecialchars($_GET['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- إحصائيات -->
            <div class="row mb-4">
                <div class="col-md-2-4">
                    <div class="stat-card" style="border-left: 4px solid #667eea;">
                        <i class="fas fa-users text-primary"></i>
                        <h3><?= $stats['total'] ?></h3>
                        <p class="text-muted mb-0">إجمالي المستخدمين</p>
                    </div>
                </div>
                <div class="col-md-2-4">
                    <div class="stat-card" style="border-left: 4px solid #28a745;">
                        <i class="fas fa-user-check text-success"></i>
                        <h3><?= $stats['active'] ?></h3>
                        <p class="text-muted mb-0">نشط</p>
                    </div>
                </div>
                <div class="col-md-2-4">
                    <div class="stat-card" style="border-left: 4px solid #dc3545;">
                        <i class="fas fa-user-shield text-danger"></i>
                        <h3><?= $stats['admins'] ?></h3>
                        <p class="text-muted mb-0">مدراء</p>
                    </div>
                </div>
                <div class="col-md-2-4">
                    <div class="stat-card" style="border-left: 4px solid #ffc107;">
                        <i class="fas fa-user-tie text-warning"></i>
                        <h3><?= $stats['managers'] ?></h3>
                        <p class="text-muted mb-0">مدراء مبيعات</p>
                    </div>
                </div>
                <div class="col-md-2-4">
                    <div class="stat-card" style="border-left: 4px solid #17a2b8;">
                        <i class="fas fa-user text-info"></i>
                        <h3><?= $stats['employees'] ?></h3>
                        <p class="text-muted mb-0">موظفين</p>
                    </div>
                </div>
            </div>

            <!-- قائمة المستخدمين -->
            <div class="row">
                <?php foreach($users as $user): ?>
                    <?php
                    $role_colors = [
                        'admin' => 'danger',
                        'manager' => 'warning',
                        'employee' => 'info'
                    ];
                    $role_labels = [
                        'admin' => 'مدير',
                        'manager' => 'مدير مبيعات',
                        'employee' => 'موظف'
                    ];
                    $role_color = $role_colors[$user['role']] ?? 'secondary';
                    $role_label = $role_labels[$user['role']] ?? 'غير محدد';
                    
                    $initial = mb_substr($user['full_name'], 0, 1);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="user-card">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <div class="user-avatar">
                                    <?= $initial ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= htmlspecialchars($user['full_name']) ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-at"></i> <?= htmlspecialchars($user['username']) ?>
                                    </small>
                                    <div class="mt-2">
                                        <span class="badge bg-<?= $role_color ?>"><?= $role_label ?></span>
                                        <?php if($user['status'] == 'active'): ?>
                                            <span class="badge bg-success">نشط</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">معطل</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <?php if($user['email']): ?>
                                    <small class="d-block mb-1">
                                        <i class="fas fa-envelope text-info"></i>
                                        <?= htmlspecialchars($user['email']) ?>
                                    </small>
                                <?php endif; ?>
                                <?php if($user['phone']): ?>
                                    <small class="d-block mb-1">
                                        <i class="fas fa-phone text-success"></i>
                                        <?= htmlspecialchars($user['phone']) ?>
                                    </small>
                                <?php endif; ?>
                                <small class="d-block mb-1">
                                    <i class="fas fa-clock text-warning"></i>
                                    آخر دخول: <?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'لم يسجل دخول' ?>
                                </small>
                                <small class="d-block">
                                    <i class="fas fa-chart-line text-primary"></i>
                                    النشاطات: <?= $user['activities_count'] ?>
                                </small>
                            </div>

                            <hr>

                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-info flex-fill" 
                                        onclick='editUser(<?= json_encode($user) ?>)'>
                                    <i class="fas fa-edit"></i> تعديل
                                </button>
                                <button class="btn btn-sm btn-warning" 
                                        onclick="resetPassword(<?= $user['id'] ?>)" 
                                        title="إعادة تعيين كلمة المرور">
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="deleteUser(<?= $user['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal إضافة مستخدم -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مستخدم جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="users_action.php" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">اسم المستخدم *</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">كلمة المرور *</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الاسم الكامل *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">البريد الإلكتروني</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الهاتف</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الصلاحية *</label>
                                <select name="role" class="form-control" required>
                                    <option value="employee">موظف</option>
                                    <option value="manager">مدير مبيعات</option>
                                    <option value="admin">مدير</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الحالة</label>
                                <select name="status" class="form-control">
                                    <option value="active">نشط</option>
                                    <option value="inactive">معطل</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_user" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal تعديل مستخدم -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل المستخدم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="users_action.php" method="POST">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم المستخدم</label>
                            <input type="text" id="edit_username" class="form-control" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الاسم الكامل *</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">البريد الإلكتروني</label>
                                <input type="email" name="email" id="edit_email" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الهاتف</label>
                                <input type="text" name="phone" id="edit_phone" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الصلاحية *</label>
                                <select name="role" id="edit_role" class="form-control" required>
                                    <option value="employee">موظف</option>
                                    <option value="manager">مدير مبيعات</option>
                                    <option value="admin">مدير</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الحالة</label>
                                <select name="status" id="edit_status" class="form-control">
                                    <option value="active">نشط</option>
                                    <option value="inactive">معطل</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="update_user" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            
            var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }

        function resetPassword(id) {
            if(confirm('هل تريد إعادة تعيين كلمة المرور؟\nكلمة المرور الجديدة ستكون: 123456')) {
                window.location.href = 'users_action.php?reset_password=' + id;
            }
        }

        function deleteUser(id) {
            if(confirm('هل أنت متأكد من حذف هذا المستخدم؟')) {
                window.location.href = 'users_action.php?delete=' + id;
            }
        }
    </script>
</body>
</html>