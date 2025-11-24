<?php
require_once 'config.php';
checkLogin();

$customer_id = $_GET['customer_id'] ?? 0;

// جلب معلومات العميل
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if(!$customer) {
    header('Location: customers.php');
    exit;
}

// جلب الفروع
$stmt = $pdo->prepare("SELECT cb.*, r.region_name 
                       FROM customer_branches cb
                       LEFT JOIN regions r ON cb.region_id = r.id
                       WHERE cb.customer_id = ?
                       ORDER BY cb.created_at DESC");
$stmt->execute([$customer_id]);
$branches = $stmt->fetchAll();

// جلب المناطق للفلتر
$regions = $pdo->query("SELECT * FROM regions WHERE status = 'active' ORDER BY region_name")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة فروع - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f8f9fa;
        }
        .navbar { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .branch-card {
            border-left: 4px solid #667eea;
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .branch-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="fas fa-building"></i> فروع العميل: <?= htmlspecialchars($customer['company_name']) ?>
            </span>
            <a href="view_customer.php?id=<?= $customer_id ?>" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-right"></i> العودة
            </a>
        </div>
    </nav>

    <div class="container">
        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php
                $messages = [
                    'added' => 'تم إضافة الفرع بنجاح',
                    'updated' => 'تم تحديث الفرع بنجاح',
                    'deleted' => 'تم حذف الفرع بنجاح'
                ];
                echo $messages[$_GET['success']] ?? 'تمت العملية بنجاح';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-transparent">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> قائمة الفروع (<?= count($branches) ?>)
                        </h5>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                            <i class="fas fa-plus"></i> إضافة فرع جديد
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if(empty($branches)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                        <p class="text-muted">لا توجد فروع لهذا العميل بعد</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                            إضافة أول فرع
                        </button>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($branches as $branch): ?>
                            <div class="col-md-6">
                                <div class="branch-card">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1">
                                                <i class="fas fa-building text-primary"></i>
                                                <?= htmlspecialchars($branch['branch_name']) ?>
                                            </h5>
                                            <?php if($branch['branch_code']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-barcode"></i> <?= $branch['branch_code'] ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if($branch['status'] == 'active'): ?>
                                                <span class="badge bg-success">نشط</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">معطل</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <i class="fas fa-map-marker-alt text-danger"></i>
                                        <strong>المنطقة:</strong> <?= $branch['region_name'] ?? 'غير محدد' ?>
                                    </div>

                                    <?php if($branch['address']): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-location-dot text-info"></i>
                                            <strong>العنوان:</strong> <?= htmlspecialchars($branch['address']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if($branch['phone']): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-phone text-success"></i>
                                            <strong>الهاتف:</strong> 
                                            <a href="tel:<?= $branch['phone'] ?>"><?= $branch['phone'] ?></a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if($branch['manager_name']): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-user-tie text-warning"></i>
                                            <strong>المدير:</strong> <?= htmlspecialchars($branch['manager_name']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-2">
                                        <i class="fas fa-door-open text-primary"></i>
                                        <strong>عدد الفتحات:</strong> <?= $branch['shop_fronts_count'] ?>
                                    </div>

                                    <?php if($branch['shop_description']): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-info-circle text-secondary"></i>
                                            <strong>الوصف:</strong> <?= htmlspecialchars($branch['shop_description']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if($branch['google_maps_url']): ?>
                                        <div class="mb-3">
                                            <a href="<?= htmlspecialchars($branch['google_maps_url']) ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-map"></i> فتح في خرائط جوجل
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex gap-2 mt-3">
                                        <a href="edit_branch.php?id=<?= $branch['id'] ?>" class="btn btn-sm btn-info flex-fill">
                                            <i class="fas fa-edit"></i> تعديل
                                        </a>
                                        <button class="btn btn-sm btn-danger" onclick="deleteBranch(<?= $branch['id'] ?>)">
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal إضافة فرع -->
    <div class="modal fade" id="addBranchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة فرع جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="branches_action.php" method="POST">
                    <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">اسم الفرع *</label>
                                <input type="text" name="branch_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">كود الفرع</label>
                                <input type="text" name="branch_code" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المنطقة</label>
                                <select name="region_id" class="form-control">
                                    <option value="">اختر المنطقة</option>
                                    <?php foreach($regions as $region): ?>
                                        <option value="<?= $region['id'] ?>"><?= $region['region_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">عدد الفتحات</label>
                                <input type="number" name="shop_fronts_count" class="form-control" value="1" min="1">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">هاتف الفرع</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">البريد الإلكتروني</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">مدير الفرع</label>
                            <input type="text" name="manager_name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">العنوان التفصيلي</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">رابط خرائط جوجل</label>
                            <input type="url" name="google_maps_url" class="form-control" 
                                   placeholder="https://goo.gl/maps/...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">وصف المحل</label>
                            <textarea name="shop_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_branch" class="btn btn-primary">حفظ الفرع</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteBranch(id) {
            if(confirm('هل أنت متأكد من حذف هذا الفرع؟')) {
                window.location.href = 'branches_action.php?delete=' + id + '&customer_id=<?= $customer_id ?>';
            }
        }
    </script>
</body>
</html>