<?php
require_once 'config.php';
checkLogin();

// جلب المناطق مع عدد العملاء والفروع
$stmt = $pdo->query("SELECT r.*, 
                     (SELECT COUNT(*) FROM customers WHERE region_id = r.id) as customers_count,
                     (SELECT COUNT(*) FROM customer_branches WHERE region_id = r.id) as branches_count
                     FROM regions r
                     ORDER BY r.region_name ASC");
$regions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المناطق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="professional_style.css">
    <style>
        .region-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: all 0.3s;
            border-left: 4px solid #667eea;
        }
        .region-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .region-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        .region-stat {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            flex: 1;
            text-align: center;
        }
        .region-stat .number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .region-stat .label {
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-map-marker-alt text-primary"></i> إدارة المناطق</h2>
                    <p class="text-muted">عرض وإدارة المناطق الجغرافية</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRegionModal">
                    <i class="fas fa-plus"></i> إضافة منطقة جديدة
                </button>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php
                    $messages = [
                        'added' => 'تم إضافة المنطقة بنجاح',
                        'updated' => 'تم تحديث المنطقة بنجاح',
                        'deleted' => 'تم حذف المنطقة بنجاح'
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
                        'has_customers' => 'لا يمكن حذف المنطقة لوجود عملاء مرتبطين بها'
                    ];
                    echo $errors[$_GET['error']] ?? htmlspecialchars($_GET['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- إحصائيات -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="region-card" style="border-left-color: #667eea;">
                        <div class="text-center">
                            <i class="fas fa-map-marked-alt fa-3x text-primary mb-3"></i>
                            <h3><?= count($regions) ?></h3>
                            <p class="text-muted mb-0">إجمالي المناطق</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="region-card" style="border-left-color: #28a745;">
                        <div class="text-center">
                            <i class="fas fa-users fa-3x text-success mb-3"></i>
                            <h3><?= array_sum(array_column($regions, 'customers_count')) ?></h3>
                            <p class="text-muted mb-0">إجمالي العملاء</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="region-card" style="border-left-color: #ffc107;">
                        <div class="text-center">
                            <i class="fas fa-building fa-3x text-warning mb-3"></i>
                            <h3><?= array_sum(array_column($regions, 'branches_count')) ?></h3>
                            <p class="text-muted mb-0">إجمالي الفروع</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- قائمة المناطق -->
            <div class="row">
                <?php if(empty($regions)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-map-marker-alt fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">لا توجد مناطق</h4>
                            <p class="text-muted">ابدأ بإضافة أول منطقة</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addRegionModal">
                                <i class="fas fa-plus"></i> إضافة منطقة
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($regions as $region): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="region-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1">
                                            <i class="fas fa-map-marker-alt text-primary"></i>
                                            <?= htmlspecialchars($region['region_name']) ?>
                                        </h5>
                                        <?php if($region['region_code']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-barcode"></i> <?= $region['region_code'] ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if($region['status'] == 'active'): ?>
                                            <span class="badge bg-success">نشط</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">معطل</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if($region['description']): ?>
                                    <p class="text-muted small mb-3">
                                        <i class="fas fa-info-circle"></i> <?= htmlspecialchars($region['description']) ?>
                                    </p>
                                <?php endif; ?>

                                <div class="region-stats">
                                    <div class="region-stat">
                                        <div class="number"><?= $region['customers_count'] ?></div>
                                        <div class="label">عميل</div>
                                    </div>
                                    <div class="region-stat">
                                        <div class="number"><?= $region['branches_count'] ?></div>
                                        <div class="label">فرع</div>
                                    </div>
                                </div>

                                <hr>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-info flex-fill" 
                                            onclick="editRegion(<?= $region['id'] ?>, '<?= htmlspecialchars($region['region_name']) ?>', '<?= htmlspecialchars($region['region_code']) ?>', '<?= htmlspecialchars($region['description']) ?>', '<?= $region['status'] ?>')">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                    <a href="customers.php?region=<?= $region['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-users"></i> العملاء
                                    </a>
                                    <?php if($region['customers_count'] == 0): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteRegion(<?= $region['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal إضافة منطقة -->
    <div class="modal fade" id="addRegionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة منطقة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="regions_action.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم المنطقة *</label>
                            <input type="text" name="region_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الكود</label>
                            <input type="text" name="region_code" class="form-control" placeholder="مثال: RD">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="وصف المنطقة..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-control">
                                <option value="active">نشط</option>
                                <option value="inactive">معطل</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_region" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal تعديل منطقة -->
    <div class="modal fade" id="editRegionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل المنطقة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="regions_action.php" method="POST">
                    <input type="hidden" name="region_id" id="edit_region_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم المنطقة *</label>
                            <input type="text" name="region_name" id="edit_region_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الكود</label>
                            <input type="text" name="region_code" id="edit_region_code" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الحالة</label>
                            <select name="status" id="edit_status" class="form-control">
                                <option value="active">نشط</option>
                                <option value="inactive">معطل</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="update_region" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editRegion(id, name, code, description, status) {
            document.getElementById('edit_region_id').value = id;
            document.getElementById('edit_region_name').value = name;
            document.getElementById('edit_region_code').value = code;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_status').value = status;
            
            var modal = new bootstrap.Modal(document.getElementById('editRegionModal'));
            modal.show();
        }

        function deleteRegion(id) {
            if(confirm('هل أنت متأكد من حذف هذه المنطقة؟')) {
                window.location.href = 'regions_action.php?delete=' + id;
            }
        }
    </script>
</body>
</html>