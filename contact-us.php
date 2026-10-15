<?php
$pageTitle = "الدعم الفني والتواصل - منصة الأستاذ محمد يوسف";
require_once __DIR__ . '/includes/header.php';
$vfcNumber = getSetting('whatsappFloatingNumber', '201092037257');
$cleanVfc = preg_replace('/[^0-9]/', '', $vfcNumber);
if (strpos($cleanVfc, '20') === 0) $displayPhone = '0' . substr($cleanVfc, 2);
else $displayPhone = $cleanVfc;
?>

<div dir="rtl" class="relative min-h-[calc(100vh-15rem)] bg-gradient-to-b from-slate-50 via-slate-50 to-teal-50/20 pt-24 pb-36 md:pb-44 px-4 sm:px-6 lg:px-8">
    <div class="absolute top-1/4 left-10 -z-10 h-72 w-72 rounded-full bg-[#0f766e]/5 blur-3xl"></div>
    <div class="absolute bottom-1/4 right-10 -z-10 h-80 w-80 rounded-full bg-[#0d3753]/5 blur-3xl"></div>
    
    <div class="mx-auto max-w-6xl">
        <div class="text-center mb-16">
            <h1 class="text-4xl font-black sm:text-5xl tracking-tight bg-gradient-to-r from-[#0f766e] to-[#0d3753] bg-clip-text text-transparent mb-4">
                الدعم الفني والتواصل
            </h1>
            <p class="mx-auto max-w-2xl text-base md:text-lg text-slate-600 leading-relaxed font-medium">
                يسعدنا دائماً خدمة طلابنا الأعزاء والإجابة على استفساراتكم. الرجاء التواصل مباشرة مع مسؤول الدعم الفني الموحد لسرعة حل مشكلتك.
            </p>
        </div>

        <div class="flex justify-center mb-16 animate-in fade-in duration-500">
            <div class="relative group rounded-3xl border border-slate-200/80 bg-white/90 backdrop-blur-md p-8 shadow-xl flex flex-col justify-between overflow-hidden max-w-md w-full">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
                
                <div>
                    <div class="mt-2 flex items-center justify-between mb-6">
                        <div class="p-3.5 rounded-2xl bg-[#0f766e]/10 text-[#0f766e] group-hover:scale-110 transition-transform duration-300 text-xl">
                            <i class="fa-solid fa-headset"></i>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-xl bg-gradient-to-r from-blue-500 to-indigo-600 text-white">
                            الدعم الفني
                        </span>
                    </div>

                    <h3 class="text-xl font-black mb-2 text-slate-900">فريق الدعم الفني الموحد</h3>
                    <p class="text-xs md:text-sm text-slate-500 mb-6 leading-relaxed">
                        تواصل مع مسؤول الدعم لحل مشاكل الأكواد، المحاضرات، الامتحانات، أو الحساب.
                    </p>

                    <div class="font-mono text-2xl font-black tracking-wider text-slate-900 mb-8 text-center bg-slate-50 py-3.5 rounded-2xl border border-slate-200/80 select-all" dir="ltr">
                        <?= htmlspecialchars($displayPhone) ?>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <a href="https://wa.me/<?= htmlspecialchars($cleanVfc) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center gap-2 w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm transition duration-200 shadow-md shadow-emerald-600/20 active:scale-[0.98]">
                        <i class="fa-brands fa-whatsapp text-lg"></i>
                        <span>مراسلة واتساب</span>
                    </a>
                    
                    <a href="tel:<?= htmlspecialchars($displayPhone) ?>" class="flex items-center justify-center gap-2 w-full py-3.5 rounded-2xl border border-slate-200 hover:border-[#0f766e] bg-slate-50 hover:bg-teal-50/50 text-slate-800 font-bold text-sm transition duration-200 active:scale-[0.98]">
                        <i class="fa-solid fa-phone text-xs text-[#0f766e]"></i>
                        <span>اتصال هاتفي</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
            <div class="rounded-3xl border border-slate-200/80 bg-white/80 backdrop-blur-sm p-6 flex gap-4 items-center shadow-sm">
                <div class="p-4 rounded-2xl bg-[#0f766e]/10 text-[#0f766e] text-2xl">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div>
                    <h4 class="font-black text-base md:text-lg text-slate-900 mb-1">البريد الإلكتروني</h4>
                    <p class="text-xs text-slate-500 mb-2">للاستفسارات العامة أو الاقتراحات ومشاكل الدفع</p>
                    <a href="mailto:i.aboelsoud21@gmail.com" class="text-[#0f766e] hover:underline font-bold text-xs md:text-sm transition">
                        i.aboelsoud21@gmail.com
                    </a>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200/80 bg-white/80 backdrop-blur-sm p-6 flex gap-4 items-center shadow-sm">
                <div class="p-4 rounded-2xl bg-[#0f766e]/10 text-[#0f766e] text-2xl">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <h4 class="font-black text-base md:text-lg text-slate-900 mb-1">أوقات الدعم الفني</h4>
                    <p class="text-xs text-slate-500 mb-2">نستقبل رسائلكم ومكالماتكم طوال أيام الأسبوع</p>
                    <span class="text-xs md:text-sm font-black text-slate-700">من الساعة 9:00 صباحاً حتى 9:00 مساءً</span>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
