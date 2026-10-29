<?php
/**
 * AuthService — handles authentication, registration, password reset.
 */

namespace App\Services;

use Core\Database;

class AuthService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * Authenticate a member by phone and password.
     * Returns member array on success, null on failure.
     */
    public function authenticate(string $phone, string $password): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM members WHERE phone = ?");
        $stmt->execute([$phone]);
        $member = $stmt->fetch();

        if ($member && password_verify($password, $member['password_hash'])) {
            return $member;
        }
        return null;
    }

    /**
     * Normalize phone number: 07xx → 2547xx
     */
    public function normalizePhone(string $rawPhone): ?string
    {
        $rawPhone = preg_replace('/[\s\-]/', '', trim($rawPhone));
        $rawPhone = ltrim($rawPhone, '+');

        if (preg_match('/^0[71][0-9]{8}$/', $rawPhone)) {
            return '254' . substr($rawPhone, 1);
        }
        if (preg_match('/^254[71][0-9]{8}$/', $rawPhone)) {
            return $rawPhone;
        }
        return null;
    }

    /**
     * Check for duplicate registration.
     */
    public function isDuplicate(string $nationalId, string $phone): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM members WHERE national_id = ? OR phone = ?");
        $stmt->execute([$nationalId, $phone]);
        return (bool) $stmt->fetch();
    }

    /**
     * Create a pending registration.
     */
    public function createPendingRegistration(string $name, string $nationalId, string $phone, string $password): string
    {
        // Clean up old pending registrations
        $stmt = $this->pdo->prepare("SELECT token FROM pending_registrations WHERE national_id = ? OR phone = ?");
        $stmt->execute([$nationalId, $phone]);
        if ($old = $stmt->fetch()) {
            $this->pdo->prepare("DELETE FROM pending_registrations WHERE token = ?")->execute([$old['token']]);
        }

        $token = bin2hex(random_bytes(16));
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            "INSERT INTO pending_registrations (token, name, national_id, phone, password_hash) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$token, $name, $nationalId, $phone, $hash]);

        return $token;
    }

    /**
     * Set login session data.
     */
    public function setSession(array $member): void
    {
        $_SESSION['member_id'] = $member['id'];
        $_SESSION['name'] = $member['name'];
        $_SESSION['paid'] = $member['paid'];
        $_SESSION['is_admin'] = (int) $member['is_admin'];
        $_SESSION['role'] = $member['role'];
        $_SESSION['is_native'] = (int) $member['is_native'];
    }

    /**
     * Destroy the session (logout).
     */
    public function logout(): void
    {
        session_destroy();
    }

    /**
     * Generate a password reset token.
     */
    public function createResetToken(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, name, email FROM members WHERE email = ?");
        $stmt->execute([$email]);
        $member = $stmt->fetch();

        if (!$member)
            return null;

        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            "UPDATE members SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?"
        );
        $stmt->execute([$token, $member['id']]);

        return [
            'token' => $token,
            'member' => $member,
        ];
    }

    /**
     * Validate and execute a password reset.
     */
    public function resetPassword(string $token, int $userId, string $newPassword): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM members WHERE id = ? AND reset_token = ? AND reset_expires > NOW()"
        );
        $stmt->execute([$userId, $token]);
        $member = $stmt->fetch();

        if (!$member)
            return false;

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE members SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$hash, $userId]);

        return true;
    }
}
