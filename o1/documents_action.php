<?php
require_once 'config.php';
checkLogin();

$uploadDir = 'uploads/documents/';
if(!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// دالة رفع ملف واحد
function uploadFile($file, $uploadDir) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $maxSize = 5242880; // 5MB
    
    if($file['error'] !== 0) {
        throw new Exception('خطأ في رفع الملف');
    }
    
    $fileType = mime_content_type($file['tmp_name']);
    if(!in_array($fileType, $allowedTypes)) {
        throw new Exception('نوع الملف غير مسموح. الأنواع المسموحة: JPG, PNG, PDF');
    }
    
    if($file['size'] > $maxSize) {
        throw new Exception('حجم الملف كبير جداً. الحد الأقصى: 5MB');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = time() . '_' . uniqid() . '.' . $extension;
    $targetPath = $uploadDir . $fileName;
    
    if(!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('فشل رفع الملف');
    }
    
    return [
        'path' => $targetPath,
        'name' => $file['name'],
        'size' => $file['size'],
        'type' => $fileType
    ];
}

// إضافة مستند
if(isset($_POST['add_document'])) {
    try {
        $pdo->beginTransaction();
        
        // إدراج المستند
        $stmt = $pdo->prepare("INSERT INTO official_documents 
                               (customer_id, document_type, document_number, issue_date, expiry_date, 
                               issuing_authority, amount, beneficiary_name, document_source, 
                               document_status, notes) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
        
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['document_type'],
            $_POST['document_number'] ?? null,
            $_POST['issue_date'] ?? null,
            $_POST['expiry_date'] ?? null,
            $_POST['issuing_authority'] ?? null,
            $_POST['amount'] ?? null,
            $_POST['beneficiary_name'] ?? null,
            $_POST['document_source'] ?? 'manual',
            $_POST['notes'] ?? null
        ]);
        
        $documentId = $pdo->lastInsertId();
        
        // رفع الملفات المرفقة
        if(isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
            $filesCount = count($_FILES['files']['name']);
            
            for($i = 0; $i < $filesCount; $i++) {
                if($_FILES['files']['error'][$i] === 0) {
                    $file = [
                        'name' => $_FILES['files']['name'][$i],
                        'type' => $_FILES['files']['type'][$i],
                        'tmp_name' => $_FILES['files']['tmp_name'][$i],
                        'error' => $_FILES['files']['error'][$i],
                        'size' => $_FILES['files']['size'][$i]
                    ];
                    
                    try {
                        $uploadedFile = uploadFile($file, $uploadDir);
                        
                        // حفظ معلومات المرفق
                        $stmt = $pdo->prepare("INSERT INTO document_attachments 
                                               (document_id, file_name, file_path, file_type, file_size, 
                                               mime_type, is_primary, uploaded_by) 
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        $stmt->execute([
                            $documentId,
                            $uploadedFile['name'],
                            $uploadedFile['path'],
                            pathinfo($uploadedFile['name'], PATHINFO_EXTENSION),
                            $uploadedFile['size'],
                            $uploadedFile['type'],
                            $i == 0 ? 1 : 0, // أول ملف يكون أساسي
                            $_SESSION['user_id']
                        ]);
                    } catch(Exception $e) {
                        // تسجيل الخطأ ولكن متابعة العملية
                        error_log("فشل رفع ملف: " . $e->getMessage());
                    }
                }
            }
        }
        
        logActivity('إضافة مستند', "تم إضافة مستند: " . $_POST['document_type']);
        
        $pdo->commit();
        header('Location: customer_documents.php?id=' . $_POST['customer_id'] . '&success=added');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header('Location: customer_documents.php?id=' . $_POST['customer_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// إضافة مرفقات لمستند موجود
if(isset($_POST['add_attachment'])) {
    try {
        $document_id = $_POST['document_id'];
        
        // جلب معلومات المستند
        $stmt = $pdo->prepare("SELECT customer_id FROM official_documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch();
        
        if(!$document) {
            throw new Exception('المستند غير موجود');
        }
        
        $pdo->beginTransaction();
        
        // رفع الملفات
        if(isset($_FILES['attachment_files']) && !empty($_FILES['attachment_files']['name'][0])) {
            $filesCount = count($_FILES['attachment_files']['name']);
            
            for($i = 0; $i < $filesCount; $i++) {
                if($_FILES['attachment_files']['error'][$i] === 0) {
                    $file = [
                        'name' => $_FILES['attachment_files']['name'][$i],
                        'type' => $_FILES['attachment_files']['type'][$i],
                        'tmp_name' => $_FILES['attachment_files']['tmp_name'][$i],
                        'error' => $_FILES['attachment_files']['error'][$i],
                        'size' => $_FILES['attachment_files']['size'][$i]
                    ];
                    
                    $uploadedFile = uploadFile($file, $uploadDir);
                    
                    // حفظ معلومات المرفق
                    $stmt = $pdo->prepare("INSERT INTO document_attachments 
                                           (document_id, file_name, file_path, file_type, file_size, 
                                           mime_type, description, uploaded_by) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $document_id,
                        $uploadedFile['name'],
                        $uploadedFile['path'],
                        pathinfo($uploadedFile['name'], PATHINFO_EXTENSION),
                        $uploadedFile['size'],
                        $uploadedFile['type'],
                        $_POST['description'] ?? null,
                        $_SESSION['user_id']
                    ]);
                }
            }
        }
        
        logActivity('إضافة مرفق', "تم إضافة مرفقات للمستند ID: $document_id");
        
        $pdo->commit();
        header('Location: customer_documents.php?id=' . $document['customer_id'] . '&success=attachment_added');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header('Location: view_document.php?id=' . $_POST['document_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// تحديث مستند
if(isset($_POST['update_document'])) {
    try {
        $stmt = $pdo->prepare("UPDATE official_documents SET 
                               document_type = ?, document_number = ?, issue_date = ?, 
                               expiry_date = ?, issuing_authority = ?, amount = ?, 
                               beneficiary_name = ?, document_source = ?, document_status = ?, 
                               notes = ?
                               WHERE id = ?");
        
        $stmt->execute([
            $_POST['document_type'],
            $_POST['document_number'] ?? null,
            $_POST['issue_date'] ?? null,
            $_POST['expiry_date'] ?? null,
            $_POST['issuing_authority'] ?? null,
            $_POST['amount'] ?? null,
            $_POST['beneficiary_name'] ?? null,
            $_POST['document_source'] ?? 'manual',
            $_POST['document_status'] ?? 'active',
            $_POST['notes'] ?? null,
            $_POST['document_id']
        ]);
        
        logActivity('تحديث مستند', "تم تحديث المستند ID: " . $_POST['document_id']);
        
        header('Location: customer_documents.php?id=' . $_POST['customer_id'] . '&success=updated');
        exit;
        
    } catch(Exception $e) {
        header('Location: edit_document.php?id=' . $_POST['document_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// حذف مستند
if(isset($_GET['delete'])) {
    try {
        $pdo->beginTransaction();
        
        $documentId = $_GET['delete'];
        
        // جلب معلومات المستند
        $stmt = $pdo->prepare("SELECT customer_id FROM official_documents WHERE id = ?");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
        
        // حذف الملفات المرفقة
        $stmt = $pdo->prepare("SELECT file_path FROM document_attachments WHERE document_id = ?");
        $stmt->execute([$documentId]);
        $attachments = $stmt->fetchAll();
        
        foreach($attachments as $attachment) {
            if($attachment['file_path'] && file_exists($attachment['file_path'])) {
                unlink($attachment['file_path']);
            }
        }
        
        // حذف المستند
        $stmt = $pdo->prepare("DELETE FROM official_documents WHERE id = ?");
        $stmt->execute([$documentId]);
        
        logActivity('حذف مستند', "تم حذف المستند ID: $documentId");
        
        $pdo->commit();
        header('Location: customer_documents.php?id=' . $_GET['customer_id'] . '&success=deleted');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header('Location: customer_documents.php?id=' . $_GET['customer_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// حذف مرفق
if(isset($_GET['delete_attachment'])) {
    try {
        $attachmentId = $_GET['delete_attachment'];
        
        $stmt = $pdo->prepare("SELECT da.*, od.customer_id 
                               FROM document_attachments da
                               JOIN official_documents od ON da.document_id = od.id
                               WHERE da.id = ?");
        $stmt->execute([$attachmentId]);
        $attachment = $stmt->fetch();
        
        if($attachment['file_path'] && file_exists($attachment['file_path'])) {
            unlink($attachment['file_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM document_attachments WHERE id = ?");
        $stmt->execute([$attachmentId]);
        
        logActivity('حذف مرفق', "تم حذف مرفق ID: $attachmentId");
        
        header('Location: customer_documents.php?id=' . $attachment['customer_id'] . '&success=attachment_deleted');
        exit;
        
    } catch(Exception $e) {
        header('Location: customer_documents.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// إذا لم يكن هناك إجراء صحيح
header('Location: customers.php');
exit;