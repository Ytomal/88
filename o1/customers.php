<?php
require_once 'config.php';
checkLogin();

// معالجة البحث والفلتر
$search = $_GET['search'] ?? '';
$region_filter = $_GET['region'] ?? '';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';

// بناء الاستعلام
$sql = "SELECT c.*, r.region_name 
        FROM customers c
        LEFT JOIN regions r ON c.region_id = r.id
        WHERE 1=1";

$params = [];

if($search) {
    $sql .= " AND (c.company_name LIKE ? OR c.owner_name LIKE ? OR c.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if($region_filter) {
    $sql .= " AND c.region_id = ?";
    $params[] = $region_filter;
}

if($status_filter) {
    $sql .= " AND c.status = ?";
    $params[] = $status_filter;
}

if($type_filter) {
    $sql .= " AND c.company_type = ?";
    $params[] = $type_filter;
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// جلب المناطق للفلتر
$regions = $pdo->query("SELECT * FROM regions WHERE status = 'active' ORDER BY region_name")->fetchAll();

// إحصائيات
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn(),
    'inactive' => $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'inactive'")->fetchColumn(),
    'with_branches' => $pdo->query("SELECT COUNT(*) FROM customers WHERE has_branches = 1")->fetchColumn()
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة العملاء - نظام إدارة العملاء</title>
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
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .stats-card.blue { border-left-color: #667eea; }
        .stats-card.green { border-left-color: #28a745; }
        .stats-card.orange { border-left-color: #fd7e14; }
        .stats-card.red { border-left-color: #dc3545; }
        
        .customer-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-right: 4px solid #667eea;
            cursor: pointer;
        }
        
        .customer-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            background: #f8f9fa;
        }
        
        .customer-card .customer-name {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
        }
        
        .customer-card .customer-name:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-right: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-users text-primary"></i> إدارة العملاء</h2>
                    <p class="text-muted">إجمالي العملاء: <?= $stats['total'] ?></p>
                </div>
                <div class="d-flex gap-2">
                    <a href="add_customer.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> إضافة عميل جديد
                    </a>
                    <button class="btn btn-success" onclick="window.print()">
                        <i class="fas fa-print"></i> طباعة
                    </button>
                </div>
            </div>

            <!-- إشعارات النجاح -->
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i>
                    <?php
                    $messages = [
                        'added' => 'تم إضافة العميل بنجاح',
                        'updated' => 'تم تحديث بيانات العميل بنجاح',
                        'deleted' => 'تم حذف العميل بنجاح'
                    ];
                    echo $messages[$_GET['success']] ?? 'تمت العملية بنجاح';
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stats-card blue">
                        <div class="stats-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                        <p class="text-muted mb-0">إجمالي العملاء</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card green">
                        <h3 class="mb-0"><?= number_format($stats['active']) ?></h3>
                        <p class="text-muted mb-0">عملاء نشطون</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card orange">
                        <h3 class="mb-0"><?= number_format($stats['inactive']) ?></h3>
                        <p class="text-muted mb-0">عملاء معطلون</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card red">
                        <h3 class="mb-0"><?= number_format($stats['with_branches']) ?></h3>
                        <p class="text-muted mb-0">عملاء بفروع</p>
                    </div>
                </div>
            </div>

            <!-- البحث والفلتر -->
            <div class="card mb-4" style="border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="ابحث عن عميل..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select name="region" class="form-control">
                                <option value="">كل المناطق</option>
                                <?php foreach($regions as $region): ?>
                                    <option value="<?= $region['id'] ?>" <?= $region_filter == $region['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($region['region_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control">
                                <option value="">كل الحالات</option>
                                <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>نشط</option>
                                <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>معطل</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="type" class="form-control">
                                <option value="">كل الأنواع</option>
                                <option value="company" <?= $type_filter == 'company' ? 'selected' : '' ?>>شركة</option>
                                <option value="large_institution" <?= $type_filter == 'large_institution' ? 'selected' : '' ?>>مؤسسة كبيرة</option>
                                <option value="individual_institution" <?= $type_filter == 'individual_institution' ? 'selected' : '' ?>>مؤسسة فردية</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> تطبيق
                            </button>
                        </div>
                    </form>
                    
                    <?php if($search || $region_filter || $status_filter || $type_filter): ?>
                        <div class="mt-3">
                            <a href="customers.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times"></i> مسح الفلتر
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- قائمة العملاء -->
            <?php if(empty($customers)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">لا توجد نتائج</h4>
                    <p class="text-muted">
                        <?php if($search || $region_filter || $status_filter): ?>
                            لم يتم العثور على عملاء مطابقين لمعايير البحث
                        <?php else: ?>
                            لم يتم إضافة أي عملاء بعد
                        <?php endif; ?>
                    </p>
                    <a href="add_customer.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus"></i> إضافة أول عميل
                    </a>
                </div>
            <?php else: ?>
                <div class="mb-3">
                    <small class="text-muted">عرض <?= count($customers) ?> عميل</small>
                </div>

                <?php foreach($customers as $customer): ?>
                    <div class="customer-card" onclick="location.href='view_customer.php?id=<?= $customer['id'] ?>'">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-1">
                                    <i class="fas fa-building text-primary"></i>
                                    <a href="view_customer.php?id=<?= $customer['id'] ?>" class="customer-name" onclick="event.stopPropagation()">
                                        <?= htmlspecialchars($customer['company_name']) ?>
                                    </a>
                                </h5>
                                <div class="d-flex gap-2 flex-wrap mb-2">
                                    <?php
                                    $types = [
                                        'company' => 'شركة',
                                        'large_institution' => 'مؤسسة كبيرة',
                                        'individual_institution' => 'مؤسسة فردية'
                                    ];
                                    ?>
                                    <span class="badge bg-info"><?= $types[$customer['company_type']] ?? '' ?></span>
                                    
                                    <?php if($customer['status'] == 'active'): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">معطل</span>
                                    <?php endif; ?>
                                    
                                    <?php if($customer['has_branches']): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-building"></i> لديه فروع
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($customer['owner_name']) ?>
                                        </small>
                                    </div>
                                    <?php if($customer['phone']): ?>
                                        <div class="col-md-4">
                                            <small class="text-muted">
                                                <i class="fas fa-phone"></i>
                                                <a href="tel:<?= $customer['phone'] ?>" onclick="event.stopPropagation()">
                                                    <?= htmlspecialchars($customer['phone']) ?>
                                                </a>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if($customer['region_name']): ?>
                                        <div class="col-md-4">
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($customer['region_name']) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="d-flex gap-2 justify-content-end flex-wrap">
                                    <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); location.href='view_customer.php?id=<?= $customer['id'] ?>'">
                                        <i class="fas fa-eye"></i> عرض
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="event.stopPropagation(); location.href='edit_customer.php?id=<?= $customer['id'] ?>'">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteCustomer(<?= $customer['id'] ?>, '<?= htmlspecialchars($customer['company_name']) ?>')">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteCustomer(id, name) {
            if(confirm(`هل أنت متأكد من حذف العميل "${name}"?\n\nتحذير: سيتم حذف جميع البيانات المرتبطة به:\n- الفروع\n- السندات الرسمية\n- الفواتير\n- المدفوعات\n- الزيارات\n- المقاسات\n\nهذا الإجراء لا يمكن التراجع عنه!`)) {
                if(confirm('تأكيد نهائي: هل أنت متأكد 100% من حذف العميل وجميع بياناته؟')) {
                    window.location.href = `customer_action.php?delete=${id}`;
                }
            }
        }
    </script>
</body>
</html>