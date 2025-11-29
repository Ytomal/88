<?php
require_once 'config.php';
checkLogin();

$customer_id = $_GET['id'] ?? 0;

// جلب معلومات العميل
$stmt = $pdo->prepare("SELECT c.*, r.region_name 
                       FROM customers c
                       LEFT JOIN regions r ON c.region_id = r.id
                       WHERE c.id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if(!$customer) {
    header('Location: customers.php');
    exit;
}

$company_type_labels = [
    'company' => 'شركة',
    'large_institution' => 'مؤسسة كبيرة',
    'individual_institution' => 'مؤسسة فردية'
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معلومات العميل - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="professional_style.css">
    <style>
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .info-row {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-weight: 600;
            color: #667eea;
            min-width: 180px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-value {
            color: #333;
            flex: 1;
        }
        .customer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        .edit-btn {
            position: absolute;
            top: 20px;
            left: 20px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- رأس الصفحة -->
            <div class="customer-header position-relative">
                <a href="edit_customer.php?id=<?= $customer_id ?>" class="btn btn-light edit-btn">
                    <i class="fas fa-edit"></i> تعديل
                </a>
                <h2 class="mb-3">
                    <i class="fas fa-building"></i>
                    <?= htmlspecialchars($customer['company_name']) ?>
                </h2>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-light text-dark">
                        <?= $company_type_labels[$customer['company_type']] ?? 'غير محدد' ?>
                    </span>
                    <?php if($customer['status'] == 'active'): ?>
                        <span class="badge bg-success">نشط</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">معطل</span>
                    <?php endif; ?>
                    <?php if($customer['has_branches']): ?>
                        <span class="badge bg-warning text-dark">
                            <i class="fas fa-building"></i> لديه فروع
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <!-- المعلومات الأساسية -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5 class="mb-4">
                            <i class="fas fa-info-circle text-primary"></i>
                            المعلومات الأساسية
                        </h5>

                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-building"></i>
                                اسم الشركة/المؤسسة
                            </div>
                            <div class="info-value">
                                <strong><?= htmlspecialchars($customer['company_name']) ?></strong>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-briefcase"></i>
                                نوع الشركة
                            </div>
                            <div class="info-value">
                                <?= $company_type_labels[$customer['company_type']] ?? 'غير محدد' ?>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-user"></i>
                                اسم المالك
                            </div>
                            <div class="info-value">
                                <?= htmlspecialchars($customer['owner_name']) ?>
                            </div>
                        </div>

                        <?php if($customer['responsible_person']): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-user-tie"></i>
                                الشخص المسؤول
                            </div>
                            <div class="info-value">
                                <?= htmlspecialchars($customer['responsible_person']) ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-toggle-on"></i>
                                الحالة
                            </div>
                            <div class="info-value">
                                <?php if($customer['status'] == 'active'): ?>
                                    <span class="badge bg-success">نشط</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">معطل</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- معلومات الاتصال -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5 class="mb-4">
                            <i class="fas fa-address-book text-primary"></i>
                            معلومات الاتصال والموقع
                        </h5>

                        <?php if($customer['phone']): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-phone"></i>
                                رقم الهاتف
                            </div>
                            <div class="info-value">
                                <a href="tel:<?= $customer['phone'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($customer['phone']) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($customer['email']): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-envelope"></i>
                                البريد الإلكتروني
                            </div>
                            <div class="info-value">
                                <a href="mailto:<?= $customer['email'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($customer['email']) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-map-marker-alt"></i>
                                المنطقة
                            </div>
                            <div class="info-value">
                                <?= htmlspecialchars($customer['region_name'] ?? 'غير محدد') ?>
                            </div>
                        </div>

                        <?php if($customer['address']): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-location-dot"></i>
                                العنوان التفصيلي
                            </div>
                            <div class="info-value">
                                <?= nl2br(htmlspecialchars($customer['address'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($customer['start_date']): ?>
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-calendar-plus"></i>
                                تاريخ البداية
                            </div>
                            <div class="info-value">
                                <?= date('Y-m-d', strtotime($customer['start_date'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-building"></i>
                                الفروع
                            </div>
                            <div class="info-value">
                                <?php if($customer['has_branches']): ?>
                                    <a href="branches.php?customer_id=<?= $customer_id ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> عرض الفروع
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">لا يوجد فروع</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الملاحظات والتواريخ -->
            <div class="row">
                <?php if($customer['notes']): ?>
                <div class="col-md-6">
                    <div class="info-card">
                        <h5 class="mb-4">
                            <i class="fas fa-sticky-note text-primary"></i>
                            الملاحظات
                        </h5>
                        <div class="alert alert-info mb-0">
                            <?= nl2br(htmlspecialchars($customer['notes'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-md-6">
                    <div class="info-card">
                        <h5 class="mb-4">
                            <i class="fas fa-clock text-primary"></i>
                            معلومات النظام
                        </h5>

                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-calendar-plus"></i>
                                تاريخ الإضافة
                            </div>
                            <div class="info-value">
                                <?= date('Y-m-d H:i', strtotime($customer['created_at'])) ?>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-calendar-check"></i>
                                آخر تحديث
                            </div>
                            <div class="info-value">
                                <?= date('Y-m-d H:i', strtotime($customer['updated_at'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="info-card">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="customer_details.php?id=<?= $customer_id ?>" class="btn btn-primary w-100">
                            <i class="fas fa-list"></i> التفاصيل الإضافية
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="customer_documents.php?id=<?= $customer_id ?>" class="btn btn-success w-100">
                            <i class="fas fa-file-contract"></i> السندات الرسمية
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="customer_sizes.php?id=<?= $customer_id ?>" class="btn btn-warning w-100">
                            <i class="fas fa-ruler"></i> المقاسات
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="customer_visits.php?id=<?= $customer_id ?>" class="btn btn-info w-100">
                            <i class="fas fa-calendar-alt"></i> الزيارات
                        </a>
                    </div>
                </div>
            </div>

            <!-- زر العودة -->
            <div class="text-center mb-4">
                <a href="customers.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-right"></i> العودة لقائمة العملاء
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>