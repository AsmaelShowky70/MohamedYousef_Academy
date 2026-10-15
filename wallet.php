<?php
header("Location: /profile.php");
exit;

$db = getDB();

// Refresh current user wallet balance from DB
$uStmt = $db->prepare("SELECT walletBalance FROM User WHERE id = ? LIMIT 1");
$uStmt->execute([$currentUser['id']]);
$userRow = $uStmt->fetch();
$balance = (float)($userRow['walletBalance'] ?? 0);

// Fetch transactions
$txStmt = $db->prepare("SELECT * FROM WalletTransaction WHERE userId = ? ORDER BY createdAt DESC");
$txStmt->execute([$currentUser['id']]);
$transactions = $txStmt->fetchAll();

$typeLabels = [
    'VFC_OVERPAYMENT' => 'فائض فودافون كاش',
    'COURSE_PURCHASE' => 'شراء كورس',
    'COLLECTION_PURCHASE' => 'شراء مجموعة',
    'ADMIN_ADJUSTMENT' => 'تعديل إداري',
    'WALLET_PAYMENT' => 'دفع من المحفظة'
];
?>

<style>
@keyframes moveBg {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
.moving-profile-bg {
    background-size: 200% 200%;
    animation: moveBg 15s ease infinite;
}
</style>

<div class="px-3 py-5 max-md:px-4 pb-24 md:pb-32">
    <!-- Animated Banner Matching Next.js profile/layout.tsx -->
    <div style="background-image: url('/profile-bg.png');" class="moving-profile-bg min-h-[300px] w-full translate-y-4 rounded-lg bg-green-200 max-md:hidden"></div>

    <div class="md:px-8">
        <!-- Main Card Container Matching Next.js max-w-[1800px] -->
        <div class="mx-auto my-10 min-h-[500px] max-w-[1800px] rounded-lg bg-PCardColor dark:bg-[#12283a] px-5 py-10 shadow-[0px_0px_25px_1px_rgba(0,0,0,0.3)] border border-transparent dark:border-slate-700 md:-translate-y-[120px]">
            
            <div class="container mx-auto px-4 py-8 max-w-2xl">
                <!-- Back Link -->
                <div class="mb-4">
                    <a href="/profile" class="text-sm text-slate-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:underline">← العودة للملف الشخصي</a>
                </div>

                <!-- Wallet Card Matching Next.js Card -->
                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0e1b29] text-card-foreground shadow-sm mb-6 shadow-[0px_0px_25px_2px_rgba(0,0,0,0.2)]">
                    <div class="flex flex-col space-y-1.5 p-6">
                        <h3 class="text-2xl font-semibold leading-none tracking-tight text-slate-900 dark:text-white">محفظتي</h3>
                    </div>
                    <div class="p-6 pt-0">
                        <div class="text-center py-6">
                            <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">الرصيد الحالي</p>
                            <p class="text-5xl font-bold text-green-600 dark:text-green-400">£<?= number_format($balance, 2) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Transactions History Card Matching Next.js Card -->
                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0e1b29] text-card-foreground shadow-sm shadow-[0px_0px_25px_2px_rgba(0,0,0,0.2)]">
                    <div class="flex flex-col space-y-1.5 p-6">
                        <h3 class="text-2xl font-semibold leading-none tracking-tight text-slate-900 dark:text-white">سجل المعاملات</h3>
                    </div>
                    <div class="p-6 pt-0">
                        <?php if (empty($transactions)): ?>
                            <p class="text-center text-slate-500 dark:text-slate-400 py-8">لا توجد معاملات حتى الآن</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($transactions as $t): ?>
                                    <?php 
                                        $amt = (float)$t['amount'];
                                        $label = $typeLabels[$t['type']] ?? $t['type'];
                                        $formattedDate = date('Y/m/d h:i A', strtotime($t['createdAt']));
                                    ?>
                                    <div class="flex items-center justify-between p-3 rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#0b1623]">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-semibold text-sm text-slate-900 dark:text-white"><?= htmlspecialchars($label) ?></span>
                                            <?php if (!empty($t['description'])): ?>
                                                <span class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($t['description']) ?></span>
                                            <?php endif; ?>
                                            <span class="text-xs text-slate-500 dark:text-slate-400"><?= $formattedDate ?></span>
                                        </div>
                                        <span class="font-bold text-lg <?= $amt > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' ?>">
                                            <?= $amt > 0 ? '+' : '' ?>£<?= number_format($amt, 2) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
