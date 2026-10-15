<?php
require_once __DIR__ . '/../config/helpers.php';
$adminUser = requireAdmin();
requireAdminPermission('settings');
$adminTitle = "إعدادات المنصة وطرق الدفع";
$activeAdminNav = "settings";
require_once __DIR__ . '/header.php';

$settings = getSettings();
?>

<div class="max-w-4xl space-y-6">
    <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm">
        <h2 class="text-base font-black text-navy-custom">إعدادات المنصة وسياسة القبول والتواصل</h2>
        <p class="text-xs text-slate-400">تعديل زر الواتساب، أرقام الدعم الفني، وسياسة قبول الطلاب الجدد</p>
    </div>

    <form id="settingsForm" onsubmit="submitSettings(event)" class="space-y-6">
        
        <!-- Subscription Model Banner (Codes Only) -->
        <div class="bg-gradient-to-r from-teal-50 to-emerald-50 p-6 rounded-3xl border border-teal-200/80 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-teal-600 text-white flex items-center justify-center text-xl shadow-md shadow-teal-600/20">
                <i class="fa-solid fa-key"></i>
            </div>
            <div>
                <h3 class="text-sm font-black text-[#0d3753]">نظام الاشتراك بالأكواد فقط (Active)</h3>
                <p class="text-xs text-slate-600 mt-0.5">تم إلغاء فودافون كاش والمحفظة بنجاح. تفعيل الكورسات والحصص للطلاب يتم حصرياً عبر أكواد التفعيل من صفحة <a href="codes.php" class="text-teal-700 underline font-bold">إدارة الأكواد</a> أو عبر الإضافة المباشرة من ملف الطالب.</p>
            </div>
        </div>

        <!-- WhatsApp Floating Button Card -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center text-lg">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-navy-custom">زر الواتساب العائم للدعم الفني</h3>
                        <p class="text-[11px] text-slate-400">إظهار أيقونة واتساب عائمة أسفل يسار الموقع لجميع الزوار</p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="whatsappFloatingEnabled" <?= ($settings['whatsappFloatingEnabled'] ?? 'true') === 'true' ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-custom"></div>
                </label>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">رقم الواتساب بالصيغة الدولية</label>
                <input type="text" id="whatsappFloatingNumber" value="<?= htmlspecialchars($settings['whatsappFloatingNumber'] ?? '201092037257') ?>" dir="ltr"
                       class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none">
                <span class="text-[10px] text-slate-400 mt-1 block">مثال: 201092037257 (بدون + أو مسافات)</span>
            </div>
        </div>

        <!-- Student Approvals Policy -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-black text-navy-custom">الموافقة التلقائية على الطلاب الجدد</h3>
                    <p class="text-[11px] text-slate-400">عند التعطيل، يصبح أي طالب يسجل نشطاً فوراً دون انتظار موافقة الأدمن اليدوية</p>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="requireApproval" <?= ($settings['requireApproval'] ?? 'false') === 'true' ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-custom"></div>
                </label>
            </div>
        </div>

        <button type="submit" id="saveSettingsBtn" class="px-8 py-3.5 bg-primary-custom hover:bg-teal-800 text-white font-extrabold text-sm rounded-2xl shadow-lg transition flex items-center gap-2">
            <i class="fa-solid fa-floppy-disk"></i> حفظ وتطبيق الإعدادات
        </button>

    </form>
</div>

<script>
function submitSettings(e) {
    e.preventDefault();
    const btn = document.getElementById('saveSettingsBtn');
    btn.disabled = true;

    const payload = {
        settings: {
            vodafoneCashEnabled: 'false',
            whatsappFloatingEnabled: document.getElementById('whatsappFloatingEnabled').checked ? 'true' : 'false',
            whatsappFloatingNumber: document.getElementById('whatsappFloatingNumber').value.trim(),
            requireApproval: document.getElementById('requireApproval').checked ? 'true' : 'false'
        }
    };

    fetch('/api/admin.php?action=update-settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('تم حفظ الإعدادات بنجاح', 'success');
            setTimeout(() => window.location.reload(), 700);
        } else {
            showToast(data.error || 'حدث خطأ', 'error');
            btn.disabled = false;
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
