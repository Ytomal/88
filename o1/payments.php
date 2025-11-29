<?php
require_once 'config.php';
checkLogin();

// معالجة إضافة دفعة
if(isset($_POST['add_payment'])) {
    try {
        $pdo->beginTransaction();
        
        // إدراج الدفعة
        $stmt = $pdo->prepare("INSERT INTO payments 
            (customer_id, payment_date, amount, payment_method, my_branch_id, received_by, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['payment_date'],
            $_POST['amount'],
            $_POST['payment_method'],
            $_POST['my_branch_id'] ?: null,
            $_SESSION['user_id'],
            $_POST['notes']
        ]);
        
        $payment_id = $pdo->lastInsertId();
        $remaining_amount = floatval($_POST['amount']);
        
        // ربط الدفعة بالفواتير
        if(isset($_POST['invoices']) && is_array($_POST['invoices'])) {
            foreach($_POST['invoices'] as $invoice_id => $allocated) {
                $allocated = floatval($allocated);
                if($allocated > 0 && $remaining_amount > 0) {
                    $allocated = min($allocated, $remaining_amount);
                    
                    // إدراج الربط
                    $stmt = $pdo->prepare("INSERT INTO payment_invoice_link 
                        (payment_id, invoice_id, allocated_amount) VALUES (?, ?, ?)");
                    $stmt->execute([$payment_id, $invoice_id, $allocated]);
                    
                    // تحديث الفاتورة
                    $stmt = $pdo->prepare("SELECT paid_amount, total_amount FROM invoices WHERE id = ?");
                    $stmt->execute([$invoice_id]);
                    $invoice = $stmt->fetch();
                    
                    $new_paid = $invoice['paid_amount'] + $allocated;
                    $new_remaining = $invoice['total_amount'] - $new_paid;
                    
                    $status = 'unpaid';
                    if($new_remaining <= 0.01) {
                        $status = 'paid';
                        $new_remaining = 0;
                    } elseif($new_paid > 0) {
                        $status = 'partial';
                    }
                    
                    $stmt = $pdo->prepare("UPDATE invoices SET 
                        paid_amount = ?, remaining_amount = ?, status = ? 
                        WHERE id = ?");
                    $stmt->execute([$new_paid, $new_remaining, $status, $invoice_id]);
                    
                    $remaining_amount -= $allocated;
                }
            }
        }
        
        $pdo->commit();
        logActivity('إضافة دفعة', "مبلغ: {$_POST['amount']} ريال");
        header("Location: payments.php?success=added");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// جلب الدفعات
$payments = $pdo->query("SELECT p.*, c.company_name, c.owner_name,
                         mb.branch_name as my_branch_name,
                         u.full_name as received_by_name
                         FROM payments p
                         JOIN customers c ON p.customer_id = c.id
                         LEFT JOIN my_branches mb ON p.my_branch_id = mb.id
                         LEFT JOIN users u ON p.received_by = u.id
                         ORDER BY p.payment_date DESC, p.id DESC")->fetchAll();

// جلب البيانات للقوائم
$customers = $pdo->query("SELECT id, company_name, owner_name FROM customers WHERE status='active' ORDER BY company_name")->fetchAll();
$my_branches = $pdo->query("SELECT id, branch_name FROM my_branches WHERE status='active' ORDER BY branch_name")->fetchAll();

// إحصائيات
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
    'total_amount' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn(),
    'today' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = CURDATE()")->fetchColumn(),
    'month' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetchColumn()
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الدفعات</title>
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
        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-right: 4px solid #28a745;
            transition: all 0.3s;
        }
        .payment-card:hover {
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
                    <h2><i class="fas fa-money-bill-wave text-success"></i> إدارة الدفعات</h2>
                    <p class="text-muted">دفعات العملاء والمقبوضات</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        <i class="fas fa-plus"></i> إضافة دفعة
                    </button>
                    <a href="invoices.php" class="btn btn-primary">
                        <i class="fas fa-file-invoice"></i> الفواتير
                    </a>
                </div>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    تمت إضافة الدفعة بنجاح!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-money-check-alt fa-2x text-success mb-2"></i>
                            <h3><?= $stats['total'] ?></h3>
                            <p class="text-muted mb-0">إجمالي الدفعات</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-coins fa-2x text-primary mb-2"></i>
                            <h3><?= number_format($stats['total_amount'], 0) ?></h3>
                            <p class="text-muted mb-0">إجمالي المبالغ (ريال)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-day fa-2x text-warning mb-2"></i>
                            <h3><?= number_format($stats['today'], 0) ?></h3>
                            <p class="text-muted mb-0">دفعات اليوم (ريال)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                            <h3><?= number_format($stats['month'], 0) ?></h3>
                            <p class="text-muted mb-0">دفعات الشهر (ريال)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- قائمة الدفعات -->
            <?php if(empty($payments)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-money-bill-wave fa-4x text-muted mb-3"></i>
                    <h4>لا توجد دفعات</h4>
                    <button class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        إضافة أول دفعة
                    </button>
                </div>
            <?php else: ?>
                <?php foreach($payments as $payment): ?>
                    <div class="payment-card">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <h5 class="text-success mb-0">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <?= number_format($payment['amount'], 2) ?> ريال
                                </h5>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> <?= date('Y-m-d', strtotime($payment['payment_date'])) ?>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <strong><?= htmlspecialchars($payment['company_name']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($payment['owner_name']) ?>
                                </small>
                            </div>
                            <div class="col-md-3">
                                <?php
                                $methods = [
                                    'cash' => 'نقداً',
                                    'bank_transfer' => 'حوالة بنكية',
                                    'check' => 'شيك',
                                    'other' => 'أخرى'
                                ];
                                ?>
                                <span class="badge bg-info mb-2">
                                    <?= $methods[$payment['payment_method']] ?? $payment['payment_method'] ?>
                                </span>
                                <br>
                                <?php if($payment['my_branch_name']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-store"></i> <?= htmlspecialchars($payment['my_branch_name']) ?>
                                    </small>
                                <?php endif; ?>
                                <br>
                                <?php if($payment['received_by_name']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($payment['received_by_name']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2 text-end">
                                <?php
                                // جلب الفواتير المرتبطة
                                $stmt = $pdo->prepare("SELECT i.invoice_number, l.allocated_amount 
                                                       FROM payment_invoice_link l 
                                                       JOIN invoices i ON l.invoice_id = i.id 
                                                       WHERE l.payment_id = ?");
                                $stmt->execute([$payment['id']]);
                                $linked = $stmt->fetchAll();
                                ?>
                                <?php if(!empty($linked)): ?>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#viewLinkedModal<?= $payment['id'] ?>">
                                        <i class="fas fa-link"></i> الفواتير (<?= count($linked) ?>)
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-warning">بدون فواتير</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if($payment['notes']): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($payment['notes']) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Modal للفواتير المرتبطة -->
                    <?php if(!empty($linked)): ?>
                    <div class="modal fade" id="viewLinkedModal<?= $payment['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">الفواتير المرتبطة</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <?php foreach($linked as $link): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                            <span>
                                                <i class="fas fa-file-invoice"></i>
                                                <?= htmlspecialchars($link['invoice_number']) ?>
                                            </span>
                                            <span class="badge bg-success">
                                                <?= number_format($link['allocated_amount'], 2) ?> ريال
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة دفعة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>العميل *</label>
                                <select name="customer_id" id="payment_customer" class="form-control" required 
                                        onchange="loadUnpaidInvoices(this.value)">
                                    <option value="">اختر العميل</option>
                                    <?php foreach($customers as $cust): ?>
                                        <option value="<?= $cust['id'] ?>">
                                            <?= htmlspecialchars($cust['company_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>تاريخ الدفع *</label>
                                <input type="date" name="payment_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>المبلغ *</label>
                                <input type="number" step="0.01" name="amount" id="payment_amount" 
                                       class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>طريقة الدفع *</label>
                                <select name="payment_method" class="form-control" required>
                                    <option value="cash">نقداً</option>
                                    <option value="bank_transfer">حوالة بنكية</option>
                                    <option value="check">شيك</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الفرع المستلم</label>
                                <select name="my_branch_id" class="form-control">
                                    <option value="">اختر الفرع</option>
                                    <?php foreach($my_branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>

                        <h5 class="mt-4 mb-3">توزيع الدفعة على الفواتير</h5>
                        <div id="invoices_container" style="max-height: 300px; overflow-y: auto; border: 2px solid #e9ecef; border-radius: 10px; padding: 15px;">
                            <p class="text-center text-muted">اختر العميل أولاً لعرض الفواتير غير المدفوعة</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_payment" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ الدفعة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadUnpaidInvoices(customerId) {
            const container = document.getElementById('invoices_container');
            
            if(!customerId) {
                container.innerHTML = '<p class="text-center text-muted">اختر العميل أولاً</p>';
                return;
            }
            
            container.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</p>';
            
            fetch('get_unpaid_invoices.php?customer_id=' + customerId)
                .then(r => r.json())
                .then(data => {
                    if(data.length === 0) {
                        container.innerHTML = '<p class="text-center text-success">لا توجد فواتير غير مدفوعة</p>';
                        return;
                    }
                    
                    let html = '';
                    data.forEach(inv => {
                        html += `
                            <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                                <div class="flex-grow-1">
                                    <strong>${inv.invoice_number}</strong>
                                    <span class="badge bg-info">${inv.invoice_date}</span>
                                    <br>
                                    <small class="text-muted">
                                        الإجمالي: ${parseFloat(inv.total_amount).toFixed(2)} ريال |
                                        <span class="text-danger">المتبقي: ${parseFloat(inv.remaining_amount).toFixed(2)} ريال</span>
                                    </small>
                                </div>
                                <div style="width: 150px;">
                                    <input type="number" 
                                           name="invoices[${inv.id}]" 
                                           class="form-control" 
                                           placeholder="المبلغ"
                                           step="0.01"
                                           min="0" 
                                           max="${inv.remaining_amount}"
                                           value="0">
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                })
                .catch(err => {
                    container.innerHTML = '<p class="text-center text-danger">حدث خطأ في التحميل</p>';
                    console.error(err);
                });
        }
    </script>
</body>
</html>