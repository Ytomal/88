<?php
require_once 'config.php';
checkLogin();

// جلب المناطق
$regions = $pdo->query("SELECT * FROM regions WHERE status = 'active' ORDER BY region_name")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة عميل جديد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="professional_style.css">
    <style>
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .branch-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
            background: #f8f9fa;
        }
        .branch-card .remove-btn {
            position: absolute;
            top: 10px;
            left: 10px;
        }
        .required-label::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-user-plus text-primary"></i> إضافة عميل جديد</h2>
                    <p class="text-muted">أدخل بيانات العميل الجديد</p>
                </div>
                <a href="customers.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i> العودة للقائمة
                </a>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="customer_action.php" method="POST" id="customerForm">
                <!-- المعلومات الأساسية -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-info-circle"></i>
                        <h5 class="mb-0">المعلومات الأساسية</h5>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required-label">اسم الشركة/المؤسسة</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-building"></i></span>
                                <input type="text" name="company_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required-label">نوع الشركة</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                <select name="company_type" class="form-control" required>
                                    <option value="">اختر النوع</option>
                                    <option value="individual_institution">مؤسسة فردية</option>
                                    <option value="large_institution">مؤسسة كبيرة</option>
                                    <option value="company">شركة</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required-label">اسم المالك</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" name="owner_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">الشخص المسؤول</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                <input type="text" name="responsible_person" class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label required-label">المنطقة</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <select name="region_id" class="form-control" required>
                                    <option value="">اختر المنطقة</option>
                                    <?php foreach($regions as $region): ?>
                                        <option value="<?= $region['id'] ?>"><?= $region['region_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="text" name="phone" class="form-control" placeholder="05xxxxxxxx">
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="example@domain.com">
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">تاريخ البداية</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" name="start_date" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">العنوان التفصيلي</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-location-dot"></i></span>
                            <textarea name="address" class="form-control" rows="2" placeholder="المدينة، الحي، الشارع..."></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-sticky-note"></i></span>
                            <textarea name="notes" class="form-control" rows="3" placeholder="أي ملاحظات إضافية..."></textarea>
                        </div>
                    </div>

                    <div class="form-check form-switch">
                        <input type="checkbox" name="has_branches" id="has_branches" class="form-check-input" value="1">
                        <label class="form-check-label" for="has_branches">
                            <i class="fas fa-building"></i> لدى العميل فروع متعددة
                        </label>
                    </div>
                </div>

                <!-- قسم الفروع -->
                <div class="form-section" id="branchesSection" style="display: none;">
                    <div class="section-header">
                        <i class="fas fa-building"></i>
                        <h5 class="mb-0">فروع العميل</h5>
                    </div>
                    
                    <div id="branchesContainer"></div>
                    
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addBranch()">
                        <i class="fas fa-plus"></i> إضافة فرع جديد
                    </button>
                </div>

                <!-- أزرار الحفظ -->
                <div class="text-center">
                    <button type="submit" name="add_customer" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> حفظ العميل
                    </button>
                    <a href="customers.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let branchCount = 0;
        const regionsOptions = `<?php 
            foreach($regions as $r) {
                echo "<option value='{$r['id']}'>{$r['region_name']}</option>";
            }
        ?>`;
        
        // إظهار/إخفاء قسم الفروع
        document.getElementById('has_branches').addEventListener('change', function() {
            const branchesSection = document.getElementById('branchesSection');
            branchesSection.style.display = this.checked ? 'block' : 'none';
            
            if(this.checked && branchCount === 0) {
                addBranch();
            }
        });

        // إضافة فرع جديد
        function addBranch() {
            branchCount++;
            
            const branchHTML = `
                <div class="branch-card" id="branch${branchCount}">
                    <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeBranch(${branchCount})">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <h6 class="mb-3"><i class="fas fa-building"></i> الفرع ${branchCount}</h6>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">اسم الفرع</label>
                            <input type="text" name="branches[${branchCount}][branch_name]" class="form-control" placeholder="فرع الرياض">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">كود الفرع</label>
                            <input type="text" name="branches[${branchCount}][branch_code]" class="form-control" placeholder="BR001">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">منطقة الفرع</label>
                            <select name="branches[${branchCount}][region_id]" class="form-control">
                                <option value="">اختر المنطقة</option>
                                ${regionsOptions}
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">هاتف الفرع</label>
                            <input type="text" name="branches[${branchCount}][phone]" class="form-control" placeholder="05xxxxxxxx">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">بريد الفرع</label>
                            <input type="email" name="branches[${branchCount}][email]" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">مدير الفرع</label>
                            <input type="text" name="branches[${branchCount}][manager_name]" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">عنوان الفرع</label>
                        <input type="text" name="branches[${branchCount}][address]" class="form-control" placeholder="المدينة، الحي، الشارع">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">عدد الفتحات</label>
                            <input type="number" name="branches[${branchCount}][shop_fronts_count]" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">رابط خرائط جوجل</label>
                            <input type="url" name="branches[${branchCount}][google_maps_url]" class="form-control" 
                                   placeholder="https://goo.gl/maps/...">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">وصف المحل</label>
                        <textarea name="branches[${branchCount}][shop_description]" class="form-control" rows="2" 
                                  placeholder="محل بفتحتين على الشارع الرئيسي..."></textarea>
                    </div>
                </div>
            `;
            
            document.getElementById('branchesContainer').insertAdjacentHTML('beforeend', branchHTML);
        }

        // حذف فرع
        function removeBranch(id) {
            if(confirm('هل تريد حذف هذا الفرع؟')) {
                document.getElementById('branch' + id).remove();
            }
        }

        // التحقق من النموذج قبل الإرسال
        document.getElementById('customerForm').addEventListener('submit', function(e) {
            const companyName = document.querySelector('input[name="company_name"]').value.trim();
            const ownerName = document.querySelector('input[name="owner_name"]').value.trim();
            const regionId = document.querySelector('select[name="region_id"]').value;
            
            if(!companyName || !ownerName || !regionId) {
                e.preventDefault();
                alert('يرجى ملء جميع الحقول المطلوبة!');
                return false;
            }
        });
    </script>
</body>
</html>