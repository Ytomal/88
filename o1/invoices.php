<?php
require_once 'config.php';

// معالجة إضافة فاتورة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_invoice'])) {
    $customer_id = intval($_POST['customer_id']);
    $invoice_number = clean_input($_POST['invoice_number']);
    $invoice_date = clean_input($_POST['invoice_date']);
    $notes = clean_input($_POST['notes']);
    
    // حساب المبلغ الإجمالي من المنتجات
    $total_amount = 0;
    if (isset($_POST['products']) && is_array($_POST['products'])) {
        foreach ($_POST['products'] as $product_id => $quantity) {
            if ($quantity > 0) {
                $product = $conn->query("SELECT price FROM products WHERE id = $product_id")->fetch_assoc();
                if ($product) {
                    $total_amount += $product['price'] * $quantity;
                }
            }
        }
    }
    
    // إدراج الفاتورة
    $insert_sql = "INSERT INTO invoices (customer_id, invoice_number, invoice_date, total_amount, remaining_amount, notes) 
                   VALUES ($customer_id, '$invoice_number', '$invoice_date', $total_amount, $total_amount, '$notes')";
    
    if ($conn->query($insert_sql)) {
        $invoice_id = $conn->insert_id;
        
        // إدراج تفاصيل الفاتورة
        foreach ($_POST['products'] as $product_id => $quantity) {
            if ($quantity > 0) {
                $product_id = intval($product_id);
                $quantity = intval($quantity);
                $product = $conn->query("SELECT price FROM products WHERE id = $product_id")->fetch_assoc();
                $unit_price = $product['price'];
                $total_price = $unit_price * $quantity;
                
                $conn->query("INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, total_price) 
                             VALUES ($invoice_id, $product_id, $quantity, $unit_price, $total_price)");
            }
        }
        
        $success_message = "تم إضافة الفاتورة بنجاح!";
    }
}

// جلب جميع الفواتير
$invoices_sql = "SELECT i.*, c.company_name, c.owner_name 
                 FROM invoices i 
                 JOIN customers c ON i.customer_id = c.id 
                 ORDER BY i.invoice_date DESC";
$invoices = $conn->query($invoices_sql);

// جلب العملاء للقائمة المنسدلة
$customers = $conn->query("SELECT id, company_name, owner_name FROM customers ORDER BY company_name");

// جلب المنتجات
$products = $conn->query("SELECT * FROM products ORDER BY product_type, product_name");
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المشتريات والفواتير</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>💰 المشتريات والفواتير</h1>
            <p>إدارة فواتير الإطارات والبطاريات والسلاستك</p>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <a href="index.php" class="btn btn-info">⬅️ العودة للصفحة الرئيسية</a>
            <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='block'">
                ➕ إضافة فاتورة جديدة
            </button>
            <a href="payments.php" class="btn btn-success">💵 إدارة الدفعات</a>
        </div>

        <!-- إحصائيات الفواتير -->
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <?php
            $total_invoices_count = $conn->query("SELECT COUNT(*) as count FROM invoices")->fetch_assoc()['count'];
            $total_sales = $conn->query("SELECT IFNULL(SUM(total_amount), 0) as total FROM invoices")->fetch_assoc()['total'];
            $total_paid = $conn->query("SELECT IFNULL(SUM(paid_amount), 0) as total FROM invoices")->fetch_assoc()['total'];
            $total_unpaid = $conn->query("SELECT IFNULL(SUM(remaining_amount), 0) as total FROM invoices")->fetch_assoc()['total'];
            $paid_invoices = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'paid'")->fetch_assoc()['count'];
            $unpaid_invoices = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'unpaid'")->fetch_assoc()['count'];
            ?>
            
            <div class="stats-card">
                <h3>إجمالي الفواتير</h3>
                <div class="number"><?php echo $total_invoices_count; ?></div>
            </div>
            
            <div class="stats-card">
                <h3>إجمالي المبيعات</h3>
                <div class="number"><?php echo number_format($total_sales, 2); ?> ريال</div>
            </div>
            
            <div class="stats-card">
                <h3>المبالغ المدفوعة</h3>
                <div class="number" style="color: #28a745;"><?php echo number_format($total_paid, 2); ?> ريال</div>
            </div>
            
            <div class="stats-card">
                <h3>المبالغ المستحقة</h3>
                <div class="number" style="color: #dc3545;"><?php echo number_format($total_unpaid, 2); ?> ريال</div>
            </div>
            
            <div class="stats-card">
                <h3>فواتير مدفوعة</h3>
                <div class="number" style="color: #28a745;"><?php echo $paid_invoices; ?></div>
            </div>
            
            <div class="stats-card">
                <h3>فواتير غير مدفوعة</h3>
                <div class="number" style="color: #dc3545;"><?php echo $unpaid_invoices; ?></div>
            </div>
        </div>

        <div class="card">
            <h2>قائمة الفواتير</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>العميل</th>
                            <th>التاريخ</th>
                            <th>المبلغ الإجمالي</th>
                            <th>المبلغ المدفوع</th>
                            <th>المبلغ المتبقي</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($invoices->num_rows > 0): ?>
                            <?php while($invoice = $invoices->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($invoice['company_name']); ?> - <?php echo htmlspecialchars($invoice['owner_name']); ?></td>
                                    <td><?php echo $invoice['invoice_date']; ?></td>
                                    <td><?php echo number_format($invoice['total_amount'], 2); ?> ريال</td>
                                    <td style="color: #28a745;"><?php echo number_format($invoice['paid_amount'], 2); ?> ريال</td>
                                    <td style="color: #dc3545;"><?php echo number_format($invoice['remaining_amount'], 2); ?> ريال</td>
                                    <td>
                                        <?php
                                        $status_text = $invoice['status'] == 'paid' ? 'مدفوعة' : ($invoice['status'] == 'partial' ? 'دفع جزئي' : 'غير مدفوعة');
                                        $status_class = $invoice['status'] == 'paid' ? 'success' : ($invoice['status'] == 'partial' ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge badge-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td>
                                        <a href="invoice_details.php?id=<?php echo $invoice['id']; ?>" class="btn btn-info btn-sm">👁️ عرض</a>
                                        <a href="print_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-warning btn-sm" target="_blank">🖨️ طباعة</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">لا توجد فواتير</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer>
            <p>&copy; 2025 نظام إدارة العملاء - جميع الحقوق محفوظة</p>
        </footer>
    </div>

    <!-- مودال إضافة فاتورة -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
            <h2>إضافة فاتورة جديدة</h2>
            <form method="POST">
                <div class="form-group">
                    <label>اختر العميل *</label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">اختر العميل</option>
                        <?php
                        $customers->data_seek(0);
                        while($customer = $customers->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $customer['id']; ?>">
                                <?php echo htmlspecialchars($customer['company_name']); ?> - <?php echo htmlspecialchars($customer['owner_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>رقم الفاتورة *</label>
                    <input type="text" name="invoice_number" class="form-control" value="INV-<?php echo date('YmdHis'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>تاريخ الفاتورة *</label>
                    <input type="date" name="invoice_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <h3 style="margin-top: 30px; color: #667eea;">المنتجات</h3>
                <div style="max-height: 400px; overflow-y: auto; border: 2px solid #ddd; padding: 15px; border-radius: 8px;">
                    <?php
                    $products->data_seek(0);
                    $current_type = '';
                    while($product = $products->fetch_assoc()): 
                        if ($current_type != $product['product_type']) {
                            if ($current_type != '') echo '</div>';
                            $type_name = $product['product_type'] == 'tire' ? 'إطارات' : ($product['product_type'] == 'battery' ? 'بطاريات' : 'سلاستك');
                            echo '<h4 style="color: #764ba2; margin-top: 15px;">' . $type_name . '</h4><div>';
                            $current_type = $product['product_type'];
                        }
                    ?>
                        <div style="display: flex; align-items: center; margin-bottom: 10px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
                            <div style="flex: 1;">
                                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                <small>السعر: <?php echo number_format($product['price'], 2); ?> ريال</small>
                            </div>
                            <div style="width: 150px;">
                                <input type="number" name="products[<?php echo $product['id']; ?>]" class="form-control" placeholder="الكمية" min="0" value="0" style="text-align: center;">
                            </div>
                        </div>
                    <?php 
                    endwhile; 
                    echo '</div>';
                    ?>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label>ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" name="add_invoice" class="btn btn-primary">حفظ الفاتورة</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addModal').style.display='none'">إلغاء</button>
            </form>
        </div>
    </div>

    <script>
        window.onclick = function(event) {
            let modal = document.getElementById('addModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>