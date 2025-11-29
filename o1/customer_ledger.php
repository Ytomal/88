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

// بناء كشف الحساب
$ledger = [];
$balance = $customer['opening_balance'];

// إضافة الرصيد الافتتاحي
if($customer['opening_balance'] != 0) {
    $ledger[] = [
        'date' => $customer['start_date'] ?? $customer['created_at'],
        'type' => 'opening_balance',
        'description' => 'رصيد افتتاحي',
        'reference' => '-',
        'debit' => $customer['opening_balance'] > 0 ? $customer['opening_balance'] : 0,
        'credit' => $customer['opening_balance'] < 0 ? abs($customer['opening_balance']) : 0,
        'balance' => $balance
    ];
}

// جلب الفواتير
$stmt = $pdo->prepare("SELECT invoice_date as date, invoice_number, total_amount 
                       FROM invoices 
                       WHERE customer_id = ? 
                       ORDER BY invoice_date, id");
$stmt->execute([$customer_id]);
$invoices = $stmt->fetchAll();

foreach($invoices as $inv) {
    $balance += $inv['total_amount'];
    $ledger[] = [
        'date' => $inv['date'],
        'type' => 'invoice',
        'description' => 'فاتورة مبيعات',
        'reference' => $inv['invoice_number'],
        'debit' => $inv['total_amount'],
        'credit' => 0,
        'balance' => $balance
    ];
}

// جلب الدفعات
$stmt = $pdo->prepare("SELECT payment_date as date, amount 
                       FROM payments 
                       WHERE customer_id = ? 
                       ORDER BY payment_date, id");
$stmt->execute([$customer_id]);
$payments = $stmt->fetchAll();

foreach($payments as $pay) {
    $balance -= $pay['amount'];
    $ledger[] = [
        'date' => $pay['date'],
        'type' => 'payment',
        'description' => 'دفعة',
        'reference' => 'دفعة نقدية',
        'debit' => 0,
        'credit' => $pay['amount'],
        'balance' => $balance
    ];
}

// ترتيب حسب التاريخ
usort($ledger, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// إعادة حساب الرصيد بعد الترتيب
$balance = $customer['opening_balance'];
foreach($ledger as &$entry) {
    if($entry['type'] == 'opening_balance') {
        $entry['balance'] = $balance;
    } else {
        $balance = $balance + $entry['debit'] - $entry['credit'];
        $entry['balance'] = $balance;
    }
}

// الإحصائيات
$total_debit = array_sum(array_column($ledger, 'debit'));
$total_credit = array_sum(array_column($ledger, 'credit'));
$final_balance = $balance;

// الفلترة
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';

if($from_date || $to_date) {
    $ledger = array_filter($ledger, function($entry) use ($from_date, $to_date) {
        $date = $entry['date'];
        if($from_date && $date < $from_date) return false;
        if($to_date && $date > $to_date) return false;
        return true;
    });
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كشف حساب - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            margin-right: 260px;
            padding: 25px;
        }
        .ledger-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .ledger-header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .ledger-table {
            font-size: 14px;
        }
        .ledger-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .balance-positive {
            color: #dc3545;
            font-weight: bold;
        }
        .balance-negative {
            color: #28a745;
            font-weight: bold;
        }
        @media print {
            .no-print { display: none !important; }
            .main-content { margin-right: 0; }
            body { background: white; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="ledger-container">
                <!-- الأزرار -->
                <div class="no-print mb-4 d-flex justify-content-between">
                    <a href="view_customer.php?id=<?= $customer_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                    <div>
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print"></i> طباعة
                        </button>
                        <button onclick="exportToExcel()" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                    </div>
                </div>

                <!-- رأس كشف الحساب -->
                <div class="ledger-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h2 class="text-primary mb-0">كشف حساب</h2>
                            <p class="text-muted">نظام إدارة العملاء</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h4><?= htmlspecialchars($customer['company_name']) ?></h4>
                            <p class="mb-0">التاريخ: <?= date('Y-m-d') ?></p>
                        </div>
                    </div>
                </div>

                <!-- الملخص -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="summary-box">
                            <small>الرصيد الافتتاحي</small>
                            <h4 class="mb-0"><?= number_format($customer['opening_balance'], 2) ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box">
                            <small>إجمالي المبيعات</small>
                            <h4 class="mb-0"><?= number_format($total_debit - $customer['opening_balance'], 2) ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box">
                            <small>إجمالي المدفوعات</small>
                            <h4 class="mb-0"><?= number_format($total_credit, 2) ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box" style="background: <?= $final_balance >= 0 ? '#dc3545' : '#28a745' ?>">
                            <small>الرصيد الحالي</small>
                            <h4 class="mb-0"><?= number_format($final_balance, 2) ?></h4>
                        </div>
                    </div>
                </div>

                <!-- الفلترة -->
                <div class="no-print mb-4">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="id" value="<?= $customer_id ?>">
                        <div class="col-md-4">
                            <label class="form-label">من تاريخ</label>
                            <input type="date" name="from" class="form-control" value="<?= $from_date ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">إلى تاريخ</label>
                            <input type="date" name="to" class="form-control" value="<?= $to_date ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> تطبيق
                                </button>
                                <a href="customer_ledger.php?id=<?= $customer_id ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> إلغاء
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- جدول كشف الحساب -->
                <div class="table-responsive">
                    <table class="table table-bordered ledger-table" id="ledgerTable">
                        <thead>
                            <tr>
                                <th width="10%">التاريخ</th>
                                <th width="15%">البيان</th>
                                <th width="15%">المرجع</th>
                                <th width="15%" class="text-end">مدين</th>
                                <th width="15%" class="text-end">دائن</th>
                                <th width="15%" class="text-end">الرصيد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($ledger)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">لا توجد حركات</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($ledger as $entry): ?>
                                    <tr>
                                        <td><?= date('Y-m-d', strtotime($entry['date'])) ?></td>
                                        <td>
                                            <?php
                                            $icons = [
                                                'opening_balance' => '<i class="fas fa-flag text-info"></i>',
                                                'invoice' => '<i class="fas fa-file-invoice text-primary"></i>',
                                                'payment' => '<i class="fas fa-money-bill-wave text-success"></i>'
                                            ];
                                            echo $icons[$entry['type']] ?? '';
                                            ?>
                                            <?= $entry['description'] ?>
                                        </td>
                                        <td><?= htmlspecialchars($entry['reference']) ?></td>
                                        <td class="text-end">
                                            <?= $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-' ?>
                                        </td>
                                        <td class="text-end">
                                            <?= $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-' ?>
                                        </td>
                                        <td class="text-end">
                                            <span class="<?= $entry['balance'] >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                                                <?= number_format($entry['balance'], 2) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="3" class="text-end">الإجمالي:</th>
                                <th class="text-end"><?= number_format($total_debit, 2) ?></th>
                                <th class="text-end"><?= number_format($total_credit, 2) ?></th>
                                <th class="text-end">
                                    <span class="<?= $final_balance >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                                        <?= number_format($final_balance, 2) ?>
                                    </span>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- معلومات إضافية -->
                <div class="mt-4 pt-4" style="border-top: 1px solid #dee2e6;">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>نوع الدفع:</strong> 
                                <?php
                                $payment_types = ['cash'=>'نقداً', 'credit'=>'آجل', 'both'=>'نقداً وآجل'];
                                echo $payment_types[$customer['payment_type']] ?? 'غير محدد';
                                ?>
                            </p>
                            <p><strong>مدة السداد المتفق عليها:</strong> <?= $customer['payment_terms'] ?> يوم</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p><strong>الحد الائتماني:</strong> <?= number_format($customer['credit_limit'], 2) ?> ريال</p>
                            <p><strong>المتاح:</strong> 
                                <?php
                                $available = $customer['credit_limit'] - $final_balance;
                                ?>
                                <span class="<?= $available >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($available, 2) ?> ريال
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <p class="text-center text-muted mt-4 mb-0">
                    <small>طباعة بتاريخ: <?= date('Y-m-d H:i:s') ?></small>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        function exportToExcel() {
            const table = document.getElementById('ledgerTable');
            const wb = XLSX.utils.table_to_book(table, {sheet: "كشف الحساب"});
            const filename = 'كشف_حساب_<?= str_replace(' ', '_', $customer['company_name']) ?>_' + new Date().getTime() + '.xlsx';
            XLSX.writeFile(wb, filename);
        }
    </script>
</body>
</html>