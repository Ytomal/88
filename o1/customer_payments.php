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

// جلب الدفعات
$stmt = $pdo->prepare("SELECT p.*, 
                       mb.branch_name as my_branch_name,
                       u.full_name as received_by_name
                       FROM payments p
                       LEFT JOIN my_branches mb ON p.my_branch_id = mb.id
                       LEFT JOIN users u ON p.received_by = u.id
                       WHERE p.customer_id = ?
                       ORDER BY p.payment_date DESC, p.id DESC");
$stmt->execute([$customer_id]);
$payments = $stmt->fetchAll();

// الإحصائيات
$total_payments = array_sum(array_column($payments, 'amount'));
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دفعات العميل - <?= htmlspecialchars($customer['company_name']) ?></title>
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
                    <h2>
                        <i class="fas fa-money-bill-wave text-success"></i> دفعات العميل
                    </h2>
                    <p class="text-muted">
                        <?= htmlspecialchars($customer['company_name']) ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        <i class="fas fa-plus"></i> إضافة دفعة
                    </button>
                    <a href="view_customer.php?id=<?= $customer_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                </div>
            </div>

            <!-- الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-receipt fa-3x text-success mb-3"></i>
                            <h3><?= count($payments) ?></h3>
                            <p class="text-muted mb-0">إجمالي الدفعات</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-coins fa-3x text-primary mb-3"></i>
                            <h3><?= number_format($total_payments, 2) ?></h3>
                            <p class="text-muted mb-0">إجمالي المبالغ (ريال)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-chart-line fa-3x text-info mb-3"></i>
                            <h3>
                                <?= count($payments) > 0 ? number_format($total_payments / count($payments), 2) : 0 ?>
                            </h3>
                            <p class="text-muted mb-0">متوسط الدفعة (ريال)</p>
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
                                <h4 class="text-success mb-0">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <?= number_format($payment['amount'], 2) ?> ريال
                                </h4>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> 
                                    <?= date('Y-m-d', strtotime($payment['payment_date'])) ?>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <?php
                                $methods = [
                                    'cash' => 'نقداً',
                                    'bank_transfer' => 'حوالة بنكية',
                                    'check' => 'شيك',
                                    'other' => 'أخرى'
                                ];
                                ?>
                                <span class="badge bg-info mb-2">
                                    <i class="fas fa-credit-card"></i>
                                    <?= $methods[$payment['payment_method']] ?? $payment['payment_method'] ?>
                                </span>
                                <br>
                                <?php if($payment['my_branch_name']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-store"></i> <?= htmlspecialchars($payment['my_branch_name']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <?php if($payment['received_by_name']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> استلمها: <?= htmlspecialchars($payment['received_by_name']) ?>
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
                                    <h5 class="modal-title">الفواتير المرتبطة بالدفعة</h5>
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

    <!-- Modal إضافة دفعة -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة دفعة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="payment_action.php" method="POST">
                    <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ الدفع *</label>
                                <input type="date" name="payment_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المبلغ *</label>
                                <input type="number" step="0.01" name="amount" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">طريقة الدفع *</label>
                                <select name="payment_method" class="form-control" required>
                                    <option value="cash">نقداً</option>
                                    <option value="bank_transfer">حوالة بنكية</option>
                                    <option value="check">شيك</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الفرع المستلم</label>
                                <select name="my_branch_id" class="form-control">
                                    <option value="">اختر الفرع</option>
                                    <?php
                                    $branches = $pdo->query("SELECT * FROM my_branches WHERE status='active' ORDER BY branch_name")->fetchAll();
                                    foreach($branches as $branch):
                                    ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>

                        <h5 class="mt-4 mb-3">توزيع الدفعة على الفواتير</h5>
                        <div id="invoices_container" style="max-height: 300px; overflow-y: auto; border: 2px solid #e9ecef; border-radius: 10px; padding: 15px;">
                            <?php
                            // جلب الفواتير غير المدفوعة
                            $stmt = $pdo->prepare("SELECT * FROM invoices 
                                                   WHERE customer_id = ? 
                                                   AND status != 'paid'
                                                   ORDER BY invoice_date");
                            $stmt->execute([$customer_id]);
                            $unpaid_invoices = $stmt->fetchAll();
                            ?>
                            
                            <?php if(empty($unpaid_invoices)): ?>
                                <p class="text-center text-success">لا توجد فواتير غير مدفوعة</p>
                            <?php else: ?>
                                <?php foreach($unpaid_invoices as $inv): ?>
                                    <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                                        <div class="flex-grow-1">
                                            <strong><?= htmlspecialchars($inv['invoice_number']) ?></strong>
                                            <span class="badge bg-info"><?= date('Y-m-d', strtotime($inv['invoice_date'])) ?></span>
                                            <br>
                                            <small class="text-muted">
                                                الإجمالي: <?= number_format($inv['total_amount'], 2) ?> ريال |
                                                <span class="text-danger">المتبقي: <?= number_format($inv['remaining_amount'], 2) ?> ريال</span>
                                            </small>
                                        </div>
                                        <div style="width: 150px;">
                                            <input type="number" 
                                                   name="invoices[<?= $inv['id'] ?>]" 
                                                   class="form-control" 
                                                   placeholder="المبلغ"
                                                   step="0.01"
                                                   min="0" 
                                                   max="<?= $inv['remaining_amount'] ?>"
                                                   value="0">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
</body>
</html>