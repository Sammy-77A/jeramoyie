<?php
/**
 * Mail (SMTP) configuration.
 * Credentials extracted from the original hardcoded EmailService.
 */

return [
    'host' => getenv('MAIL_HOST') ?: 'mail.jeramy1.top',
    'port' => (int) (getenv('MAIL_PORT') ?: 465),
    'username' => getenv('MAIL_USERNAME') ?: 'support@jeramy1.top',
    'password' => getenv('MAIL_PASSWORD') ?: '',
    'from_email' => getenv('MAIL_FROM') ?: 'support@jeramy1.top',
    'from_name' => 'Jera Moyie SHG',
    'encryption' => 'ssl',   // ssl = SMTPS on port 465
];
