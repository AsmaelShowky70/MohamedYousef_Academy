<?php
$whatsappNumber = getSetting('whatsappFloatingNumber', '201092037257');
$whatsappEnabled = getSetting('whatsappFloatingEnabled', 'true') === 'true';
?>

<!-- Footer Matching Original Next.js Design -->
<footer class="relative z-10 -mt-20 md:-mt-28 w-full bg-[#f4fafa] pb-12 pt-20 md:pt-28 text-slate-800 border-t border-slate-200/80 bg-cover bg-center bg-no-repeat" style="background-image: url('/public/designs/footer/footer-bg.svg');">
    <div class="w-full px-6 lg:px-14">
        <div class="grid gap-10 text-right grid-cols-2 md:grid-cols-6 max-w-7xl mx-auto">
            
            <!-- Col 1: Brand & Credits -->
            <div class="flex flex-col items-center space-y-4 max-md:text-center md:items-start col-span-2 md:col-span-3">
                <a href="/index.php" class="inline-block">
                    <img src="/public/identity/logo.png" alt="شعار أ. محمد يوسف" class="h-16 w-auto object-contain" priority/>
                </a>
                <div class="text-xs text-slate-600 dark:text-slate-400 leading-loose">
                    <p class="font-bold text-slate-800 dark:text-slate-200">جميع الحقوق محفوظة © <?= date('Y') ?> لمنصة الأستاذ محمد يوسف</p>
                </div>
            </div>

            <!-- Col 2: Pages -->
            <div class="flex flex-col space-y-3 max-md:items-center">
                <h3 class="text-base font-black text-[#d2155a]">الصفحات</h3>
                <a href="/index.php" class="text-xs sm:text-sm font-semibold text-slate-600 transition-colors hover:text-[#d2155a]">الرئيسية</a>
                <a href="/about-us.php" class="text-xs sm:text-sm font-semibold text-slate-600 transition-colors hover:text-[#d2155a]">من نحن</a>
                <a href="/privacy.php" class="text-xs sm:text-sm font-semibold text-slate-600 transition-colors hover:text-[#d2155a]">سياسة الخصوصية</a>
                <a href="/terms.php" class="text-xs sm:text-sm font-semibold text-slate-600 transition-colors hover:text-[#d2155a]">الشروط والأحكام</a>
                <a href="/exchange.php" class="text-xs sm:text-sm font-semibold text-slate-600 transition-colors hover:text-[#d2155a]">سياسة الاستبدال والاسترجاع</a>
            </div>

            <!-- Col 3: Social Media -->
            <div class="flex flex-col space-y-3 max-md:items-center">
                <h3 class="text-base font-black text-[#d2155a]">السوشيال ميديا</h3>
                <a href="https://youtube.com/@mohamedyoussef-arabic?si=wCtbJvUBEZCWiIy7" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-xs sm:text-sm font-semibold text-slate-600 transition-colors hover:text-[#d2155a]">
                    <i class="fa-brands fa-youtube text-red-600 text-lg"></i>
                    <span>يوتيوب</span>
                </a>
                <a href="https://www.facebook.com/share/1JmfgFeh8d/" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-xs sm:text-sm font-semibold text-slate-600 transition-colors hover:text-[#d2155a]">
                    <i class="fa-brands fa-facebook text-[#1877F2] text-lg"></i>
                    <span>فيسبوك</span>
                </a>
            </div>

            <!-- Col 4: Contact & Support -->
            <div class="flex flex-col space-y-3 max-md:items-center">
                <h3 class="text-base font-black text-[#d2155a]">تواصل معنا</h3>
                <a href="/contact-us.php" class="inline-block w-fit rounded-xl bg-[#5e6e85] px-6 py-2.5 text-center text-xs font-bold text-white shadow-md transition-all hover:bg-[#4f5d72]">
                    الدعم الفني
                </a>
                <a class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-center text-xs font-bold text-white shadow-md transition-all mt-1 hover:brightness-110 active:scale-95" 
                   style="background-color: #25D366 !important; color: #ffffff !important; text-decoration: none !important;" 
                   href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsappNumber) ?>" 
                   target="_blank" 
                   rel="noopener noreferrer">
                    <i class="fa-brands fa-whatsapp text-base" style="color: #ffffff !important;"></i>
                    <span style="color: #ffffff !important; font-weight: 800; font-size: 13px;">01092037257</span>
                </a>
                <a href="/parent.php" class="text-xs font-bold text-[#0f766e] hover:underline pt-2">
                    <i class="fa-solid fa-user-shield ml-1"></i> مساحة ولي الأمر
                </a>
            </div>

        </div>
    </div>
</footer>

<!-- Floating WhatsApp Button & Dedicated Styling -->
<style>
.wa-float-btn {
    position: fixed !important;
    bottom: 24px !important;
    right: 24px !important;
    z-index: 99999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 60px !important;
    height: 60px !important;
    background-color: #25D366 !important;
    color: #ffffff !important;
    border-radius: 50% !important;
    box-shadow: 0 4px 20px rgba(37, 211, 102, 0.55), 0 2px 8px rgba(0, 0, 0, 0.2) !important;
    text-decoration: none !important;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
    cursor: pointer !important;
}
.wa-float-btn:hover {
    background-color: #128C7E !important;
    color: #ffffff !important;
    transform: scale(1.12) !important;
    box-shadow: 0 8px 28px rgba(37, 211, 102, 0.7), 0 4px 12px rgba(0, 0, 0, 0.25) !important;
}
.wa-float-btn i, .wa-float-btn svg {
    font-size: 32px !important;
    width: 32px !important;
    height: 32px !important;
    color: #ffffff !important;
    fill: #ffffff !important;
}
.wa-ripple {
    position: absolute !important;
    top: -5px !important;
    left: -5px !important;
    right: -5px !important;
    bottom: -5px !important;
    border-radius: 50% !important;
    border: 2px solid #25D366 !important;
    animation: wa-ripple-anim 1.8s infinite cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
    pointer-events: none !important;
}
@keyframes wa-ripple-anim {
    0% { transform: scale(0.95); opacity: 0.9; }
    50% { transform: scale(1.22); opacity: 0.3; }
    100% { transform: scale(1.4); opacity: 0; }
}
</style>

<?php if ($whatsappEnabled && !empty($whatsappNumber)): ?>
<a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsappNumber) ?>?text=<?= urlencode('السلام عليكم، استفسار بخصوص منصة أ. محمد يوسف') ?>" 
   target="_blank" 
   rel="noopener noreferrer"
   class="wa-float-btn" 
   title="تواصل معنا عبر واتساب"
   aria-label="واتساب">
    <span class="wa-ripple"></span>
    <i class="fa-brands fa-whatsapp"></i>
</a>
<?php endif; ?>

<!-- Toast Notification Utility -->
<div id="toast-container" class="fixed top-5 right-5 z-[9999] flex flex-col gap-2 pointer-events-none"></div>

<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const bgClass = type === 'success' ? 'bg-emerald-600' : (type === 'error' ? 'bg-red-600' : 'bg-slate-800');
    const icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-triangle-exclamation' : 'fa-info-circle');
    
    toast.className = `${bgClass} text-white px-5 py-3 rounded-xl shadow-xl flex items-center gap-3 text-sm font-bold pointer-events-auto transform transition-all duration-300 translate-x-10 opacity-0`;
    toast.innerHTML = `<i class="fa-solid ${icon}"></i><span>${message}</span>`;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-10', 'opacity-0');
    }, 20);
    
    setTimeout(() => {
        toast.classList.add('translate-x-10', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function handleLogout() {
    if (!confirm('هل أنت متأكد من تسجيل الخروج؟')) return;
    fetch('/api/auth.php?action=logout', { method: 'POST' })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                window.location.href = '/login.php';
            }
        });
}
</script>

</body>
</html>
