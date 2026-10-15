<?php
$pageTitle = "تسجيل طالب جديد - منصة الأستاذ محمد يوسف";
$activeNav = "register";
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$years = getCachedYears($db);
?>

<div class="flex-1 flex items-center justify-center py-16 px-4 bg-slate-50">
    <div class="w-full max-w-lg bg-white rounded-3xl shadow-xl border border-slate-200/80 p-8 space-y-6">
        <!-- Header -->
        <div class="text-center space-y-2">
            <h2 class="text-2xl font-black text-navy-custom">إنشاء حساب طالب جديد</h2>
            <p class="text-xs text-slate-500 font-medium">سجل الآن للوصول إلى الحصص والمذكرات والاختبارات التفاعلية</p>
        </div>

        <form id="regForm" onsubmit="submitRegister(event)" class="space-y-4">
            <div id="regAlert" class="hidden p-3 rounded-xl text-xs font-bold bg-red-50 text-red-700 border border-red-200"></div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الكامل (ثلاثي أو رباعي)</label>
                <input type="text" id="fullName" required placeholder="مثال: أحمد محمد علي"
                       class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:bg-white focus:border-primary-custom focus:ring-2 focus:ring-primary-custom/20 outline-none transition">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم هاتف الطالب (للدخول)</label>
                    <input type="text" id="phone" required placeholder="010XXXXXXXX" dir="ltr"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:bg-white focus:border-primary-custom focus:ring-2 focus:ring-primary-custom/20 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم هاتف ولي الأمر</label>
                    <input type="text" id="parentPhone" required placeholder="010XXXXXXXX" dir="ltr"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:bg-white focus:border-primary-custom focus:ring-2 focus:ring-primary-custom/20 outline-none transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الصف الدراسي</label>
                    <select id="yearId" required
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:bg-white focus:border-primary-custom focus:ring-2 focus:ring-primary-custom/20 outline-none transition">
                        <option value="">اختر الصف الدراسي...</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= htmlspecialchars($y['id']) ?>"><?= htmlspecialchars($y['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">كلمة المرور</label>
                    <input type="password" id="password" required placeholder="••••••••" dir="ltr"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:bg-white focus:border-primary-custom focus:ring-2 focus:ring-primary-custom/20 outline-none transition">
                </div>
            </div>

            <div class="p-3.5 bg-amber-50/80 border border-amber-200/90 rounded-2xl space-y-2">
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-xs font-bold text-amber-950">
                        <i class="fa-solid fa-building-columns text-amber-600"></i>
                        <span>كود طالب السنتر (خاص بطلاب السنتر - اختياري)</span>
                    </label>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-200/70 text-amber-900">اختياري</span>
                </div>
                <p class="text-[11px] text-amber-800 leading-relaxed font-medium">إذا كنت تحضر في السنتر، يرجى كتابة كودك المسلم لك بالسنتر ليتعرف الأستاذ على حسابك كطالب سنتر.</p>
                <input type="text" id="centerCode" placeholder="مثال: C-105 أو كودك بالسنتر (اتركه فارغاً إذا كنت أونلاين فقط)"
                       class="w-full px-4 py-2.5 bg-white border border-amber-300 rounded-xl text-sm font-bold text-slate-800 placeholder-slate-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition">
            </div>

            <div class="p-3 rounded-xl bg-teal-50 border border-teal-200/60 text-xs text-teal-800 font-medium">
                <i class="fa-solid fa-circle-info ml-1 text-primary-custom"></i> التفعيل فوري ومباشر، ستتمكن من الدخول وشحن الكود مباشرة بعد الضغط على إنشاء الحساب.
            </div>

            <button type="submit" id="regBtn" class="w-full py-3.5 bg-primary-custom hover:bg-teal-800 text-white font-extrabold text-sm rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                <span>تأكيد وإنشاء الحساب</span>
                <i class="fa-solid fa-user-plus text-xs"></i>
            </button>
        </form>

        <div class="text-center pt-2">
            <span class="text-xs text-slate-500">لديك حساب بالفعل؟</span>
            <a href="/login.php" class="text-xs font-bold text-primary-custom hover:underline mr-1">سجل دخول الآن</a>
        </div>
    </div>
</div>

<script>
function submitRegister(e) {
    e.preventDefault();
    const btn = document.getElementById('regBtn');
    const alertBox = document.getElementById('regAlert');
    alertBox.classList.add('hidden');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> جاري الإنشاء...';

    const payload = {
        fullName: document.getElementById('fullName').value,
        phone: document.getElementById('phone').value,
        parentPhone: document.getElementById('parentPhone').value,
        yearId: document.getElementById('yearId').value,
        password: document.getElementById('password').value,
        centerCode: document.getElementById('centerCode') ? document.getElementById('centerCode').value.trim() : ''
    };

    fetch('/api/auth.php?action=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.href = data.redirect || '/profile.php';
            }, 700);
        } else {
            alertBox.textContent = data.error || 'حدث خطأ أثناء التسجيل';
            alertBox.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = '<span>تأكيد وإنشاء الحساب</span> <i class="fa-solid fa-user-plus text-xs"></i>';
        }
    })
    .catch(err => {
        alertBox.textContent = 'تعذر الاتصال بالخادم، يرجى المحاولة لاحقاً';
        alertBox.classList.remove('hidden');
        btn.disabled = false;
        btn.innerHTML = '<span>تأكيد وإنشاء الحساب</span> <i class="fa-solid fa-user-plus text-xs"></i>';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
