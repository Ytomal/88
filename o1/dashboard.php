<?php
require_once 'config.php';
checkLogin();

// إحصائيات العملاء
$stats = [
    'total_customers' => $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
    'active_customers' => $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn(),
    'total_branches' => $pdo->query("SELECT COUNT(*) FROM customer_branches WHERE status = 'active'")->fetchColumn(),
    'total_regions' => $pdo->query("SELECT COUNT(*) FROM regions WHERE status = 'active'")->fetchColumn(),
];

// إحصائيات السندات
$docs_stats = [
    'total_docs' => $pdo->query("SELECT COUNT(*) FROM official_documents")->fetchColumn(),
    'active_docs' => $pdo->query("SELECT COUNT(*) FROM official_documents WHERE document_status = 'active'")->fetchColumn(),
    'expired_docs' => $pdo->query("SELECT COUNT(*) FROM official_documents WHERE document_status = 'expired'")->fetchColumn(),
    'expiring_soon' => $pdo->query("SELECT COUNT(*) FROM official_documents WHERE document_status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn(),
];

// إحصائيات الفواتير
$invoice_stats = [
    'total_invoices' => $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn(),
    'total_amount' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices")->fetchColumn(),
    'paid_amount' => $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices")->fetchColumn(),
    'remaining_amount' => $pdo->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM invoices")->fetchColumn(),
];

// أحدث العملاء
$recent_customers = $pdo->query("SELECT c.*, r.region_name 
                                 FROM customers c 
                                 LEFT JOIN regions r ON c.region_id = r.id 
                                 ORDER BY c.created_at DESC 
                                 LIMIT 5")->fetchAll();

// السندات المنتهية قريباً
$expiring_docs = $pdo->query("SELECT od.*, c.company_name 
                               FROM official_documents od
                               JOIN customers c ON od.customer_id = c.id
                               WHERE od.document_status = 'active' 
                               AND od.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                               ORDER BY od.expiry_date ASC
                               LIMIT 5")->fetchAll();

// توزيع العملاء حسب المناطق
$customers_by_region = $pdo->query("SELECT r.region_name, COUNT(c.id) as count
                                    FROM regions r
                                    LEFT JOIN customers c ON r.id = c.region_id
                                    WHERE r.status = 'active'
                                    GROUP BY r.id, r.region_name
                                    ORDER BY count DESC
                                    LIMIT 10")->fetchAll();

// توزيع العملاء حسب النوع
$customers_by_type = $pdo->query("SELECT 
                                  CASE company_type
                                      WHEN 'company' THEN 'شركة'
                                      WHEN 'large_institution' THEN 'مؤسسة كبيرة'
                                      WHEN 'individual_institution' THEN 'مؤسسة فردية'
                                      ELSE 'غير محدد'
                                  END as type_name,
                                  COUNT(*) as count
                                  FROM customers
                                  GROUP BY company_type")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - نظام إدارة العملاء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .stat-card.blue { border-left-color: #667eea; }
        .stat-card.green { border-left-color: #28a745; }
        .stat-card.orange { border-left-color: #fd7e14; }
        .stat-card.red { border-left-color: #dc3545; }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-icon.green { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
        .stat-icon.orange { background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%); color: white; }
        .stat-icon.red { background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); color: white; }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .recent-items {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .recent-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }
        .recent-item:last-child { border-bottom: none; }
        .recent-item:hover { background: #f8f9fa; border-radius: 8px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content" style="margin-right: 260px; padding: 25px;">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-chart-line text-primary"></i> لوحة التحكم</h2>
                    <p class="text-muted">مرحباً <?= htmlspecialchars($_SESSION['full_name']) ?></p>
                </div>
                <div>
                    <span class="badge bg-primary"><?= date('Y-m-d') ?></span>
                    <span class="badge bg-success"><?= date('h:i A') ?></span>
                </div>
            </div>

            <!-- إحصائيات العملاء -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card blue">
                        <div class="stat-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="mb-0"><?= number_format($stats['total_customers']) ?></h3>
                        <p class="text-muted mb-0">إجمالي العملاء</p>
                        <small class="text-success">
                            <i class="fas fa-check-circle"></i> <?= $stats['active_customers'] ?> نشط
                        </small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card green">
                        <div class="stat-icon green">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="mb-0"><?= number_format($stats['total_branches']) ?></h3>
                        <p class="text-muted mb-0">إجمالي الفروع</p>
                        <small class="text-muted">موزعة على <?= $stats['total_regions'] ?> منطقة</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card orange">
                        <div class="stat-icon orange">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <h3 class="mb-0"><?= number_format($docs_stats['total_docs']) ?></h3>
                        <p class="text-muted mb-0">السندات الرسمية</p>
                        <small class="text-success">
                            <i class="fas fa-check"></i> <?= $docs_stats['active_docs'] ?> نشط
                        </small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card red">
                        <div class="stat-icon red">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="mb-0"><?= number_format($docs_stats['expiring_soon']) ?></h3>
                        <p class="text-muted mb-0">سندات تنتهي قريباً</p>
                        <small class="text-danger">خلال 30 يوم</small>
                    </div>
                </div>
            </div>

            <!-- إحصائيات الفواتير -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card blue">
                        <div class="stat-icon blue">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <h3 class="mb-0"><?= number_format($invoice_stats['total_invoices']) ?></h3>
                        <p class="text-muted mb-0">إجمالي الفواتير</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card green">
                        <div class="stat-icon green">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3 class="mb-0"><?= number_format($invoice_stats['total_amount'], 0) ?></h3>
                        <p class="text-muted mb-0">إجمالي المبالغ (ريال)</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card green">
                        <div class="stat-icon green">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <h3 class="mb-0"><?= number_format($invoice_stats['paid_amount'], 0) ?></h3>
                        <p class="text-muted mb-0">المدفوع (ريال)</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card orange">
                        <div class="stat-icon orange">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <h3 class="mb-0"><?= number_format($invoice_stats['remaining_amount'], 0) ?></h3>
                        <p class="text-muted mb-0">المتبقي (ريال)</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- أحدث العملاء -->
                <div class="col-md-6 mb-4">
                    <div class="recent-items">
                        <h5 class="mb-4">
                            <i class="fas fa-user-plus text-primary"></i> أحدث العملاء
                        </h5>
                        <?php if(empty($recent_customers)): ?>
                            <p class="text-muted text-center">لا توجد عملاء بعد</p>
                        <?php else: ?>
                            <?php foreach($recent_customers as $customer): ?>
                                <div class="recent-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-building text-primary"></i>
                                                <?= htmlspecialchars($customer['company_name']) ?>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> <?= htmlspecialchars($customer['owner_name']) ?>
                                            </small><br>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i> <?= $customer['region_name'] ?? 'غير محدد' ?>
                                            </small>
                                        </div>
                                        <small class="text-muted"><?= date('Y-m-d', strtotime($customer['created_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="customers.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i> عرض جميع العملاء
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- السندات المنتهية قريباً -->
                <div class="col-md-6 mb-4">
                    <div class="recent-items">
                        <h5 class="mb-4">
                            <i class="fas fa-exclamation-triangle text-warning"></i> سندات تنتهي قريباً
                        </h5>
                        <?php if(empty($expiring_docs)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="text-muted">لا توجد سندات تنتهي قريباً</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($expiring_docs as $doc): ?>
                                <?php
                                $expiry = new DateTime($doc['expiry_date']);
                                $now = new DateTime();
                                $days = $now->diff($expiry)->days;
                                $badge_class = $days <= 7 ? 'danger' : ($days <= 15 ? 'warning' : 'info');
                                ?>
                                <div class="recent-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-file-alt text-primary"></i>
                                                <?= htmlspecialchars($doc['document_type']) ?>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-building"></i> <?= htmlspecialchars($doc['company_name']) ?>
                                            </small><br>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> ينتهي: <?= date('Y-m-d', strtotime($doc['expiry_date'])) ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?= $badge_class ?>">
                                            <i class="fas fa-clock"></i> <?= $days ?> يوم
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- الرسوم البيانية -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="chart-container">
                        <h5 class="mb-4">
                            <i class="fas fa-map-marked-alt text-primary"></i> توزيع العملاء حسب المناطق
                        </h5>
                        <canvas id="regionsChart" height="250"></canvas>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="chart-container">
                        <h5 class="mb-4">
                            <i class="fas fa-chart-pie text-primary"></i> توزيع العملاء حسب النوع
                        </h5>
                        <canvas id="typesChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        // رسم توزيع العملاء حسب المناطق
        const regionsData = <?= json_encode($customers_by_region) ?>;
        const regionsCtx = document.getElementById('regionsChart').getContext('2d');
        new Chart(regionsCtx, {
            type: 'bar',
            data: {
                labels: regionsData.map(r => r.region_name),
                datasets: [{
                    label: 'عدد العملاء',
                    data: regionsData.map(r => r.count),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // رسم توزيع العملاء حسب النوع
        const typesData = <?= json_encode($customers_by_type) ?>;
        const typesCtx = document.getElementById('typesChart').getContext('2d');
        new Chart(typesCtx, {
            type: 'doughnut',
            data: {
                labels: typesData.map(t => t.type_name),
                datasets: [{
                    data: typesData.map(t => t.count),
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(23, 162, 184, 0.8)'
                    ],
                    borderColor: [
                        'rgba(102, 126, 234, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(23, 162, 184, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>