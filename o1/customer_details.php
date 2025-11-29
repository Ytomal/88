<?php
require_once 'config.php';

$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// جلب بيانات العميل
$sql = "SELECT * FROM customers WHERE id = $customer_id";
$customer = $conn->query($sql)->fetch_assoc();

if (!$customer) {
    header("Location: index.php");
    exit();
}

// حساب الإحصائيات
$total_invoices = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE customer_id = $customer_id")->fetch_assoc()['count'];
$total_purchases = $conn->query("SELECT IFNULL(SUM(total_amount), 0) as total FROM invoices WHERE customer_id = $customer_id")->fetch_assoc()['total'];
$total_paid = $conn->query("SELECT IFNULL(SUM(amount), 0) as total FROM payments WHERE customer_id = $customer_id")->fetch_assoc()['total'];
$total_visits = $conn->query("SELECT COUNT(*) as count FROM visits WHERE customer_id = $customer_id")->fetch_assoc()['count'];
$total_documents = $conn->query("SELECT COUNT(*) as count FROM official_documents WHERE customer_id = $customer_id")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل العميل - <?php echo htmlspecialchars($customer['company_name']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📋 تفاصيل العميل</h1>
            <p><?php echo htmlspecialchars($customer['company_name']); ?></p>
        </header>

        <div class="card">
            <a href="index.php" class="btn btn-info">⬅️ العودة للقائمة الرئيسية</a>
            <a href="edit_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-warning">✏️ تعديل البيانات</a>
        </div>

        <!-- معلومات العميل الأساسية -->
        <div class="card">
            <h2>المعلومات الأساسية</h2>
            <table>
                <tr>
                    <th style="width: 200px;">اسم الشركة</th>
                    <td><?php echo htmlspecialchars($customer['company_name']); ?></td>
                </tr>
                <tr>
                    <th>اسم المالك</th>
                    <td><?php echo htmlspecialchars($customer['owner_name']); ?></td>
                </tr>
                <tr>
                    <th>الشخص المسؤول</th>
                    <td><?php echo htmlspecialchars($customer['responsible_person']); ?></td>
                </tr>
                <tr>
                    <th>رقم الهاتف</th>
                    <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                </tr>
                <tr>
                    <th>البريد الإلكتروني</th>
                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                </tr>
                <tr>
                    <th>العنوان</th>
                    <td><?php echo htmlspecialchars($customer['address']); ?></td>
                </tr>
                <tr>
                    <th>تاريخ بداية التعامل</th>
                    <td><?php echo $customer['start_date']; ?></td>
                </tr>
                <tr>
                    <th>الحالة</th>
                    <td>
                        <span class="badge badge-<?php echo $customer['status'] == 'active' ? 'success' : 'danger'; ?>">
                            <?php echo $customer['status'] == 'active' ? 'نشط' : 'غير نشط'; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>ملاحظات</th>
                    <td><?php echo nl2br(htmlspecialchars($customer['notes'])); ?></td>
                </tr>
            </table>
        </div>

        <!-- إحصائيات سريعة -->
        <div class="grid">
            <div class="grid-item" onclick="location.href='customer_documents.php?customer_id=<?php echo $customer_id; ?>'">
                <h3>📄 المستندات الرسمية</h3>
                <p><?php echo $total_documents; ?></p>
            </div>
            
            <div class="grid-item" onclick="location.href='customer_products.php?customer_id=<?php echo $customer_id; ?>'">
                <h3>🛒 المنتجات المشتراة</h3>
                <p>عرض</p>
            </div>
            
            <div class="grid-item" onclick="location.href='customer_sizes.php?customer_id=<?php echo $customer_id; ?>'">
                <h3>📏 المقاسات</h3>
                <p>إدارة</p>
            </div>
            
            <div class="grid-item" onclick="location.href='customer_visits.php?customer_id=<?php echo $customer_id; ?>'">
                <h3>🚶 الزيارات</h3>
                <p><?php echo $total_visits; ?></p>
            </div>
            
            <div class="grid-item" onclick="location.href='customer_invoices.php?customer_id=<?php echo $customer_id; ?>'">
                <h3>💰 الفواتير</h3>
                <p><?php echo $total_invoices; ?></p>
            </div>
            
            <div class="grid-item" onclick="location.href='customer_payments.php?customer_id=<?php echo $customer_id; ?>'">
                <h3>💵 الدفعات</h3>
                <p><?php echo number_format($total_paid, 2); ?> ريال</p>
            </div>
            
            <div class="grid-item" onclick="location.href='customer_info.php?customer_id=<?php echo $customer_id; ?>'">
                <h3>ℹ️ معلومات تفصيلية</h3>
                <p>عرض</p>
            </div>
            
            <div class="grid-item" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                <h3>📊 الملخص المالي</h3>
                <p style="font-size: 1.2em;">
                    المشتريات: <?php echo number_format($total_purchases, 2); ?> ريال<br>
                    المدفوع: <?php echo number_format($total_paid, 2); ?> ريال<br>
                    المتبقي: <?php echo number_format($total_purchases - $total_paid, 2); ?> ريال
                </p>
            </div>
        </div>

        <!-- آخر الأنشطة -->
        <div class="card">
            <h2>آخر الأنشطة</h2>
            
            <h3 style="color: #667eea; margin-top: 20px;">آخر الزيارات</h3>
            <?php
            $visits_sql = "SELECT * FROM visits WHERE customer_id = $customer_id ORDER BY visit_date DESC, visit_time DESC LIMIT 5";
            $visits = $conn->query($visits_sql);
            ?>
            <table>
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>نوع الزيارة</th>
                        <th>الملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($visits->num_rows > 0): ?>
                        <?php while($visit = $visits->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $visit['visit_date']; ?></td>
                                <td><?php echo $visit['visit_time']; ?></td>
                                <td><?php echo htmlspecialchars($visit['visit_type']); ?></td>
                                <td><?php echo htmlspecialchars($visit['notes']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center;">لا توجد زيارات</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <h3 style="color: #667eea; margin-top: 30px;">آخر الفواتير</h3>
            <?php
            $invoices_sql = "SELECT * FROM invoices WHERE customer_id = $customer_id ORDER BY invoice_date DESC LIMIT 5";
            $invoices = $conn->query($invoices_sql);
            ?>
            <table>
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>التاريخ</th>
                        <th>المبلغ الإجمالي</th>
                        <th>المبلغ المدفوع</th>
                        <th>المتبقي</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($invoices->num_rows > 0): ?>
                        <?php while($invoice = $invoices->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td><?php echo $invoice['invoice_date']; ?></td>
                                <td><?php echo number_format($invoice['total_amount'], 2); ?> ريال</td>
                                <td><?php echo number_format($invoice['paid_amount'], 2); ?> ريال</td>
                                <td><?php echo number_format($invoice['remaining_amount'], 2); ?> ريال</td>
                                <td>
                                    <?php
                                    $status_text = $invoice['status'] == 'paid' ? 'مدفوعة' : ($invoice['status'] == 'partial' ? 'دفع جزئي' : 'غير مدفوعة');
                                    $status_class = $invoice['status'] == 'paid' ? 'success' : ($invoice['status'] == 'partial' ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge badge-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">لا توجد فواتير</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <footer>
            <p>&copy; 2025 نظام إدارة العملاء - جميع الحقوق محفوظة</p>
        </footer>
    </div>
</body>
</html>