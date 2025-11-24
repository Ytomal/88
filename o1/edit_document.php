<?php
require_once 'config.php';
checkLogin();

$doc_id = $_GET['id'] ?? 0;

// جلب بيانات السند
$stmt = $pdo->prepare("SELECT od.*, c.company_name 
                       FROM official_documents od
                       JOIN customers c ON od.customer_id = c.id
                       WHERE od.id = ?");
$stmt->execute([$doc_id]);
$document = $stmt->fetch();

if(!$document) {
    header('Location: customers.php');
    exit;
}

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
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل السند</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="professional_style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-edit text-primary"></i> تعديل السند</h2>
                    <p class="text-muted"><?= htmlspecialchars($document['company_name']) ?></p>
                </div>
                <a href="view_document.php?id=<?= $doc_id ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i> العودة
                </a>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form action="documents_action.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="document_id" value="<?= $doc_id ?>">
                        <input type="hidden" name="customer_id" value="<?= $document['customer_id'] ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نوع السند *</label>
                                <select name="document_type" class="form-control" required onchange="togglePromissoryFields(this)">
                                    <?php foreach($doc_types as $key => $value): ?>
                                        <option value="<?= $key ?>" <?= $document['document_type'] == $key ? 'selected' : '' ?>>
                                            <?= $value ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رقم السند</label>
                                <input type="text" name="document_number" class="form-control" 
                                       value="<?= htmlspecialchars($document['document_number'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">تاريخ الإصدار</label>
                                <input type="date" name="issue_date" class="form-control" 
                                       value="<?= $document['issue_date'] ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">تاريخ الانتهاء</label>
                                <input type="date" name="expiry_date" class="form-control" 
                                       value="<?= $document['expiry_date'] ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">المصدر</label>
                                <select name="document_source" class="form-control">
                                    <option value="manual" <?= $document['document_source'] == 'manual' ? 'selected' : '' ?>>يدوي</option>
                                    <option value="electronic" <?= $document['document_source'] == 'electronic' ? 'selected' : '' ?>>إلكتروني</option>
                                    <option value="scanned" <?= $document['document_source'] == 'scanned' ? 'selected' : '' ?>>ممسوح ضوئياً</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الجهة المصدرة</label>
                            <input type="text" name="issuing_authority" class="form-control" 
                                   value="<?= htmlspecialchars($document['issuing_authority'] ?? '') ?>">
                        </div>

                        <div class="row" id="promissoryFields">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المبلغ (ريال)</label>
                                <input type="number" name="amount" class="form-control" step="0.01" 
                                       value="<?= $document['amount'] ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">اسم المستفيد</label>
                                <input type="text" name="beneficiary_name" class="form-control" 
                                       value="<?= htmlspecialchars($document['beneficiary_name'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">حالة السند</label>
                            <select name="document_status" class="form-control">
                                <option value="active" <?= $document['document_status'] == 'active' ? 'selected' : '' ?>>نشط</option>
                                <option value="expired" <?= $document['document_status'] == 'expired' ? 'selected' : '' ?>>منتهي</option>
                                <option value="cancelled" <?= $document['document_status'] == 'cancelled' ? 'selected' : '' ?>>ملغي</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="4"><?= htmlspecialchars($document['notes'] ?? '') ?></textarea>
                        </div>

                        <hr>

                        <div class="d-flex gap-2">
                            <button type="submit" name="update_document" class="btn btn-primary">
                                <i class="fas fa-save"></i> حفظ التعديلات
                            </button>
                            <a href="view_document.php?id=<?= $doc_id ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePromissoryFields(select) {
            const promissoryFields = document.getElementById('promissoryFields');
            if(select.value === 'promissory_note_manual' || select.value === 'promissory_note_electronic') {
                promissoryFields.style.display = 'flex';
            } else {
                promissoryFields.style.display = 'none';
            }
        }

        // تفعيل عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            togglePromissoryFields(document.querySelector('select[name="document_type"]'));
        });
    </script>
</body>
</html>