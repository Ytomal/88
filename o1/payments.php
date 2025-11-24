<?php
require_once 'config.php';

// معالجة إضافة دفعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $customer_id = intval($_POST['customer_id']);
    $payment_date = clean_input($_POST['payment_date']);
    $amount = floatval($_POST['amount']);
    $payment_method = clean_input($_POST['payment_method']);
    $notes = clean_input($_POST['notes']);
    
    // إدراج الدفعة
    $insert_sql = "INSERT INTO payments (customer_id, payment_date, amount, payment_method, notes) 
                   VALUES ($customer_id, '$payment_date', $amount, '$payment_method', '$notes')";
    
    if ($conn->query($insert_sql)) {
        $payment_id = $conn->insert_id;
        
        // ربط الدفعة بالفواتير
        $remaining_amount = $amount;
        
        if (isset($_POST['invoices']) && is_array($_POST['invoices'])) {
            foreach ($_POST['invoices'] as $invoice_id => $allocated) {
                $allocated = floatval($allocated);
                if ($allocated > 0) {
                    $invoice_id = intval($invoice_id);
                    
                    // إدراج الربط
                    $conn->query("INSERT INTO payment_invoice_link (payment_id, invoice_id, allocated_amount) 
                                 VALUES ($payment_id, $invoice_id, $allocated)");
                    
                    // تحديث الفاتورة
                    $invoice = $conn->query("SELECT paid_amount, total_amount FROM invoices WHERE id = $invoice_id")->fetch_assoc();
                    $new_paid = $invoice['paid_amount'] + $allocated;
                    $new_remaining = $invoice['total_amount'] - $new_paid;
                    
                    // تحديد حالة الفاتورة
                    $status = 'unpaid';
                    if ($new_remaining <= 0.01) {
                        $status = 'paid';
                        $new_remaining = 0;
                    } elseif ($new_paid > 0) {
                        $status = 'partial';
                    }
                    
                    $conn->query("UPDATE invoices SET 
                                 paid_amount = $new_paid, 
                                 remaining_amount = $new_remaining, 
                                 status = '$status' 
                                 WHERE id = $invoice_id");
                }
            }
        }
        
        $success_message = "تم إضافة الدفعة بنجاح!";
    }
}

// جلب جميع الدفعات
$payments_sql = "SELECT p.*, c.company_name, c.owner_name 
                 FROM payments p 
                 JOIN customers c ON p.customer_id = c.id 
                 ORDER BY p.payment_date DESC";
$payments = $conn->query($payments_sql);

// جلب العملاء
$customers = $conn->query("SELECT id, company_name, owner_name FROM customers ORDER BY company_name");
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الدفعات</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>💵 إدارة الدفعات</h1>
            <p>تسجيل وإدارة دفعات العملاء</p>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <a href="index.php" class="btn btn-info">⬅️ العودة للصفحة الرئيسية</a>
            <a href="invoices.php" class="btn btn-warning">💰 الفواتير</a>
            <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='block'">
                ➕ إضافة دفعة جديدة
            </button>
        </div>

        <!-- إحصائيات الدفعات -->
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
            <?php
            $total_payments_count = $conn->query("SELECT COUNT(*) as count FROM payments")->fetch_assoc()['count'];
            $total_payments = $conn->query("SELECT IFNULL(SUM(amount), 0) as total FROM payments")->fetch_assoc()['total'];
            $today_payments = $conn->query("SELECT IFNULL(SUM(amount), 0) as total FROM payments WHERE payment_date = CURDATE()")->fetch_assoc()['total'];
            $this_month_payments = $conn->query("SELECT IFNULL(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetch_assoc()['total'];
            ?>
            
            <div class="stats-card">
                <h3>إجمالي الدفعات</h3>
                <div class="number"><?php echo $total_payments_count; ?></div>
            </div>
            
            <div class="stats-card">
                <h3>إجمالي المبالغ المحصلة</h3>
                <div class="number"><?php echo number_format($total_payments, 2); ?> ريال</div>
            </div>
            
            <div class="stats-card">
                <h3>دفعات اليوم</h3>
                <div class="number" style="color: #28a745;"><?php echo number_format($today_payments, 2); ?> ريال</div>
            </div>
            
            <div class="stats-card">
                <h3>دفعات هذا الشهر</h3>
                <div class="number" style="color: #17a2b8;"><?php echo number_format($this_month_payments, 2); ?> ريال</div>
            </div>
        </div>

        <div class="card">
            <h2>قائمة الدفعات</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>العميل</th>
                            <th>المبلغ</th>
                            <th>طريقة الدفع</th>
                            <th>الفواتير المرتبطة</th>
                            <th>ملاحظات</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payments->num_rows > 0): ?>
                            <?php while($payment = $payments->fetch_assoc()): ?>
                                <?php
                                // جلب الفواتير المرتبطة
                                $linked_invoices = $conn->query("SELECT i.invoice_number, l.allocated_amount 
                                                                FROM payment_invoice_link l 
                                                                JOIN invoices i ON l.invoice_id = i.id 
                                                                WHERE l.payment_id = {$payment['id']}");
                                ?>
                                <tr>
                                    <td><?php echo $payment['id']; ?></td>
                                    <td><?php echo $payment['payment_date']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['company_name']); ?> - <?php echo htmlspecialchars($payment['owner_name']); ?></td>
                                    <td style="color: #28a745; font-weight: bold;"><?php echo number_format($payment['amount'], 2); ?> ريال</td>
                                    <td>
                                        <?php
                                        $method_text = [
                                            'cash' => 'نقداً',
                                            'bank_transfer' => 'حوالة بنكية',
                                            'check' => 'شيك',
                                            'other' => 'أخرى'
                                        ];
                                        ?>
                                        <span class="badge badge-info"><?php echo $method_text[$payment['payment_method']] ?? $payment['payment_method']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($linked_invoices->num_rows > 0): ?>
                                            <?php while($link = $linked_invoices->fetch_assoc()): ?>
                                                <span class="badge badge-success" style="display: block; margin: 2px 0;">
                                                    <?php echo htmlspecialchars($link['invoice_number']); ?>: 
                                                    <?php echo number_format($link['allocated_amount'], 2); ?> ريال
                                                </span>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <span class="badge badge-warning">لا توجد فواتير مرتبطة</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['notes']); ?></td>
                                    <td>
                                        <a href="payment_receipt.php?id=<?php echo $payment['id']; ?>" class="btn btn-info btn-sm" target="_blank">🖨️ طباعة</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">لا توجد دفعات</td>
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

    <!-- مودال إضافة دفعة -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
            <h2>إضافة دفعة جديدة</h2>
            <form method="POST" id="paymentForm">
                <div class="form-group">
                    <label>اختر العميل *</label>
                    <select name="customer_id" id="customer_select" class="form-control" required onchange="loadUnpaidInvoices(this.value)">
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
                    <label>تاريخ الدفع *</label>
                    <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>المبلغ المدفوع *</label>
                    <input type="number" name="amount" id="payment_amount" class="form-control" step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label>طريقة الدفع *</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">نقداً</option>
                        <option value="bank_transfer">حوالة بنكية</option>
                        <option value="check">شيك</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                
                <h3 style="margin-top: 30px; color: #667eea;">توزيع الدفعة على الفواتير</h3>
                <div id="invoices_container" style="max-height: 300px; overflow-y: auto; border: 2px solid #ddd; padding: 15px; border-radius: 8px;">
                    <p style="text-align: center; color: #999;">اختر العميل أولاً لعرض الفواتير غير المدفوعة</p>
                </div>
                
                <button type="submit" name="add_payment" class="btn btn-primary" style="margin-top: 20px;">حفظ الدفعة</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addModal').style.display='none'">إلغاء</button>
            </form>
        </div>
    </div>

    <script>
        function loadUnpaidInvoices(customerId) {
            if (!customerId) {
                document.getElementById('invoices_container').innerHTML = '<p style="text-align: center; color: #999;">اختر العميل أولاً لعرض الفواتير غير المدفوعة</p>';
                return;
            }
            
            // في التطبيق الفعلي، استخدم AJAX لجلب البيانات
            // هنا نستخدم إعادة تحميل الصفحة كحل مؤقت
            fetch('get_unpaid_invoices.php?customer_id=' + customerId)
                .then(response => response.json())
                .then(data => {
                    let html = '';
                    if (data.length > 0) {
                        data.forEach(invoice => {
                            html += `
                                <div style="display: flex; align-items: center; margin-bottom: 10px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
                                    <div style="flex: 1;">
                                        <strong>فاتورة: ${invoice.invoice_number}</strong><br>
                                        <small>التاريخ: ${invoice.invoice_date}</small><br>
                                        <small>المبلغ الإجمالي: ${parseFloat(invoice.total_amount).toFixed(2)} ريال</small><br>
                                        <small style="color: #dc3545;">المتبقي: ${parseFloat(invoice.remaining_amount).toFixed(2)} ريال</small>
                                    </div>
                                    <div style="width: 150px;">
                                        <input type="number" name="invoices[${invoice.id}]" class="form-control" placeholder="المبلغ المخصص" min="0" max="${invoice.remaining_amount}" step="0.01" value="0" style="text-align: center;">
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        html = '<p style="text-align: center; color: #28a745;">لا توجد فواتير غير مدفوعة لهذا العميل</p>';
                    }
                    document.getElementById('invoices_container').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('invoices_container').innerHTML = '<p style="text-align: center; color: #dc3545;">حدث خطأ في تحميل الفواتير</p>';
                });
        }

        window.onclick = function(event) {
            let modal = document.getElementById('addModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>