<?php
/**
 * Model: CommunityCustomer
 */
class CommunityCustomer
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function create($full_name, $phone, $national_id, $password_hash, $email = null)
    {
        $stmt = $this->pdo->prepare("INSERT INTO community_customers (full_name, phone_number, national_id, email, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $phone, $national_id, $email, $password_hash]);
        return $this->pdo->lastInsertId();
    }

    public function findByPhone($phone)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM community_customers WHERE phone_number = ?");
        $stmt->execute([$phone]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByNationalId($national_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM community_customers WHERE national_id = ?");
        $stmt->execute([$national_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM community_customers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status)
    {
        $stmt = $this->pdo->prepare("UPDATE community_customers SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function getAll($search = '')
    {
        $sql = "SELECT * FROM community_customers";
        $params = [];
        if ($search) {
            $sql .= " WHERE full_name LIKE ? OR phone_number LIKE ? OR national_id LIKE ?";
            $params = ["%$search%", "%$search%", "%$search%"];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCount()
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM community_customers")->fetchColumn();
    }

    public function phoneOrIdExists($phone, $national_id, $exclude_id = null)
    {
        $sql = "SELECT id FROM community_customers WHERE (phone_number = ? OR national_id = ?)";
        $params = [$phone, $national_id];
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmail($email)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM community_customers WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePassword($id, $password_hash)
    {
        $stmt = $this->pdo->prepare("UPDATE community_customers SET password = ? WHERE id = ?");
        return $stmt->execute([$password_hash, $id]);
    }

    public function emailExists($email, $exclude_id = null)
    {
        $sql = "SELECT id FROM community_customers WHERE email = ?";
        $params = [$email];
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
    }
}
