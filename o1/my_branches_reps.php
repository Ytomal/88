<?php
require_once 'config.php';
checkLogin();

// معالجة إضافة فرع
if(isset($_POST['add_branch'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO my_branches (branch_name, branch_code, region_id, manager_name, phone, address, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['branch_name'],
            $_POST['branch_code'],
            $_POST['region_id'],
            $_POST['manager_name'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['status']
        ]);
        header("Location: my_branches_reps.php?success=branch_added");
        exit;
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// معالجة إضافة مندوب
if(isset($_POST['add_rep'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO sales_representatives (name, employee_code, phone, email, my_branch_id, department, status, notes) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['employee_code'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['my_branch_id'],
            $_POST['department'],
            $_POST['status'],
            $_POST['notes']
        ]);
        header("Location: my_branches_reps.php?success=rep_added");
        exit;
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// جلب البيانات
$branches = $pdo->query("SELECT b.*, r.region_name 
                         FROM my_branches b 
                         LEFT JOIN regions r ON b.region_id = r.id 
                         ORDER BY b.created_at DESC")->fetchAll();

$reps = $pdo->query("SELECT s.*, b.branch_name 
                     FROM sales_representatives s 
                     LEFT JOIN my_branches b ON s.my_branch_id = b.id 
                     ORDER BY s.created_at DESC")->fetchAll();

$regions = $pdo->query("SELECT * FROM regions WHERE status='active' ORDER BY region_name")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الفروع والمندوبين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            margin-right: 260px;
            padding: 25px;
        }
        .item-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-right: 4px solid #667eea;
            transition: all 0.3s;
        }
        .item-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-building text-primary"></i> إدارة الفروع والمندوبين</h2>
                    <p class="text-muted">فروع شركتك والمندوبين التابعين لها</p>
                </div>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    تمت العملية بنجاح!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- الفروع -->
            <div class="section-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-store"></i> فروع شركتك (<?= count($branches) ?>)</h4>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                        <i class="fas fa-plus"></i> إضافة فرع
                    </button>
                </div>

                <?php if(empty($branches)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-store fa-3x text-muted mb-3"></i>
                        <p class="text-muted">لا توجد فروع</p>
                    </div>
                <?php else: ?>
                    <?php foreach($branches as $branch): ?>
                        <div class="item-card">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5>
                                        <i class="fas fa-store text-primary"></i>
                                        <?= htmlspecialchars($branch['branch_name']) ?>
                                    </h5>
                                    <div class="d-flex gap-2 flex-wrap mb-2">
                                        <?php if($branch['branch_code']): ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-barcode"></i> <?= $branch['branch_code'] ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if($branch['region_name']): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-map-marker-alt"></i> <?= $branch['region_name'] ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="badge bg-<?= $branch['status']=='active'?'success':'danger' ?>">
                                            <?= $branch['status']=='active'?'نشط':'معطل' ?>
                                        </span>
                                    </div>
                                    <?php if($branch['manager_name']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-user-tie"></i> المدير: <?= htmlspecialchars($branch['manager_name']) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if($branch['phone']): ?>
                                        <small class="text-muted ms-3">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($branch['phone']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php
                                    $rep_count = $pdo->query("SELECT COUNT(*) FROM sales_representatives WHERE my_branch_id={$branch['id']}")->fetchColumn();
                                    ?>
                                    <div class="mb-2">
                                        <span class="badge bg-primary">
                                            <i class="fas fa-users"></i> <?= $rep_count ?> مندوب
                                        </span>
                                    </div>
                                    <button class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- المندوبين -->
            <div class="section-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-user-tie"></i> المندوبين والموظفين (<?= count($reps) ?>)</h4>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRepModal">
                        <i class="fas fa-plus"></i> إضافة مندوب
                    </button>
                </div>

                <?php if(empty($reps)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">لا يوجد مندوبين</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($reps as $rep): ?>
                            <div class="col-md-6">
                                <div class="item-card">
                                    <h5>
                                        <i class="fas fa-user text-success"></i>
                                        <?= htmlspecialchars($rep['name']) ?>
                                    </h5>
                                    <div class="d-flex gap-2 flex-wrap mb-2">
                                        <?php if($rep['employee_code']): ?>
                                            <span class="badge bg-secondary"><?= $rep['employee_code'] ?></span>
                                        <?php endif; ?>
                                        <?php
                                        $depts = [
                                            'sales_rep' => 'مندوب مبيعات',
                                            'branch' => 'فرع',
                                            'management' => 'إدارة',
                                            'other' => 'أخرى'
                                        ];
                                        ?>
                                        <span class="badge bg-primary"><?= $depts[$rep['department']] ?? '' ?></span>
                                        <?php if($rep['branch_name']): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-store"></i> <?= htmlspecialchars($rep['branch_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="badge bg-<?= $rep['status']=='active'?'success':'danger' ?>">
                                            <?= $rep['status']=='active'?'نشط':'معطل' ?>
                                        </span>
                                    </div>
                                    <small class="text-muted d-block">
                                        <?php if($rep['phone']): ?>
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($rep['phone']) ?>
                                        <?php endif; ?>
                                        <?php if($rep['email']): ?>
                                            <i class="fas fa-envelope ms-2"></i> <?= htmlspecialchars($rep['email']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Branch Modal -->
    <div class="modal fade" id="addBranchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة فرع جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>اسم الفرع *</label>
                            <input type="text" name="branch_name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>كود الفرع</label>
                                <input type="text" name="branch_code" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>المنطقة</label>
                                <select name="region_id" class="form-control">
                                    <option value="">اختر المنطقة</option>
                                    <?php foreach($regions as $region): ?>
                                        <option value="<?= $region['id'] ?>"><?= $region['region_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>اسم المدير</label>
                            <input type="text" name="manager_name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>الهاتف</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>العنوان</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>الحالة</label>
                            <select name="status" class="form-control">
                                <option value="active">نشط</option>
                                <option value="inactive">معطل</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_branch" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Rep Modal -->
    <div class="modal fade" id="addRepModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مندوب/موظف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>الاسم *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>كود الموظف</label>
                                <input type="text" name="employee_code" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>القسم *</label>
                                <select name="department" class="form-control" required>
                                    <option value="sales_rep">مندوب مبيعات</option>
                                    <option value="branch">فرع</option>
                                    <option value="management">إدارة</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>الفرع التابع له</label>
                            <select name="my_branch_id" class="form-control">
                                <option value="">بدون فرع</option>
                                <?php foreach($branches as $branch): ?>
                                    <option value="<?= $branch['id'] ?>"><?= $branch['branch_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>الهاتف</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>الحالة</label>
                            <select name="status" class="form-control">
                                <option value="active">نشط</option>
                                <option value="inactive">معطل</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_rep" class="btn btn-success">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>