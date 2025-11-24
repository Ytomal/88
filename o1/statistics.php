<?php
require_once 'config.php';

// إحصائيات العملاء
$total_customers = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];
$active_customers = $conn->query("SELECT COUNT(*) as count FROM customers WHERE status = 'active'")->fetch_assoc()['count'];

// إحصائيات المبيعات
$total_invoices = $conn->query("SELECT COUNT(*) as count FROM invoices")->fetch_assoc()['count'];
$total_sales = $conn->query("SELECT IFNULL(SUM(total_amount), 0) as total FROM invoices")->fetch_assoc()['total'];
$total_paid = $conn->query("SELECT IFNULL(SUM(paid_amount), 0) as total FROM invoices")->fetch_assoc()['total'];
$total_unpaid = $conn->query("SELECT IFNULL(SUM(remaining_amount), 0) as total FROM invoices WHERE status != 'paid'")->fetch_assoc()['total'];

// إحصائيات المنتجات
$total_tires_sold = $conn->query("SELECT IFNULL(SUM(ii.quantity), 0) as total FROM invoice_items ii JOIN products p ON ii.product_id = p.id WHERE p.product_type = 'tire'")->fetch_assoc()['total'];
$total_batteries_sold = $conn->query("SELECT IFNULL(SUM(ii.quantity), 0) as total FROM invoice_items ii JOIN products p ON ii.product_id = p.id WHERE p.product_type = 'battery'")->fetch_assoc()['total'];
$total_slastics_sold = $conn->query("SELECT IFNULL(SUM(ii.quantity), 0) as total FROM invoice_items ii JOIN products p ON ii.product_id = p.id WHERE p.product_type = 'slastic'")->fetch_assoc()['total'];

// أفضل العملاء
$top_customers = $conn->query("SELECT c.company_name, c.owner_name, IFNULL(SUM(i.total_amount), 0) as total_purchases 
                               FROM customers c 
                               LEFT JOIN invoices i ON c.id = i.customer_id 
                               GROUP BY c.id 
                               ORDER BY total_purchases DESC 
                               LIMIT 10");

// المبيعات الشهرية
$monthly_sales = $conn->query("SELECT DATE_FORMAT(invoice_date, '%Y-%m') as month, 
                               IFNULL(SUM(total_amount), 0) as total 
                               FROM invoices 
                               WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                               GROUP BY month 
                               ORDER BY month ASC");

// المنتجات الأكثر مبيعاً
$top_products = $conn->query("SELECT p.product_name, p.product_type, IFNULL(SUM(ii.quantity), 0) as total_quantity, 
                              IFNULL(SUM(ii.total_price), 0) as total_revenue
                              FROM products p 
                              LEFT JOIN invoice_items ii ON p.id = ii.product_id 
                              GROUP BY p.id 
                              ORDER BY total_quantity DESC 
                              LIMIT 10");

// العملاء ذوي المستحقات
$customers_with_debt = $conn->query("SELECT c.company_name, c.owner_name, IFNULL(SUM(i.remaining_amount), 0) as debt 
                                    FROM customers c 
                                    JOIN invoices i ON c.id = i.customer_id 
                                    WHERE i.status != 'paid' 
                                    GROUP BY c.id 
                                    HAVING debt > 0 
                                    ORDER BY debt DESC 
                                    LIMIT 10");
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإحصائيات والتقارير</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 الإحصائيات والتقارير</h1>
            <p>نظرة شاملة على أداء النشاط التجاري</p>
        </header>

        <div class="card">
            <a href="index.php" class="btn btn-info">⬅️ العودة للصفحة الرئيسية</a>
            <button class="btn btn-primary" onclick="window.print()">🖨️ طباعة التقرير</button>
        </div>

        <!-- الإحصائيات العامة -->
        <div class="card">
            <h2>📈 الإحصائيات العامة</h2>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stats-card">
                    <h3>إجمالي العملاء</h3>
                    <div class="number"><?php echo $total_customers; ?></div>
                    <small>نشط: <?php echo $active_customers; ?></small>
                </div>
                
                <div class="stats-card">
                    <h3>إجمالي الفواتير</h3>
                    <div class="number"><?php echo $total_invoices; ?></div>
                </div>
                
                <div class="stats-card">
                    <h3>إجمالي المبيعات</h3>
                    <div class="number"><?php echo number_format($total_sales, 2); ?> ريال</div>
                </div>
                
                <div class="stats-card">
                    <h3>المبالغ المحصلة</h3>
                    <div class="number" style="color: #28a745;"><?php echo number_format($total_paid, 2); ?> ريال</div>
                </div>
                
                <div class="stats-card">
                    <h3>المبالغ المستحقة</h3>
                    <div class="number" style="color: #dc3545;"><?php echo number_format($total_unpaid, 2); ?> ريال</div>
                </div>
                
                <div class="stats-card">
                    <h3>نسبة التحصيل</h3>
                    <div class="number" style="color: #17a2b8;">
                        <?php echo $total_sales > 0 ? number_format(($total_paid / $total_sales) * 100, 1) : 0; ?>%
                    </div>
                </div>
            </div>
        </div>

        <!-- إحصائيات المنتجات -->
        <div class="card">
            <h2>🛒 إحصائيات المنتجات المباعة</h2>
            <div class="grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="grid-item" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h3>🚗 إطارات</h3>
                    <p><?php echo $total_tires_sold; ?> قطعة</p>
                </div>
                
                <div class="grid-item" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h3>🔋 بطاريات</h3>
                    <p><?php echo $total_batteries_sold; ?> قطعة</p>
                </div>
                
                <div class="grid-item" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h3>🔧 سلاستك</h3>
                    <p><?php echo $total_slastics_sold; ?> قطعة</p>
                </div>
            </div>
        </div>

        <!-- أفضل العملاء -->
        <div class="card">
            <h2>⭐ أفضل 10 عملاء</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الشركة</th>
                            <th>اسم المالك</th>
                            <th>إجمالي المشتريات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        while($customer = $top_customers->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($customer['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['owner_name']); ?></td>
                                <td style="color: #28a745; font-weight: bold;"><?php echo number_format($customer['total_purchases'], 2); ?> ريال</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- المبيعات الشهرية -->
        <div class="card">
            <h2>📅 المبيعات الشهرية (آخر 12 شهر)</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>الشهر</th>
                            <th>إجمالي المبيعات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($month = $monthly_sales->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $month['month']; ?></td>
                                <td style="color: #667eea; font-weight: bold;"><?php echo number_format($month['total'], 2); ?> ريال</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- المنتجات الأكثر مبيعاً -->
        <div class="card">
            <h2>🏆 المنتجات الأكثر مبيعاً</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم المنتج</th>
                            <th>النوع</th>
                            <th>الكمية المباعة</th>
                            <th>إجمالي الإيرادات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        while($product = $top_products->fetch_assoc()): 
                            $type_name = $product['product_type'] == 'tire' ? 'إطار' : ($product['product_type'] == 'battery' ? 'بطارية' : 'سلاستك');
                        ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><span class="badge badge-info"><?php echo $type_name; ?></span></td>
                                <td><?php echo $product['total_quantity']; ?> قطعة</td>
                                <td style="color: #28a745; font-weight: bold;"><?php echo number_format($product['total_revenue'], 2); ?> ريال</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- العملاء ذوي المستحقات -->
        <div class="card">
            <h2>⚠️ العملاء ذوي المستحقات الأعلى</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الشركة</th>
                            <th>اسم المالك</th>
                            <th>المبلغ المستحق</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        while($debtor = $customers_with_debt->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($debtor['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($debtor['owner_name']); ?></td>
                                <td style="color: #dc3545; font-weight: bold;"><?php echo number_format($debtor['debt'], 2); ?> ريال</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer>
            <p>&copy; 2025 نظام إدارة العملاء - جميع الحقوق محفوظة</p>
            <p>تم إنشاء التقرير في: <?php echo date('Y-m-d H:i:s'); ?></p>
        </footer>
    </div>
</body>
</html>