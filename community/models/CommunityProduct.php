<?php
/**
 * Model: CommunityProduct
 */
class CommunityProduct
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function create($data)
    {
        $stmt = $this->pdo->prepare("INSERT INTO community_products (product_name, product_type, description, minimum_amount, maximum_amount, interest_rate, penalty_rate, duration_in_months, requires_approval, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['product_name'], $data['product_type'], $data['description'] ?? '',
            $data['minimum_amount'], $data['maximum_amount'], $data['interest_rate'],
            $data['penalty_rate'], $data['duration_in_months'],
            $data['requires_approval'] ?? 1, $data['is_active'] ?? 1
        ]);
        return $this->pdo->lastInsertId();
    }

    public function update($id, $data)
    {
        $stmt = $this->pdo->prepare("UPDATE community_products SET product_name=?, product_type=?, description=?, minimum_amount=?, maximum_amount=?, interest_rate=?, penalty_rate=?, duration_in_months=?, requires_approval=?, is_active=? WHERE id=?");
        return $stmt->execute([
            $data['product_name'], $data['product_type'], $data['description'] ?? '',
            $data['minimum_amount'], $data['maximum_amount'], $data['interest_rate'],
            $data['penalty_rate'], $data['duration_in_months'],
            $data['requires_approval'] ?? 1, $data['is_active'] ?? 1, $id
        ]);
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM community_products WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function findById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM community_products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getActive($type = null)
    {
        $sql = "SELECT * FROM community_products WHERE is_active = 1";
        $params = [];
        if ($type) {
            $sql .= " AND product_type = ?";
            $params[] = $type;
        }
        $sql .= " ORDER BY product_name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll()
    {
        return $this->pdo->query("SELECT * FROM community_products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLoanProducts()
    {
        return $this->getActive('okoa_loan');
    }

    public function getSavingProducts()
    {
        return $this->getActive('saving_scheme');
    }
}
