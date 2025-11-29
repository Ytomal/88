<?php
require_once 'config.php';
checkLogin();

// التحقق من الصلاحيات
if($_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit;
}

// جلب السنوات المالية
$fiscal_years = $pdo->query("SELECT * FROM fiscal_years ORDER BY start_date DESC")->fetchAll();

// إضافة سنة مالية
if(isset($_POST['add_year'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO fiscal_years (year_name, start_date, end_date, notes) 
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['year_name'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['notes']
        ]);
        
        logActivity('إضافة سنة مالية', "تم إضافة السنة المالية: {$_POST['year_name']}");
        
        header('Location: fiscal_years.php?success=added');
        exit;
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// إغلاق سنة مالية ونقل الأرصدة
if(isset($_POST['close_year'])) {
    try {
        $pdo->beginTransaction();
        
        $year_id = $_POST['year_id'];
        $new_year_id = $_POST['new_year_id'];
        
        // التحقق من السنة الجديدة
        $stmt = $pdo->prepare("SELECT * FROM fiscal_years WHERE id = ?");
        $stmt->execute([$new_year_id]);
        $new_year = $stmt->fetch();
        
        if(!$new_year) {
            throw new Exception('السنة المالية الجديدة غير موجودة');
        }
        
        // حساب الأرصدة النهائية لكل عميل
        $sql = "SELECT 
                    c.id as customer_id,
                    c.company_name,
                    COALESCE(c.opening_balance, 0) + 
                    COALESCE(SUM(i.total_amount), 0) - 
                    COALESCE(SUM(p.amount), 0) as final_balance
                FROM customers c
                LEFT JOIN invoices i ON c.id = i.customer_id
                LEFT JOIN payments p ON c.id = p.customer_id
                WHERE c.status = 'active'
                GROUP BY c.id";
        
        $customers_balances = $pdo->query($sql)->fetchAll();
        
        // نقل الأرصدة للسنة الجديدة
        $stmt = $pdo->prepare("INSERT INTO opening_balances 
                               (fiscal_year_id, customer_id, opening_balance, notes) 
                               VALUES (?, ?, ?, ?)");
        
        foreach($customers_balances as $customer) {
            $stmt->execute([
                $new_year_id,
                $customer['customer_id'],
                $customer['final_balance'],
                "رصيد منقول من السنة المالية السابقة"
            ]);
            
            // تحديث الرصيد الافتتاحي في جدول العملاء
            $stmt_update = $pdo->prepare("UPDATE customers 
                                          SET opening_balance = ?, 
                                              current_fiscal_year_id = ?
                                          WHERE id = ?");
            $stmt_update->execute([
                $customer['final_balance'],
                $new_year_id,
                $customer['customer_id']
            ]);
        }
        
        // إغلاق السنة القديمة
        $stmt = $pdo->prepare("UPDATE fiscal_years 
                               SET status = 'closed', 
                                   closed_at = NOW(), 
                                   closed_by = ?
                               WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $year_id]);
        
        logActivity('إغلاق سنة مالية', "تم إغلاق السنة المالية ونقل " . count($customers_balances) . " رصيد");
        
        $pdo->commit();
        header('Location: fiscal_years.php?success=closed');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// فتح سنة مغلقة
if(isset($_GET['reopen'])) {
    try {
        $stmt = $pdo->prepare("UPDATE fiscal_years 
                               SET status = 'open', 
                                   closed_at = NULL, 
                                   closed_by = NULL
                               WHERE id = ?");
        $stmt->execute([$_GET['reopen']]);
        
        logActivity('إعادة فتح سنة مالية', "تم إعادة فتح السنة المالية ID: {$_GET['reopen']}");
        
        header('Location: fiscal_years.php?success=reopened');
        exit;
    } catch(Exception $e) {
        $error = $e->getMessage();
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
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-right: 5px solid #667eea;
        }
        .year-card.closed {
            opacity: 0.7;
            border-right-color: #6c757d;
        }
        .year-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-calendar-alt text-primary"></i> إدارة السنوات المالية</h2>
                    <p class="text-muted">إدارة وإغلاق ونقل الأرصدة بين السنوات</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addYearModal">
                    <i class="fas fa-plus"></i> إضافة سنة مالية
                </button>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php
                    $messages = [
                        'added' => 'تمت إضافة السنة المالية بنجاح',
                        'closed' => 'تم إغلاق السنة المالية ونقل الأرصدة بنجاح',
                        'reopened' => 'تم إعادة فتح السنة المالية'
                    ];
                    echo $messages[$_GET['success']] ?? 'تمت العملية بنجاح';
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <strong>خطأ:</strong> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- قائمة السنوات المالية -->
            <?php if(empty($fiscal_years)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h4>لا توجد سنوات مالية</h4>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addYearModal">
                        إضافة أول سنة مالية
                    </button>
                </div>
            <?php else: ?>
                <?php foreach($fiscal_years as $year): ?>
                    <div class="year-card <?= $year['status'] == 'closed' ? 'closed' : '' ?>">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4>
                                    <i class="fas fa-calendar"></i>
                                    <?= htmlspecialchars($year['year_name']) ?>
                                    <?php if($year['status'] == 'open'): ?>
                                        <span class="badge bg-success">مفتوحة</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">مغلقة</span>
                                    <?php endif; ?>
                                </h4>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-calendar-day"></i>
                                    من: <?= date('Y-m-d', strtotime($year['start_date'])) ?> |
                                    إلى: <?= date('Y-m-d', strtotime($year['end_date'])) ?>
                                </p>
                                <?php if($year['notes']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-sticky-note"></i>
                                        <?= htmlspecialchars($year['notes']) ?>
                                    </small>
                                <?php endif; ?>
                                <?php if($year['status'] == 'closed' && $year['closed_at']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-lock"></i>
                                            تم الإغلاق في: <?= date('Y-m-d H:i', strtotime($year['closed_at'])) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 text-end">
                                <?php if($year['status'] == 'open'): ?>
                                    <?php
                                    // جلب عدد العملاء
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE current_fiscal_year_id = ? OR current_fiscal_year_id IS NULL");
                                    $stmt->execute([$year['id']]);
                                    $customer_count = $stmt->fetchColumn();
                                    ?>
                                    <div class="mb-2">
                                        <span class="badge bg-info">
                                            <i class="fas fa-users"></i> <?= $customer_count ?> عميل
                                        </span>
                                    </div>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#closeYearModal<?= $year['id'] ?>">
                                        <i class="fas fa-lock"></i> إغلاق السنة
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-success btn-sm" onclick="if(confirm('هل تريد إعادة فتح هذه السنة؟')) location.href='fiscal_years.php?reopen=<?= $year['id'] ?>'">
                                        <i class="fas fa-lock-open"></i> إعادة الفتح
                                    </button>
                                <?php endif; ?>
                                <a href="year_report.php?id=<?= $year['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-file-pdf"></i> التقرير
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Modal إغلاق السنة -->
                    <?php if($year['status'] == 'open'): ?>
                    <div class="modal fade" id="closeYearModal<?= $year['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">إغلاق السنة المالية</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="year_id" value="<?= $year['id'] ?>">
                                    <div class="modal-body">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <strong>تنبيه:</strong> عند إغلاق السنة المالية، سيتم:
                                            <ul class="mt-2">
                                                <li>حساب الأرصدة النهائية لجميع العملاء</li>
                                                <li>نقل الأرصدة كأرصدة افتتاحية للسنة الجديدة</li>
                                                <li>لن تتمكن من التعديل على هذه السنة بعد الإغلاق</li>
                                            </ul>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">نقل الأرصدة إلى السنة المالية *</label>
                                            <select name="new_year_id" class="form-control" required>
                                                <option value="">اختر السنة الجديدة</option>
                                                <?php foreach($fiscal_years as $fy): ?>
                                                    <?php if($fy['id'] != $year['id'] && $fy['status'] == 'open'): ?>
                                                        <option value="<?= $fy['id'] ?>">
                                                            <?= htmlspecialchars($fy['year_name']) ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <p class="text-muted small">
                                            <i class="fas fa-info-circle"></i>
                                            سيتم نقل أرصدة <?= $customer_count ?> عميل نشط
                                        </p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                        <button type="submit" name="close_year" class="btn btn-warning">
                                            <i class="fas fa-lock"></i> إغلاق ونقل الأرصدة
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal إضافة سنة -->
    <div class="modal fade" id="addYearModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة سنة مالية جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم السنة المالية *</label>
                            <input type="text" name="year_name" class="form-control" 
                                   placeholder="مثال: السنة المالية 2026" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ البداية *</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ النهاية *</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_year" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>