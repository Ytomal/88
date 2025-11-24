<?php
require_once 'config.php';
checkLogin();

$uploadDir = 'uploads/documents/';
if(!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// إضافة سند
if(isset($_POST['add_document'])) {
    try {
        $pdo->beginTransaction();
        
        $filePath = null;
        
        if(isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $fileName = time() . '_' . basename($_FILES['file']['name']);
            $targetPath = $uploadDir . $fileName;
            
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            $fileType = mime_content_type($_FILES['file']['tmp_name']);
            
            if(!in_array($fileType, $allowedTypes)) {
                throw new Exception('نوع الملف غير مسموح');
            }
            
            if($_FILES['file']['size'] > 5242880) {
                throw new Exception('حجم الملف كبير جداً');
            }
            
            if(move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                $filePath = $targetPath;
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO official_documents 
                               (customer_id, document_type, document_number, issue_date, expiry_date, 
                               issuing_authority, amount, beneficiary_name, document_source, 
                               document_status, file_path, notes) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)");
        
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
            $filePath,
            $_POST['notes'] ?? null
        ]);
        
        $documentId = $pdo->lastInsertId();
        
        if($filePath) {
            $stmt = $pdo->prepare("INSERT INTO document_attachments 
                                   (document_id, file_name, file_path, file_type, file_size, 
                                   mime_type, is_primary, uploaded_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            
            $stmt->execute([
                $documentId,
                basename($filePath),
                $filePath,
                pathinfo($filePath, PATHINFO_EXTENSION),
                filesize($filePath),
                mime_content_type($filePath),
                $_SESSION['user_id']
            ]);
        }
        
        logActivity('إضافة سند', "تم إضافة سند: " . $_POST['document_type']);
        
        $pdo->commit();
        header('Location: documents.php?customer_id=' . $_POST['customer_id'] . '&success=added');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header('Location: documents.php?customer_id=' . $_POST['customer_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// تحديث سند
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
        
        logActivity('تحديث سند', "تم تحديث السند ID: " . $_POST['document_id']);
        
        header('Location: documents.php?customer_id=' . $_POST['customer_id'] . '&success=updated');
        exit;
        
    } catch(Exception $e) {
        header('Location: edit_document.php?id=' . $_POST['document_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// حذف سند
if(isset($_GET['delete'])) {
    try {
        $pdo->beginTransaction();
        
        $documentId = $_GET['delete'];
        
        $stmt = $pdo->prepare("SELECT file_path FROM official_documents WHERE id = ?");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT file_path FROM document_attachments WHERE document_id = ?");
        $stmt->execute([$documentId]);
        $attachments = $stmt->fetchAll();
        
        if($document['file_path'] && file_exists($document['file_path'])) {
            unlink($document['file_path']);
        }
        
        foreach($attachments as $attachment) {
            if($attachment['file_path'] && file_exists($attachment['file_path'])) {
                unlink($attachment['file_path']);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM official_documents WHERE id = ?");
        $stmt->execute([$documentId]);
        
        logActivity('حذف سند', "تم حذف السند ID: " . $documentId);
        
        $pdo->commit();
        header('Location: documents.php?customer_id=' . $_GET['customer_id'] . '&success=deleted');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header('Location: documents.php?customer_id=' . $_GET['customer_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// إضافة مرفق
if(isset($_POST['add_attachment'])) {
    try {
        if(!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
            throw new Exception('يرجى اختيار ملف');
        }
        
        $fileName = time() . '_' . basename($_FILES['file']['name']);
        $targetPath = $uploadDir . $fileName;
        
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        $fileType = mime_content_type($_FILES['file']['tmp_name']);
        
        if(!in_array($fileType, $allowedTypes)) {
            throw new Exception('نوع الملف غير مسموح');
        }
        
        if($_FILES['file']['size'] > 5242880) {
            throw new Exception('حجم الملف كبير');
        }
        
        if(!move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            throw new Exception('فشل رفع الملف');
        }
        
        $stmt = $pdo->prepare("INSERT INTO document_attachments 
                               (document_id, file_name, file_path, file_type, file_size, 
                               mime_type, description, uploaded_by) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['document_id'],
            basename($targetPath),
            $targetPath,
            pathinfo($targetPath, PATHINFO_EXTENSION),
            filesize($targetPath),
            mime_content_type($targetPath),
            $_POST['description'] ?? null,
            $_SESSION['user_id']
        ]);
        
        $stmt = $pdo->prepare("SELECT customer_id FROM official_documents WHERE id = ?");
        $stmt->execute([$_POST['document_id']]);
        $customerId = $stmt->fetchColumn();
        
        header('Location: documents.php?customer_id=' . $customerId . '&success=attachment_added');
        exit;
        
    } catch(Exception $e) {
        header('Location: view_document.php?id=' . $_POST['document_id'] . '&error=' . urlencode($e->getMessage()));
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
        
        header('Location: documents.php?customer_id=' . $attachment['customer_id'] . '&success=attachment_deleted');
        exit;
        
    } catch(Exception $e) {
        header('Location: documents.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}
?>