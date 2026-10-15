<?php
$pageTitle = "من نحن - منصة الأستاذ محمد يوسف";
require_once __DIR__ . '/includes/header.php';
?>

<div dir="rtl" class="relative min-h-screen bg-gradient-to-b from-slate-50 via-slate-50 to-teal-50/20 pt-24 pb-36 md:pb-44 px-4 sm:px-6 lg:px-8 overflow-hidden">
    <div class="absolute top-10 left-10 -z-10 h-72 w-72 rounded-full bg-[#0f766e]/5 blur-3xl"></div>
    <div class="absolute bottom-10 right-10 -z-10 h-96 w-96 rounded-full bg-[#0d3753]/5 blur-3xl"></div>
    
    <div class="mx-auto max-w-5xl">
        <!-- Title & Subtitle -->
        <div class="text-center mb-16">
            <span class="inline-flex items-center gap-1.5 py-1 px-3.5 rounded-full text-xs font-bold bg-[#0f766e]/10 text-[#0f766e] mb-4">
                <i class="fa-solid fa-sparkles"></i>
                <span>تعرّف علينا</span>
            </span>
            <h1 class="text-4xl font-black sm:text-5xl tracking-tight bg-gradient-to-r from-[#0f766e] to-[#0d3753] bg-clip-text text-transparent mb-4 leading-tight">
                منصة الأستاذ محمد يوسف
            </h1>
            <p class="mx-auto max-w-2xl text-base md:text-lg text-slate-600 leading-relaxed font-medium">
                رحلتك نحو التميز والتفوق في اللغة العربية تبدأ من هنا. نقدم لك تعليماً تفاعلياً حديثاً يضمن لك فهم المادة وتخطي أصعب الامتحانات بسهولة.
            </p>
        </div>

        <!-- Mission & Vision Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
            <div class="rounded-3xl border border-slate-200/80 bg-white/90 backdrop-blur-md p-8 shadow-sm relative overflow-hidden group hover:shadow-xl transition duration-300">
                <div class="absolute top-0 left-0 w-1.5 h-full bg-[#0f766e]"></div>
                <h2 class="text-2xl font-black text-slate-900 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-bullseye text-[#0f766e]"></i>
                    <span>رسالتنا</span>
                </h2>
                <p class="text-slate-600 leading-relaxed text-sm md:text-base font-medium">
                    تمكين الطلاب وتزويدهم بالمعرفة والمهارات اللازمة لفهم وتذوق اللغة العربية، وكسر حاجز الخوف من قواعدها من خلال تقديم محتوى تعليمي تفاعلي عالي الجودة ومتاح للجميع في أي وقت ومن أي مكان.
                </p>
            </div>

            <div class="rounded-3xl border border-slate-200/80 bg-white/90 backdrop-blur-md p-8 shadow-sm relative overflow-hidden group hover:shadow-xl transition duration-300">
                <div class="absolute top-0 left-0 w-1.5 h-full bg-[#0d3753]"></div>
                <h2 class="text-2xl font-black text-slate-900 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-eye text-[#0d3753]"></i>
                    <span>رؤيتنا</span>
                </h2>
                <p class="text-slate-600 leading-relaxed text-sm md:text-base font-medium">
                    أن نكون المنصة التعليمية الرائدة والأولى في تقديم وتبسيط مناهج اللغة العربية للمرحلة الثانوية بمصر والعالم العربي، متبنين أفضل الوسائل والتقنيات التكنولوجية الحديثة في التعليم عن بعد.
                </p>
            </div>
        </div>

        <!-- Numbers / Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mb-16">
            <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 p-6 rounded-2xl text-center hover:bg-white hover:shadow-lg transition duration-300">
                <div class="mx-auto w-12 h-12 rounded-2xl flex items-center justify-center mb-4 text-blue-600 bg-blue-50 text-xl">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="text-3xl font-black text-slate-900 mb-1 font-mono">+3000</div>
                <div class="text-xs font-bold text-slate-500">طالب وطالبة</div>
            </div>

            <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 p-6 rounded-2xl text-center hover:bg-white hover:shadow-lg transition duration-300">
                <div class="mx-auto w-12 h-12 rounded-2xl flex items-center justify-center mb-4 text-emerald-600 bg-emerald-50 text-xl">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div class="text-3xl font-black text-slate-900 mb-1 font-mono">+300</div>
                <div class="text-xs font-bold text-slate-500">محاضرة تفاعلية</div>
            </div>

            <div class="bg-white/80 backdrop-blur-sm border border-slate-200/80 p-6 rounded-2xl text-center hover:bg-white hover:shadow-lg transition duration-300 col-span-2 md:col-span-1">
                <div class="mx-auto w-12 h-12 rounded-2xl flex items-center justify-center mb-4 text-amber-600 bg-amber-50 text-xl">
                    <i class="fa-solid fa-award"></i>
                </div>
                <div class="text-3xl font-black text-slate-900 mb-1 font-mono">+25</div>
                <div class="text-xs font-bold text-slate-500">سنوات خبرة</div>
            </div>
        </div>

        <!-- Features / Why Us -->
        <div class="mb-16">
            <h2 class="text-3xl font-black text-center text-[#0d3753] mb-10">ما الذي يميز منصتنا؟</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <div class="relative group rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm transition-all duration-300 hover:shadow-xl hover:-translate-y-1 overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-cyan-500"></div>
                    <div class="w-12 h-12 rounded-2xl bg-[#0f766e]/10 text-[#0f766e] flex items-center justify-center mb-6 text-xl group-hover:scale-110 transition duration-300">
                        <i class="fa-solid fa-lightbulb"></i>
                    </div>
                    <h3 class="text-lg font-black text-slate-900 mb-2">شرح مبسط ومحترف</h3>
                    <p class="text-xs md:text-sm text-slate-500 leading-relaxed font-medium">
                        نعتمد على أحدث طرق التدريس التفاعلية وتبسيط مفاهيم وقواعد اللغة العربية لتصبح سهلة وممتعة.
                    </p>
                </div>

                <div class="relative group rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm transition-all duration-300 hover:shadow-xl hover:-translate-y-1 overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 to-orange-500"></div>
                    <div class="w-12 h-12 rounded-2xl bg-[#c79a32]/10 text-[#c79a32] flex items-center justify-center mb-6 text-xl group-hover:scale-110 transition duration-300">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <h3 class="text-lg font-black text-slate-900 mb-2">متابعة وتقييم مستمر</h3>
                    <p class="text-xs md:text-sm text-slate-500 leading-relaxed font-medium">
                        اختبارات دورية وواجبات تفاعلية بعد كل محاضرة لقياس مستواك وتطوير مهاراتك بشكل مستمر.
                    </p>
                </div>

                <div class="relative group rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm transition-all duration-300 hover:shadow-xl hover:-translate-y-1 overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6 text-xl group-hover:scale-110 transition duration-300">
                        <i class="fa-solid fa-headset"></i>
                    </div>
                    <h3 class="text-lg font-black text-slate-900 mb-2">دعم فني على مدار الساعة</h3>
                    <p class="text-xs md:text-sm text-slate-500 leading-relaxed font-medium">
                        فريق دعم فني متكامل للإجابة على استفساراتك وحل أي مشكلات تقنية فوراً ومتابعة أولياء الأمور.
                    </p>
                </div>

            </div>
        </div>

        <!-- CTA Call to Action -->
        <div class="rounded-3xl bg-gradient-to-r from-[#0f766e] to-[#0d3753] p-8 md:p-12 text-center text-white relative overflow-hidden shadow-2xl">
            <div class="absolute -top-12 -right-12 h-40 w-40 rounded-full bg-white/10 blur-xl"></div>
            <div class="absolute -bottom-12 -left-12 h-40 w-40 rounded-full bg-white/10 blur-xl"></div>
            <h3 class="text-2xl md:text-3xl font-black mb-4 leading-tight">هل أنت مستعد لبدء رحلتك في التفوق؟</h3>
            <p class="text-white/90 max-w-xl mx-auto mb-8 text-sm md:text-base font-medium">
                انضم الآن إلى آلاف الطلاب الذين غيروا نظرتهم للغة العربية وحققوا أعلى الدرجات.
            </p>
            <a href="/register.php" class="inline-flex items-center gap-2 px-8 py-4 rounded-xl bg-white text-slate-900 font-black hover:bg-slate-50 transition duration-200 active:scale-[0.98] shadow-lg">
                <span>ابدأ الآن مجاناً</span>
                <i class="fa-solid fa-arrow-left text-[#0f766e]"></i>
            </a>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
