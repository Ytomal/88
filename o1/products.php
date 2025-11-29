<?php
require_once 'config.php';
checkLogin();

// معالجة إضافة منتج
if(isset($_POST['add_product'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO products 
            (product_name, product_type, brand, size_description, model, origin_country, 
             unit_price, quantity_in_stock, minimum_stock, barcode, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['product_name'],
            $_POST['product_type'],
            $_POST['brand'],
            $_POST['size_description'],
            $_POST['model'],
            $_POST['origin_country'],
            $_POST['unit_price'],
            $_POST['quantity_in_stock'],
            $_POST['minimum_stock'],
            $_POST['barcode'],
            $_POST['notes'],
            $_POST['status']
        ]);
        
        logActivity('إضافة منتج', "تم إضافة المنتج: {$_POST['product_name']}");
        header("Location: products.php?success=added");
        exit;
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// معالجة التعديل
if(isset($_POST['update_product'])) {
    try {
        $stmt = $pdo->prepare("UPDATE products SET 
            product_name=?, product_type=?, brand=?, size_description=?, model=?, origin_country=?,
            unit_price=?, quantity_in_stock=?, minimum_stock=?, barcode=?, notes=?, status=?
            WHERE id=?");
        
        $stmt->execute([
            $_POST['product_name'],
            $_POST['product_type'],
            $_POST['brand'],
            $_POST['size_description'],
            $_POST['model'],
            $_POST['origin_country'],
            $_POST['unit_price'],
            $_POST['quantity_in_stock'],
            $_POST['minimum_stock'],
            $_POST['barcode'],
            $_POST['notes'],
            $_POST['status'],
            $_POST['product_id']
        ]);
        
        header("Location: products.php?success=updated");
        exit;
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// معالجة الحذف
if(isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: products.php?success=deleted");
        exit;
    } catch(Exception $e) {
        $error = "لا يمكن حذف المنتج لأنه مرتبط بفواتير";
    }
}

// البحث والفلتر
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$brand_filter = $_GET['brand'] ?? '';

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if($search) {
    $sql .= " AND (product_name LIKE ? OR size_description LIKE ? OR barcode LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if($type_filter) {
    $sql .= " AND product_type = ?";
    $params[] = $type_filter;
}

if($brand_filter) {
    $sql .= " AND brand = ?";
    $params[] = $brand_filter;
}

$sql .= " ORDER BY product_type, brand, product_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// جلب الماركات للفلتر
$brands = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL ORDER BY brand")->fetchAll();

// إحصائيات
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'tires' => $pdo->query("SELECT COUNT(*) FROM products WHERE product_type='tire'")->fetchColumn(),
    'batteries' => $pdo->query("SELECT COUNT(*) FROM products WHERE product_type='battery'")->fetchColumn(),
    'low_stock' => $pdo->query("SELECT COUNT(*) FROM products WHERE quantity_in_stock <= minimum_stock")->fetchColumn()
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المنتجات</title>
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
        .product-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-right: 4px solid #667eea;
            transition: all 0.3s;
        }
        .product-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .product-card.low-stock {
            border-right-color: #dc3545;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
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
                    <h2><i class="fas fa-box text-primary"></i> إدارة المنتجات</h2>
                    <p class="text-muted">الكفرات، البطاريات والإكسسوارات</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus"></i> إضافة منتج جديد
                </button>
            </div>

            <!-- Alerts -->
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    تمت العملية بنجاح!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-box fa-2x text-primary mb-2"></i>
                        <h3><?= $stats['total'] ?></h3>
                        <p class="text-muted mb-0">إجمالي المنتجات</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-circle fa-2x text-success mb-2"></i>
                        <h3><?= $stats['tires'] ?></h3>
                        <p class="text-muted mb-0">الكفرات</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-car-battery fa-2x text-warning mb-2"></i>
                        <h3><?= $stats['batteries'] ?></h3>
                        <p class="text-muted mb-0">البطاريات</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                        <h3><?= $stats['low_stock'] ?></h3>
                        <p class="text-muted mb-0">مخزون منخفض</p>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="بحث..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="type" class="form-control">
                                <option value="">كل الأنواع</option>
                                <option value="tire" <?= $type_filter=='tire'?'selected':'' ?>>كفرات</option>
                                <option value="battery" <?= $type_filter=='battery'?'selected':'' ?>>بطاريات</option>
                                <option value="slastic" <?= $type_filter=='slastic'?'selected':'' ?>>سلاستك</option>
                                <option value="oil" <?= $type_filter=='oil'?'selected':'' ?>>زيوت</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="brand" class="form-control">
                                <option value="">كل الماركات</option>
                                <?php foreach($brands as $brand): ?>
                                    <option value="<?= $brand['brand'] ?>" <?= $brand_filter==$brand['brand']?'selected':'' ?>>
                                        <?= htmlspecialchars($brand['brand']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">بحث</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Products List -->
            <?php if(empty($products)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                    <h4>لا توجد منتجات</h4>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addModal">
                        إضافة أول منتج
                    </button>
                </div>
            <?php else: ?>
                <?php foreach($products as $product): ?>
                    <div class="product-card <?= $product['quantity_in_stock'] <= $product['minimum_stock'] ? 'low-stock' : '' ?>">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5><?= htmlspecialchars($product['product_name']) ?></h5>
                                <div class="d-flex gap-2 flex-wrap mb-2">
                                    <span class="badge bg-primary">
                                        <?php
                                        $types = ['tire'=>'كفر', 'battery'=>'بطارية', 'slastic'=>'سلاستك', 'oil'=>'زيت'];
                                        echo $types[$product['product_type']] ?? $product['product_type'];
                                        ?>
                                    </span>
                                    <?php if($product['brand']): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($product['brand']) ?></span>
                                    <?php endif; ?>
                                    <?php if($product['size_description']): ?>
                                        <span class="badge bg-info"><?= htmlspecialchars($product['size_description']) ?></span>
                                    <?php endif; ?>
                                    <?php if($product['status']=='inactive'): ?>
                                        <span class="badge bg-danger">معطل</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?php if($product['barcode']): ?>
                                        <i class="fas fa-barcode"></i> <?= htmlspecialchars($product['barcode']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-primary mb-0"><?= number_format($product['unit_price'], 2) ?> ريال</h4>
                                    <small class="text-muted">السعر</small>
                                </div>
                                <div class="text-center mt-2">
                                    <h5 class="mb-0 <?= $product['quantity_in_stock'] <= $product['minimum_stock'] ? 'text-danger' : '' ?>">
                                        <?= $product['quantity_in_stock'] ?> قطعة
                                    </h5>
                                    <small class="text-muted">المخزون (الحد الأدنى: <?= $product['minimum_stock'] ?>)</small>
                                </div>
                            </div>
                            <div class="col-md-3 text-end">
                                <button class="btn btn-sm btn-warning" onclick="editProduct(<?= htmlspecialchars(json_encode($product)) ?>)">
                                    <i class="fas fa-edit"></i> تعديل
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?= $product['id'] ?>)">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </div>
                        </div>
                        <?php if($product['notes']): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($product['notes']) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة منتج جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>اسم المنتج *</label>
                                <input type="text" name="product_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>النوع *</label>
                                <select name="product_type" class="form-control" required>
                                    <option value="tire">كفر</option>
                                    <option value="battery">بطارية</option>
                                    <option value="slastic">سلاستك</option>
                                    <option value="oil">زيت</option>
                                    <option value="accessory">إكسسوار</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>الماركة</label>
                                <input type="text" name="brand" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>المقاس</label>
                                <input type="text" name="size_description" class="form-control" placeholder="مثل: 195/65 R15">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الموديل</label>
                                <input type="text" name="model" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>بلد المنشأ</label>
                                <input type="text" name="origin_country" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>السعر *</label>
                                <input type="number" step="0.01" name="unit_price" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الباركود</label>
                                <input type="text" name="barcode" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>الكمية في المخزون</label>
                                <input type="number" name="quantity_in_stock" class="form-control" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الحد الأدنى للمخزون</label>
                                <input type="number" name="minimum_stock" class="form-control" value="10">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الحالة</label>
                                <select name="status" class="form-control">
                                    <option value="active">نشط</option>
                                    <option value="inactive">معطل</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_product" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل المنتج</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <input type="hidden" name="product_id" id="edit_id">
                    <div class="modal-body">
                        <!-- نفس الحقول مع id مختلفة -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>اسم المنتج *</label>
                                <input type="text" name="product_name" id="edit_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>النوع *</label>
                                <select name="product_type" id="edit_type" class="form-control" required>
                                    <option value="tire">كفر</option>
                                    <option value="battery">بطارية</option>
                                    <option value="slastic">سلاستك</option>
                                    <option value="oil">زيت</option>
                                    <option value="accessory">إكسسوار</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>الماركة</label>
                                <input type="text" name="brand" id="edit_brand" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>المقاس</label>
                                <input type="text" name="size_description" id="edit_size" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الموديل</label>
                                <input type="text" name="model" id="edit_model" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>بلد المنشأ</label>
                                <input type="text" name="origin_country" id="edit_origin" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>السعر *</label>
                                <input type="number" step="0.01" name="unit_price" id="edit_price" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الباركود</label>
                                <input type="text" name="barcode" id="edit_barcode" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>الكمية في المخزون</label>
                                <input type="number" name="quantity_in_stock" id="edit_qty" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الحد الأدنى للمخزون</label>
                                <input type="number" name="minimum_stock" id="edit_min" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الحالة</label>
                                <select name="status" id="edit_status" class="form-control">
                                    <option value="active">نشط</option>
                                    <option value="inactive">معطل</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>ملاحظات</label>
                            <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="update_product" class="btn btn-primary">تحديث</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editProduct(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.product_name;
            document.getElementById('edit_type').value = product.product_type;
            document.getElementById('edit_brand').value = product.brand || '';
            document.getElementById('edit_size').value = product.size_description || '';
            document.getElementById('edit_model').value = product.model || '';
            document.getElementById('edit_origin').value = product.origin_country || '';
            document.getElementById('edit_price').value = product.unit_price;
            document.getElementById('edit_barcode').value = product.barcode || '';
            document.getElementById('edit_qty').value = product.quantity_in_stock;
            document.getElementById('edit_min').value = product.minimum_stock;
            document.getElementById('edit_status').value = product.status;
            document.getElementById('edit_notes').value = product.notes || '';
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteProduct(id) {
            if(confirm('هل أنت متأكد من حذف هذا المنتج؟')) {
                window.location.href = 'products.php?delete=' + id;
            }
        }
    </script>
</body>
</html>