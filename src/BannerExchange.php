<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class BannerExchange
{
    public function __construct(private Database $db, private array $config)
    {
    }

    public function table(string $name): string
    {
        return $this->config['db']['prefix'] . $name;
    }

    public function settings(): array
    {
        $stmt = $this->db->pdo()->query('SELECT `key_name`, `value_text` FROM ' . $this->table('settings'));
        $rows = $stmt->fetchAll();
        $settings = [];

        foreach ($rows as $row) {
            $settings[$row['key_name']] = $row['value_text'];
        }

        return $settings;
    }

    public function register(array $data): array
    {
        $pdo = $this->db->pdo();
        $settings = $this->settings();
        $requireEmailVerification = (int) ($settings['require_email_verification'] ?? 0) === 1;
        $requireAdminApproval = (int) ($settings['require_admin_approval'] ?? 0) === 1;

        $verifyToken = $requireEmailVerification ? bin2hex(random_bytes(24)) : null;
        $isApproved = $requireAdminApproval ? 0 : 1;

        $stmt = $pdo->prepare('INSERT INTO ' . $this->table('users') . ' (email, username, password_hash, timezone, country_code, agree_rules, is_approved, email_verified_at, verify_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');

        $stmt->execute([
            strtolower(trim($data['email'] ?? '')),
            trim((string) ($data['username'] ?? '')),
            password_hash((string) ($data['password'] ?? ''), PASSWORD_DEFAULT),
            $data['timezone'] ?: 'UTC',
            strtoupper($data['country_code'] ?: 'ALL'),
            (int) !empty($data['agree_rules']),
            $isApproved,
            $requireEmailVerification ? null : gmdate('Y-m-d H:i:s'),
            $verifyToken,
        ]);

        $userId = (int) $pdo->lastInsertId();
        $this->addCredits($userId, 100, 'welcome_bonus');

        if ($requireEmailVerification && $verifyToken) {
            $appUrl = $this->appUrl();
            if ($appUrl !== '') {
                $verifyUrl = $appUrl . '/verify_email.php?token=' . urlencode($verifyToken);
                $this->sendMail(strtolower(trim($data['email'] ?? '')), 'Verify your account', "Please verify your account by opening: {$verifyUrl}");
            }
        }

        return [
            'id' => $userId,
            'requires_email_verification' => $requireEmailVerification,
            'requires_admin_approval' => $requireAdminApproval,
        ];
    }

    public function authenticate(string $username, string $password): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM ' . $this->table('users') . ' WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['user' => null, 'error' => 'Invalid username/password'];
        }

        if (empty($user['email_verified_at']) && !empty($user['verify_token'])) {
            return ['user' => null, 'error' => 'Please verify your email address before login.'];
        }

        if ((int) $user['is_approved'] !== 1) {
            return ['user' => null, 'error' => 'Your account is waiting for admin approval.'];
        }

        return ['user' => $user, 'error' => null];
    }

    public function login(string $username, string $password): ?array
    {
        $auth = $this->authenticate($username, $password);
        return $auth['user'];
    }

    public function verifyEmailToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $stmt = $this->db->pdo()->prepare('UPDATE ' . $this->table('users') . ' SET email_verified_at = NOW(), verify_token = NULL WHERE verify_token = ? AND email_verified_at IS NULL');
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }

    public function moderatorRightsForUser(int $userId): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT rights_json FROM ' . $this->table('moderators') . ' WHERE user_id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$userId]);
        $json = $stmt->fetchColumn();

        if (!$json) {
            return [];
        }

        $rights = json_decode((string) $json, true);
        return is_array($rights) ? $rights : [];
    }

    public function upsertModerator(int $userId, array $rights): void
    {
        $encoded = json_encode(array_values(array_unique($rights)), JSON_THROW_ON_ERROR);
        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('INSERT INTO ' . $this->table('moderators') . ' (user_id, rights_json, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE rights_json = VALUES(rights_json), is_active = 1, updated_at = NOW()');
        $stmt->execute([$userId, $encoded]);
    }

    public function listModerators(): array
    {
        $sql = 'SELECT m.id, m.user_id, m.rights_json, m.is_active, u.username, u.email
                FROM ' . $this->table('moderators') . ' m
                JOIN ' . $this->table('users') . ' u ON u.id = m.user_id
                ORDER BY m.id DESC';

        return $this->db->pdo()->query($sql)->fetchAll();
    }

    public function addCredits(int $userId, int $amount, string $reason): void
    {
        $pdo = $this->db->pdo();
        $pdo->prepare('UPDATE ' . $this->table('users') . ' SET credits = credits + ? WHERE id = ?')->execute([$amount, $userId]);
        $pdo->prepare('INSERT INTO ' . $this->table('credit_ledger') . ' (user_id, amount, reason, created_at) VALUES (?, ?, ?, NOW())')->execute([$userId, $amount, $reason]);
    }

    public function addBanner(int $userId, array $data): void
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO ' . $this->table('banners') . ' (user_id, size_key, category_id, type, image_url, html_code, target_url, alt_text, countries, allowed_days, start_hour, end_hour, is_active, is_sponsored, priority, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())'
        );

        $stmt->execute([
            $userId,
            $data['size_key'],
            (int) $data['category_id'],
            $data['type'],
            $data['image_url'] ?: null,
            $data['html_code'] ?: null,
            $data['target_url'] ?: null,
            $data['alt_text'] ?: '',
            strtoupper($data['countries'] ?: 'ALL'),
            $data['allowed_days'] ?: '1,2,3,4,5,6,7',
            (int) $data['start_hour'],
            (int) $data['end_hour'],
            (int) !empty($data['is_sponsored']),
            (int) ($data['priority'] ?? 0),
        ]);
    }

    public function pickBanner(string $sizeKey, int $viewerUserId, string $countryCode = 'ALL'): ?array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $day = (int) $now->format('N');
        $hour = (int) $now->format('G');

        $sql = 'SELECT b.* FROM ' . $this->table('banners') . ' b
                JOIN ' . $this->table('users') . ' u ON u.id = b.user_id
                WHERE b.is_active = 1 AND u.is_approved = 1 AND b.size_key = :size AND b.user_id != :viewer
                  AND (b.countries = "ALL" OR FIND_IN_SET(:country, b.countries) > 0)
                  AND FIND_IN_SET(:day, b.allowed_days) > 0
                  AND :hour BETWEEN b.start_hour AND b.end_hour
                ORDER BY b.is_sponsored DESC, b.priority DESC, RAND()
                LIMIT 1';

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([
            'size' => $sizeKey,
            'viewer' => $viewerUserId,
            'country' => strtoupper($countryCode),
            'day' => (string) $day,
            'hour' => $hour,
        ]);

        $banner = $stmt->fetch();
        if (!$banner) {
            return null;
        }

        $token = bin2hex(random_bytes(16));
        $this->db->pdo()->prepare('INSERT INTO ' . $this->table('impressions') . ' (banner_id, viewer_user_id, event_token, ip, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([(int) $banner['id'], $viewerUserId, $token, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

        $this->grantViewerCredit($viewerUserId);

        $banner['event_token'] = $token;
        return $banner;
    }

    public function processClick(string $token): ?string
    {
        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('SELECT i.banner_id, b.target_url FROM ' . $this->table('impressions') . ' i JOIN ' . $this->table('banners') . ' b ON b.id = i.banner_id WHERE i.event_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $pdo->prepare('INSERT INTO ' . $this->table('clicks') . ' (banner_id, event_token, ip, created_at) VALUES (?, ?, ?, NOW())')->execute([(int) $row['banner_id'], $token, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

        $owner = $pdo->prepare('SELECT user_id FROM ' . $this->table('banners') . ' WHERE id = ?');
        $owner->execute([(int) $row['banner_id']]);
        $ownerId = (int) $owner->fetchColumn();
        if ($ownerId > 0) {
            $this->addCredits($ownerId, 1, 'banner_click');
        }

        return $row['target_url'];
    }

    public function publicStats(): array
    {
        $pdo = $this->db->pdo();

        return [
            'users' => (int) $pdo->query('SELECT COUNT(*) FROM ' . $this->table('users'))->fetchColumn(),
            'banners' => (int) $pdo->query('SELECT COUNT(*) FROM ' . $this->table('banners'))->fetchColumn(),
            'impressions' => (int) $pdo->query('SELECT COUNT(*) FROM ' . $this->table('impressions'))->fetchColumn(),
            'clicks' => (int) $pdo->query('SELECT COUNT(*) FROM ' . $this->table('clicks'))->fetchColumn(),
            'top' => $pdo->query('SELECT username, credits FROM ' . $this->table('users') . ' ORDER BY credits DESC LIMIT 10')->fetchAll(),
        ];
    }

    public function detailedStats(string $period = 'day'): array
    {
        $period = in_array($period, ['hour', 'day', 'month'], true) ? $period : 'day';

        $formats = [
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'month' => '%Y-%m',
        ];

        $limits = [
            'hour' => 48,
            'day' => 60,
            'month' => 24,
        ];

        $impressions = $this->aggregateByPeriod('impressions', $formats[$period], $limits[$period]);
        $clicks = $this->aggregateByPeriod('clicks', $formats[$period], $limits[$period]);

        return [
            'period' => $period,
            'impressions' => $impressions,
            'clicks' => $clicks,
        ];
    }

    public function createCampaign(string $subject, string $body, bool $sendNow = false): int
    {
        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('INSERT INTO ' . $this->table('email_campaigns') . ' (subject_line, body_text, status, created_at, sent_at) VALUES (?, ?, ?, NOW(), NULL)');
        $stmt->execute([$subject, $body, $sendNow ? 'sending' : 'draft']);

        $id = (int) $pdo->lastInsertId();
        if ($sendNow) {
            $this->sendCampaign($id);
        }

        return $id;
    }

    public function sendCampaign(int $campaignId): void
    {
        $pdo = $this->db->pdo();

        $campaignStmt = $pdo->prepare('SELECT * FROM ' . $this->table('email_campaigns') . ' WHERE id = ? LIMIT 1');
        $campaignStmt->execute([$campaignId]);
        $campaign = $campaignStmt->fetch();
        if (!$campaign) {
            return;
        }

        $users = $pdo->query('SELECT email FROM ' . $this->table('users') . ' WHERE is_approved = 1')->fetchAll();
        $sent = 0;

        foreach ($users as $user) {
            $email = (string) $user['email'];
            if (!$email) {
                continue;
            }

            $this->sendMail($email, (string) $campaign['subject_line'], (string) $campaign['body_text']);
            $sent++;
        }

        $update = $pdo->prepare('UPDATE ' . $this->table('email_campaigns') . ' SET status = ?, sent_count = ?, sent_at = NOW() WHERE id = ?');
        $update->execute(['sent', $sent, $campaignId]);
    }

    public function listCampaigns(): array
    {
        return $this->db->pdo()->query('SELECT * FROM ' . $this->table('email_campaigns') . ' ORDER BY id DESC')->fetchAll();
    }


    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        if (strlen($newPassword) < 6) {
            return ['ok' => false, 'error' => 'New password must be at least 6 characters long.'];
        }

        $stmt = $this->db->pdo()->prepare('SELECT password_hash FROM ' . $this->table('users') . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $hash = (string) $stmt->fetchColumn();

        if ($hash === '' || !password_verify($currentPassword, $hash)) {
            return ['ok' => false, 'error' => 'Current password is not correct.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->pdo()->prepare('UPDATE ' . $this->table('users') . ' SET password_hash = ? WHERE id = ?')->execute([$newHash, $userId]);

        return ['ok' => true, 'error' => null];
    }

    public function adminSetPassword(int $userId, string $newPassword): array
    {
        if (strlen($newPassword) < 6) {
            return ['ok' => false, 'error' => 'Password must be at least 6 characters long.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->pdo()->prepare('UPDATE ' . $this->table('users') . ' SET password_hash = ? WHERE id = ?')->execute([$newHash, $userId]);

        return ['ok' => true, 'error' => null];
    }

    public function requestPasswordReset(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        $stmt = $this->db->pdo()->prepare('SELECT id FROM ' . $this->table('users') . ' WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $userId = (int) $stmt->fetchColumn();
        if ($userId <= 0) {
            return true;
        }

        $token = bin2hex(random_bytes(24));
        $this->db->pdo()->prepare('UPDATE ' . $this->table('users') . ' SET reset_token = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?')->execute([$token, $userId]);

        $url = $this->appUrl() . '/reset_password.php?token=' . urlencode($token);
        $this->sendMail($email, 'Password reset', "Open this link to reset your password: {$url}");

        return true;
    }

    public function resetPasswordByToken(string $token, string $newPassword): array
    {
        if ($token === '') {
            return ['ok' => false, 'error' => 'Reset token is missing.'];
        }
        if (strlen($newPassword) < 6) {
            return ['ok' => false, 'error' => 'New password must be at least 6 characters long.'];
        }

        $stmt = $this->db->pdo()->prepare('SELECT id FROM ' . $this->table('users') . ' WHERE reset_token = ? AND reset_expires_at IS NOT NULL AND reset_expires_at >= NOW() LIMIT 1');
        $stmt->execute([$token]);
        $userId = (int) $stmt->fetchColumn();

        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Reset token is invalid or expired.'];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->pdo()->prepare('UPDATE ' . $this->table('users') . ' SET password_hash = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?')->execute([$hash, $userId]);

        return ['ok' => true, 'error' => null];
    }

    private function sendMail(string $to, string $subject, string $body): bool
    {
        $to = trim($to);
        if ($to === '') {
            return false;
        }

        $fromDomain = parse_url($this->appUrl(), PHP_URL_HOST);
        if (!is_string($fromDomain) || $fromDomain === '') {
            $fromDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        }

        $fromDomain = preg_replace('/:\d+$/', '', (string) $fromDomain);
        $from = 'no-reply@' . $fromDomain;
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: Banner Exchange <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    private function appUrl(): string
    {
        $settings = $this->settings();
        $appUrl = rtrim((string) ($settings['app_url'] ?? ''), '/');
        if ($appUrl !== '') {
            return $appUrl;
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $scheme . '://' . $_SERVER['HTTP_HOST'];
        }

        return '';
    }

    private function aggregateByPeriod(string $tableSuffix, string $dateFormat, int $limit): array
    {
        $table = $this->table($tableSuffix);
        $sql = 'SELECT DATE_FORMAT(created_at, :fmt) AS bucket, COUNT(*) AS total
                FROM ' . $table . '
                GROUP BY bucket
                ORDER BY bucket DESC
                LIMIT ' . (int) $limit;

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute(['fmt' => $dateFormat]);
        $rows = $stmt->fetchAll();

        return array_reverse($rows);
    }

    private function grantViewerCredit(int $viewerUserId): void
    {
        $settings = $this->settings();
        $bonus = (int) ($settings['bonus_per_impression'] ?? 1);
        $this->addCredits($viewerUserId, $bonus, 'viewer_impression_bonus');
    }
}
