<?php
require_once 'config.php';
checkLogin();

$customer_id = $_GET['id'] ?? 0;

// جلب معلومات العميل
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if(!$customer) {
    header('Location: customers.php');
    exit;
}

// جلب المناطق
$regions = $pdo->query("SELECT * FROM regions WHERE status = 'active' ORDER BY region_name")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل العميل - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="professional_style.css">
    <style>
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-user-edit text-primary"></i> تعديل بيانات العميل</h2>
                    <p class="text-muted"><?= htmlspecialchars($customer['company_name']) ?></p>
                </div>
                <div class="d-flex gap-2">
                    <a href="view_customer.php?id=<?= $customer_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                </div>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="customer_action.php" method="POST">
                <input type="hidden" name="customer_id" value="<?= $customer_id ?>">

                <!-- المعلومات الأساسية -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-info-circle"></i>
                        <h5 class="mb-0">المعلومات الأساسية</h5>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">اسم الشركة/المؤسسة *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-building"></i></span>
                                <input type="text" name="company_name" class="form-control" 
                                       value="<?= htmlspecialchars($customer['company_name']) ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">نوع الشركة *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                <select name="company_type" class="form-control" required>
                                    <option value="individual_institution" <?= $customer['company_type'] == 'individual_institution' ? 'selected' : '' ?>>
                                        مؤسسة فردية
                                    </option>
                                    <option value="large_institution" <?= $customer['company_type'] == 'large_institution' ? 'selected' : '' ?>>
                                        مؤسسة كبيرة
                                    </option>
                                    <option value="company" <?= $customer['company_type'] == 'company' ? 'selected' : '' ?>>
                                        شركة
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">اسم المالك *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" name="owner_name" class="form-control" 
                                       value="<?= htmlspecialchars($customer['owner_name']) ?>" required>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">الشخص المسؤول</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                <input type="text" name="responsible_person" class="form-control" 
                                       value="<?= htmlspecialchars($customer['responsible_person'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">المنطقة *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <select name="region_id" class="form-control" required>
                                    <option value="">اختر المنطقة</option>
                                    <?php foreach($regions as $region): ?>
                                        <option value="<?= $region['id'] ?>" <?= $customer['region_id'] == $region['id'] ? 'selected' : '' ?>>
                                            <?= $region['region_name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="text" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">تاريخ البداية</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" name="start_date" class="form-control" 
                                       value="<?= $customer['start_date'] ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">العنوان التفصيلي</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-location-dot"></i></span>
                            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-sticky-note"></i></span>
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-control">
                                <option value="active" <?= $customer['status'] == 'active' ? 'selected' : '' ?>>نشط</option>
                                <option value="inactive" <?= $customer['status'] == 'inactive' ? 'selected' : '' ?>>معطل</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">الفروع</label>
                            <div class="form-check form-switch mt-2">
                                <input type="checkbox" name="has_branches" class="form-check-input" value="1" 
                                       <?= $customer['has_branches'] ? 'checked' : '' ?>>
                                <label class="form-check-label">لدى العميل فروع متعددة</label>
                            </div>
                            <?php if($customer['has_branches']): ?>
                                <small class="text-muted">
                                    <a href="branches.php?customer_id=<?= $customer_id ?>">
                                        <i class="fas fa-building"></i> إدارة الفروع
                                    </a>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- معلومات إضافية -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-chart-line"></i>
                        <h5 class="mb-0">معلومات إضافية</h5>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>تاريخ الإضافة:</strong> <?= date('Y-m-d H:i', strtotime($customer['created_at'])) ?>
                        <br>
                        <strong>آخر تحديث:</strong> <?= date('Y-m-d H:i', strtotime($customer['updated_at'])) ?>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <a href="branches.php?customer_id=<?= $customer_id ?>" class="btn btn-outline-primary w-100 mb-2">
                                <i class="fas fa-building"></i> إدارة الفروع
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="documents.php?customer_id=<?= $customer_id ?>" class="btn btn-outline-success w-100 mb-2">
                                <i class="fas fa-file-contract"></i> السندات الرسمية
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="invoices.php?customer_id=<?= $customer_id ?>" class="btn btn-outline-warning w-100 mb-2">
                                <i class="fas fa-file-invoice"></i> الفواتير
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="customer_visits.php?id=<?= $customer_id ?>" class="btn btn-outline-info w-100 mb-2">
                                <i class="fas fa-calendar-alt"></i> الزيارات
                            </a>
                        </div>
                    </div>
                </div>

                <!-- أزرار الحفظ -->
                <div class="text-center mb-4">
                    <button type="submit" name="update_customer" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> حفظ التعديلات
                    </button>
                    <a href="view_customer.php?id=<?= $customer_id ?>" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times"></i> إلغاء
                    </a>
                    <button type="button" class="btn btn-danger btn-lg" onclick="deleteCustomer()">
                        <i class="fas fa-trash"></i> حذف العميل
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteCustomer() {
            if(confirm('هل أنت متأكد من حذف هذا العميل؟\n\nتحذير: سيتم حذف جميع البيانات المرتبطة به:\n- الفروع\n- السندات الرسمية\n- الفواتير\n- المدفوعات\n- الزيارات\n- المقاسات\n\nهذا الإجراء لا يمكن التراجع عنه!')) {
                if(confirm('تأكيد نهائي: هل أنت متأكد 100% من حذف العميل وجميع بياناته؟')) {
                    window.location.href = 'customer_action.php?delete=<?= $customer_id ?>';
                }
            }
        }
    </script>
</body>
</html>