<?php
require_once 'config.php';
checkLogin();

$invoice_id = $_GET['id'] ?? 0;

// جلب الفاتورة
$stmt = $pdo->prepare("SELECT i.*, c.company_name, c.owner_name, c.phone, c.address,
                       r.region_name,
                       mb.branch_name as my_branch_name, mb.phone as my_branch_phone,
                       sr.name as sales_rep_name,
                       cb.branch_name as customer_branch_name
                       FROM invoices i
                       JOIN customers c ON i.customer_id = c.id
                       LEFT JOIN regions r ON c.region_id = r.id
                       LEFT JOIN my_branches mb ON i.my_branch_id = mb.id
                       LEFT JOIN sales_representatives sr ON i.sales_rep_id = sr.id
                       LEFT JOIN customer_branches cb ON i.branch_id = cb.id
                       WHERE i.id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if(!$invoice) {
    header('Location: invoices.php');
    exit;
}

// جلب تفاصيل الفاتورة (المنتجات)
$stmt = $pdo->prepare("SELECT ii.*, p.product_name, p.product_type, p.brand, p.size_description
                       FROM invoice_items ii
                       JOIN products p ON ii.product_id = p.id
                       WHERE ii.invoice_id = ?
                       ORDER BY p.product_type, p.product_name");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll();

// جلب الدفعات المرتبطة
$stmt = $pdo->prepare("SELECT p.*, l.allocated_amount, u.full_name as received_by_name
                       FROM payment_invoice_link l
                       JOIN payments p ON l.payment_id = p.id
                       LEFT JOIN users u ON p.received_by = u.id
                       WHERE l.invoice_id = ?
                       ORDER BY p.payment_date DESC");
$stmt->execute([$invoice_id]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الفاتورة - <?= $invoice['invoice_number'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .invoice-container {
            max-width: 900px;
            margin: 30px auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .invoice-header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .info-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .invoice-container { box-shadow: none; margin: 0; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- الأزرار -->
        <div class="no-print mb-4 d-flex justify-content-between">
            <a href="invoices.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة
            </a>
            <div>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> طباعة
                </button>
            </div>
        </div>

        <!-- رأس الفاتورة -->
        <div class="invoice-header">
            <div class="row">
                <div class="col-md-6">
                    <h2 class="text-primary mb-0">فاتورة مبيعات</h2>
                    <p class="text-muted">نظام إدارة العملاء</p>
                </div>
                <div class="col-md-6 text-end">
                    <h3><?= htmlspecialchars($invoice['invoice_number']) ?></h3>
                    <p class="mb-0">التاريخ: <?= date('Y-m-d', strtotime($invoice['invoice_date'])) ?></p>
                    <?php
                    $status_colors = ['paid'=>'success', 'partial'=>'warning', 'unpaid'=>'danger'];
                    $status_names = ['paid'=>'مدفوعة', 'partial'=>'مدفوعة جزئياً', 'unpaid'=>'غير مدفوعة'];
                    ?>
                    <span class="badge bg-<?= $status_colors[$invoice['status']] ?>">
                        <?= $status_names[$invoice['status']] ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- معلومات العميل -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="info-section">
                    <h5 class="text-primary mb-3">بيانات العميل</h5>
                    <p class="mb-1"><strong>الشركة:</strong> <?= htmlspecialchars($invoice['company_name']) ?></p>
                    <p class="mb-1"><strong>المالك:</strong> <?= htmlspecialchars($invoice['owner_name']) ?></p>
                    <?php if($invoice['phone']): ?>
                        <p class="mb-1"><strong>الهاتف:</strong> <?= htmlspecialchars($invoice['phone']) ?></p>
                    <?php endif; ?>
                    <?php if($invoice['region_name']): ?>
                        <p class="mb-1"><strong>المنطقة:</strong> <?= htmlspecialchars($invoice['region_name']) ?></p>
                    <?php endif; ?>
                    <?php if($invoice['customer_branch_name']): ?>
                        <p class="mb-0"><strong>الفرع:</strong> <?= htmlspecialchars($invoice['customer_branch_name']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-section">
                    <h5 class="text-primary mb-3">بيانات البائع</h5>
                    <?php if($invoice['my_branch_name']): ?>
                        <p class="mb-1"><strong>الفرع:</strong> <?= htmlspecialchars($invoice['my_branch_name']) ?></p>
                    <?php endif; ?>
                    <?php if($invoice['sales_rep_name']): ?>
                        <p class="mb-1"><strong>المندوب:</strong> <?= htmlspecialchars($invoice['sales_rep_name']) ?></p>
                    <?php endif; ?>
                    <?php if($invoice['my_branch_phone']): ?>
                        <p class="mb-0"><strong>الهاتف:</strong> <?= htmlspecialchars($invoice['my_branch_phone']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- جدول المنتجات -->
        <h5 class="mb-3">تفاصيل الفاتورة</h5>
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th width="5%">#</th>
                    <th>المنتج</th>
                    <th width="10%">الكمية</th>
                    <th width="15%">السعر</th>
                    <th width="15%">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                foreach($items as $item): 
                    $type_names = ['tire'=>'كفر', 'battery'=>'بطارية', 'slastic'=>'سلاستك'];
                ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td>
                            <?= htmlspecialchars($item['product_name']) ?>
                            <?php if($item['brand']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($item['brand']) ?></small>
                            <?php endif; ?>
                            <?php if($item['size_description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($item['size_description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $item['quantity'] ?></td>
                        <td class="text-end"><?= number_format($item['unit_price'], 2) ?></td>
                        <td class="text-end"><strong><?= number_format($item['total_price'], 2) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <td colspan="4" class="text-end"><strong>الإجمالي:</strong></td>
                    <td class="text-end"><strong><?= number_format($invoice['total_amount'], 2) ?> ريال</strong></td>
                </tr>
                <?php if($invoice['paid_amount'] > 0): ?>
                <tr class="table-success">
                    <td colspan="4" class="text-end"><strong>المدفوع:</strong></td>
                    <td class="text-end"><strong><?= number_format($invoice['paid_amount'], 2) ?> ريال</strong></td>
                </tr>
                <?php endif; ?>
                <?php if($invoice['remaining_amount'] > 0): ?>
                <tr class="table-danger">
                    <td colspan="4" class="text-end"><strong>المتبقي:</strong></td>
                    <td class="text-end"><strong><?= number_format($invoice['remaining_amount'], 2) ?> ريال</strong></td>
                </tr>
                <?php endif; ?>
            </tfoot>
        </table>

        <!-- الدفعات -->
        <?php if(!empty($payments)): ?>
            <h5 class="mt-4 mb-3">الدفعات المرتبطة</h5>
            <table class="table table-sm">
                <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th>المبلغ</th>
                        <th>الطريقة</th>
                        <th>المستلم</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($payments as $payment): ?>
                        <tr>
                            <td><?= date('Y-m-d', strtotime($payment['payment_date'])) ?></td>
                            <td><?= number_format($payment['allocated_amount'], 2) ?> ريال</td>
                            <td>
                                <?php
                                $methods = ['cash'=>'نقداً', 'bank_transfer'=>'حوالة', 'check'=>'شيك', 'other'=>'أخرى'];
                                echo $methods[$payment['payment_method']] ?? $payment['payment_method'];
                                ?>
                            </td>
                            <td><?= htmlspecialchars($payment['received_by_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- ملاحظات -->
        <?php if($invoice['notes']): ?>
            <div class="alert alert-info mt-4">
                <strong><i class="fas fa-sticky-note"></i> ملاحظات:</strong><br>
                <?= nl2br(htmlspecialchars($invoice['notes'])) ?>
            </div>
        <?php endif; ?>

        <!-- التوقيعات -->
        <div class="row mt-5 pt-4" style="border-top: 1px solid #dee2e6;">
            <div class="col-md-6 text-center">
                <div style="border-top: 2px solid #000; display: inline-block; padding-top: 10px; min-width: 200px;">
                    توقيع المستلم
                </div>
            </div>
            <div class="col-md-6 text-center">
                <div style="border-top: 2px solid #000; display: inline-block; padding-top: 10px; min-width: 200px;">
                    توقيع البائع
                </div>
            </div>
        </div>

        <p class="text-center text-muted mt-4 mb-0">
            <small>نشكركم لتعاملكم معنا</small>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>