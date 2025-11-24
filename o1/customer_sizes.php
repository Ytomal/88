<?php
require_once 'config.php';

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// جلب بيانات العميل
$customer = $conn->query("SELECT * FROM customers WHERE id = $customer_id")->fetch_assoc();
if (!$customer) {
    header("Location: index.php");
    exit();
}

// معالجة الإضافة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_size'])) {
    $size_description = clean_input($_POST['size_description']);
    $quantity = intval($_POST['quantity']);
    $notes = clean_input($_POST['notes']);
    
    $insert_sql = "INSERT INTO sizes (customer_id, size_description, quantity, notes) 
                   VALUES ($customer_id, '$size_description', $quantity, '$notes')";
    
    if ($conn->query($insert_sql)) {
        $success_message = "تم إضافة المقاس بنجاح!";
    }
}

// معالجة التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_size'])) {
    $size_id = intval($_POST['size_id']);
    $size_description = clean_input($_POST['size_description']);
    $quantity = intval($_POST['quantity']);
    $notes = clean_input($_POST['notes']);
    
    $update_sql = "UPDATE sizes SET 
                   size_description = '$size_description',
                   quantity = $quantity,
                   notes = '$notes'
                   WHERE id = $size_id AND customer_id = $customer_id";
    
    if ($conn->query($update_sql)) {
        $success_message = "تم تحديث المقاس بنجاح!";
    }
}

// معالجة الحذف
if (isset($_GET['delete'])) {
    $size_id = intval($_GET['delete']);
    $conn->query("DELETE FROM sizes WHERE id = $size_id AND customer_id = $customer_id");
    header("Location: customer_sizes.php?customer_id=$customer_id");
    exit();
}

// جلب المقاسات
$sizes_sql = "SELECT * FROM sizes WHERE customer_id = $customer_id ORDER BY created_at DESC";
$sizes = $conn->query($sizes_sql);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المقاسات - <?php echo htmlspecialchars($customer['company_name']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📏 مقاسات الإطارات</h1>
            <p><?php echo htmlspecialchars($customer['company_name']); ?> - <?php echo htmlspecialchars($customer['owner_name']); ?></p>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <a href="customer_details.php?id=<?php echo $customer_id; ?>" class="btn btn-info">⬅️ العودة لتفاصيل العميل</a>
            <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='block'">
                ➕ إضافة مقاس جديد
            </button>
        </div>

        <div class="card">
            <h2>قائمة المقاسات المفضلة</h2>
            <p style="color: #666; margin-bottom: 20px;">هذه هي المقاسات التي يشتريها العميل بشكل متكرر</p>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>وصف المقاس</th>
                            <th>الكمية المعتادة</th>
                            <th>ملاحظات</th>
                            <th>تاريخ الإضافة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sizes->num_rows > 0): ?>
                            <?php while($size = $sizes->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $size['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($size['size_description']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo $size['quantity']; ?> قطعة</span></td>
                                    <td><?php echo htmlspecialchars($size['notes']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($size['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editSize(<?php echo htmlspecialchars(json_encode($size)); ?>)">✏️ تعديل</button>
                                        <a href="?customer_id=<?php echo $customer_id; ?>&delete=<?php echo $size['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من الحذف؟')">🗑️ حذف</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">لا توجد مقاسات مسجلة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>💡 نصائح</h2>
            <ul style="line-height: 2;">
                <li>سجل المقاسات التي يطلبها العميل بشكل متكرر لسرعة معالجة الطلبات المستقبلية</li>
                <li>يمكنك تحديث الكمية المعتادة حسب احتياجات العميل</li>
                <li>استخدم حقل الملاحظات لتسجيل تفاصيل إضافية مثل موديل السيارة</li>
            </ul>
        </div>

        <footer>
            <p>&copy; 2025 نظام إدارة العملاء - جميع الحقوق محفوظة</p>
        </footer>
    </div>

    <!-- مودال إضافة مقاس -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
            <h2>إضافة مقاس جديد</h2>
            <form method="POST">
                <div class="form-group">
                    <label>وصف المقاس *</label>
                    <input type="text" name="size_description" class="form-control" placeholder="مثال: 195/65 R15" required>
                    <small style="color: #666;">مثل: 205/55 R16، 185/60 R14، إلخ</small>
                </div>
                
                <div class="form-group">
                    <label>الكمية المعتادة</label>
                    <input type="number" name="quantity" class="form-control" value="4" min="1">
                    <small style="color: #666;">عدد القطع التي يطلبها العميل عادة</small>
                </div>
                
                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="مثال: لسيارة كامري 2018، يفضل الماركة الكورية"></textarea>
                </div>
                
                <button type="submit" name="add_size" class="btn btn-primary">حفظ</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addModal').style.display='none'">إلغاء</button>
            </form>
        </div>
    </div>

    <!-- مودال تعديل مقاس -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
            <h2>تعديل المقاس</h2>
            <form method="POST">
                <input type="hidden" name="size_id" id="edit_size_id">
                
                <div class="form-group">
                    <label>وصف المقاس *</label>
                    <input type="text" name="size_description" id="edit_size_description" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>الكمية المعتادة</label>
                    <input type="number" name="quantity" id="edit_quantity" class="form-control" min="1">
                </div>
                
                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" name="edit_size" class="btn btn-primary">تحديث</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('editModal').style.display='none'">إلغاء</button>
            </form>
        </div>
    </div>

    <script>
        function editSize(size) {
            document.getElementById('edit_size_id').value = size.id;
            document.getElementById('edit_size_description').value = size.size_description;
            document.getElementById('edit_quantity').value = size.quantity;
            document.getElementById('edit_notes').value = size.notes;
            document.getElementById('editModal').style.display = 'block';
        }

        window.onclick = function(event) {
            let addModal = document.getElementById('addModal');
            let editModal = document.getElementById('editModal');
            if (event.target == addModal) {
                addModal.style.display = "none";
            }
            if (event.target == editModal) {
                editModal.style.display = "none";
            }
        }
    </script>
</body>
</html>