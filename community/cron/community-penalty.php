<?php
require_once __DIR__ . '/../../includes/guard.php';
require_cli_or_cron_secret();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/CommunityLoan.php';
$loanModel = new CommunityLoan($pdo);
$overdue = $loanModel->getOverdue();
$processed = 0; $today = new DateTime();
foreach ($overdue as $loan) {
    $due = new DateTime($loan['due_date']);
    $days_late = max(0, (int)$today->diff($due)->days);
    if ($days_late <= 0) continue;
    $penalty_rate = (float)$loan['penalty_rate'];
    if ($penalty_rate <= 0) continue;
    $daily_penalty = ((float)$loan['total_repayable'] * $penalty_rate / 100) / 30;
    $new_penalty = round($daily_penalty * $days_late, 2);
    if (abs($new_penalty - (float)$loan['penalty_accrued']) > 0.01) {
        $stmt = $pdo->prepare("UPDATE community_loans SET penalty_accrued = ? WHERE id = ?");
        $stmt->execute([$new_penalty, $loan['id']]);
        $processed++;
        log_community_audit($pdo, 'admin', 0, 'Penalty Calculated', "Loan #{$loan['id']} ({$loan['customer_name']}): {$days_late} day(s) late, penalty = KSh " . number_format($new_penalty, 2));
    }
}
echo date('Y-m-d H:i:s') . " — Penalty cron complete. {$processed} loan(s) updated.\n";