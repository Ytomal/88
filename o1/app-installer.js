// تسجيل Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('✅ Service Worker registered:', registration);
                
                // التحقق من التحديثات
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // يوجد تحديث جديد
                            showUpdateNotification();
                        }
                    });
                });
            })
            .catch(error => {
                console.error('❌ Service Worker registration failed:', error);
            });
    });
}

// عرض إشعار التحديث
function showUpdateNotification() {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 15px;
        animation: slideUp 0.3s ease;
    `;
    
    notification.innerHTML = `
        <span>🔄 يوجد تحديث جديد للتطبيق</span>
        <button onclick="updateApp()" style="
            background: white;
            color: #667eea;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        ">تحديث الآن</button>
    `;
    
    document.body.appendChild(notification);
}

// تحديث التطبيق
function updateApp() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration().then(registration => {
            if (registration && registration.waiting) {
                registration.waiting.postMessage({ action: 'skipWaiting' });
                window.location.reload();
            }
        });
    }
}

// معالج تثبيت التطبيق
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    console.log('💾 التطبيق جاهز للتثبيت');
    e.preventDefault();
    deferredPrompt = e;
    
    // عرض زر التثبيت
    showInstallButton();
});

// عرض زر التثبيت
function showInstallButton() {
    const installButton = document.createElement('button');
    installButton.id = 'install-app-btn';
    installButton.innerHTML = '📱 تثبيت التطبيق';
    installButton.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 15px 25px;
        border-radius: 50px;
        cursor: pointer;
        font-size: 1em;
        font-weight: bold;
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        z-index: 9999;
        transition: all 0.3s ease;
    `;
    
    installButton.addEventListener('mouseenter', () => {
        installButton.style.transform = 'scale(1.05)';
        installButton.style.boxShadow = '0 8px 20px rgba(102, 126, 234, 0.6)';
    });
    
    installButton.addEventListener('mouseleave', () => {
        installButton.style.transform = 'scale(1)';
        installButton.style.boxShadow = '0 5px 15px rgba(102, 126, 234, 0.4)';
    });
    
    installButton.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('✅ المستخدم قبل التثبيت');
                installButton.remove();
            } else {
                console.log('❌ المستخدم رفض التثبيت');
            }
            
            deferredPrompt = null;
        }
    });
    
    document.body.appendChild(installButton);
}

// مراقبة حالة التثبيت
window.addEventListener('appinstalled', (e) => {
    console.log('✅ تم تثبيت التطبيق بنجاح');
    
    // إزالة زر التثبيت إذا كان موجوداً
    const installBtn = document.getElementById('install-app-btn');
    if (installBtn) {
        installBtn.remove();
    }
    
    // عرض رسالة نجاح
    showSuccessMessage('تم تثبيت التطبيق بنجاح! ✅');
});

// عرض رسالة نجاح
function showSuccessMessage(message) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #28a745;
        color: white;
        padding: 15px 30px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        z-index: 10000;
        animation: slideDown 0.3s ease;
    `;
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideUp 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// طلب إذن الإشعارات
async function requestNotificationPermission() {
    if ('Notification' in window && 'serviceWorker' in navigator) {
        const permission = await Notification.requestPermission();
        
        if (permission === 'granted') {
            console.log('✅ تم منح إذن الإشعارات');
            
            // الاشتراك في Push Notifications
            const registration = await navigator.serviceWorker.ready;
            
            try {
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array('YOUR_PUBLIC_VAPID_KEY')
                });
                
                console.log('✅ تم الاشتراك في الإشعارات:', subscription);
                
                // إرسال معلومات الاشتراك للخادم
                await fetch('/api/subscribe-notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(subscription)
                });
            } catch (error) {
                console.error('❌ فشل الاشتراك في الإشعارات:', error);
            }
        }
    }
}

// تحويل VAPID key
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    
    return outputArray;
}

// التحقق من حالة الاتصال
window.addEventListener('online', () => {
    showSuccessMessage('✅ تم استعادة الاتصال بالإنترنت');
});

window.addEventListener('offline', () => {
    showSuccessMessage('⚠️ لا يوجد اتصال بالإنترنت - وضع عدم الاتصال');
});

// طلب إذن الإشعارات عند تحميل الصفحة (اختياري)
// window.addEventListener('load', () => {
//     setTimeout(() => {
//         requestNotificationPermission();
//     }, 5000); // بعد 5 ثوانٍ من التحميل
// });

// إضافة أنماط الحركة
const style = document.createElement('style');
style.textContent = `
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px) translateX(-50%);
        }
        to {
            opacity: 1;
            transform: translateY(0) translateX(-50%);
        }
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px) translateX(-50%);
        }
        to {
            opacity: 1;
            transform: translateY(0) translateX(-50%);
        }
    }
`;
document.head.appendChild(style);