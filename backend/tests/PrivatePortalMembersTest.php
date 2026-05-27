<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminPrivateMembersService;
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

    public function testAnonymizedMemberCannotBeResetOrReceiveModulesOrSuspension(): void
    {
        $database = $this->editorialSqlDatabase();
        $repository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $service = new AdminPrivateMembersService(
            $repository,
            $moduleRepository
        );

        $passwordHash = password_hash('ActivePassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $repository->create('member@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($repository->anonymize($userId));

        $anonymizeResult = $service->handleAction(
            [
                'private_member_action' => 'anonymize',
                'private_user_id' => $userId,
            ],
            'admin@example.com'
        );
        $this->assertTrue($anonymizeResult['success']);

        $suspendResult = $service->handleAction(
            [
                'private_member_action' => 'suspend',
                'private_user_id' => $userId,
            ],
            'admin@example.com'
        );
        $this->assertFalse($suspendResult['success']);
        $this->assertSame('Un compte anonymisé ne peut pas être suspendu.', $suspendResult['error']);

        $resetResult = $service->handleAction(
            [
                'private_member_action' => 'reset',
                'private_user_id' => $userId,
            ],
            'admin@example.com'
        );
        $this->assertFalse($resetResult['success']);
        $this->assertSame('Un compte anonymisé ne peut pas recevoir de reset.', $resetResult['error']);

        $moduleResult = $service->handleAction(
            [
                'private_member_action' => 'modules',
                'private_user_id' => $userId,
                'modules' => ['documents'],
            ],
            'admin@example.com'
        );
        $this->assertFalse($moduleResult['success']);
        $this->assertSame('Un compte anonymisé ne peut pas recevoir de modules.', $moduleResult['error']);
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

    public function testMfaTotpAcceptsCurrentCode(): void
    {
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
