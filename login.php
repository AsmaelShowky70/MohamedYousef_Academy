<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
$currentUser = getCurrentUser();

if ($currentUser) {
    if (in_array($currentUser['role'] ?? '', ['ADMIN', 'SUPERADMIN'])) {
        header('Location: admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawPhone = $_POST['phone'] ?? '';
    $phone = normalizePhone($rawPhone);
    $password = trim($_POST['password'] ?? '');

    if ($phone === '' || $password === '') {
        $error = 'برجاء إدخال رقم الهاتف وكلمة المرور';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM User WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if ($user && verifyPlatformPassword($password, $user['password'], $user['role'] ?? '')) {
            if ($user['status'] === 'BANNED') {
                $error = 'هذا الحساب محظور من دخول المنصة، للتواصل مع الدعم الفني: 01092037257';
            } elseif ($user['status'] === 'PENDING') {
                $error = 'الحساب قيد المراجعة في انتظار موافقة الإدارة';
            } else {
                // Device check for students (max 1 device unless increased by Admin)
                if ($user['role'] === 'STUDENT') {
                    $devId = getDeviceIdentifier();
                    $maxDevices = max(1, (int)($user['maxDevices'] ?? 1));

                    $stmtDevs = $db->prepare("SELECT * FROM Device WHERE userId = ?");
                    $stmtDevs->execute([$user['id']]);
                    $allDevs = $stmtDevs->fetchAll();

                    $existingDev = null;
                    foreach ($allDevs as $d) {
                        if ($d['identifier'] === $devId) {
                            $existingDev = $d;
                            break;
                        }
                    }

                    if ($existingDev) {
                        $db->prepare("UPDATE Device SET lastActiveAt = NOW() WHERE id = ?")->execute([$existingDev['id']]);
                    } else {
                        if (count($allDevs) >= $maxDevices) {
                            $error = 'لقد تجاوزت الحد الأقصى للأجهزة المسموح بها (' . $maxDevices . ' جهاز فقط). الحساب مقيد للعمل على جهازك المسجل فقط. إذا قمت بتغيير جهازك يرجى التواصل مع الدعم الفني لفك الربط: 01092037257';
                        } else {
                            // Register new device
                            $newDevId = generateId(5);
                            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                            $os = 'Unknown OS';
                            if (strpos($ua, 'Windows') !== false) $os = 'Windows';
                            elseif (strpos($ua, 'Android') !== false) $os = 'Android';
                            elseif (strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) $os = 'iOS';
                            elseif (strpos($ua, 'Mac') !== false) $os = 'macOS';

                            $browser = 'Unknown Browser';
                            if (strpos($ua, 'Chrome') !== false) $browser = 'Chrome';
                            elseif (strpos($ua, 'Safari') !== false) $browser = 'Safari';
                            elseif (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';
                            elseif (strpos($ua, 'Edge') !== false) $browser = 'Edge';

                            $devType = (strpos($ua, 'Mobile') !== false || strpos($ua, 'Android') !== false || strpos($ua, 'iPhone') !== false) ? 'phone' : 'pc';

                            $insDev = $db->prepare("INSERT INTO Device (id, userId, identifier, deviceType, browser, os, createdAt, lastActiveAt) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                            $insDev->execute([$newDevId, $user['id'], $devId, $devType, $browser, $os]);
                        }
                    }
                }

                if (!$error) {
                    $db->prepare("UPDATE User SET lastLoginAt = NOW() WHERE id = ?")->execute([$user['id']]);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_phone'] = $user['phone'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['fullName'] ?? $user['firstName'];

                    if (in_array($user['role'], ['ADMIN', 'SUPERADMIN'])) {
                        header('Location: admin/index.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                }
            }
        } else {
            $error = 'بيانات الدخول غير صحيحة، تأكد من رقم الهاتف وكلمة المرور';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes"/>
    <title>تسجيل الدخول - منصة الأستاذ محمد يوسف</title>
    <link rel="icon" type="image/png" href="public/identity/logo.png"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/app.css"/>
    <script>
      (function() {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          document.documentElement.classList.add('dark');
        } else {
          document.documentElement.classList.remove('dark');
        }
      })();
    </script>
    <style>
      :root {
        --font-family: 'Tajawal', 'Cairo', sans-serif;
        --PPBackgroundColor: #ffffff;
        --PPrimaryColor: #0f766e;
        --PButtonColor: #c79a32;
        --PTextColor: #111a14;
        --PCardColor: #d6e2f1;
        --PThirdColor: #0d3753;
        --background: 220 14.3% 97%;
        --foreground: 204 73% 19%;
        --card: 212 51% 89%;
        --card-foreground: 204 73% 19%;
        --primary: 175 77% 26%;
      }
      html.dark, .dark {
        --PPBackgroundColor: #0a0f1d;
        --PPrimaryColor: #0f766e;
        --PButtonColor: #c79a32;
        --PTextColor: #f3f6f4;
        --PCardColor: #12283a;
        --PThirdColor: #0d3753;
        --background: 220 43% 5.5%;
        --foreground: 210 40% 98%;
        --card: 204 73% 19%;
        --card-foreground: 210 40% 98%;
      }
      body {
        font-family: 'Tajawal', 'Cairo', sans-serif;
      }
      html.dark body {
        background-color: #0a0f1d !important;
        color: #f1f5f9 !important;
      }
      html.dark .bg-background {
        background-color: #0e1b29 !important;
        border-color: #1e3347 !important;
        color: #f8fafc !important;
      }
      html.dark input[type="text"], html.dark input[type="password"] {
        background-color: #0d1825 !important;
        color: #f8fafc !important;
        border-color: #1e3347 !important;
      }
      html.dark input[type="text"]:focus, html.dark input[type="password"]:focus {
        background-color: #132337 !important;
        border-color: #0f766e !important;
        color: #ffffff !important;
      }
      html.dark .text-PTextColor {
        color: #f8fafc !important;
      }
      html.dark .cursor-pointer svg path {
        fill: #94a3b8 !important;
      }
    </style>
</head>
<body dir="rtl" class="flex min-h-screen flex-col bg-PBackgroundColor text-PTextColor">
<div class="relative">
<div class="rtl  top-0 flex min-h-screen w-screen  flex-grow items-center p-4 pb-24 md:pb-32"><div class=" flex min-h-[530px] w-full "><div class="  max-w-[800px] flex-grow flex-col rounded-[10px] border-2 bg-background shadow-[0px_0px_25px_1px_rgba(0,0,0,0.4)]   lg:mx-20 "><div class="flex min-h-[10%] w-full min-w-[300px] items-center justify-between rounded-t-[10px] border-b-2 px-4 py-4"><h3 class="text-xl font-bold flex items-center"><svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user ml-2 inline"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>تسجيل الدخول</h3>
<button type="button" onclick="toggleTheme()" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 shadow-sm transition" title="تبديل الوضع الليلي / الفاتح">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="block dark:hidden">
        <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
    </svg>
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden dark:block text-yellow-400">
        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
    </svg>
</button>
</div><div class="h-full w-full flex-grow  space-y-10 p-10 px-20 max-md:px-5"><div><div class="mb-4 max-md:flex max-md:justify-center"><img alt="logo" loading="lazy" width="150" height="80" decoding="async" data-nimg="1" class="object-contain" style="color:transparent"  src="public/identity/logo.png"/></div><h5 class="mb-10 font-bold opacity-75">ادخل رقم الهاتف والكلمة المرور للدخول إلي حسابك<br/><span class="mt-2 block text-sm opacity-80 font-medium">(برجاء التأكد من إدخال بياناتك بشكل صحيح)</span></h5></div>
<?php if (!empty($error)): ?>
<div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 dark:bg-gray-800 dark:text-red-400 font-bold text-center">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>
<form method="POST" action="login.php" class=" w-full space-y-6">
<input type="text" placeholder="رقم الهاتف" class="placeholder:text-PTextColor/60 w-full rounded-md border  border-slate-400 bg-transparent  px-4  py-4  outline-none transition duration-300 ease-in-out focus:ring-2 focus:ring-PPrimaryColor focus:ring-opacity-50" required="" name="phone"/><div class="relative "><input type="password" placeholder="كلمة المرور" class="placeholder:text-PTextColor/60 w-full rounded-md border border-slate-400  bg-transparent  px-4 py-4  outline-none transition duration-300 ease-in-out focus:ring-2 focus:ring-PPrimaryColor focus:ring-opacity-50" required="" name="password"/><div class="absolute inset-y-0 left-0 flex cursor-pointer items-center pl-3"><svg width="35px" height="35px" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><path clip-rule="evenodd" d="M17.7469 15.4149C17.9855 14.8742 18.1188 14.2724 18.1188 14.0016C18.1188 11.6544 16.2952 9.7513 14.046 9.7513C11.7969 9.7513 9.97332 11.6544 9.97332 14.0016C9.97332 16.3487 12.0097 17.8886 14.046 17.8886C15.3486 17.8886 16.508 17.2515 17.2517 16.2595C17.4466 16.0001 17.6137 15.7168 17.7469 15.4149ZM14.046 15.7635C14.5551 15.7635 15.0205 15.5684 15.3784 15.2457C15.81 14.8566 16 14.2807 16 14.0016C16 12.828 15.1716 11.8764 14.046 11.8764C12.9205 11.8764 12 12.8264 12 14C12 14.8104 12.9205 15.7635 14.046 15.7635Z" fill="#000" fill-rule="evenodd"></path><path clip-rule="evenodd" d="M1.09212 14.2724C1.07621 14.2527 1.10803 14.2931 1.09212 14.2724C0.96764 14.1021 0.970773 13.8996 1.09268 13.7273C1.10161 13.7147 1.11071 13.7016 1.11993 13.6882C4.781 8.34319 9.32105 5.5 14.0142 5.5C18.7025 5.5 23.2385 8.33554 26.8956 13.6698C26.965 13.771 27 13.875 27 13.9995C27 14.1301 26.9593 14.2399 26.8863 14.3461C23.2302 19.6702 18.6982 22.5 14.0142 22.5C9.30912 22.5 4.75717 19.6433 1.09212 14.2724ZM3.93909 13.3525C3.6381 13.7267 3.6381 14.2722 3.93908 14.6465C7.07417 18.5443 10.6042 20.3749 14.0142 20.3749C17.4243 20.3749 20.9543 18.5443 24.0894 14.6465C24.3904 14.2722 24.3904 13.7267 24.0894 13.3525C20.9543 9.45475 17.4243 7.62513 14.0142 7.62513C10.6042 7.62513 7.07417 9.45475 3.93909 13.3525Z" fill="#000" fill-rule="evenodd"></path></svg></div></div><input type="submit" class="h rounded-md border border-PPrimaryColor bg-PPrimaryColor px-5 py-2 font-bold  text-white transition duration-300 ease-in-out hover:cursor-pointer hover:bg-white hover:text-PPrimaryColor" value="تسجيل الدخول"/><p class="my-3">ليس لديك حساب؟<a class="mx-2 text-cyan-700" href="register.php">! إنشاء حساب</a></p><div class="mt-4 pt-4 border-t border-slate-200"><a class="flex items-center justify-center gap-2 text-sm font-semibold text-PPrimaryColor hover:underline transition" href="parent.php">الدخول كولي أمر</a></div></form></div></div></div></div><div class="ripple-background absolute bottom-0 max-md:hidden "><div class="circle xxlarge shade1 absolute"></div><div class="circle xlarge shade2"></div><div class="circle large shade3"></div><div class="circle medium shade4"></div><div class="circle small shade5"></div></div></div>
</div>

<script>
function toggleTheme() {
    const isDark = document.documentElement.classList.toggle('dark');
    try {
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    } catch(e) {}
}
// Password visibility toggle
const toggleEye = document.querySelector('.cursor-pointer svg');
if (toggleEye) {
    toggleEye.addEventListener('click', () => {
        const passInput = document.querySelector('input[name="password"]');
        if (passInput) {
            passInput.type = passInput.type === 'password' ? 'text' : 'password';
        }
    });
}
</script>
</body>
</html>