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
                <a href="/parent.php" class="text-xs font-bold text-[#0f766e] hover:underline pt-2">
                    <i class="fa-solid fa-user-shield ml-1"></i> مساحة ولي الأمر
                </a>
            </div>

        </div>
    </div>
</footer>

<!-- Floating WhatsApp Button -->
<?php if ($whatsappEnabled && !empty($whatsappNumber)): ?>
<a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsappNumber) ?>?text=<?= urlencode('السلام عليكم، استفسار بخصوص منصة أ. محمد يوسف') ?>" 
   target="_blank" 
   class="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg transition-all duration-300 hover:scale-110 hover:shadow-xl hover:bg-emerald-600 group"
   title="تواصل معنا عبر واتساب">
    <i class="fa-brands fa-whatsapp text-3xl"></i>
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
