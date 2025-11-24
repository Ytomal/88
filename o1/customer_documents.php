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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_document'])) {
    $document_type = clean_input($_POST['document_type']);
    $document_number = clean_input($_POST['document_number']);
    $issue_date = clean_input($_POST['issue_date']);
    $expiry_date = clean_input($_POST['expiry_date']);
    $notes = clean_input($_POST['notes']);
    
    $insert_sql = "INSERT INTO official_documents (customer_id, document_type, document_number, issue_date, expiry_date, notes) 
                   VALUES ($customer_id, '$document_type', '$document_number', '$issue_date', '$expiry_date', '$notes')";
    
    if ($conn->query($insert_sql)) {
        $success_message = "تم إضافة المستند بنجاح!";
    }
}

// معالجة التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_document'])) {
    $doc_id = intval($_POST['doc_id']);
    $document_type = clean_input($_POST['document_type']);
    $document_number = clean_input($_POST['document_number']);
    $issue_date = clean_input($_POST['issue_date']);
    $expiry_date = clean_input($_POST['expiry_date']);
    $notes = clean_input($_POST['notes']);
    
    $update_sql = "UPDATE official_documents SET 
                   document_type = '$document_type',
                   document_number = '$document_number',
                   issue_date = '$issue_date',
                   expiry_date = '$expiry_date',
                   notes = '$notes'
                   WHERE id = $doc_id AND customer_id = $customer_id";
    
    if ($conn->query($update_sql)) {
        $success_message = "تم تحديث المستند بنجاح!";
    }
}

// معالجة الحذف
if (isset($_GET['delete'])) {
    $doc_id = intval($_GET['delete']);
    $conn->query("DELETE FROM official_documents WHERE id = $doc_id AND customer_id = $customer_id");
    header("Location: customer_documents.php?customer_id=$customer_id");
    exit();
}

// جلب المستندات
$documents_sql = "SELECT * FROM official_documents WHERE customer_id = $customer_id ORDER BY created_at DESC";
$documents = $conn->query($documents_sql);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المستندات الرسمية - <?php echo htmlspecialchars($customer['company_name']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📄 المستندات الرسمية</h1>
            <p><?php echo htmlspecialchars($customer['company_name']); ?> - <?php echo htmlspecialchars($customer['owner_name']); ?></p>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <a href="customer_details.php?id=<?php echo $customer_id; ?>" class="btn btn-info">⬅️ العودة لتفاصيل العميل</a>
            <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='block'">
                ➕ إضافة مستند جديد
            </button>
        </div>

        <div class="card">
            <h2>قائمة المستندات</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نوع المستند</th>
                            <th>رقم المستند</th>
                            <th>تاريخ الإصدار</th>
                            <th>تاريخ الانتهاء</th>
                            <th>الحالة</th>
                            <th>ملاحظات</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($documents->num_rows > 0): ?>
                            <?php while($doc = $documents->fetch_assoc()): ?>
                                <?php
                                $today = date('Y-m-d');
                                $expiry = $doc['expiry_date'];
                                $is_expired = $expiry && $expiry < $today;
                                $days_remaining = $expiry ? (strtotime($expiry) - strtotime($today)) / (60 * 60 * 24) : null;
                                $near_expiry = $days_remaining !== null && $days_remaining <= 30 && $days_remaining > 0;
                                ?>
                                <tr>
                                    <td><?php echo $doc['id']; ?></td>
                                    <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                    <td><?php echo htmlspecialchars($doc['document_number']); ?></td>
                                    <td><?php echo $doc['issue_date']; ?></td>
                                    <td><?php echo $doc['expiry_date'] ?: 'غير محدد'; ?></td>
                                    <td>
                                        <?php if ($is_expired): ?>
                                            <span class="badge badge-danger">منتهي</span>
                                        <?php elseif ($near_expiry): ?>
                                            <span class="badge badge-warning">قرب الانتهاء (<?php echo round($days_remaining); ?> يوم)</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">ساري</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($doc['notes']); ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editDocument(<?php echo htmlspecialchars(json_encode($doc)); ?>)">✏️ تعديل</button>
                                        <a href="?customer_id=<?php echo $customer_id; ?>&delete=<?php echo $doc['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من الحذف؟')">🗑️ حذف</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">لا توجد مستندات</td>
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

    <!-- مودال إضافة مستند -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
            <h2>إضافة مستند جديد</h2>
            <form method="POST">
                <div class="form-group">
                    <label>نوع المستند *</label>
                    <select name="document_type" class="form-control" required>
                        <option value="">اختر النوع</option>
                        <option value="سجل تجاري">سجل تجاري</option>
                        <option value="بطاقة ضريبية">بطاقة ضريبية</option>
                        <option value="رخصة البلدية">رخصة البلدية</option>
                        <option value="شهادة الزكاة">شهادة الزكاة</option>
                        <option value="عقد إيجار">عقد إيجار</option>
                        <option value="أخرى">أخرى</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>رقم المستند *</label>
                    <input type="text" name="document_number" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>تاريخ الإصدار</label>
                    <input type="date" name="issue_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>تاريخ الانتهاء</label>
                    <input type="date" name="expiry_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea name="notes" class="form-control"></textarea>
                </div>
                
                <button type="submit" name="add_document" class="btn btn-primary">حفظ</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addModal').style.display='none'">إلغاء</button>
            </form>
        </div>
    </div>

    <!-- مودال تعديل مستند -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
            <h2>تعديل المستند</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="doc_id" id="edit_doc_id">
                
                <div class="form-group">
                    <label>نوع المستند *</label>
                    <select name="document_type" id="edit_document_type" class="form-control" required>
                        <option value="سجل تجاري">سجل تجاري</option>
                        <option value="بطاقة ضريبية">بطاقة ضريبية</option>
                        <option value="رخصة البلدية">رخصة البلدية</option>
                        <option value="شهادة الزكاة">شهادة الزكاة</option>
                        <option value="عقد إيجار">عقد إيجار</option>
                        <option value="أخرى">أخرى</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>رقم المستند *</label>
                    <input type="text" name="document_number" id="edit_document_number" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>تاريخ الإصدار</label>
                    <input type="date" name="issue_date" id="edit_issue_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>تاريخ الانتهاء</label>
                    <input type="date" name="expiry_date" id="edit_expiry_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea name="notes" id="edit_notes" class="form-control"></textarea>
                </div>
                
                <button type="submit" name="edit_document" class="btn btn-primary">تحديث</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('editModal').style.display='none'">إلغاء</button>
            </form>
        </div>
    </div>

    <script>
        function editDocument(doc) {
            document.getElementById('edit_doc_id').value = doc.id;
            document.getElementById('edit_document_type').value = doc.document_type;
            document.getElementById('edit_document_number').value = doc.document_number;
            document.getElementById('edit_issue_date').value = doc.issue_date;
            document.getElementById('edit_expiry_date').value = doc.expiry_date;
            document.getElementById('edit_notes').value = doc.notes;
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