<?php
require_once 'config.php';
checkLogin();

// معالجة الفلاتر
$view_type = $_GET['view'] ?? 'all';
$entity_id = $_GET['entity_id'] ?? 0;

// بناء الاستعلام
$where_conditions = ["1=1"];
$params = [];

if($view_type == 'branch' && $entity_id) {
    $where_conditions[] = "EXISTS (
        SELECT 1 FROM customer_branch_rep_assignments cbra
        WHERE cbra.customer_id = c.id 
        AND cbra.my_branch_id = ? 
        AND cbra.status = 'active'
    )";
    $params[] = $entity_id;
} elseif($view_type == 'rep' && $entity_id) {
    $where_conditions[] = "EXISTS (
        SELECT 1 FROM customer_branch_rep_assignments cbra
        WHERE cbra.customer_id = c.id 
        AND cbra.sales_rep_id = ? 
        AND cbra.status = 'active'
    )";
    $params[] = $entity_id;
} elseif($view_type == 'customer' && $entity_id) {
    $where_conditions[] = "c.id = ?";
    $params[] = $entity_id;
}

$where_clause = implode(' AND ', $where_conditions);

// جلب المقاسات من الفواتير (الأكثر طلباً)
$sql = "SELECT 
    p.size_description,
    p.brand,
    p.product_type,
    c.id as customer_id,
    c.company_name,
    COUNT(DISTINCT ii.invoice_id) as times_ordered,
    SUM(ii.quantity) as total_quantity,
    SUM(ii.total_price) as total_revenue,
    MAX(i.invoice_date) as last_order_date,
    MIN(i.invoice_date) as first_order_date
FROM products p
JOIN invoice_items ii ON p.id = ii.product_id
JOIN invoices i ON ii.invoice_id = i.id
JOIN customers c ON i.customer_id = c.id
WHERE $where_clause
AND p.size_description IS NOT NULL 
AND p.size_description != ''
AND p.product_type = 'tire'
GROUP BY p.size_description, p.brand, c.id
ORDER BY total_quantity DESC
LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sizes_data = $stmt->fetchAll();

// الإحصائيات العامة
$stats_sql = "SELECT 
    COUNT(DISTINCT CONCAT(p.size_description, '-', c.id)) as unique_combinations,
    COUNT(DISTINCT c.id) as total_customers,
    SUM(ii.quantity) as total_sold,
    SUM(ii.total_price) as total_revenue
FROM products p
JOIN invoice_items ii ON p.id = ii.product_id
JOIN invoices i ON ii.invoice_id = i.id
JOIN customers c ON i.customer_id = c.id
WHERE $where_clause
AND p.size_description IS NOT NULL 
AND p.size_description != ''
AND p.product_type = 'tire'";

$stmt_stats = $pdo->prepare($stats_sql);
$stmt_stats->execute($params);
$stats = $stmt_stats->fetch();

// أكثر المقاسات طلباً بشكل عام
$top_sizes = $pdo->query("SELECT 
    p.size_description,
    p.brand,
    COUNT(DISTINCT c.id) as customer_count,
    SUM(ii.quantity) as total_quantity,
    SUM(ii.total_price) as revenue
FROM products p
JOIN invoice_items ii ON p.id = ii.product_id
JOIN invoices i ON ii.invoice_id = i.id
JOIN customers c ON i.customer_id = c.id
WHERE p.size_description IS NOT NULL 
AND p.size_description != '' 
AND p.product_type = 'tire'
GROUP BY p.size_description, p.brand
ORDER BY total_quantity DESC
LIMIT 15")->fetchAll();

// جلب القوائم للفلاتر
$branches = $pdo->query("SELECT * FROM my_branches WHERE status='active' ORDER BY branch_name")->fetchAll();
$reps = $pdo->query("SELECT * FROM sales_representatives WHERE status='active' ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT id, company_name FROM customers WHERE status='active' ORDER BY company_name LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المقاسات والإحصائيات</title>
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
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            text-align: center;
            height: 100%;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .size-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-right: 4px solid #667eea;
            transition: all 0.3s;
        }
        .size-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .size-card.hot {
            border-right-color: #dc3545;
            background: linear-gradient(to left, #fff 0%, #fff5f5 100%);
        }
        .size-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin: 5px;
        }
        .trend-up {
            color: #28a745;
        }
        .trend-down {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-ruler text-primary"></i> المقاسات والإحصائيات</h2>
                    <p class="text-muted">تحليل المقاسات الأكثر طلباً تلقائياً من الفواتير</p>
                </div>
            </div>

            <!-- الفلاتر -->
            <div class="card mb-4" style="border-radius: 15px;">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-filter"></i> عرض حسب</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <select name="view" class="form-control" onchange="updateEntitySelect(this.value)">
                                <option value="all" <?= $view_type=='all'?'selected':'' ?>>الكل</option>
                                <option value="branch" <?= $view_type=='branch'?'selected':'' ?>>حسب الفرع</option>
                                <option value="rep" <?= $view_type=='rep'?'selected':'' ?>>حسب المندوب</option>
                                <option value="customer" <?= $view_type=='customer'?'selected':'' ?>>حسب العميل</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="entity_select_container">
                            <?php if($view_type == 'branch'): ?>
                                <select name="entity_id" class="form-control">
                                    <option value="">اختر الفرع</option>
                                    <?php foreach($branches as $b): ?>
                                        <option value="<?= $b['id'] ?>" <?= $entity_id==$b['id']?'selected':'' ?>>
                                            <?= htmlspecialchars($b['branch_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif($view_type == 'rep'): ?>
                                <select name="entity_id" class="form-control">
                                    <option value="">اختر المندوب</option>
                                    <?php foreach($reps as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= $entity_id==$r['id']?'selected':'' ?>>
                                            <?= htmlspecialchars($r['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif($view_type == 'customer'): ?>
                                <select name="entity_id" class="form-control">
                                    <option value="">اختر العميل</option>
                                    <?php foreach($customers as $cu): ?>
                                        <option value="<?= $cu['id'] ?>" <?= $entity_id==$cu['id']?'selected':'' ?>>
                                            <?= htmlspecialchars($cu['company_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> عرض
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-list fa-2x text-primary mb-3"></i>
                        <h3><?= number_format($stats['unique_combinations']) ?></h3>
                        <p class="text-muted mb-0">مقاس مختلف</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-users fa-2x text-success mb-3"></i>
                        <h3><?= number_format($stats['total_customers']) ?></h3>
                        <p class="text-muted mb-0">عميل</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-box fa-2x text-warning mb-3"></i>
                        <h3><?= number_format($stats['total_sold']) ?></h3>
                        <p class="text-muted mb-0">قطعة مباعة</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-dollar-sign fa-2x text-info mb-3"></i>
                        <h3><?= number_format($stats['total_revenue'], 0) ?></h3>
                        <p class="text-muted mb-0">ريال إيرادات</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- قائمة المقاسات -->
                <div class="col-md-8 mb-4">
                    <div class="card" style="border-radius: 15px;">
                        <div class="card-body">
                            <h5 class="mb-4"><i class="fas fa-th-list"></i> المقاسات حسب العميل</h5>
                            
                            <?php if(empty($sizes_data)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                    <h5>لا توجد بيانات</h5>
                                    <p class="text-muted">لا توجد طلبات مسجلة للمقاسات</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($sizes_data as $size): ?>
                                    <div class="size-card <?= $size['total_quantity'] > 50 ? 'hot' : '' ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h6>
                                                    <i class="fas fa-tire"></i>
                                                    <?= htmlspecialchars($size['size_description']) ?>
                                                    <?php if($size['total_quantity'] > 50): ?>
                                                        <span class="badge bg-danger">🔥 رائج</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="text-muted mb-1">
                                                    <i class="fas fa-building"></i>
                                                    <?= htmlspecialchars($size['company_name']) ?>
                                                </p>
                                                <?php if($size['brand']): ?>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($size['brand']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="text-center">
                                                    <div class="mb-2">
                                                        <strong class="text-success"><?= number_format($size['total_quantity']) ?></strong>
                                                        <small class="text-muted">قطعة</small>
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong class="text-primary"><?= $size['times_ordered'] ?></strong>
                                                        <small class="text-muted">طلب</small>
                                                    </div>
                                                    <div>
                                                        <strong class="text-info"><?= number_format($size['total_revenue'], 0) ?></strong>
                                                        <small class="text-muted">ريال</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <a href="customer_details.php?id=<?= $size['customer_id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i>
                                                أول طلب: <?= date('Y-m-d', strtotime($size['first_order_date'])) ?> |
                                                آخر طلب: <?= date('Y-m-d', strtotime($size['last_order_date'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- الأكثر طلباً بشكل عام -->
                <div class="col-md-4 mb-4">
                    <div class="card" style="border-radius: 15px;">
                        <div class="card-body">
                            <h5 class="mb-4"><i class="fas fa-fire text-danger"></i> الأكثر طلباً</h5>
                            
                            <?php if(empty($top_sizes)): ?>
                                <p class="text-center text-muted">لا توجد بيانات</p>
                            <?php else: ?>
                                <?php foreach($top_sizes as $index => $top): ?>
                                    <div class="mb-3 p-3" style="background: #f8f9fa; border-radius: 10px;">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <span class="badge bg-primary me-2"><?= $index + 1 ?></span>
                                                <strong><?= htmlspecialchars($top['size_description']) ?></strong>
                                                <?php if($top['brand']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($top['brand']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <div class="text-success fw-bold"><?= number_format($top['total_quantity']) ?></div>
                                                <small class="text-muted"><?= $top['customer_count'] ?> عميل</small>
                                            </div>
                                        </div>
                                        <div class="progress mt-2" style="height: 5px;">
                                            <div class="progress-bar bg-success" style="width: <?= min(100, ($top['total_quantity'] / max(array_column($top_sizes, 'total_quantity'))) * 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- معلومات إضافية -->
                    <div class="card mt-3" style="border-radius: 15px;">
                        <div class="card-body">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-info-circle"></i> معلومة
                            </h6>
                            <p class="small text-muted mb-0">
                                يتم احتساب المقاسات تلقائياً من الفواتير المسجلة. البيانات محدثة بشكل فوري عند إضافة أو تعديل أي فاتورة.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const branches = <?= json_encode($branches) ?>;
        const reps = <?= json_encode($reps) ?>;
        const customers = <?= json_encode($customers) ?>;

        function updateEntitySelect(viewType) {
            const container = document.getElementById('entity_select_container');
            let html = '';
            
            if(viewType === 'branch') {
                html = '<select name="entity_id" class="form-control"><option value="">اختر الفرع</option>';
                branches.forEach(b => html += `<option value="${b.id}">${b.branch_name}</option>`);
                html += '</select>';
            } else if(viewType === 'rep') {
                html = '<select name="entity_id" class="form-control"><option value="">اختر المندوب</option>';
                reps.forEach(r => html += `<option value="${r.id}">${r.name}</option>`);
                html += '</select>';
            } else if(viewType === 'customer') {
                html = '<select name="entity_id" class="form-control"><option value="">اختر العميل</option>';
                customers.forEach(c => html += `<option value="${c.id}">${c.company_name}</option>`);
                html += '</select>';
            }
            
            container.innerHTML = html;
        }
    </script>
</body>
</html>