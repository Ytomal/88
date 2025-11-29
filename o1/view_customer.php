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

// إحصائيات العميل
$stats = [];

// الفواتير
$stmt = $pdo->prepare("SELECT COUNT(*) as count, 
                       COALESCE(SUM(total_amount), 0) as total,
                       COALESCE(SUM(paid_amount), 0) as paid,
                       COALESCE(SUM(remaining_amount), 0) as remaining
                       FROM invoices WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$stats['invoices'] = $stmt->fetch();

// الدفعات
$stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
                       FROM payments WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$stats['payments'] = $stmt->fetch();

// الزيارات
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$stats['visits'] = $stmt->fetchColumn();

// السندات
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM official_documents WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$stats['documents'] = $stmt->fetchColumn();

// الفروع
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customer_branches WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$stats['branches'] = $stmt->fetchColumn();

// الرصيد الحالي
$current_balance = $customer['opening_balance'] + $stats['invoices']['total'] - $stats['payments']['total'];

// الفروع والمندوبين المرتبطين
$assignments = $pdo->prepare("SELECT a.*, 
                               mb.branch_name, mb.phone as branch_phone,
                               sr.name as rep_name, sr.phone as rep_phone
                               FROM customer_branch_rep_assignments a
                               LEFT JOIN my_branches mb ON a.my_branch_id = mb.id
                               LEFT JOIN sales_representatives sr ON a.sales_rep_id = sr.id
                               WHERE a.customer_id = ? AND a.status = 'active'
                               ORDER BY a.is_primary DESC, a.created_at DESC");
$assignments->execute([$customer_id]);
$assignments = $assignments->fetchAll();

// حساب متوسط مدة السداد
$stmt = $pdo->prepare("SELECT 
    AVG(DATEDIFF(p.payment_date, i.invoice_date)) as avg_days
    FROM payments p
    JOIN payment_invoice_link pil ON p.id = pil.payment_id
    JOIN invoices i ON pil.invoice_id = i.id
    WHERE i.customer_id = ?");
$stmt->execute([$customer_id]);
$avg_payment_days = round($stmt->fetchColumn() ?? 0);

// حساب المبيعات السنوية
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as yearly_sales
                       FROM invoices 
                       WHERE customer_id = ? 
                       AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)");
$stmt->execute([$customer_id]);
$yearly_sales = $stmt->fetchColumn();

$company_type_labels = [
    'company' => 'شركة',
    'large_institution' => 'مؤسسة كبيرة',
    'individual_institution' => 'مؤسسة فردية'
];

$payment_type_labels = [
    'cash' => 'نقداً',
    'credit' => 'آجل',
    'both' => 'نقداً وآجل'
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($customer['company_name']) ?> - تفاصيل العميل</title>
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
        .customer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            position: relative;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            height: 100%;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .assignment-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-right: 4px solid #667eea;
        }
        .assignment-item.primary {
            border-right-color: #28a745;
            background: #d4edda;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- رأس الصفحة -->
            <div class="customer-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h2 class="mb-3">
                            <i class="fas fa-building"></i>
                            <?= htmlspecialchars($customer['company_name']) ?>
                        </h2>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge bg-light text-dark">
                                <?= $company_type_labels[$customer['company_type']] ?? '' ?>
                            </span>
                            <span class="badge bg-<?= $customer['status'] == 'active' ? 'success' : 'secondary' ?>">
                                <?= $customer['status'] == 'active' ? 'نشط' : 'معطل' ?>
                            </span>
                            <span class="badge bg-info">
                                <?= $payment_type_labels[$customer['payment_type']] ?? '' ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <a href="edit_customer.php?id=<?= $customer_id ?>" class="btn btn-light">
                            <i class="fas fa-edit"></i> تعديل
                        </a>
                        <a href="customers.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-right"></i> العودة
                        </a>
                    </div>
                </div>
            </div>

            <!-- الإحصائيات السريعة -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card" onclick="location.href='customer_invoices.php?id=<?= $customer_id ?>'">
                        <div class="text-center">
                            <i class="fas fa-file-invoice fa-3x text-primary mb-3"></i>
                            <h3><?= $stats['invoices']['count'] ?></h3>
                            <p class="text-muted mb-0">إجمالي الفواتير</p>
                            <small class="text-success">
                                <?= number_format($stats['invoices']['total'], 2) ?> ريال
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card" onclick="location.href='customer_payments.php?id=<?= $customer_id ?>'">
                        <div class="text-center">
                            <i class="fas fa-money-bill-wave fa-3x text-success mb-3"></i>
                            <h3><?= $stats['payments']['count'] ?></h3>
                            <p class="text-muted mb-0">إجمالي الدفعات</p>
                            <small class="text-success">
                                <?= number_format($stats['payments']['total'], 2) ?> ريال
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card" onclick="location.href='customer_ledger.php?id=<?= $customer_id ?>'">
                        <div class="text-center">
                            <i class="fas fa-balance-scale fa-3x text-<?= $current_balance >= 0 ? 'warning' : 'danger' ?> mb-3"></i>
                            <h3><?= number_format($current_balance, 2) ?></h3>
                            <p class="text-muted mb-0">الرصيد الحالي</p>
                            <small class="text-muted">
                                الحد المسموح: <?= number_format($customer['credit_limit'], 2) ?>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card" onclick="location.href='customer_visits.php?id=<?= $customer_id ?>'">
                        <div class="text-center">
                            <i class="fas fa-calendar-alt fa-3x text-info mb-3"></i>
                            <h3><?= $stats['visits'] ?></h3>
                            <p class="text-muted mb-0">إجمالي الزيارات</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- معلومات العميل الأساسية -->
                <div class="col-md-6 mb-4">
                    <div class="info-card">
                        <h5 class="mb-4">
                            <i class="fas fa-info-circle text-primary"></i> المعلومات الأساسية
                        </h5>
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>اسم المالك:</strong></td>
                                <td><?= htmlspecialchars($customer['owner_name']) ?></td>
                            </tr>
                            <?php if($customer['responsible_person']): ?>
                            <tr>
                                <td><strong>الشخص المسؤول:</strong></td>
                                <td><?= htmlspecialchars($customer['responsible_person']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if($customer['phone']): ?>
                            <tr>
                                <td><strong>الهاتف:</strong></td>
                                <td><a href="tel:<?= $customer['phone'] ?>"><?= htmlspecialchars($customer['phone']) ?></a></td>
                            </tr>
                            <?php endif; ?>
                            <?php if($customer['email']): ?>
                            <tr>
                                <td><strong>البريد الإلكتروني:</strong></td>
                                <td><a href="mailto:<?= $customer['email'] ?>"><?= htmlspecialchars($customer['email']) ?></a></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>المنطقة:</strong></td>
                                <td><?= htmlspecialchars($customer['region_name'] ?? 'غير محدد') ?></td>
                            </tr>
                            <?php if($customer['start_date']): ?>
                            <tr>
                                <td><strong>تاريخ البداية:</strong></td>
                                <td><?= date('Y-m-d', strtotime($customer['start_date'])) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- المعلومات المالية -->
                <div class="col-md-6 mb-4">
                    <div class="info-card">
                        <h5 class="mb-4">
                            <i class="fas fa-dollar-sign text-success"></i> المعلومات المالية
                        </h5>
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>نوع الدفع:</strong></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= $payment_type_labels[$customer['payment_type']] ?? '' ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>مدة السداد:</strong></td>
                                <td><?= $customer['payment_terms'] ?> يوم</td>
                            </tr>
                            <tr>
                                <td><strong>متوسط السداد الفعلي:</strong></td>
                                <td>
                                    <span class="badge bg-<?= $avg_payment_days <= $customer['payment_terms'] ? 'success' : 'warning' ?>">
                                        <?= $avg_payment_days ?> يوم
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>الرصيد الافتتاحي:</strong></td>
                                <td><?= number_format($customer['opening_balance'], 2) ?> ريال</td>
                            </tr>
                            <tr>
                                <td><strong>الحد الائتماني:</strong></td>
                                <td><?= number_format($customer['credit_limit'], 2) ?> ريال</td>
                            </tr>
                            <tr>
                                <td><strong>الرصيد الحالي:</strong></td>
                                <td>
                                    <strong class="text-<?= $current_balance >= 0 ? 'warning' : 'danger' ?>">
                                        <?= number_format($current_balance, 2) ?> ريال
                                    </strong>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>المبيعات السنوية:</strong></td>
                                <td><?= number_format($yearly_sales, 2) ?> ريال</td>
                            </tr>
                            <tr>
                                <td><strong>المبيعات المقترحة:</strong></td>
                                <td>
                                    <?php 
                                    $suggested = $yearly_sales > 0 ? $yearly_sales * 1.1 : $customer['credit_limit'] * 12;
                                    ?>
                                    <?= number_format($suggested, 2) ?> ريال
                                    <small class="text-muted">(زيادة 10%)</small>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- الفروع والمندوبين -->
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">
                        <i class="fas fa-link text-primary"></i> الفروع والمندوبين المرتبطين
                    </h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                        <i class="fas fa-plus"></i> إضافة ربط
                    </button>
                </div>

                <?php if(empty($assignments)): ?>
                    <p class="text-center text-muted">لا توجد ارتباطات</p>
                <?php else: ?>
                    <?php foreach($assignments as $assign): ?>
                        <div class="assignment-item <?= $assign['is_primary'] ? 'primary' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <?php if($assign['is_primary']): ?>
                                        <span class="badge bg-success mb-2">المسؤول الرئيسي</span>
                                    <?php endif; ?>
                                    
                                    <?php if($assign['branch_name']): ?>
                                        <p class="mb-1">
                                            <i class="fas fa-store text-primary"></i>
                                            <strong>الفرع:</strong> <?= htmlspecialchars($assign['branch_name']) ?>
                                            <?php if($assign['branch_phone']): ?>
                                                <small class="text-muted">- <?= htmlspecialchars($assign['branch_phone']) ?></small>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if($assign['rep_name']): ?>
                                        <p class="mb-1">
                                            <i class="fas fa-user-tie text-success"></i>
                                            <strong>المندوب:</strong> <?= htmlspecialchars($assign['rep_name']) ?>
                                            <?php if($assign['rep_phone']): ?>
                                                <small class="text-muted">- <?= htmlspecialchars($assign['rep_phone']) ?></small>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> منذ: <?= date('Y-m-d', strtotime($assign['assignment_date'])) ?>
                                    </small>
                                </div>
                                <button class="btn btn-sm btn-danger" onclick="deleteAssignment(<?= $assign['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- روابط سريعة -->
            <div class="info-card">
                <h5 class="mb-4"><i class="fas fa-link"></i> روابط سريعة</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="customer_ledger.php?id=<?= $customer_id ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-book"></i> كشف الحساب
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="customer_documents.php?id=<?= $customer_id ?>" class="btn btn-outline-success w-100">
                            <i class="fas fa-file-contract"></i> السندات (<?= $stats['documents'] ?>)
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="branches.php?customer_id=<?= $customer_id ?>" class="btn btn-outline-warning w-100">
                            <i class="fas fa-building"></i> الفروع (<?= $stats['branches'] ?>)
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="customer_sizes.php?id=<?= $customer_id ?>" class="btn btn-outline-info w-100">
                            <i class="fas fa-ruler"></i> المقاسات
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal إضافة ربط -->
    <div class="modal fade" id="addAssignmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة ربط فرع/مندوب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="customer_assignment_action.php" method="POST">
                    <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">الفرع</label>
                            <select name="my_branch_id" class="form-control">
                                <option value="">بدون فرع</option>
                                <?php
                                $branches = $pdo->query("SELECT * FROM my_branches WHERE status='active' ORDER BY branch_name")->fetchAll();
                                foreach($branches as $b):
                                ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">المندوب</label>
                            <select name="sales_rep_id" class="form-control">
                                <option value="">بدون مندوب</option>
                                <?php
                                $reps = $pdo->query("SELECT * FROM sales_representatives WHERE status='active' ORDER BY name")->fetchAll();
                                foreach($reps as $r):
                                ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_primary" value="1" class="form-check-input" id="isPrimary">
                                <label class="form-check-label" for="isPrimary">
                                    تعيين كمسؤول رئيسي
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_assignment" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteAssignment(id) {
            if(confirm('هل أنت متأكد من حذف هذا الربط؟')) {
                window.location.href = 'customer_assignment_action.php?delete=' + id + '&customer_id=<?= $customer_id ?>';
            }
        }
    </script>
</body>
</html>