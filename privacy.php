<?php
$pageTitle = "سياسة الخصوصية - منصة الأستاذ محمد يوسف";
require_once __DIR__ . '/includes/header.php';
?>

<div dir="rtl" class="relative min-h-screen bg-gradient-to-b from-slate-50 via-slate-50 to-teal-50/20 pt-24 pb-36 md:pb-44 px-4 sm:px-6 lg:px-8">
    <div class="absolute top-20 right-10 -z-10 h-72 w-72 rounded-full bg-[#0f766e]/5 blur-3xl"></div>
    <div class="absolute bottom-20 left-10 -z-10 h-80 w-80 rounded-full bg-[#0d3753]/5 blur-3xl"></div>

    <div class="mx-auto max-w-5xl">
        <!-- Header -->
        <div class="text-center mb-12 border-b border-slate-200/80 pb-10">
            <div class="mx-auto w-16 h-16 rounded-2xl bg-[#0f766e]/10 text-[#0f766e] flex items-center justify-center mb-6 text-2xl">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="text-3xl font-black sm:text-4xl text-slate-900 mb-4 leading-tight">سياسة الخصوصية</h1>
            <div class="flex items-center justify-center gap-2 text-xs font-bold text-slate-400">
                <i class="fa-solid fa-calendar-days"></i>
                <span>آخر تحديث: 14 يوليو 2026</span>
            </div>
        </div>

        <!-- Interactive Tabs Navigation -->
        <div class="flex flex-wrap gap-2 justify-center mb-8" id="policyTabs">
            <button onclick="switchTab('definitions')" id="tab-btn-definitions" class="policy-tab-btn active flex items-center gap-2 px-5 py-3 rounded-2xl font-bold text-xs md:text-sm transition duration-200 border bg-[#0f766e] border-[#0f766e] text-white shadow-md shadow-[#0f766e]/20">
                <i class="fa-solid fa-book-open"></i>
                <span>مفاهيم أساسية</span>
            </button>
            <button onclick="switchTab('collection')" id="tab-btn-collection" class="policy-tab-btn flex items-center gap-2 px-5 py-3 rounded-2xl font-bold text-xs md:text-sm transition duration-200 border bg-white/80 border-slate-200 text-slate-600 hover:bg-white hover:text-slate-900">
                <i class="fa-solid fa-eye"></i>
                <span>البيانات التي نجمعها</span>
            </button>
            <button onclick="switchTab('usage')" id="tab-btn-usage" class="policy-tab-btn flex items-center gap-2 px-5 py-3 rounded-2xl font-bold text-xs md:text-sm transition duration-200 border bg-white/80 border-slate-200 text-slate-600 hover:bg-white hover:text-slate-900">
                <i class="fa-solid fa-gears"></i>
                <span>كيف نستخدم بياناتك؟</span>
            </button>
            <button onclick="switchTab('security')" id="tab-btn-security" class="policy-tab-btn flex items-center gap-2 px-5 py-3 rounded-2xl font-bold text-xs md:text-sm transition duration-200 border bg-white/80 border-slate-200 text-slate-600 hover:bg-white hover:text-slate-900">
                <i class="fa-solid fa-lock"></i>
                <span>الأمان والمشاركة</span>
            </button>
            <button onclick="switchTab('others')" id="tab-btn-others" class="policy-tab-btn flex items-center gap-2 px-5 py-3 rounded-2xl font-bold text-xs md:text-sm transition duration-200 border bg-white/80 border-slate-200 text-slate-600 hover:bg-white hover:text-slate-900">
                <i class="fa-solid fa-circle-question"></i>
                <span>تنبيهات وتواصل</span>
            </button>
        </div>

        <!-- Content Box -->
        <div class="bg-white/90 backdrop-blur-md border border-slate-200/80 rounded-3xl p-6 sm:p-10 shadow-xl min-h-[400px]">
            
            <!-- Tab 1: Definitions -->
            <div id="tab-content-definitions" class="tab-pane space-y-6">
                <h2 class="text-2xl font-black text-slate-900 mb-4">مفاهيم أساسية</h2>
                <div class="border-r-2 border-[#0f766e]/35 pr-4 py-1">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">توضيح بسيط</h3>
                    <p class="text-slate-600 leading-relaxed font-medium">خصوصيتك تهمنا جداً، وهدف الصفحة دي هو توضيح إزاي بنتعامل مع بياناتك الشخصية اللي بتسجلها عندنا بشكل مبسط وسهل الفهم من غير تعقيدات قانونية.</p>
                </div>
                <div class="border-r-2 border-[#0f766e]/35 pr-4 py-1 mt-6">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">تعريفات تهمك</h3>
                    <p class="text-slate-600 leading-relaxed mb-4 font-medium">معاني بعض الكلمات اللي بنستخدمها في السياسة دي:</p>
                    <div class="grid grid-cols-1 gap-4 text-sm font-medium">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <strong class="text-[#0f766e]">الحساب الشخصي:</strong> هو الحساب اللي بتسجله باسمك ورقم تليفونك علشان تدخل منه تشوف كورساتك ومحاضراتك.
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <strong class="text-[#0f766e]">المنصة:</strong> ونقصد بيها منصة الأستاذ محمد يوسف التعليمية وموقعها الإلكتروني.
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <strong class="text-[#0f766e]">ملفات الكوكيز (Cookies):</strong> هي ملفات صغيرة جداً بيحفظها المتصفح على جهازك علشان تفضل مسجل دخولك في الموقع تلقائياً من غير ما تضطر تكتب الباسورد كل مرة.
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <strong class="text-[#0f766e]">البيانات الشخصية:</strong> هي أي معلومات بتسجلها بنفسك وتخصك، زي اسمك بالكامل، رقم تليفونك، تليفون ولي أمرك، والبريد الإلكتروني.
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <strong class="text-[#0f766e]">بيانات الاستخدام:</strong> هي معلومات بتتجمع تلقائياً لما تدخل تتصفح الموقع، زي نوع جهازك ووقت زيارة المحاضرة.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Collection -->
            <div id="tab-content-collection" class="tab-pane hidden space-y-6">
                <h2 class="text-2xl font-black text-slate-900 mb-4">البيانات التي نجمعها عنك</h2>
                <div class="border-r-2 border-[#0f766e]/35 pr-4 py-1">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">1. البيانات التي تدخلها بنفسك</h3>
                    <p class="text-slate-600 leading-relaxed mb-3 font-medium">عند إنشاء حساب جديد أو استخدام المنصة، قد نطلب منك تسجيل بعض البيانات مثل:</p>
                    <ul class="list-disc list-inside space-y-1.5 text-slate-600 pr-2 font-medium text-sm">
                        <li>اسمك بالكامل (ثلاثي أو رباعي) لتسجيله في كشوف المتابعة.</li>
                        <li>رقم تليفونك الشخصي لربطه بالحساب وتفعيله.</li>
                        <li>رقم تليفون ولي الأمر لنتمكن من التواصل معه وإرسال التقارير والدرجات.</li>
                        <li>البريد الإلكتروني لاستعادة الحساب واستقبال الرسائل الهامة.</li>
                    </ul>
                </div>
                <div class="border-r-2 border-[#0f766e]/35 pr-4 py-1 mt-6">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">2. البيانات الفنية التي تُجمع تلقائياً</h3>
                    <p class="text-slate-600 leading-relaxed font-medium text-sm">أثناء تصفحك للموقع، تُجمع تلقائياً بعض البيانات الفنية مثل: عنوان الـ IP الخاص بجهازك، نوع المتصفح، ونظام التشغيل. نستخدم هذه البيانات فقط لضمان عمل السيرفرات بكفاءة وتفعيل قفل الأجهزة الآمن.</p>
                </div>
                <div class="border-r-2 border-[#0f766e]/35 pr-4 py-1 mt-6">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">3. ملفات الكوكيز والجلسة</h3>
                    <p class="text-slate-600 leading-relaxed font-medium text-sm">نحن نستخدم الكوكيز للتأكد من أنك تظل مسجل الدخول أثناء الانتقال بين صفحات المنصة ولتخصيص تجربتك وحفظ تقدمك.</p>
                </div>
            </div>

            <!-- Tab 3: Usage -->
            <div id="tab-content-usage" class="tab-pane hidden space-y-6">
                <h2 class="text-2xl font-black text-slate-900 mb-4">كيف نستخدم بياناتك؟</h2>
                <p class="text-slate-600 leading-relaxed font-medium">نستخدم البيانات التي نجمعها لتقديم أفضل وأسهل تجربة تعليمية ممكنة، وتحديداً للأغراض التالية:</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 font-medium text-sm">
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <h4 class="font-bold text-slate-900 mb-1">تقديم الخدمة ومتابعة مستواك</h4>
                        <p class="text-xs text-slate-500">لحفظ تقدمك في الفيديوهات، ورصد درجاتك في الامتحانات وعرضها لك.</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <h4 class="font-bold text-slate-900 mb-1">التواصل الفوري معك ومع ولي أمرك</h4>
                        <p class="text-xs text-slate-500">لإرسال إشعارات الدفع، أو إرسال تقارير المتابعة والدرجات لولي الأمر.</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <h4 class="font-bold text-slate-900 mb-1">إدارة حسابك وحمايته</h4>
                        <p class="text-xs text-slate-500">لتسجيل دخولك بأمان والتأكد من عدم استخدام حسابك من قبل أطراف غير مصرح لها.</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <h4 class="font-bold text-slate-900 mb-1">تحسين أداء المنصة التعليمية</h4>
                        <p class="text-xs text-slate-500">لتحليل جودة وسرعة تشغيل المحاضرات وتطوير المناهج باستمرار.</p>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Security -->
            <div id="tab-content-security" class="tab-pane hidden space-y-6">
                <h2 class="text-2xl font-black text-slate-900 mb-4">أمان وحماية بياناتك</h2>
                <div class="border-r-2 border-[#0f766e]/35 pr-4 py-1">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">أمن وحماية البيانات</h3>
                    <p class="text-slate-600 leading-relaxed font-medium text-sm">أمان بياناتك يمثل أولوية قصوى بالنسبة لنا. نستخدم تقنيات حديثة وتشفير لكلمات المرور لمنع الوصول غير المصرح به إليها.</p>
                </div>
                <div class="border-r-2 border-[#0f766e]/35 pr-4 py-1 mt-6">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">عدم مشاركة البيانات مع الآخرين</h3>
                    <p class="text-slate-600 leading-relaxed mb-3 font-medium text-sm">نحن لا نبيع أو نقوم بتأجير أو مشاركة بياناتك الشخصية مع أي شركات إعلانية أو جهات خارجية بغرض التسويق إطلاقاً.</p>
                </div>
            </div>

            <!-- Tab 5: Others -->
            <div id="tab-content-others" class="tab-pane hidden space-y-6">
                <h2 class="text-2xl font-black text-slate-900 mb-4">تنبيهات عامة والتواصل</h2>
                <div class="border-r-2 border-[#0f766e]/35 pr-4 py-1">
                    <h3 class="text-lg font-bold text-slate-900 mb-2">خصوصية الطلاب</h3>
                    <p class="text-slate-600 leading-relaxed font-medium text-sm">المنصة مخصصة لطلاب المدارس والأزهر ونلتزم التزاماً كاملاً بحماية خصوصيتهم وأمانهم على الإنترنت.</p>
                </div>
                <div class="mt-8 pt-8 border-t border-slate-100">
                    <h4 class="font-bold text-slate-900 mb-2 text-base">تواصل معنا بخصوص الخصوصية</h4>
                    <p class="text-xs text-slate-500 mb-4 font-medium">إذا كان لديك أي استفسار أو مخاوف بشأن خصوصية بياناتك، يمكنك مراسلتنا فوراً:</p>
                    <div class="flex items-center gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-200/80 w-fit">
                        <i class="fa-solid fa-envelope text-[#0f766e]"></i>
                        <a href="mailto:arabicmohammed407@gmail.com" class="text-sm font-bold text-slate-900 hover:text-[#0f766e] transition font-mono">arabicmohammed407@gmail.com</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
    document.getElementById('tab-content-' + tabId).classList.remove('hidden');

    document.querySelectorAll('.policy-tab-btn').forEach(btn => {
        btn.className = 'policy-tab-btn flex items-center gap-2 px-5 py-3 rounded-2xl font-bold text-xs md:text-sm transition duration-200 border bg-white/80 border-slate-200 text-slate-600 hover:bg-white hover:text-slate-900';
    });

    const activeBtn = document.getElementById('tab-btn-' + tabId);
    activeBtn.className = 'policy-tab-btn active flex items-center gap-2 px-5 py-3 rounded-2xl font-bold text-xs md:text-sm transition duration-200 border bg-[#0f766e] border-[#0f766e] text-white shadow-md shadow-[#0f766e]/20';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
