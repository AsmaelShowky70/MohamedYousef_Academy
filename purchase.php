<?php
$pageTitle = "شراء واشتراك في الكورس - منصة الأستاذ محمد يوسف";
require_once __DIR__ . '/includes/header.php';

if (!$currentUser) {
    header("Location: /login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$db = getDB();
$courseId = trim($_GET['id'] ?? '');

if (!$courseId) {
    header("Location: /courses.php");
    exit;
}

// Fetch Course
$stmt = $db->prepare("SELECT * FROM Course WHERE id = ? LIMIT 1");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    echo '<div class="min-h-screen flex items-center justify-center text-center p-6"><h1 class="text-3xl font-bold text-slate-800">الكورس غير موجود</h1></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Check single-lesson request
$collectionId = trim($_GET['collectionId'] ?? '');
$collection = null;
if ($collectionId) {
    $stmtCol = $db->prepare("SELECT * FROM Collection WHERE id = ? AND courseId = ? LIMIT 1");
    $stmtCol->execute([$collectionId, $courseId]);
    $collection = $stmtCol->fetch();
}
$isLesson = ($collection !== null);

// Check if already enrolled
if ($isLesson) {
    $chkColCode = $db->prepare("
        SELECT 1 FROM _CollectionToUser WHERE A = ? AND B = ? 
        UNION 
        SELECT 1 FROM CourseCode WHERE userId = ? AND collectionId = ? AND expires > NOW()
        UNION
        SELECT 1 FROM CourseCode WHERE userId = ? AND courseId = ? AND (collectionId IS NULL OR collectionId = '') AND expires > NOW()
        LIMIT 1
    ");
    $chkColCode->execute([$collection['id'], $currentUser['id'], $currentUser['id'], $collection['id'], $currentUser['id'], $courseId]);
    $alreadyEnrolled = (bool)$chkColCode->fetch();
    $price = (float)$collection['price'];
} else {
    $chkCode = $db->prepare("SELECT id FROM CourseCode WHERE userId = ? AND courseId = ? AND (collectionId IS NULL OR collectionId = '') AND expires > NOW() LIMIT 1");
    $chkCode->execute([$currentUser['id'], $courseId]);
    $alreadyEnrolled = (bool)$chkCode->fetch();
    $price = (float)$course['price'];
}

// Fetch settings
$vfcNumber = getSetting('vodafoneCashPhoneNumber', '01092037257');
$vfcEnabled = getSetting('vodafoneCashEnabled', 'true') === 'true';
$walletBalance = (float)($currentUser['walletBalance'] ?? 0);
?>

<div class="min-h-screen bg-slate-50/60 pb-36">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-24">
        
        <?php if ($alreadyEnrolled): ?>
            <!-- Already Enrolled Banner -->
            <div class="my-12 rounded-3xl bg-white border border-emerald-200 p-8 md:p-12 text-center shadow-lg max-w-2xl mx-auto">
                <div class="w-20 h-20 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-5 text-3xl">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-900 mb-2">
                    <?= $isLesson ? 'أنت مشترك بالفعل في هذه الحصة!' : 'أنت مشترك بالفعل في هذا الكورس!' ?>
                </h2>
                <p class="text-slate-500 text-sm mb-6">
                    <?= $isLesson ? 'اشتراكك في حصة (' . htmlspecialchars($collection['title']) . ') مفعل وساري المفعول، يمكنك البدء بمشاهدتها فوراً.' : 'اشتراكك مفعل وساري المفعول، يمكنك البدء بمشاهدة الدروس فوراً.' ?>
                </p>
                <a href="/course.php?id=<?= urlencode($courseId) ?>" class="inline-flex items-center gap-2 px-8 py-3.5 rounded-xl bg-[#0f766e] text-white font-black text-sm hover:bg-[#0d635c] transition shadow-md">
                    <i class="fa-solid fa-play"></i>
                    <span>الانتقال لمحتوى <?= $isLesson ? 'الحصة' : 'الكورس' ?></span>
                </a>
            </div>
        <?php else: ?>

            <!-- Course Header Image -->
            <div class="flex w-full justify-center mb-8">
                <div class="max-w-2xl w-full aspect-video rounded-2xl overflow-hidden shadow-2xl border border-slate-200/80 bg-slate-100">
                    <img 
                        src="<?= htmlspecialchars($course['thumbnail'] ?: '/public/identity/logo.png') ?>" 
                        alt="<?= htmlspecialchars($course['title']) ?>" 
                        class="w-full h-full object-cover"
                    />
                </div>
            </div>

            <!-- Checkout Details & Actions Grid -->
            <div class="relative flex flex-col gap-8 lg:flex-row items-start justify-between">
                
                <!-- Right Side: Course Details (Expanded) -->
                <div class="flex flex-col gap-5 rounded-2xl bg-white p-6 md:p-8 shadow-sm border border-slate-200/80 flex-grow w-full lg:w-3/5">
                    <div class="border-b border-slate-100 pb-4">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold <?= $isLesson ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-teal-50 text-[#0f766e] border-teal-100' ?> mb-3 border">
                            <?= $isLesson ? '🎯 حصة دراسية منفصلة' : 'كورس تفاعلي' ?>
                        </span>
                        <h1 class="text-2xl md:text-3xl font-black text-[#0d3753]">
                            <?= $isLesson ? 'حصة: ' . htmlspecialchars($collection['title']) : htmlspecialchars($course['title']) ?>
                        </h1>
                        <?php if ($isLesson): ?>
                            <span class="block text-xs font-bold text-slate-500 mt-1.5">
                                من كورس: <?= htmlspecialchars($course['title']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-3">
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-circle-info text-[#c79a32]"></i>
                            <span><?= $isLesson ? 'تفاصيل الحصة' : 'تفاصيل الكورس' ?></span>
                        </h2>
                        <p class="text-slate-600 text-sm leading-relaxed font-medium">
                            <?= nl2br(htmlspecialchars($course['description'] ?: 'شرح تفصيلي للمنهج مع تدريبات وامتحانات دورية وتصحيح تفاعلي ومتابعة مستمرة مع الأستاذ محمد يوسف.')) ?>
                        </p>
                    </div>

                    <div class="pt-4 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs font-bold text-slate-600">
                        <div class="flex items-center gap-2 p-3 rounded-xl bg-slate-50">
                            <i class="fa-solid fa-video text-[#0f766e]"></i>
                            <span>محاضرات فيديو عالية الدقة</span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl bg-slate-50">
                            <i class="fa-solid fa-file-pdf text-[#0f766e]"></i>
                            <span>مذكرات وملفات PDF</span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl bg-slate-50">
                            <i class="fa-solid fa-clock-rotate-left text-[#0f766e]"></i>
                            <span>صلاحية مستمرة للعام الدراسي</span>
                        </div>
                    </div>
                </div>

                <!-- Left Side: Order Summary Card -->
                <div class="flex min-w-full lg:min-w-[420px] lg:w-2/5 flex-col gap-6">
                    <div class="rounded-2xl bg-white p-6 md:p-8 shadow-xl border border-slate-200/80 space-y-6">
                        
                        <h3 class="text-xl font-black text-[#0d3753] border-b border-slate-100 pb-3 flex items-center justify-between">
                            <span>ملخص الطلبية</span>
                            <i class="fa-solid fa-receipt text-slate-400"></i>
                        </h3>

                        <!-- Price Breakdown -->
                        <div class="space-y-3 text-sm font-bold text-slate-700">
                            <div class="flex items-center justify-between">
                                <span>سعر <?= $isLesson ? 'الحصة' : 'الكورس' ?></span>
                                <span class="font-mono text-base"><?= number_format($price, 2) ?> ج.م</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <span>خصم</span>
                                <span class="font-mono text-[#c79a32]">-0.00 ج.م</span>
                            </div>
                            <div class="h-px w-full bg-slate-100 my-2"></div>
                            <div class="flex items-center justify-between text-base font-black text-slate-900">
                                <span>السعر النهائي</span>
                                <span class="font-mono text-xl text-[#0f766e]"><?= number_format($price, 2) ?> ج.م</span>
                            </div>
                        </div>

                        <!-- Code-Only Activation Container (Requirement #6) -->
                        <div class="space-y-4 pt-2">
                            <div class="rounded-2xl border-2 border-[#0f766e]/30 bg-white p-5 md:p-6 space-y-4 shadow-sm">
                                <div class="flex items-center gap-2.5 text-[#0d3753] font-black text-sm">
                                    <div class="w-8 h-8 rounded-xl bg-teal-50 text-[#0f766e] flex items-center justify-center">
                                        <i class="fa-solid fa-key text-base"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-black text-slate-800"><?= $isLesson ? 'تفعيل فوري بكود الحصة' : 'تفعيل فوري بكود الكورس' ?></h4>
                                        <p class="text-[11px] text-slate-400 font-medium">أدخل الكود المستلم من السنتر أو خدمة العملاء</p>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="activationCodeInput" 
                                            placeholder="STU-XXXXXX أو HSA-XXXXXX" 
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-mono font-black uppercase outline-none focus:border-[#0f766e] focus:bg-white focus:ring-2 focus:ring-[#0f766e]/20 text-center tracking-widest transition"
                                            dir="ltr"
                                        />
                                    </div>

                                    <button 
                                        id="activateCodeBtn" 
                                        onclick="activateViaCode()" 
                                        class="w-full py-3.5 rounded-xl bg-[#0f766e] hover:bg-[#0d3753] text-white font-black text-sm shadow-md shadow-[#0f766e]/20 transition duration-300 flex items-center justify-center gap-2"
                                    >
                                        <i class="fa-solid fa-bolt"></i>
                                        <span>تفعيل المحتوى الآن</span>
                                    </button>
                                </div>

                                <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
                                    <span class="text-slate-500 font-semibold">ليس لديك كود بعد؟</span>
                                    <a 
                                        href="https://wa.me/201092037257?text=<?= urlencode('السلام عليكم، أود الحصول على كود تفعيل: ' . ($isLesson ? $collection['title'] . ' في ' : '') . $course['title']) ?>" 
                                        target="_blank" 
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold border border-emerald-200 transition"
                                    >
                                        <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                                        <span>طلب كود عبر واتساب الدعم</span>
                                    </a>
                                </div>
                            </div>

                            <?php if ($isLesson): ?>
                                <div class="pt-2 text-center">
                                    <a href="/purchase.php?id=<?= urlencode($courseId) ?>" class="text-xs text-[#0f766e] hover:underline font-bold inline-flex items-center gap-1.5">
                                        <i class="fa-solid fa-cart-shopping"></i>
                                        <span>أو تفعيل الكورس كاملاً بـ <?= number_format($course['price'], 0) ?> ج.م</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

            </div>

        <?php endif; ?>

    </div>
</div>

<script>
const courseId = <?= json_encode($courseId) ?>;
const collectionId = <?= json_encode($collectionId ?: null) ?>;
const isLesson = <?= json_encode($isLesson) ?>;

// 1. Pay from wallet
async function payViaWallet() {
    const confirmMsg = isLesson 
        ? 'هل تريد بالتأكيد خصم سعر الحصة من رصيد محفظتك؟' 
        : 'هل تريد بالتأكيد خصم سعر الكورس من رصيد محفظتك؟';
    if (!confirm(confirmMsg)) return;
    const btn = document.getElementById('walletPayBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري الدفع...';

    try {
        const payload = { courseId: courseId };
        if (collectionId) payload.collectionId = collectionId;

        const res = await fetch('/api/custom.php?action=pay-from-wallet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.href = '/course.php?id=' + encodeURIComponent(courseId);
            }, 1500);
        } else {
            showToast(data.error || 'فشل الدفع من المحفظة', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-wallet"></i><span>الدفع من المحفظة</span>';
        }
    } catch (err) {
        showToast('حدث خطأ أثناء الاتصال بالخادم', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-wallet"></i><span>الدفع من المحفظة</span>';
    }
}

// 2. Vodafone Cash confirmation
async function confirmVFCPayment() {
    const phoneInput = document.getElementById('vfcSenderPhone');
    const senderPhone = phoneInput.value.trim();
    if (!senderPhone) {
        showToast('الرجاء إدخال رقم الهاتف المحول منه', 'error');
        phoneInput.focus();
        return;
    }

    const btn = document.getElementById('vfcConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري التحقق...';

    try {
        const payload = { courseId: courseId, senderPhone: senderPhone };
        if (collectionId) payload.collectionId = collectionId;

        const res = await fetch('/api/custom.php?action=check-vfc-payment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.href = '/course.php?id=' + encodeURIComponent(courseId);
            }, 1800);
        } else {
            showToast(data.error || 'لم يتم تأكيد التحويل', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check"></i><span>تأكيد الدفع والتفعيل</span>';
        }
    } catch (err) {
        showToast('حدث خطأ أثناء التحقق من الدفع', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i><span>تأكيد الدفع والتفعيل</span>';
    }
}

// 3. Activate code
async function activateViaCode() {
    const input = document.getElementById('activationCodeInput');
    const code = input.value.trim().toUpperCase();
    if (!code) {
        showToast('يرجى إدخال كود التفعيل', 'error');
        input.focus();
        return;
    }

    const btn = document.getElementById('activateCodeBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    try {
        const res = await fetch('/api/custom.php?action=activate-code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.href = '/course.php?id=' + encodeURIComponent(data.courseId || courseId);
            }, 1500);
        } else {
            showToast(data.error || 'الكود غير صالح', 'error');
            btn.disabled = false;
            btn.innerHTML = 'تفعيل';
        }
    } catch (err) {
        showToast('حدث خطأ أثناء تفعيل الكود', 'error');
        btn.disabled = false;
        btn.innerHTML = 'تفعيل';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
