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

// جلب الفروع
$branches = $pdo->prepare("SELECT cb.*, r.region_name 
                          FROM customer_branches cb
                          LEFT JOIN regions r ON cb.region_id = r.id
                          WHERE cb.customer_id = ?
                          ORDER BY cb.created_at DESC");
$branches->execute([$customer_id]);
$branches = $branches->fetchAll();

// جلب السندات
$documents = $pdo->prepare("SELECT * FROM official_documents 
                           WHERE customer_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT 5");
$documents->execute([$customer_id]);
$documents = $documents->fetchAll();

// جلب الفواتير
$invoices = $pdo->prepare("SELECT * FROM invoices 
                          WHERE customer_id = ? 
                          ORDER BY invoice_date DESC 
                          LIMIT 5");
$invoices->execute([$customer_id]);
$invoices = $invoices->fetchAll();

// إحصائيات العميل
$stats = [
    'branches_count' => $pdo->prepare("SELECT COUNT(*) FROM customer_branches WHERE customer_id = ?")->execute([$customer_id]) ? $pdo->query("SELECT COUNT(*) FROM customer_branches WHERE customer_id = $customer_id")->fetchColumn() : 0,
    'documents_count' => $pdo->query("SELECT COUNT(*) FROM official_documents WHERE customer_id = $customer_id")->fetchColumn(),
    'invoices_count' => $pdo->query("SELECT COUNT(*) FROM invoices WHERE customer_id = $customer_id")->fetchColumn(),
    'total_amount' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE customer_id = $customer_id")->fetchColumn(),
    'paid_amount' => $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices WHERE customer_id = $customer_id")->fetchColumn(),
    'remaining_amount' => $pdo->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM invoices WHERE customer_id = $customer_id")->fetchColumn(),
    'visits_count' => $pdo->query("SELECT COUNT(*) FROM visits WHERE customer_id = $customer_id")->fetchColumn(),
];

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
    <title>عرض العميل - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="professional_style.css">
    <style>
        .customer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            height: 100%;
        }
        .stat-box {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .stat-box:hover {
            transform: translateY(-5px);
        }
        .stat-box h3 {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-weight: 600;
            color: #667eea;
            min-width: 150px;
        }
        .info-value {
            color: #333;
            flex: 1;
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i {
            color: #667eea;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .branch-item, .document-item, .invoice-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    تم تحديث بيانات العميل بنجاح
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- رأس الصفحة -->
            <div class="customer-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h2 class="mb-2">
                            <i class="fas fa-building"></i> <?= htmlspecialchars($customer['company_name']) ?>
                        </h2>
                        <p class="mb-2">
                            <i class="fas fa-user"></i> المالك: <?= htmlspecialchars($customer['owner_name']) ?>
                        </p>
                        <div class="d-flex gap-2 mt-3">
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
                    <div class="action-buttons">
                        <a href="edit_customer.php?id=<?= $customer_id ?>" class="btn btn-light">
                            <i class="fas fa-edit"></i> تعديل
                        </a>
                        <a href="customers.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-right"></i> العودة
                        </a>
                    </div>
                </div>
            </div>

            <!-- الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-box">
                        <i class="fas fa-building fa-2x text-primary mb-2"></i>
                        <h3><?= number_format($stats['branches_count']) ?></h3>
                        <p class="text-muted mb-0">الفروع</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <i class="fas fa-file-contract fa-2x text-success mb-2"></i>
                        <h3><?= number_format($stats['documents_count']) ?></h3>
                        <p class="text-muted mb-0">السندات</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <i class="fas fa-file-invoice fa-2x text-warning mb-2"></i>
                        <h3><?= number_format($stats['invoices_count']) ?></h3>
                        <p class="text-muted mb-0">الفواتير</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                        <h3><?= number_format($stats['visits_count']) ?></h3>
                        <p class="text-muted mb-0">الزيارات</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- المعلومات الأساسية -->
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="section-title">
                            <i class="fas fa-info-circle"></i>
                            <span>المعلومات الأساسية</span>
                        </div>

                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-building"></i> اسم الشركة:</div>
                            <div class="info-value"><?= htmlspecialchars($customer['company_name']) ?></div>
                        </div>

                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-briefcase"></i> نوع الشركة:</div>
                            <div class="info-value"><?= $company_type_labels[$customer['company_type']] ?? 'غير محدد' ?></div>
                        </div>

                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-user"></i> اسم المالك:</div>
                            <div class="info-value"><?= htmlspecialchars($customer['owner_name']) ?></div>
                        </div>

                        <?php if($customer['responsible_person']): ?>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-user-tie"></i> الشخص المسؤول:</div>
                            <div class="info-value"><?= htmlspecialchars($customer['responsible_person']) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if($customer['phone']): ?>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-phone"></i> الهاتف:</div>
                            <div class="info-value">
                                <a href="tel:<?= $customer['phone'] ?>"><?= $customer['phone'] ?></a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($customer['email']): ?>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-envelope"></i> البريد:</div>
                            <div class="info-value">
                                <a href="mailto:<?= $customer['email'] ?>"><?= $customer['email'] ?></a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($customer['region_name']): ?>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-map-marker-alt"></i> المنطقة:</div>
                            <div class="info-value"><?= $customer['region_name'] ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if($customer['address']): ?>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-location-dot"></i> العنوان:</div>
                            <div class="info-value"><?= htmlspecialchars($customer['address']) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if($customer['start_date']): ?>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-calendar"></i> تاريخ البداية:</div>
                            <div class="info-value"><?= date('Y-m-d', strtotime($customer['start_date'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-clock"></i> تاريخ الإضافة:</div>
                            <div class="info-value"><?= date('Y-m-d H:i', strtotime($customer['created_at'])) ?></div>
                        </div>

                        <?php if($customer['notes']): ?>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-sticky-note"></i> ملاحظات:</div>
                            <div class="info-value"><?= nl2br(htmlspecialchars($customer['notes'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- الإحصائيات المالية -->
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="section-title">
                            <i class="fas fa-chart-line"></i>
                            <span>الإحصائيات المالية</span>
                        </div>

                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-money-bill-wave"></i> إجمالي الفواتير:</div>
                            <div class="info-value">
                                <strong class="text-primary"><?= number_format($stats['total_amount'], 2) ?> ريال</strong>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-check-circle"></i> المدفوع:</div>
                            <div class="info-value">
                                <strong class="text-success"><?= number_format($stats['paid_amount'], 2) ?> ريال</strong>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-hourglass-half"></i> المتبقي:</div>
                            <div class="info-value">
                                <strong class="text-danger"><?= number_format($stats['remaining_amount'], 2) ?> ريال</strong>
                            </div>
                        </div>

                        <?php if($stats['total_amount'] > 0): ?>
                            <div class="progress mt-3" style="height: 30px;">
                                <?php 
                                $paid_percentage = ($stats['paid_amount'] / $stats['total_amount']) * 100;
                                ?>
                                <div class="progress-bar bg-success" style="width: <?= $paid_percentage ?>%">
                                    <?= number_format($paid_percentage, 1) ?>% مدفوع
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4">
                            <h6 class="mb-3">روابط سريعة:</h6>
                            <div class="d-grid gap-2">
                                <a href="branches.php?customer_id=<?= $customer_id ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-building"></i> إدارة الفروع (<?= $stats['branches_count'] ?>)
                                </a>
                                <a href="documents.php?customer_id=<?= $customer_id ?>" class="btn btn-outline-success">
                                    <i class="fas fa-file-contract"></i> السندات الرسمية (<?= $stats['documents_count'] ?>)
                                </a>
                                <a href="invoices.php?customer_id=<?= $customer_id ?>" class="btn btn-outline-warning">
                                    <i class="fas fa-file-invoice"></i> الفواتير (<?= $stats['invoices_count'] ?>)
                                </a>
                                <a href="customer_visits.php?id=<?= $customer_id ?>" class="btn btn-outline-info">
                                    <i class="fas fa-calendar-check"></i> الزيارات (<?= $stats['visits_count'] ?>)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الفروع -->
            <?php if(!empty($branches)): ?>
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="section-title mb-0">
                        <i class="fas fa-building"></i>
                        <span>الفروع (<?= count($branches) ?>)</span>
                    </div>
                    <a href="branches.php?customer_id=<?= $customer_id ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> عرض الكل
                    </a>
                </div>
                <div class="row">
                    <?php foreach(array_slice($branches, 0, 3) as $branch): ?>
                        <div class="col-md-4">
                            <div class="branch-item">
                                <h6><i class="fas fa-building text-primary"></i> <?= htmlspecialchars($branch['branch_name']) ?></h6>
                                <?php if($branch['region_name']): ?>
                                    <small><i class="fas fa-map-marker-alt"></i> <?= $branch['region_name'] ?></small><br>
                                <?php endif; ?>
                                <?php if($branch['phone']): ?>
                                    <small><i class="fas fa-phone"></i> <?= $branch['phone'] ?></small><br>
                                <?php endif; ?>
                                <small><i class="fas fa-door-open"></i> <?= $branch['shop_fronts_count'] ?> فتحة</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- أحدث الفواتير -->
            <?php if(!empty($invoices)): ?>
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="section-title mb-0">
                        <i class="fas fa-file-invoice"></i>
                        <span>أحدث الفواتير</span>
                    </div>
                    <a href="invoices.php?customer_id=<?= $customer_id ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-eye"></i> عرض الكل
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>المبلغ الإجمالي</th>
                                <th>المدفوع</th>
                                <th>المتبقي</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($invoices as $invoice): ?>
                                <tr>
                                    <td><strong><?= $invoice['invoice_number'] ?></strong></td>
                                    <td><?= date('Y-m-d', strtotime($invoice['invoice_date'])) ?></td>
                                    <td><?= number_format($invoice['total_amount'], 2) ?></td>
                                    <td class="text-success"><?= number_format($invoice['paid_amount'], 2) ?></td>
                                    <td class="text-danger"><?= number_format($invoice['remaining_amount'], 2) ?></td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'unpaid' => '<span class="badge bg-danger">غير مدفوعة</span>',
                                            'partial' => '<span class="badge bg-warning">مدفوعة جزئياً</span>',
                                            'paid' => '<span class="badge bg-success">مدفوعة</span>'
                                        ];
                                        echo $status_badges[$invoice['status']] ?? '';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>