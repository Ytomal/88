<?php
require_once 'config.php';
require_once 'auth.php';

check_login();
validate_session();

// جلب جميع العملاء
$sql = "SELECT * FROM customers ORDER BY created_at DESC";
$result = $conn->query($sql);

// معالجة الإضافة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $company_name = clean_input($_POST['company_name']);
    $owner_name = clean_input($_POST['owner_name']);
    $responsible_person = clean_input($_POST['responsible_person']);
    $phone = clean_input($_POST['phone']);
    $email = clean_input($_POST['email']);
    $address = clean_input($_POST['address']);
    $start_date = clean_input($_POST['start_date']);
    $notes = clean_input($_POST['notes']);
    
    $insert_sql = "INSERT INTO customers (company_name, owner_name, responsible_person, phone, email, address, start_date, notes) 
                   VALUES ('$company_name', '$owner_name', '$responsible_person', '$phone', '$email', '$address', '$start_date', '$notes')";
    
    if ($conn->query($insert_sql)) {
        log_activity('إضافة عميل', "تم إضافة العميل: $company_name");
        header("Location: index.php");
        exit();
    }
}

// معالجة الحذف
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $customer = $conn->query("SELECT company_name FROM customers WHERE id = $id")->fetch_assoc();
    $conn->query("DELETE FROM customers WHERE id = $id");
    log_activity('حذف عميل', "تم حذف العميل: " . $customer['company_name']);
    header("Location: index.php");
    exit();
}

// الإحصائيات
$total_customers = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];
$total_invoices = $conn->query("SELECT COUNT(*) as count FROM invoices")->fetch_assoc()['count'];
$total_sales = $conn->query("SELECT IFNULL(SUM(total_amount), 0) as total FROM invoices")->fetch_assoc()['total'];
$total_unpaid = $conn->query("SELECT IFNULL(SUM(remaining_amount), 0) as total FROM invoices WHERE status != 'paid'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الصفحة الرئيسية - نظام إدارة العملاء</title>
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
</head>
<body>
    <div class="container">
        <header>
            <h1>🚗 نظام إدارة العملاء</h1>
            <p>إدارة متكاملة لعملاء الإطارات والبطاريات والسلاستك</p>
        </header>

        <?php display_user_bar(); ?>

        <!-- التخطيط مع سايد بار -->
        <div class="layout-with-sidebar">
            <!-- السايد بار -->
            <?php include 'sidebar.php'; ?>

            <!-- المحتوى الرئيسي -->
            <div class="main-content">

        <!-- الإحصائيات -->
        <div class="grid">
            <div class="stats-card">
                <h3>إجمالي العملاء</h3>
                <div class="number"><?php echo $total_customers; ?></div>
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
                <h3>المبالغ المستحقة</h3>
                <div class="number" style="color: #dc3545;"><?php echo number_format($total_unpaid, 2); ?> ريال</div>
            </div>
        </div>

        <div class="card">
            <h2>قائمة العملاء</h2>
            <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='block'">
                ➕ إضافة عميل جديد
            </button>
            <a href="invoices.php" class="btn btn-info">💰 المشتريات والفواتير</a>
            <a href="payments.php" class="btn btn-success">💵 الدفعات</a>
            <a href="statistics.php" class="btn btn-warning">📊 الإحصائيات</a>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الشركة</th>
                            <th>اسم المالك</th>
                            <th>الشخص المسؤول</th>
                            <th>رقم الهاتف</th>
                            <th>تاريخ بداية التعامل</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                    <td>
                                        <a href="customer_details.php?id=<?php echo $row['id']; ?>" style="color: #667eea; font-weight: bold; text-decoration: none;">
                                            <?php echo htmlspecialchars($row['owner_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['responsible_person']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><?php echo $row['start_date']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['status'] == 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo $row['status'] == 'active' ? 'نشط' : 'غير نشط'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="customer_details.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">👁️ عرض</a>
                                        <a href="edit_customer.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">✏️ تعديل</a>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من الحذف؟')">🗑️ حذف</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">لا توجد بيانات</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        </div> <!-- نهاية main-content -->
        </div> <!-- نهاية layout-with-sidebar -->

        <footer>
            <p>&copy; 2025 نظام إدارة العملاء - جميع الحقوق محفوظة</p>
        </footer>
    </div>

    <!-- مودال إضافة عميل -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
            <h2>إضافة عميل جديد</h2>
            <form method="POST">
                <div class="form-group">
                    <label>اسم الشركة *</label>
                    <input type="text" name="company_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>اسم المالك *</label>
                    <input type="text" name="owner_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>الشخص المسؤول</label>
                    <input type="text" name="responsible_person" class="form-control">
                </div>
                <div class="form-group">
                    <label>رقم الهاتف</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="form-group">
                    <label>العنوان</label>
                    <textarea name="address" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>تاريخ بداية التعامل</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea name="notes" class="form-control"></textarea>
                </div>
                <button type="submit" name="add_customer" class="btn btn-primary">حفظ</button>
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
    <script src="app-installer.js"></script>
</body>
</html>