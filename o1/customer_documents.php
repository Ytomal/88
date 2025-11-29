<?php
require_once 'config.php';
checkLogin();

$customer_id = $_GET['id'] ?? 0;

// جلب بيانات العميل
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if(!$customer) {
    header('Location: customers.php');
    exit;
}

// جلب المستندات مع المرفقات
$stmt = $pdo->prepare("SELECT od.*, 
                       (SELECT COUNT(*) FROM document_attachments WHERE document_id = od.id) as attachments_count
                       FROM official_documents od
                       WHERE od.customer_id = ?
                       ORDER BY od.created_at DESC");
$stmt->execute([$customer_id]);
$documents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المستندات الرسمية - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .main-content {
            margin-right: 260px;
            padding: 25px;
        }
        .document-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        .document-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .attachment-preview {
            display: inline-block;
            margin: 5px;
            position: relative;
        }
        .attachment-preview img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
        }
        .file-upload-area {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .file-upload-area:hover {
            background: #f8f9fa;
            border-color: #764ba2;
        }
        .file-upload-area.drag-over {
            background: #e3f2fd;
            border-color: #2196f3;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-file-contract text-primary"></i> المستندات الرسمية</h2>
                    <p class="text-muted"><?= htmlspecialchars($customer['company_name']) ?></p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                        <i class="fas fa-plus"></i> إضافة مستند
                    </button>
                    <a href="view_customer.php?id=<?= $customer_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                </div>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php
                    $messages = [
                        'added' => 'تم إضافة المستند بنجاح',
                        'updated' => 'تم تحديث المستند بنجاح',
                        'deleted' => 'تم حذف المستند بنجاح',
                        'attachment_added' => 'تم إضافة المرفق بنجاح'
                    ];
                    echo $messages[$_GET['success']] ?? 'تمت العملية بنجاح';
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- قائمة المستندات -->
            <?php if(empty($documents)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-contract fa-4x text-muted mb-3"></i>
                    <h4>لا توجد مستندات</h4>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                        إضافة أول مستند
                    </button>
                </div>
            <?php else: ?>
                <?php foreach($documents as $doc): ?>
                    <?php
                    $today = date('Y-m-d');
                    $is_expired = $doc['expiry_date'] && $doc['expiry_date'] < $today;
                    $days_remaining = $doc['expiry_date'] ? round((strtotime($doc['expiry_date']) - time()) / 86400) : null;
                    $near_expiry = $days_remaining !== null && $days_remaining <= 30 && $days_remaining > 0;
                    
                    $doc_types = [
                        'commercial_register' => 'السجل التجاري',
                        'tax_certificate' => 'الشهادة الضريبية',
                        'municipal_license' => 'الرخصة البلدية',
                        'civil_defense' => 'شهادة الدفاع المدني',
                        'promissory_note_manual' => 'سند لأمر يدوي',
                        'promissory_note_electronic' => 'سند لأمر إلكتروني',
                        'chamber_letter' => 'خطاب الغرفة التجارية',
                        'other' => 'أخرى'
                    ];
                    ?>
                    
                    <div class="document-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5>
                                    <i class="fas fa-file-alt text-primary"></i>
                                    <?= $doc_types[$doc['document_type']] ?? $doc['document_type'] ?>
                                </h5>
                                <div class="mb-2">
                                    <?php if($doc['document_number']): ?>
                                        <span class="badge bg-info">رقم: <?= htmlspecialchars($doc['document_number']) ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if($is_expired): ?>
                                        <span class="badge bg-danger">منتهي</span>
                                    <?php elseif($near_expiry): ?>
                                        <span class="badge bg-warning">قرب الانتهاء (<?= $days_remaining ?> يوم)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">ساري</span>
                                    <?php endif; ?>
                                    
                                    <?php if($doc['attachments_count'] > 0): ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-paperclip"></i> <?= $doc['attachments_count'] ?> مرفق
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-plus"></i> الإصدار: 
                                            <?= $doc['issue_date'] ? date('Y-m-d', strtotime($doc['issue_date'])) : '-' ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-times"></i> الانتهاء: 
                                            <?= $doc['expiry_date'] ? date('Y-m-d', strtotime($doc['expiry_date'])) : '-' ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <?php if($doc['notes']): ?>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($doc['notes']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4 text-end">
                                <a href="view_document.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-primary mb-1">
                                    <i class="fas fa-eye"></i> عرض
                                </a>
                                <a href="edit_document.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-warning mb-1">
                                    <i class="fas fa-edit"></i> تعديل
                                </a>
                                <button class="btn btn-sm btn-success mb-1" onclick="uploadAttachment(<?= $doc['id'] ?>)">
                                    <i class="fas fa-paperclip"></i> إضافة مرفق
                                </button>
                                <button class="btn btn-sm btn-danger mb-1" onclick="deleteDocument(<?= $doc['id'] ?>)">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal إضافة مستند -->
    <div class="modal fade" id="addDocumentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مستند جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="documents_action.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نوع المستند *</label>
                                <select name="document_type" class="form-control" required id="docType">
                                    <option value="">اختر النوع</option>
                                    <option value="commercial_register">السجل التجاري</option>
                                    <option value="tax_certificate">الشهادة الضريبية</option>
                                    <option value="municipal_license">الرخصة البلدية</option>
                                    <option value="civil_defense">شهادة الدفاع المدني</option>
                                    <option value="promissory_note_manual">سند لأمر يدوي</option>
                                    <option value="promissory_note_electronic">سند لأمر إلكتروني</option>
                                    <option value="chamber_letter">خطاب الغرفة التجارية</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رقم المستند</label>
                                <input type="text" name="document_number" class="form-control">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">تاريخ الإصدار</label>
                                <input type="date" name="issue_date" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">تاريخ الانتهاء</label>
                                <input type="date" name="expiry_date" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">المصدر</label>
                                <select name="document_source" class="form-control">
                                    <option value="manual">يدوي</option>
                                    <option value="electronic">إلكتروني</option>
                                    <option value="scanned">ممسوح ضوئياً</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الجهة المصدرة</label>
                            <input type="text" name="issuing_authority" class="form-control">
                        </div>

                        <!-- حقول السند لأمر -->
                        <div id="promissoryFields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">المبلغ (ريال)</label>
                                    <input type="number" name="amount" class="form-control" step="0.01">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">اسم المستفيد</label>
                                    <input type="text" name="beneficiary_name" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رفع الملفات (صور أو PDF)</label>
                            <div class="file-upload-area" id="fileUploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <h5>اسحب وأفلت الملفات هنا</h5>
                                <p class="text-muted">أو انقر للاختيار</p>
                                <input type="file" name="files[]" id="fileInput" multiple accept="image/*,.pdf" style="display: none;">
                            </div>
                            <small class="text-muted">الحد الأقصى: 5MB لكل ملف. الأنواع المسموحة: JPG, PNG, PDF</small>
                            <div id="filePreview" class="mt-3"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_document" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ المستند
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal إضافة مرفق -->
    <div class="modal fade" id="uploadAttachmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مرفقات</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="documents_action.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="document_id" id="attachmentDocId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اختر الملفات</label>
                            <input type="file" name="attachment_files[]" class="form-control" multiple accept="image/*,.pdf" required>
                            <small class="text-muted">يمكنك اختيار عدة ملفات</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">وصف المرفقات</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_attachment" class="btn btn-primary">
                            <i class="fas fa-upload"></i> رفع
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // إظهار حقول السند لأمر
        document.getElementById('docType').addEventListener('change', function() {
            const promissoryFields = document.getElementById('promissoryFields');
            if(this.value === 'promissory_note_manual' || this.value === 'promissory_note_electronic') {
                promissoryFields.style.display = 'block';
            } else {
                promissoryFields.style.display = 'none';
            }
        });

        // منطقة السحب والإفلات
        const uploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');

        uploadArea.addEventListener('click', () => fileInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            fileInput.files = e.dataTransfer.files;
            displayFiles();
        });

        fileInput.addEventListener('change', displayFiles);

        function displayFiles() {
            filePreview.innerHTML = '';
            const files = Array.from(fileInput.files);
            
            files.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'alert alert-info d-flex justify-content-between align-items-center';
                div.innerHTML = `
                    <span><i class="fas fa-file"></i> ${file.name} (${(file.size/1024).toFixed(2)} KB)</span>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                filePreview.appendChild(div);
            });
        }

        function removeFile(index) {
            const dt = new DataTransfer();
            const files = Array.from(fileInput.files);
            files.splice(index, 1);
            files.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
            displayFiles();
        }

        function uploadAttachment(docId) {
            document.getElementById('attachmentDocId').value = docId;
            new bootstrap.Modal(document.getElementById('uploadAttachmentModal')).show();
        }

        function deleteDocument(id) {
            if(confirm('هل أنت متأكد من حذف هذا المستند وجميع مرفقاته؟')) {
                window.location.href = `documents_action.php?delete=${id}&customer_id=<?= $customer_id ?>`;
            }
        }
    </script>
</body>
</html>