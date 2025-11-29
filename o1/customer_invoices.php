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

// جلب الفواتير
$stmt = $pdo->prepare("SELECT i.*, 
                       mb.branch_name as my_branch_name,
                       sr.name as sales_rep_name,
                       cb.branch_name as customer_branch_name
                       FROM invoices i
                       LEFT JOIN my_branches mb ON i.my_branch_id = mb.id
                       LEFT JOIN sales_representatives sr ON i.sales_rep_id = sr.id
                       LEFT JOIN customer_branches cb ON i.branch_id = cb.id
                       WHERE i.customer_id = ?
                       ORDER BY i.invoice_date DESC, i.id DESC");
$stmt->execute([$customer_id]);
$invoices = $stmt->fetchAll();

// الإحصائيات
$total_invoices = count($invoices);
$total_amount = array_sum(array_column($invoices, 'total_amount'));
$paid_amount = array_sum(array_column($invoices, 'paid_amount'));
$remaining_amount = array_sum(array_column($invoices, 'remaining_amount'));
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فواتير العميل - <?= htmlspecialchars($customer['company_name']) ?></title>
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
        .invoice-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-right: 4px solid #667eea;
            transition: all 0.3s;
            cursor: pointer;
        }
        .invoice-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        #search_results {
            position: absolute;
            z-index: 1060;
            background: white;
            border: 2px solid #667eea;
            border-radius: 10px;
            max-height: 400px;
            overflow-y: auto;
            width: calc(100% - 30px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            display: none;
        }
        .search-result-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-result-item:hover {
            background: #f8f9fa;
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
                        <i class="fas fa-file-invoice text-primary"></i> فواتير العميل
                    </h2>
                    <p class="text-muted">
                        <?= htmlspecialchars($customer['company_name']) ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="view_customer.php?id=<?= $customer_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                </div>
            </div>

            <!-- الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-file-invoice fa-3x text-primary mb-3"></i>
                            <h3><?= $total_invoices ?></h3>
                            <p class="text-muted mb-0">إجمالي الفواتير</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-money-bill-wave fa-3x text-info mb-3"></i>
                            <h3><?= number_format($total_amount, 0) ?></h3>
                            <p class="text-muted mb-0">إجمالي المبيعات (ريال)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h3><?= number_format($paid_amount, 0) ?></h3>
                            <p class="text-muted mb-0">المدفوع (ريال)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-hourglass-half fa-3x text-danger mb-3"></i>
                            <h3><?= number_format($remaining_amount, 0) ?></h3>
                            <p class="text-muted mb-0">المتبقي (ريال)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- البحث السريع -->
            <div class="card mb-4" style="border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
                <div class="card-body">
                    <div class="position-relative">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" 
                                   id="invoice_search" 
                                   class="form-control" 
                                   placeholder="ابحث برقم الفاتورة، التاريخ، المبلغ..." 
                                   autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="search_results"></div>
                    </div>
                </div>
            </div>

            <!-- قائمة الفواتير -->
            <div id="all_invoices">
                <?php if(empty($invoices)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
                        <h4>لا توجد فواتير</h4>
                    </div>
                <?php else: ?>
                    <?php foreach($invoices as $inv): ?>
                        <div class="invoice-card" onclick="location.href='invoice_details.php?id=<?= $inv['id'] ?>'">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h5>
                                        <i class="fas fa-file-alt text-primary"></i>
                                        <?= htmlspecialchars($inv['invoice_number']) ?>
                                    </h5>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> <?= date('Y-m-d', strtotime($inv['invoice_date'])) ?>
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <?php if($inv['customer_branch_name']): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-building"></i> <?= htmlspecialchars($inv['customer_branch_name']) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if($inv['my_branch_name']): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-store"></i> فرع: <?= htmlspecialchars($inv['my_branch_name']) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if($inv['sales_rep_name']): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-user-tie"></i> <?= htmlspecialchars($inv['sales_rep_name']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2 text-center">
                                    <h5 class="text-primary mb-0"><?= number_format($inv['total_amount'], 2) ?></h5>
                                    <small class="text-muted">الإجمالي</small>
                                    <br>
                                    <h6 class="text-danger mb-0"><?= number_format($inv['remaining_amount'], 2) ?></h6>
                                    <small class="text-muted">المتبقي</small>
                                </div>
                                <div class="col-md-2 text-end">
                                    <?php
                                    $status_colors = ['paid'=>'success', 'partial'=>'warning', 'unpaid'=>'danger'];
                                    $status_names = ['paid'=>'مدفوعة', 'partial'=>'جزئي', 'unpaid'=>'غير مدفوعة'];
                                    ?>
                                    <span class="badge bg-<?= $status_colors[$inv['status']] ?> mb-2">
                                        <?= $status_names[$inv['status']] ?>
                                    </span>
                                    <br>
                                    <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); location.href='invoice_details.php?id=<?= $inv['id'] ?>'">
                                        <i class="fas fa-eye"></i> عرض
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // بيانات الفواتير للبحث
        const invoices = <?= json_encode($invoices) ?>;
        let searchTimeout;

        // البحث في الفواتير
        document.getElementById('invoice_search').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim().toLowerCase();
            
            if(query.length < 2) {
                document.getElementById('search_results').style.display = 'none';
                document.getElementById('all_invoices').style.display = 'block';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                const results = invoices.filter(inv => {
                    return inv.invoice_number.toLowerCase().includes(query) ||
                           inv.invoice_date.includes(query) ||
                           inv.total_amount.toString().includes(query) ||
                           (inv.customer_branch_name && inv.customer_branch_name.toLowerCase().includes(query)) ||
                           (inv.my_branch_name && inv.my_branch_name.toLowerCase().includes(query)) ||
                           (inv.sales_rep_name && inv.sales_rep_name.toLowerCase().includes(query));
                });
                
                displaySearchResults(results);
            }, 300);
        });

        function displaySearchResults(results) {
            const container = document.getElementById('search_results');
            const allInvoices = document.getElementById('all_invoices');
            
            if(results.length === 0) {
                container.innerHTML = '<div class="p-3 text-center text-muted">لا توجد نتائج</div>';
                container.style.display = 'block';
                allInvoices.style.display = 'none';
                return;
            }
            
            let html = '';
            const statusNames = {paid: 'مدفوعة', partial: 'جزئي', unpaid: 'غير مدفوعة'};
            const statusColors = {paid: 'success', partial: 'warning', unpaid: 'danger'};
            
            results.forEach(inv => {
                html += `
                    <div class="search-result-item" onclick="location.href='invoice_details.php?id=${inv.id}'">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><i class="fas fa-file-invoice text-primary"></i> ${inv.invoice_number}</strong>
                                <span class="badge bg-${statusColors[inv.status]} ms-2">${statusNames[inv.status]}</span>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> ${inv.invoice_date} |
                                    الإجمالي: <strong>${parseFloat(inv.total_amount).toFixed(2)}</strong> ريال |
                                    المتبقي: <strong class="text-danger">${parseFloat(inv.remaining_amount).toFixed(2)}</strong> ريال
                                </small>
                            </div>
                            <i class="fas fa-chevron-left text-muted"></i>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            container.style.display = 'block';
            allInvoices.style.display = 'none';
        }

        function clearSearch() {
            document.getElementById('invoice_search').value = '';
            document.getElementById('search_results').style.display = 'none';
            document.getElementById('all_invoices').style.display = 'block';
        }

        // إخفاء نتائج البحث عند النقر خارجها
        document.addEventListener('click', function(e) {
            const search = document.getElementById('invoice_search');
            const results = document.getElementById('search_results');
            if(!search.contains(e.target) && !results.contains(e.target)) {
                // لا نخفي النتائج، فقط نسمح بالنقر خارجها
            }
        });
    </script>
</body>
</html>