<?php
require_once 'config.php';
checkAdmin(); // يجب أن يكون مدير فقط

// جلب السنوات المالية
$fiscal_years = $pdo->query("SELECT * FROM fiscal_years ORDER BY start_date DESC")->fetchAll();

// جلب السنة المالية الحالية
$current_year = $pdo->query("SELECT * FROM fiscal_years WHERE status = 'open' ORDER BY start_date DESC LIMIT 1")->fetch();

// معالجة قفل السنة
if(isset($_POST['close_year'])) {
    try {
        $year_id = $_POST['year_id'];
        
        $pdo->beginTransaction();
        
        // جلب بيانات السنة
        $stmt = $pdo->prepare("SELECT * FROM fiscal_years WHERE id = ?");
        $stmt->execute([$year_id]);
        $year = $stmt->fetch();
        
        if(!$year) {
            throw new Exception('السنة المالية غير موجودة');
        }
        
        if($year['status'] == 'closed') {
            throw new Exception('هذه السنة مقفلة بالفعل');
        }
        
        // حساب الأرصدة النهائية لكل عميل
        $customers = $pdo->query("SELECT id, company_name, opening_balance FROM customers WHERE status = 'active'")->fetchAll();
        
        foreach($customers as $customer) {
            // حساب إجمالي المبيعات
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_sales
                                   FROM invoices 
                                   WHERE customer_id = ? 
                                   AND invoice_date BETWEEN ? AND ?");
            $stmt->execute([$customer['id'], $year['start_date'], $year['end_date']]);
            $sales = $stmt->fetchColumn();
            
            // حساب إجمالي المدفوعات
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_payments
                                   FROM payments 
                                   WHERE customer_id = ? 
                                   AND payment_date BETWEEN ? AND ?");
            $stmt->execute([$customer['id'], $year['start_date'], $year['end_date']]);
            $payments = $stmt->fetchColumn();
            
            // الرصيد النهائي = الرصيد الافتتاحي + المبيعات - المدفوعات
            $closing_balance = $customer['opening_balance'] + $sales - $payments;
            
            // حفظ الرصيد النهائي
            $stmt = $pdo->prepare("INSERT INTO opening_balances (fiscal_year_id, customer_id, opening_balance, notes)
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $year_id,
                $customer['id'],
                $closing_balance,
                "الرصيد الختامي للسنة المالية {$year['year_name']}"
            ]);
        }
        
        // قفل السنة
        $stmt = $pdo->prepare("UPDATE fiscal_years 
                               SET status = 'closed', 
                                   closed_at = NOW(), 
                                   closed_by = ?
                               WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $year_id]);
        
        logActivity('قفل سنة مالية', "تم قفل السنة المالية: {$year['year_name']}");
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "تم قفل السنة المالية {$year['year_name']} بنجاح";
        header('Location: fiscal_year_closing.php');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// معالجة فتح سنة جديدة
if(isset($_POST['open_new_year'])) {
    try {
        $pdo->beginTransaction();
        
        // التحقق من وجود سنة مفتوحة
        $open_year = $pdo->query("SELECT * FROM fiscal_years WHERE status = 'open'")->fetch();
        if($open_year) {
            throw new Exception('يجب قفل السنة الحالية أولاً');
        }
        
        // إنشاء سنة جديدة
        $stmt = $pdo->prepare("INSERT INTO fiscal_years (year_name, start_date, end_date, status, notes)
                               VALUES (?, ?, ?, 'open', ?)");
        $stmt->execute([
            $_POST['year_name'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['notes'] ?? null
        ]);
        
        $new_year_id = $pdo->lastInsertId();
        
        // نقل الأرصدة الختامية من السنة السابقة كأرصدة افتتاحية
        if($_POST['previous_year_id']) {
            $stmt = $pdo->prepare("SELECT customer_id, opening_balance 
                                   FROM opening_balances 
                                   WHERE fiscal_year_id = ?");
            $stmt->execute([$_POST['previous_year_id']]);
            $balances = $stmt->fetchAll();
            
            foreach($balances as $balance) {
                // تحديث الرصيد الافتتاحي للعميل
                $stmt = $pdo->prepare("UPDATE customers 
                                       SET opening_balance = ?,
                                           current_fiscal_year_id = ?
                                       WHERE id = ?");
                $stmt->execute([
                    $balance['opening_balance'],
                    $new_year_id,
                    $balance['customer_id']
                ]);
            }
        }
        
        logActivity('فتح سنة مالية', "تم فتح سنة مالية جديدة: {$_POST['year_name']}");
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "تم فتح السنة المالية {$_POST['year_name']} بنجاح";
        header('Location: fiscal_year_closing.php');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة السنوات المالية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .main-content {
            margin-right: 260px;
            padding: 25px;
        }
        .year-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .year-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .year-card.open {
            border-right: 5px solid #28a745;
        }
        .year-card.closed {
            border-right: 5px solid #dc3545;
            opacity: 0.8;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-calendar-check text-primary"></i> إدارة السنوات المالية</h2>
                    <p class="text-muted">قفل السنوات وفتح سنوات جديدة</p>
                </div>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#openYearModal">
                        <i class="fas fa-plus"></i> فتح سنة جديدة
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                </div>
            </div>

            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if(isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if($current_year): ?>
                <div class="warning-box">
                    <h5><i class="fas fa-info-circle"></i> السنة المالية الحالية</h5>
                    <p class="mb-0">
                        <strong><?= htmlspecialchars($current_year['year_name']) ?></strong><br>
                        من <?= date('Y-m-d', strtotime($current_year['start_date'])) ?> 
                        إلى <?= date('Y-m-d', strtotime($current_year['end_date'])) ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>تنبيه:</strong> لا توجد سنة مالية مفتوحة حالياً. يجب فتح سنة جديدة لمتابعة العمليات المالية.
                </div>
            <?php endif; ?>

            <!-- قائمة السنوات المالية -->
            <h4 class="mb-3">سجل السنوات المالية</h4>
            
            <?php if(empty($fiscal_years)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h5>لا توجد سنوات مالية</h5>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#openYearModal">
                        إنشاء أول سنة مالية
                    </button>
                </div>
            <?php else: ?>
                <?php foreach($fiscal_years as $year): ?>
                    <div class="year-card <?= $year['status'] ?>">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5>
                                    <?= $year['status'] == 'open' ? '🟢' : '🔴' ?>
                                    <?= htmlspecialchars($year['year_name']) ?>
                                    <?php if($year['status'] == 'open'): ?>
                                        <span class="badge bg-success">مفتوحة</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">مقفلة</span>
                                    <?php endif; ?>
                                </h5>
                                <p class="mb-2">
                                    <i class="fas fa-calendar"></i>
                                    من <?= date('Y-m-d', strtotime($year['start_date'])) ?>
                                    إلى <?= date('Y-m-d', strtotime($year['end_date'])) ?>
                                </p>
                                
                                <?php if($year['status'] == 'closed'): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-lock"></i>
                                        تم القفل في: <?= date('Y-m-d H:i', strtotime($year['closed_at'])) ?>
                                    </small>
                                <?php endif; ?>
                                
                                <?php if($year['notes']): ?>
                                    <p class="text-muted small mb-0">
                                        <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($year['notes']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4 text-end">
                                <?php if($year['status'] == 'open'): ?>
                                    <button class="btn btn-danger" onclick="closeYear(<?= $year['id'] ?>, '<?= htmlspecialchars($year['year_name']) ?>')">
                                        <i class="fas fa-lock"></i> قفل السنة
                                    </button>
                                <?php else: ?>
                                    <a href="fiscal_year_report.php?id=<?= $year['id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-file-alt"></i> تقرير السنة
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal قفل السنة -->
    <div class="modal fade" id="closeYearModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i> تأكيد قفل السنة المالية
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="year_id" id="closeYearId">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <strong>تحذير:</strong> قفل السنة المالية هو إجراء لا يمكن التراجع عنه!
                        </div>
                        
                        <p>سيتم تنفيذ الإجراءات التالية:</p>
                        <ul>
                            <li>حساب الأرصدة النهائية لجميع العملاء</li>
                            <li>حفظ الأرصدة الختامية</li>
                            <li>قفل السنة المالية <strong id="yearNameDisplay"></strong></li>
                            <li>منع إضافة أو تعديل أي عمليات في هذه السنة</li>
                        </ul>
                        
                        <p class="text-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>هل أنت متأكد من رغبتك في قفل هذه السنة؟</strong>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="close_year" class="btn btn-danger">
                            <i class="fas fa-lock"></i> قفل السنة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal فتح سنة جديدة -->
    <div class="modal fade" id="openYearModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">فتح سنة مالية جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم السنة المالية *</label>
                            <input type="text" name="year_name" class="form-control" 
                                   value="السنة المالية <?= date('Y') + 1 ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ البداية *</label>
                                <input type="date" name="start_date" class="form-control" 
                                       value="<?= date('Y') + 1 ?>-01-01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ النهاية *</label>
                                <input type="date" name="end_date" class="form-control" 
                                       value="<?= date('Y') + 1 ?>-12-31" required>
                            </div>
                        </div>
                        
                        <?php
                        $last_closed = $pdo->query("SELECT * FROM fiscal_years WHERE status = 'closed' ORDER BY end_date DESC LIMIT 1")->fetch();
                        ?>
                        
                        <?php if($last_closed): ?>
                            <div class="mb-3">
                                <label class="form-label">نقل الأرصدة من السنة السابقة</label>
                                <select name="previous_year_id" class="form-control">
                                    <option value="">عدم نقل الأرصدة</option>
                                    <option value="<?= $last_closed['id'] ?>" selected>
                                        <?= htmlspecialchars($last_closed['year_name']) ?>
                                    </option>
                                </select>
                                <small class="text-muted">
                                    سيتم نقل الأرصدة الختامية من السنة المختارة كأرصدة افتتاحية للسنة الجديدة
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>ملاحظة:</strong> يجب قفل السنة الحالية قبل فتح سنة جديدة.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="open_new_year" class="btn btn-success">
                            <i class="fas fa-folder-open"></i> فتح السنة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function closeYear(id, name) {
            document.getElementById('closeYearId').value = id;
            document.getElementById('yearNameDisplay').textContent = name;
            new bootstrap.Modal(document.getElementById('closeYearModal')).show();
        }
    </script>
</body>
</html>