<?php
require_once 'config.php';
require_once 'auth.php';

check_login();

class DocumentUploader {
    private $conn;
    private $upload_dir = 'uploads/documents/';
    private $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
    private $max_file_size = 10485760; // 10MB
    
    public function __construct($conn) {
        $this->conn = $conn;
        
        // إنشاء مجلد الرفع إذا لم يكن موجوداً
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    // رفع ملف جديد
    public function uploadFile($file, $customer_id, $document_id = null, $uploaded_by = null) {
        // التحقق من وجود الملف
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'لم يتم رفع أي ملف'];
        }
        
        // التحقق من حجم الملف
        if ($file['size'] > $this->max_file_size) {
            return ['success' => false, 'message' => 'حجم الملف كبير جداً (الحد الأقصى 10 ميجابايت)'];
        }
        
        // التحقق من نوع الملف
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $this->allowed_types)) {
            return ['success' => false, 'message' => 'نوع الملف غير مسموح به'];
        }
        
        // إنشاء اسم فريد للملف
        $new_filename = $customer_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
        $file_path = $this->upload_dir . $new_filename;
        
        // نقل الملف
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // حفظ معلومات الملف في قاعدة البيانات
            $customer_id = intval($customer_id);
            $document_id = $document_id ? intval($document_id) : 'NULL';
            $uploaded_by = $uploaded_by ? intval($uploaded_by) : $_SESSION['user_id'];
            $file_name = $this->conn->real_escape_string($file['name']);
            $file_type = $this->conn->real_escape_string($file['type']);
            $file_size = intval($file['size']);
            
            $sql = "INSERT INTO uploaded_documents (customer_id, document_id, file_name, file_path, file_type, file_size, uploaded_by) 
                    VALUES ($customer_id, $document_id, '$file_name', '$file_path', '$file_type', $file_size, $uploaded_by)";
            
            if ($this->conn->query($sql)) {
                // تحديث مسار الملف في جدول المستندات الرسمية إذا كان معرّف المستند موجود
                if ($document_id != 'NULL') {
                    $this->conn->query("UPDATE official_documents SET file_path = '$file_path' WHERE id = $document_id");
                }
                
                // تسجيل النشاط
                log_activity('رفع مستند', "تم رفع ملف: $file_name للعميل رقم $customer_id");
                
                return [
                    'success' => true, 
                    'message' => 'تم رفع الملف بنجاح',
                    'file_id' => $this->conn->insert_id,
                    'file_path' => $file_path
                ];
            } else {
                // حذف الملف في حالة فشل حفظ البيانات
                unlink($file_path);
                return ['success' => false, 'message' => 'فشل حفظ معلومات الملف'];
            }
        } else {
            return ['success' => false, 'message' => 'فشل رفع الملف'];
        }
    }
    
    // جلب ملفات العميل
    public function getCustomerFiles($customer_id) {
        $customer_id = intval($customer_id);
        $sql = "SELECT ud.*, u.full_name as uploaded_by_name 
                FROM uploaded_documents ud 
                LEFT JOIN users u ON ud.uploaded_by = u.id 
                WHERE ud.customer_id = $customer_id 
                ORDER BY ud.created_at DESC";
        
        return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    
    // حذف ملف
    public function deleteFile($file_id) {
        $file_id = intval($file_id);
        
        // جلب معلومات الملف
        $file = $this->conn->query("SELECT * FROM uploaded_documents WHERE id = $file_id")->fetch_assoc();
        
        if ($file) {
            // حذف الملف الفعلي
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
            
            // حذف السجل من قاعدة البيانات
            if ($this->conn->query("DELETE FROM uploaded_documents WHERE id = $file_id")) {
                log_activity('حذف مستند', "تم حذف ملف: {$file['file_name']}");
                return ['success' => true, 'message' => 'تم حذف الملف بنجاح'];
            }
        }
        
        return ['success' => false, 'message' => 'فشل حذف الملف'];
    }
    
    // تنزيل ملف
    public function downloadFile($file_id) {
        $file_id = intval($file_id);
        $file = $this->conn->query("SELECT * FROM uploaded_documents WHERE id = $file_id")->fetch_assoc();
        
        if ($file && file_exists($file['file_path'])) {
            // تسجيل النشاط
            log_activity('تنزيل مستند', "تم تنزيل ملف: {$file['file_name']}");
            
            // إرسال الملف للمتصفح
            header('Content-Type: ' . $file['file_type']);
            header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
            header('Content-Length: ' . $file['file_size']);
            readfile($file['file_path']);
            exit();
        }
        
        die('الملف غير موجود');
    }
}

// معالجة الطلبات
$uploader = new DocumentUploader($conn);

// معالجة رفع ملف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $customer_id = $_POST['customer_id'] ?? 0;
    $document_id = $_POST['document_id'] ?? null;
    
    $result = $uploader->uploadFile($_FILES['document'], $customer_id, $document_id);
    
    if (isset($_POST['ajax'])) {
        echo json_encode($result);
        exit();
    } else {
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
        header("Location: customer_documents.php?customer_id=$customer_id");
        exit();
    }
}

// معالجة التنزيل
if (isset($_GET['download'])) {
    $uploader->downloadFile($_GET['download']);
}

// معالجة الحذف
if (isset($_GET['delete'])) {
    $result = $uploader->deleteFile($_GET['delete']);
    if (isset($_GET['ajax'])) {
        echo json_encode($result);
        exit();
    } else {
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
        $customer_id = $_GET['customer_id'] ?? 0;
        header("Location: customer_documents.php?customer_id=$customer_id");
        exit();
    }
}

// عرض صفحة رفع الملفات
$customer_id = $_GET['customer_id'] ?? 0;
$customer = $conn->query("SELECT * FROM customers WHERE id = " . intval($customer_id))->fetch_assoc();

if (!$customer) {
    die('العميل غير موجود');
}

$files = $uploader->getCustomerFiles($customer_id);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رفع المستندات - <?php echo htmlspecialchars($customer['company_name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .upload-area {
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            background: #e8ecff;
            border-color: #764ba2;
        }
        
        .upload-area.dragover {
            background: #d4e4ff;
            border-color: #28a745;
        }
        
        .file-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .file-icon {
            font-size: 3em;
            margin-left: 20px;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📤 رفع المستندات</h1>
            <p><?php echo htmlspecialchars($customer['company_name']); ?> - <?php echo htmlspecialchars($customer['owner_name']); ?></p>
        </header>

        <?php display_user_bar(); ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <div class="card">
            <a href="customer_details.php?id=<?php echo $customer_id; ?>" class="btn btn-info">⬅️ العودة لتفاصيل العميل</a>
        </div>

        <div class="card">
            <h2>رفع ملف جديد</h2>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                
                <div class="upload-area" id="uploadArea">
                    <div style="font-size: 4em; margin-bottom: 20px;">📁</div>
                    <h3>اسحب الملف هنا أو انقر للاختيار</h3>
                    <p style="color: #666; margin-top: 10px;">الملفات المسموحة: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX</p>
                    <p style="color: #666;">الحد الأقصى للحجم: 10 ميجابايت</p>
                    <input type="file" name="document" id="fileInput" style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="margin-top: 20px;">⬆️ رفع الملف</button>
            </form>
        </div>

        <div class="card">
            <h2>📂 الملفات المرفوعة (<?php echo count($files); ?>)</h2>
            
            <?php if (count($files) > 0): ?>
                <?php foreach ($files as $file): ?>
                    <div class="file-item">
                        <div class="file-icon">
                            <?php
                            $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                            $icons = [
                                'pdf' => '📕',
                                'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
                                'doc' => '📘', 'docx' => '📘',
                                'xls' => '📗', 'xlsx' => '📗'
                            ];
                            echo $icons[$ext] ?? '📄';
                            ?>
                        </div>
                        
                        <div class="file-info">
                            <h3 style="margin: 0 0 10px 0; color: #667eea;"><?php echo htmlspecialchars($file['file_name']); ?></h3>
                            <p style="margin: 5px 0; color: #666;">
                                <strong>الحجم:</strong> <?php echo number_format($file['file_size'] / 1024, 2); ?> كيلوبايت
                            </p>
                            <p style="margin: 5px 0; color: #666;">
                                <strong>رفع بواسطة:</strong> <?php echo htmlspecialchars($file['uploaded_by_name']); ?>
                            </p>
                            <p style="margin: 5px 0; color: #999;">
                                <strong>التاريخ:</strong> <?php echo date('Y-m-d H:i', strtotime($file['created_at'])); ?>
                            </p>
                        </div>
                        
                        <div class="file-actions">
                            <a href="?download=<?php echo $file['id']; ?>" class="btn btn-success btn-sm">⬇️ تنزيل</a>
                            <a href="?delete=<?php echo $file['id']; ?>&customer_id=<?php echo $customer_id; ?>" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من الحذف؟')">🗑️ حذف</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">لم يتم رفع أي ملفات بعد</div>
            <?php endif; ?>
        </div>

        <footer>
            <p>&copy; 2025 نظام إدارة العملاء - جميع الحقوق محفوظة</p>
        </footer>
    </div>

    <script>
        // دعم السحب والإفلات
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');

        uploadArea.addEventListener('click', () => fileInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                document.getElementById('uploadForm').submit();
            }
        });

        // عرض اسم الملف المختار
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) {
                const fileName = e.target.files[0].name;
                uploadArea.querySelector('h3').textContent = 'تم اختيار: ' + fileName;
            }
        });
    </script>
</body>
</html>