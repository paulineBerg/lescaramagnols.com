<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminPrivateMembersService;
use Caramagnols\PrivateApps\Documents\PrivateDocumentRepository;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateMfaVerifier;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PrivatePortalMembersTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testInviteCreatesPendingUserAndHashedTokenWithoutDuplicateAccount(): void
    {
        $database = $this->editorialSqlDatabase();
        $repository = new PrivateUserRepository($database);
        $service = new AdminPrivateMembersService(
            $repository,
            new PrivateModulePermissionRepository($database, new PrivateModuleRegistry())
        );

        $result = $service->handleAction([
            'private_member_action' => 'invite',
            'email' => 'family@example.com',
        ], 'admin@example.com');

        $this->assertTrue($result['success']);
        $member = $repository->findByEmail('family@example.com');
        $this->assertIsArray($member);
        $this->assertSame('invited', $member['status'] ?? null);

        $tokenHash = $database->pdo()
            ->query(sprintf('SELECT `token_hash` FROM `%s` LIMIT 1', $database->table('private_user_invites')))
            ->fetchColumn();
        $this->assertIsString($tokenHash);
        $this->assertSame('argon2id', password_get_info($tokenHash)['algoName'] ?? null);

        $duplicate = $service->handleAction([
            'private_member_action' => 'invite',
            'email' => 'family@example.com',
        ], 'admin@example.com');

        $this->assertFalse($duplicate['success']);
    }

    public function testInviteActivationAndPasswordResetUseHashedTokens(): void
    {
        $repository = new PrivateUserRepository($this->editorialSqlDatabase());
        $passwordHash = password_hash('TempPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $repository->create('family@example.com', $passwordHash, 'invited');
        $this->assertIsInt($userId);

        $inviteToken = $repository->createInviteToken($userId, 'family@example.com');
        $this->assertIsString($inviteToken);
        $activatedHash = password_hash('NewPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($activatedHash);
        $activated = $repository->activateByInviteToken($inviteToken, $activatedHash);

        $this->assertIsArray($activated);
        $this->assertSame('active', $activated['status'] ?? null);

        $resetToken = $repository->createPasswordResetToken($userId, '127.0.0.1', 'phpunit');
        $this->assertIsString($resetToken);
        $resetHash = password_hash('ResetPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($resetHash);
        $reset = $repository->resetPasswordByToken($resetToken, $resetHash, '127.0.0.1');

        $this->assertIsArray($reset);
        $this->assertTrue(password_verify('ResetPassword1!', (string) ($reset['password_hash'] ?? '')));
    }

    public function testPasswordAndStatusNoopUpdatesRemainSuccessful(): void
    {
        $repository = new PrivateUserRepository($this->editorialSqlDatabase());
        $passwordHash = password_hash('TempPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $repository->create('noop@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);

        $this->assertTrue($repository->setPasswordHash($userId, $passwordHash));
        $this->assertTrue($repository->setPasswordHash($userId, $passwordHash));
        $this->assertTrue($repository->updateStatus($userId, 'active'));
        $this->assertTrue($repository->updateStatus($userId, 'active'));
    }

    public function testInviteAndPasswordResetTokensAreSingleUse(): void
    {
        $database = $this->editorialSqlDatabase();
        $repository = new PrivateUserRepository($database);
        $userPasswordHash = password_hash('TempPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($userPasswordHash);
        $userId = $repository->create('singleuse@example.com', $userPasswordHash, 'invited');
        $this->assertIsInt($userId);

        $inviteToken = $repository->createInviteToken($userId, 'singleuse@example.com');
        $this->assertIsString($inviteToken);
        $this->assertIsArray(
            $repository->activateByInviteToken(
                $inviteToken,
                password_hash('ActivePassword1!', PASSWORD_ARGON2ID),
                '127.0.0.1'
            )
        );
        $this->assertNull($repository->activateByInviteToken($inviteToken, password_hash('ActivePassword2!', PASSWORD_ARGON2ID), '127.0.0.1'));

        $resetToken = $repository->createPasswordResetToken($userId, '127.0.0.1', 'phpunit');
        $this->assertIsString($resetToken);
        $resetHash = password_hash('ResetPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($resetHash);
        $this->assertIsArray(
            $repository->resetPasswordByToken($resetToken, $resetHash, '127.0.0.1')
        );
        $this->assertNull($repository->resetPasswordByToken($resetToken, $resetHash, '127.0.0.1'));
    }

    public function testModuleAssignmentAndMfaBackupCodeAreServerSideOnly(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);

        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));
        $this->assertTrue($moduleRepository->userHasModuleAccess($userId, 'documents'));
        $this->assertFalse($moduleRepository->userHasModuleAccess($userId, 'discussions'));

        $backupHash = password_hash('BACKUP-1', PASSWORD_ARGON2ID);
        $this->assertIsString($backupHash);
        $statement = $database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s` (`private_user_id`, `code_hash`) VALUES (:user_id, :code_hash)',
                $database->table('private_mfa_backup_codes')
            )
        );
        $statement->execute([
            'user_id' => $userId,
            'code_hash' => $backupHash,
        ]);

        $this->assertTrue($userRepository->consumeMfaBackupCode($userId, 'BACKUP-1'));
        $this->assertFalse($userRepository->consumeMfaBackupCode($userId, 'BACKUP-1'));
    }

    public function testMemberProfileCanBeSavedWithoutChangingLoginEmail(): void
    {
        $repository = new PrivateUserRepository($this->editorialSqlDatabase());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $repository->create('member@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);

        $this->assertTrue($repository->updateMemberProfile(
            $userId,
            '<strong>Pauline Bergon</strong>',
            "2738 route de la Mole\n83310 Cogolin",
            '+33 6 12 34 56 78'
        ));

        $profile = $repository->profileForUser($userId);
        $this->assertIsArray($profile);
        $this->assertSame('member@example.com', $profile['email']);
        $this->assertSame('Pauline Bergon', $profile['fullName']);
        $this->assertSame("2738 route de la Mole\n83310 Cogolin", $profile['postalAddress']);
        $this->assertSame('+33 6 12 34 56 78', $profile['phone']);

        $this->assertFalse($repository->updateMemberProfile($userId, 'Pauline', '', 'standard'));
        $unchanged = $repository->profileForUser($userId);
        $this->assertIsArray($unchanged);
        $this->assertSame('member@example.com', $unchanged['email']);
        $this->assertSame('+33 6 12 34 56 78', $unchanged['phone']);
    }

    public function testModuleCannotBeRevokedWhileUserDataExists(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $service = new AdminPrivateMembersService($userRepository, $moduleRepository);
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('with-documents@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);

        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));
        $documentRepository = new PrivateDocumentRepository($database);
        $this->assertIsArray($documentRepository->create(
            $userId,
            'doc-' . bin2hex(random_bytes(8)),
            'documents/test-file.pdf',
            'test-file.pdf',
            'pdf',
            'application/pdf',
            120,
            $userId
        ));

        $result = $service->handleAction([
            'private_member_action' => 'modules',
            'private_user_id' => $userId,
            'modules' => [],
        ], 'admin@example.com');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Impossible de retirer un module', (string) $result['error']);
        $this->assertTrue($moduleRepository->userHasModuleAccess($userId, 'documents'));
    }

    public function testMfaTotpAcceptsCurrentCode(): void
    {
        global $appConfig;

        $appConfig['private']['mfa_totp_enabled'] = true;
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);

        $secret = 'JBSWY3DPEHPK3PXP';
        $statement = $database->pdo()->prepare(
            sprintf(
                'UPDATE `%s` SET `mfa_enabled` = 1, `mfa_secret_encrypted` = :secret WHERE `id` = :id',
                $userRepository->table()
            )
        );
        $statement->execute([
            'secret' => $secret,
            'id' => $userId,
        ]);

        $user = $userRepository->findById($userId);
        $this->assertIsArray($user);

        $verifier = new PrivateMfaVerifier();
        $this->assertTrue($verifier->requiresMfa($user));
        $this->assertTrue($verifier->verify($user, $this->totpCode($secret), $userRepository));
        $this->assertFalse($verifier->verify($user, 'ABCDEF', $userRepository));
    }

    private function totpCode(string $secret): string
    {
        $secretBinary = $this->base32Decode($secret);
        $counter = intdiv(time(), 30);
        $time = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $time, $secretBinary, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($encoded) as $char) {
            $value = strpos($alphabet, $char);
            $this->assertNotFalse($value);
            $bits .= str_pad(decbin((int) $value), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) >= 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}
