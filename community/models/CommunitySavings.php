<?php
class CommunitySavings {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    public function deposit($customer_id, $product_id, $amount, $reference_code) {
        return $this->pdo->prepare("INSERT INTO community_savings (customer_id, product_id, amount, transaction_type, reference_code) VALUES (?,?,?,'deposit',?)")->execute([$customer_id, $product_id, $amount, $reference_code]);
    }

    public function withdraw($customer_id, $product_id, $amount, $reference_code) {
        $balance = $this->getBalance($customer_id, $product_id);
        if ($amount > $balance) throw new Exception("Insufficient balance. Available: KSh " . number_format($balance, 2));
        return $this->pdo->prepare("INSERT INTO community_savings (customer_id, product_id, amount, transaction_type, reference_code) VALUES (?,?,?,'withdrawal',?)")->execute([$customer_id, $product_id, $amount, $reference_code]);
    }

    public function getBalance($customer_id, $product_id = null) {
        $sql = "SELECT COALESCE(SUM(CASE WHEN transaction_type='deposit' THEN amount ELSE -amount END),0) FROM community_savings WHERE customer_id=?";
        $params = [$customer_id];
        if ($product_id) { $sql .= " AND product_id=?"; $params[] = $product_id; }
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return (float)$stmt->fetchColumn();
    }

    public function getTransactions($customer_id, $product_id = null, $limit = 50) {
        $sql = "SELECT s.*, p.product_name FROM community_savings s JOIN community_products p ON s.product_id=p.id WHERE s.customer_id=?";
        $params = [$customer_id];
        if ($product_id) { $sql .= " AND s.product_id=?"; $params[] = $product_id; }
        $sql .= " ORDER BY s.created_at DESC LIMIT " . (int)$limit;
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllTransactions($limit = 200) {
        $stmt = $this->pdo->prepare("SELECT s.*, p.product_name, c.full_name as customer_name FROM community_savings s JOIN community_products p ON s.product_id=p.id JOIN community_customers c ON s.customer_id=c.id ORDER BY s.created_at DESC LIMIT " . (int)$limit);
        $stmt->execute(); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalSavings() {
        return (float)$this->pdo->query("SELECT COALESCE(SUM(CASE WHEN transaction_type='deposit' THEN amount ELSE -amount END),0) FROM community_savings")->fetchColumn();
    }
}