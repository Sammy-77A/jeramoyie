<?php
// includes/NotificationService.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../EmailService.php';

class NotificationService
{
    /**
     * Sends a notification to the user via DB and Email.
     * 
     * @param int $userId The ID of the member.
     * @param string $title The notification title.
     * @param string $message The HTML/Plain notification message.
     * @param string $type The type of notification (general, announcement, reminder, request, approval)
     * @return bool True on success (DB insertion), regardless of Email success.
     */
    public static function notify($userId, $title, $message, $type = 'general')
    {
        global $pdo;

        // 1. Fetch user email and name (members and community_customers tables may be involved, we will assume members first. If community, maybe add support later or pass email/name directly).
        // Check member
        $stmt = $pdo->prepare("SELECT name, email FROM members WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Check community customer just in case, or default to generic logging if no email
            $stmt = $pdo->prepare("SELECT full_name as name, email FROM community_customers WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // 2. Insert into database
        $dbSuccess = false;
        try {
            $insertStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $dbSuccess = $insertStmt->execute([$userId, $title, $message, $type]);
        }
        catch (PDOException $e) {
            error_log("Failed to insert notification into DB: " . $e->getMessage());
        // If DB logic strictly needs to pass, throw or return here. For now, try sending email even if DB fails.
        }

        // 3. Send Email
        $emailSent = false;
        if ($user && !empty($user['email'])) {
            $emailService = new EmailService();
            $result = $emailService->sendNotificationEmail($user['email'], $user['name'], $title, $message);

            if ($result['success']) {
                $emailSent = true;
            }
            else {
                error_log("Failed to send notification email to " . $user['email'] . ": " . ($result['message'] ?? 'Unknown error'));
            }
        }

        // 4. Update email_sent flag in DB if needed (optional)
        if ($dbSuccess && $emailSent) {
            try {
                $lastId = $pdo->lastInsertId();
                if ($lastId) {
                    $pdo->exec("UPDATE notifications SET email_sent = 1 WHERE id = " . $lastId);
                }
            }
            catch (Exception $e) {
            // Ignore silent update errors
            }
        }

        // Return true if DB insertion was successful. Email failure shouldn't crash transactions.
        return $dbSuccess;
    }
}
