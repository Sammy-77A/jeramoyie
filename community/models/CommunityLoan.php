<?php
class CommunityLoan {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    public function apply($customer_id, $product_id, $amount, $interest_amount, $total_repayable, $status, $due_date) {
        $stmt = $this->pdo->prepare("INSERT INTO community_loans (customer_id, product_id, amount, interest_amount, total_repayable, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $product_id, $amount, $interest_amount, $total_repayable, $status, $due_date]);
        return $this->pdo->lastInsertId();
    }

    public function approve($loan_id, $admin_id, $comment = '') {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE community_loans SET status='approved', approved_by=? WHERE id=? AND status='pending'")->execute([$admin_id, $loan_id]);
            $this->pdo->prepare("INSERT INTO community_loan_approvals (loan_id, approved_by, decision, comment) VALUES (?,?,'approved',?)")->execute([$loan_id, $admin_id, $comment]);
            $this->pdo->commit(); return true;
        } catch (Exception $e) { $this->pdo->rollBack(); return false; }
    }

    public function reject($loan_id, $admin_id, $comment = '') {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE community_loans SET status='rejected' WHERE id=? AND status='pending'")->execute([$loan_id]);
            $this->pdo->prepare("INSERT INTO community_loan_approvals (loan_id, approved_by, decision, comment) VALUES (?,?,'rejected',?)")->execute([$loan_id, $admin_id, $comment]);
            $this->pdo->commit(); return true;
        } catch (Exception $e) { $this->pdo->rollBack(); return false; }
    }

    public function repay($loan_id, $amount, $payment_method, $reference_code) {
        $this->pdo->beginTransaction();
        try {
            $loan = $this->pdo->prepare("SELECT total_repayable, penalty_accrued FROM community_loans WHERE id=? AND status='approved'");
            $loan->execute([$loan_id]); $loan = $loan->fetch(PDO::FETCH_ASSOC);
            if (!$loan) throw new Exception("Loan not found or not active");
            $paid = $this->getTotalRepaid($loan_id);
            $outstanding = (float)$loan['total_repayable'] + (float)$loan['penalty_accrued'] - $paid;
            $amount = min($amount, $outstanding);
            $this->pdo->prepare("INSERT INTO community_loan_repayments (loan_id, amount, payment_method, reference_code) VALUES (?,?,?,?)")->execute([$loan_id, $amount, $payment_method, $reference_code]);
            if (($paid + $amount) >= ((float)$loan['total_repayable'] + (float)$loan['penalty_accrued'] - 0.01))
                $this->pdo->prepare("UPDATE community_loans SET status='completed' WHERE id=?")->execute([$loan_id]);
            $this->pdo->commit(); return true;
        } catch (Exception $e) { $this->pdo->rollBack(); throw $e; }
    }

    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT l.*, p.product_name, p.penalty_rate, c.full_name as customer_name, c.phone_number FROM community_loans l JOIN community_products p ON l.product_id=p.id JOIN community_customers c ON l.customer_id=c.id WHERE l.id=?");
        $stmt->execute([$id]); return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByCustomer($customer_id) {
        $stmt = $this->pdo->prepare("SELECT l.*, p.product_name FROM community_loans l JOIN community_products p ON l.product_id=p.id WHERE l.customer_id=? ORDER BY l.created_at DESC");
        $stmt->execute([$customer_id]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll($status = null) {
        $sql = "SELECT l.*, p.product_name, c.full_name as customer_name, c.phone_number FROM community_loans l JOIN community_products p ON l.product_id=p.id JOIN community_customers c ON l.customer_id=c.id";
        $params = [];
        if ($status) { $sql .= " WHERE l.status=?"; $params[] = $status; }
        $sql .= " ORDER BY l.created_at DESC";
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOverdue() {
        return $this->pdo->query("SELECT l.*, p.product_name, p.penalty_rate, c.full_name as customer_name FROM community_loans l JOIN community_products p ON l.product_id=p.id JOIN community_customers c ON l.customer_id=c.id WHERE l.status='approved' AND l.due_date < CURDATE()")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRepayments($loan_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM community_loan_repayments WHERE loan_id=? ORDER BY created_at DESC");
        $stmt->execute([$loan_id]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalRepaid($loan_id) {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM community_loan_repayments WHERE loan_id=?");
        $stmt->execute([$loan_id]); return (float)$stmt->fetchColumn();
    }

    public function getOutstanding($loan) {
        return max(0, (float)$loan['total_repayable'] + (float)($loan['penalty_accrued'] ?? 0) - $this->getTotalRepaid($loan['id']));
    }

    public function hasActiveLoan($customer_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM community_loans WHERE customer_id=? AND status IN ('pending','approved')");
        $stmt->execute([$customer_id]); return (int)$stmt->fetchColumn() > 0;
    }
}