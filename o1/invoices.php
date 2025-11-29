<?php
require_once 'config.php';
checkLogin();

// معالجة إضافة فاتورة
if(isset($_POST['add_invoice'])) {
    try {
        $pdo->beginTransaction();
        
        // حساب الإجمالي
        $total = 0;
        if(isset($_POST['products']) && is_array($_POST['products'])) {
            foreach($_POST['products'] as $product_id => $qty) {
                if($qty > 0) {
                    $stmt = $pdo->prepare("SELECT unit_price FROM products WHERE id=?");
                    $stmt->execute([$product_id]);
                    $price = $stmt->fetchColumn();
                    if($price) {
                        $total += ($price * $qty);
                    }
                }
            }
        }
        
        // التحقق من وجود منتجات
        if($total <= 0) {
            throw new Exception('يجب إضافة منتج واحد على الأقل للفاتورة');
        }
        
        // إدراج الفاتورة
        $stmt = $pdo->prepare("INSERT INTO invoices 
            (customer_id, branch_id, my_branch_id, sales_rep_id, invoice_number, invoice_date, 
             total_amount, remaining_amount, status, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?)");
        
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['customer_branch_id'] ?: null,
            $_POST['my_branch_id'] ?: null,
            $_POST['sales_rep_id'] ?: null,
            $_POST['invoice_number'],
            $_POST['invoice_date'],
            $total,
            $total,
            $_POST['notes'] ?? ''
        ]);
        
        $invoice_id = $pdo->lastInsertId();
        
        // إدراج تفاصيل الفاتورة
        $stmt_item = $pdo->prepare("INSERT INTO invoice_items 
            (invoice_id, product_id, quantity, unit_price, total_price) 
            VALUES (?, ?, ?, ?, ?)");
        
        $stmt_update = $pdo->prepare("UPDATE products SET quantity_in_stock = quantity_in_stock - ? WHERE id = ?");
        
        foreach($_POST['products'] as $product_id => $qty) {
            if($qty > 0) {
                $stmt = $pdo->prepare("SELECT unit_price, quantity_in_stock FROM products WHERE id=?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if(!$product) {
                    throw new Exception("المنتج رقم $product_id غير موجود");
                }
                
                if($product['quantity_in_stock'] < $qty) {
                    throw new Exception("الكمية المطلوبة غير متوفرة في المخزون للمنتج رقم $product_id");
                }
                
                $item_total = $product['unit_price'] * $qty;
                
                $stmt_item->execute([$invoice_id, $product_id, $qty, $product['unit_price'], $item_total]);
                $stmt_update->execute([$qty, $product_id]);
            }
        }
        
        $pdo->commit();
        logActivity('إضافة فاتورة', "فاتورة رقم: {$_POST['invoice_number']}");
        header("Location: invoices.php?success=added");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// جلب الفواتير
$invoices = $pdo->query("SELECT i.*, c.company_name, c.owner_name,
                         mb.branch_name as my_branch_name, 
                         sr.name as sales_rep_name,
                         cb.branch_name as customer_branch_name
                         FROM invoices i
                         JOIN customers c ON i.customer_id = c.id
                         LEFT JOIN my_branches mb ON i.my_branch_id = mb.id
                         LEFT JOIN sales_representatives sr ON i.sales_rep_id = sr.id
                         LEFT JOIN customer_branches cb ON i.branch_id = cb.id
                         ORDER BY i.invoice_date DESC, i.id DESC")->fetchAll();

// جلب البيانات للقوائم
$customers = $pdo->query("SELECT id, company_name, owner_name FROM customers WHERE status='active' ORDER BY company_name")->fetchAll();
$my_branches = $pdo->query("SELECT id, branch_name FROM my_branches WHERE status='active' ORDER BY branch_name")->fetchAll();
$sales_reps = $pdo->query("SELECT id, name, department FROM sales_representatives WHERE status='active' ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY product_type, brand, product_name")->fetchAll();

// إحصائيات
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn(),
    'total_amount' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices")->fetchColumn(),
    'paid_amount' => $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices")->fetchColumn(),
    'remaining' => $pdo->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM invoices")->fetchColumn()
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الفواتير</title>
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
        }
        .invoice-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        /* إصلاح Modal */
        .modal-backdrop {
            z-index: 1040;
        }
        
        .modal {
            z-index: 1050;
        }
        
        .modal.show {
            display: block !important;
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
        
        .search-result-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-file-invoice text-primary"></i> إدارة الفواتير</h2>
                    <p class="text-muted">فواتير المبيعات والمشتريات</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
                        <i class="fas fa-plus"></i> فاتورة جديدة
                    </button>
                    <a href="payments.php" class="btn btn-success">
                        <i class="fas fa-money-bill-wave"></i> الدفعات
                    </a>
                </div>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    تمت إضافة الفاتورة بنجاح!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <strong>خطأ:</strong> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-file-invoice fa-2x text-primary mb-2"></i>
                            <h3><?= $stats['total'] ?></h3>
                            <p class="text-muted mb-0">إجمالي الفواتير</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-money-bill-wave fa-2x text-info mb-2"></i>
                            <h3><?= number_format($stats['total_amount'], 0) ?></h3>
                            <p class="text-muted mb-0">إجمالي المبيعات (ريال)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h3><?= number_format($stats['paid_amount'], 0) ?></h3>
                            <p class="text-muted mb-0">المدفوع (ريال)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-hourglass-half fa-2x text-danger mb-2"></i>
                            <h3><?= number_format($stats['remaining'], 0) ?></h3>
                            <p class="text-muted mb-0">المتبقي (ريال)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- قائمة الفواتير -->
            <?php if(empty($invoices)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
                    <h4>لا توجد فواتير</h4>
                    <button class="btn btn-primary mt-3" type="button" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
                        إضافة أول فاتورة
                    </button>
                </div>
            <?php else: ?>
                <?php foreach($invoices as $inv): ?>
                    <div class="invoice-card">
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
                                <strong><?= htmlspecialchars($inv['company_name']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php if($inv['customer_branch_name']): ?>
                                        <i class="fas fa-building"></i> <?= htmlspecialchars($inv['customer_branch_name']) ?>
                                    <?php endif; ?>
                                </small>
                                <br>
                                <small class="text-muted">
                                    <?php if($inv['my_branch_name']): ?>
                                        <i class="fas fa-store"></i> فرع: <?= htmlspecialchars($inv['my_branch_name']) ?>
                                    <?php endif; ?>
                                    <?php if($inv['sales_rep_name']): ?>
                                        | <i class="fas fa-user-tie"></i> <?= htmlspecialchars($inv['sales_rep_name']) ?>
                                    <?php endif; ?>
                                </small>
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
                                <a href="invoice_details.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> عرض
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Invoice Modal -->
    <div class="modal fade" id="addInvoiceModal" tabindex="-1" aria-labelledby="addInvoiceModalLabel">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addInvoiceModalLabel">إضافة فاتورة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="invoiceForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">العميل *</label>
                                <select name="customer_id" id="customer_select" class="form-control" required>
                                    <option value="">اختر العميل</option>
                                    <?php foreach($customers as $cust): ?>
                                        <option value="<?= $cust['id'] ?>">
                                            <?= htmlspecialchars($cust['company_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">فرع العميل</label>
                                <select name="customer_branch_id" id="customer_branches" class="form-control">
                                    <option value="">بدون فرع</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">رقم الفاتورة *</label>
                                <input type="text" name="invoice_number" class="form-control" 
                                       value="INV-<?= date('YmdHis') ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">تاريخ الفاتورة *</label>
                                <input type="date" name="invoice_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">الإجمالي</label>
                                <input type="text" id="total_display" class="form-control" readonly value="0.00 ريال">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">فرعك (من يبيع)</label>
                                <select name="my_branch_id" class="form-control">
                                    <option value="">اختر الفرع</option>
                                    <?php foreach($my_branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المندوب/الموظف</label>
                                <select name="sales_rep_id" class="form-control">
                                    <option value="">اختر المندوب</option>
                                    <?php foreach($sales_reps as $rep): ?>
                                        <?php
                                        $dept_names = ['sales_rep'=>'مندوب', 'branch'=>'فرع', 'management'=>'إدارة'];
                                        ?>
                                        <option value="<?= $rep['id'] ?>">
                                            <?= htmlspecialchars($rep['name']) ?> (<?= $dept_names[$rep['department']] ?? '' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <h5 class="mt-4 mb-3">المنتجات</h5>
                        
                        <!-- بحث المنتجات -->
                        <div class="mb-3 position-relative">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" 
                                       id="product_search" 
                                       class="form-control" 
                                       placeholder="ابحث عن المنتج (الاسم، الماركة، المقاس، الباركود)..." 
                                       autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <!-- نتائج البحث -->
                            <div id="search_results"></div>
                        </div>

                        <!-- المنتجات المضافة للفاتورة -->
                        <div style="background: #f8f9fa; border-radius: 10px; padding: 15px; min-height: 200px;">
                            <h6 class="text-primary mb-3">المنتجات المضافة للفاتورة</h6>
                            <div id="selected_products">
                                <p class="text-center text-muted">لا توجد منتجات. استخدم البحث أعلاه لإضافة منتجات</p>
                            </div>
                        </div>

                        <!-- البيانات المخفية للإرسال -->
                        <div id="hidden_products"></div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_invoice" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ الفاتورة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // بيانات المنتجات
        const products = <?= json_encode($products) ?>;
        let selectedProducts = {};
        let searchTimeout;

        // البحث في المنتجات
        document.getElementById('product_search').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim().toLowerCase();
            
            if(query.length < 2) {
                document.getElementById('search_results').style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                const results = products.filter(p => {
                    return p.product_name.toLowerCase().includes(query) ||
                           (p.brand && p.brand.toLowerCase().includes(query)) ||
                           (p.size_description && p.size_description.toLowerCase().includes(query)) ||
                           (p.barcode && p.barcode.toLowerCase().includes(query));
                });
                
                displaySearchResults(results);
            }, 300);
        });

        function displaySearchResults(results) {
            const container = document.getElementById('search_results');
            
            if(results.length === 0) {
                container.innerHTML = '<div class="p-3 text-center text-muted">لا توجد نتائج</div>';
                container.style.display = 'block';
                return;
            }
            
            let html = '';
            const typeNames = {tire: '🔵 كفر', battery: '🔋 بطارية', slastic: '⚙️ سلاستك', oil: '🛢️ زيت'};
            
            results.forEach(product => {
                const isSelected = selectedProducts[product.id];
                const opacity = isSelected ? 'style="opacity: 0.5;"' : '';
                html += `
                    <div class="search-result-item" ${opacity} onclick="addProduct(${product.id})">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${product.product_name}</strong>
                                <span class="badge bg-primary ms-2">${typeNames[product.product_type] || product.product_type}</span>
                                ${product.brand ? `<span class="badge bg-secondary">${product.brand}</span>` : ''}
                                ${product.size_description ? `<span class="badge bg-info">${product.size_description}</span>` : ''}
                                <br>
                                <small class="text-muted">
                                    السعر: <strong>${parseFloat(product.unit_price).toFixed(2)}</strong> ريال |
                                    المخزون: <strong class="${product.quantity_in_stock <= 5 ? 'text-danger' : 'text-success'}">${product.quantity_in_stock}</strong> قطعة
                                    ${product.barcode ? `| الباركود: ${product.barcode}` : ''}
                                </small>
                            </div>
                            ${isSelected ? '<span class="badge bg-success">مضاف ✓</span>' : ''}
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            container.style.display = 'block';
        }

        function addProduct(productId) {
            const product = products.find(p => p.id == productId);
            if(!product) return;
            
            if(selectedProducts[productId]) {
                selectedProducts[productId].quantity++;
            } else {
                selectedProducts[productId] = {
                    ...product,
                    quantity: 1
                };
            }
            
            updateSelectedProducts();
            clearSearch();
        }

        function updateSelectedProducts() {
            const container = document.getElementById('selected_products');
            const hiddenContainer = document.getElementById('hidden_products');
            
            if(Object.keys(selectedProducts).length === 0) {
                container.innerHTML = '<p class="text-center text-muted">لا توجد منتجات. استخدم البحث أعلاه لإضافة منتجات</p>';
                hiddenContainer.innerHTML = '';
                calculateTotal();
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table table-sm">';
            html += '<thead><tr><th>المنتج</th><th width="100">الكمية</th><th width="100">السعر</th><th width="100">الإجمالي</th><th width="50"></th></tr></thead><tbody>';
            
            let hiddenHtml = '';
            
            Object.values(selectedProducts).forEach(product => {
                const total = product.quantity * product.unit_price;
                const typeNames = {tire: 'كفر', battery: 'بطارية', slastic: 'سلاستك', oil: 'زيت'};
                
                html += `
                    <tr>
                        <td>
                            <strong>${product.product_name}</strong><br>
                            <small class="text-muted">
                                ${typeNames[product.product_type] || product.product_type}
                                ${product.brand ? ` - ${product.brand}` : ''}
                                ${product.size_description ? ` - ${product.size_description}` : ''}
                            </small>
                        </td>
                        <td>
                            <input type="number" 
                                   class="form-control form-control-sm" 
                                   value="${product.quantity}" 
                                   min="1" 
                                   max="${product.quantity_in_stock}"
                                   onchange="updateQuantity(${product.id}, this.value)"
                                   style="width: 80px;">
                        </td>
                        <td>${parseFloat(product.unit_price).toFixed(2)}</td>
                        <td><strong>${total.toFixed(2)}</strong></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeProduct(${product.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                
                hiddenHtml += `<input type="hidden" name="products[${product.id}]" value="${product.quantity}">`;
            });
            
            html += '</tbody></table></div>';
            
            container.innerHTML = html;
            hiddenContainer.innerHTML = hiddenHtml;
            calculateTotal();
        }

        function updateQuantity(productId, quantity) {
            quantity = parseInt(quantity);
            if(quantity <= 0) {
                removeProduct(productId);
                return;
            }
            
            if(selectedProducts[productId]) {
                const maxQty = products.find(p => p.id == productId).quantity_in_stock;
                selectedProducts[productId].quantity = Math.min(quantity, maxQty);
                updateSelectedProducts();
            }
        }

        function removeProduct(productId) {
            delete selectedProducts[productId];
            updateSelectedProducts();
        }

        function clearSearch() {
            document.getElementById('product_search').value = '';
            document.getElementById('search_results').style.display = 'none';
        }

        function calculateTotal() {
            let total = 0;
            Object.values(selectedProducts).forEach(product => {
                total += (product.quantity * product.unit_price);
            });
            document.getElementById('total_display').value = total.toFixed(2) + ' ريال';
        }

       // تحميل فروع العميل
        document.getElementById('customer_select').addEventListener('change', function() {
            const customerId = this.value;
            const branchSelect = document.getElementById('customer_branches');
            
            if(!customerId) {
                branchSelect.innerHTML = '<option value="">بدون فرع</option>';
                return;
            }
            
            // تحميل الفروع من السيرفر
            fetch('get_customer_branches.php?customer_id=' + customerId)
                .then(r => r.json())
                .then(data => {
                    let html = '<option value="">بدون فرع</option>';
                    data.forEach(b => {
                        html += `<option value="${b.id}">${b.branch_name}</option>`;
                    });
                    branchSelect.innerHTML = html;
                })
                .catch(err => {
                    console.error('خطأ في تحميل الفروع:', err);
                    branchSelect.innerHTML = '<option value="">بدون فرع</option>';
                });
        });

        // إخفاء نتائج البحث عند النقر خارجها
        document.addEventListener('click', function(e) {
            const search = document.getElementById('product_search');
            const results = document.getElementById('search_results');
            if(!search.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });

        // التحقق قبل الإرسال
        document.getElementById('invoiceForm').addEventListener('submit', function(e) {
            if(Object.keys(selectedProducts).length === 0) {
                e.preventDefault();
                alert('يجب إضافة منتج واحد على الأقل للفاتورة');
                return false;
            }
        });

        // Barcode Scanner Support
        let barcodeBuffer = '';
        let barcodeTimeout;
        
        document.addEventListener('keypress', function(e) {
            // تجاهل إذا كان التركيز على حقل إدخال
            if(e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            clearTimeout(barcodeTimeout);
            
            if(e.key === 'Enter') {
                if(barcodeBuffer.length > 3) {
                    searchByBarcode(barcodeBuffer);
                }
                barcodeBuffer = '';
            } else {
                barcodeBuffer += e.key;
                barcodeTimeout = setTimeout(() => {
                    barcodeBuffer = '';
                }, 100);
            }
        });

        function searchByBarcode(barcode) {
            const product = products.find(p => p.barcode === barcode);
            if(product) {
                addProduct(product.id);
                
                // إظهار إشعار
                const toast = document.createElement('div');
                toast.className = 'alert alert-success position-fixed top-0 start-50 translate-middle-x mt-3';
                toast.style.zIndex = '9999';
                toast.innerHTML = `<i class="fas fa-check"></i> تم إضافة: ${product.product_name}`;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
            }
        }
    </script>
</body>
</html>