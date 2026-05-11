<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class LegacyUserBridgeService
{
    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    /** @var array<string, list<string>> */
    private array $columnCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection
    ) {
    }

    public function findOrImportByIdentifier(string $identifier): ?User
    {
        $identifier = trim(mb_strtolower($identifier));
        if ($identifier === '') {
            return null;
        }

        $localUser = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('LOWER(u.email) = :identifier')
            ->orWhere('LOWER(u.username) = :identifier')
            ->setParameter('identifier', $identifier)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($localUser instanceof User) {
            return $localUser;
        }

        $legacyRow = $this->findLegacyRow($identifier);
        if ($legacyRow === null) {
            return null;
        }

        $user = $this->hydrateFromLegacyRow(new User(), $legacyRow);
        $this->em->persist($user);
        $this->em->flush();
        $this->syncUser($user);

        return $user;
    }

    public function syncUser(User $user): void
    {
        if (!$this->tableExists('users')) {
            return;
        }

        $columns = $this->tableColumns('users');
        if ($columns === []) {
            return;
        }

        $payload = $this->buildLegacyPayload($user, $columns);
        if ($payload === []) {
            return;
        }

        $targetId = $user->getId();
        $existingId = $this->findLegacyIdByIdentifier($user->getEmail(), $user->getUsername());
        if ($existingId !== null) {
            if (
                $targetId !== null
                && $existingId !== $targetId
                && !$this->legacyIdExists($targetId)
                && in_array('id', $columns, true)
            ) {
                $payload['id'] = $targetId;
            }

            $this->connection->update('users', $payload, ['id' => $existingId]);

            return;
        }

        if ($targetId !== null && !$this->legacyIdExists($targetId) && in_array('id', $columns, true)) {
            $payload['id'] = $targetId;
        }

        $this->connection->insert('users', $payload);
    }

    public function ensureLegacyMirror(User $user): void
    {
        $this->syncUser($user);
    }

    public function deleteUser(User $user): void
    {
        if (!$this->tableExists('users')) {
            return;
        }

        $email = trim($user->getEmail());
        $username = trim($user->getUsername());
        if ($email === '' && $username === '') {
            return;
        }

        $sql = 'DELETE FROM users WHERE LOWER(email) = LOWER(:email) OR LOWER(COALESCE(username, \'\')) = LOWER(:username)';
        $this->connection->executeStatement($sql, [
            'email' => $email,
            'username' => $username,
        ]);
    }

    public function issuePasswordResetCode(string $email): ?string
    {
        $user = $this->findOrImportByIdentifier($email);
        if (!$user instanceof User) {
            return null;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = (new \DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s');

        $this->connection->update('fitopia_users', [
            'reset_password_code' => $code,
            'reset_password_expires_at' => $expiresAt,
        ], [
            'id' => $user->getId(),
        ]);

        return $code;
    }

    public function resetPasswordWithCode(string $email, string $otpCode, string $hashedPassword): bool
    {
        $user = $this->findOrImportByIdentifier($email);
        if (!$user instanceof User || $user->getId() === null) {
            return false;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT reset_password_code, reset_password_expires_at FROM fitopia_users WHERE id = :id LIMIT 1',
            ['id' => $user->getId()]
        );

        if (!is_array($row)) {
            return false;
        }

        $storedCode = trim((string) ($row['reset_password_code'] ?? ''));
        $expiresAt = trim((string) ($row['reset_password_expires_at'] ?? ''));
        if ($storedCode === '' || !hash_equals($storedCode, trim($otpCode))) {
            return false;
        }

        try {
            $expires = new \DateTimeImmutable($expiresAt);
        } catch (\Throwable) {
            return false;
        }

        if ($expires <= new \DateTimeImmutable()) {
            return false;
        }

        $user->setPassword($hashedPassword);
        $user->setPasswordLastChangedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        $this->em->flush();
        $this->syncUser($user);

        $this->connection->update('fitopia_users', [
            'reset_password_code' => null,
            'reset_password_expires_at' => null,
        ], [
            'id' => $user->getId(),
        ]);

        return true;
    }

    private function findLegacyRow(string $identifier): ?array
    {
        if (!$this->tableExists('users')) {
            return null;
        }

        $sql = 'SELECT * FROM users WHERE LOWER(email) = LOWER(:identifier) OR LOWER(COALESCE(username, \'\')) = LOWER(:identifier) LIMIT 1';
        $row = $this->connection->fetchAssociative($sql, ['identifier' => $identifier]);

        return is_array($row) ? $row : null;
    }

    private function hydrateFromLegacyRow(User $user, array $row): User
    {
        $email = $this->pickString($row, ['email']);
        $username = $this->pickString($row, ['username']);
        $role = $this->resolveLegacyRole($row);

        $user
            ->setEmail($email !== '' ? $email : $username)
            ->setUsername($username !== '' ? $username : $this->usernameFromEmail($email))
            ->setPassword($this->pickString($row, ['password']))
            ->setRole($role)
            ->setFirstName($this->pickNullableString($row, ['first_name', 'firstname', 'prenom']))
            ->setLastName($this->pickNullableString($row, ['last_name', 'lastname', 'nom']))
            ->setPhone($this->pickNullableString($row, ['phone']))
            ->setBirthDate($this->pickNullableString($row, ['birth_date']))
            ->setGender($this->pickNullableString($row, ['gender']))
            ->setAvatarPath($this->pickNullableString($row, ['avatar_path', 'avatar']))
            ->setProfessionalTitle($this->pickNullableString($row, ['professional_title']))
            ->setSpecialization($this->pickNullableString($row, ['specialization']))
            ->setQualification($this->pickNullableString($row, ['qualification']))
            ->setYearsExperience($this->pickNullableString($row, ['years_experience', 'years_of_experience']))
            ->setBio($this->pickNullableString($row, ['bio']))
            ->setLicenseNumber($this->pickNullableString($row, ['license_number']))
            ->setHeight($this->pickNullableString($row, ['height']))
            ->setWeight($this->pickNullableString($row, ['weight']))
            ->setTargetWeight($this->pickNullableString($row, ['target_weight']))
            ->setFitnessLevel($this->pickNullableString($row, ['fitness_level']))
            ->setHealthConditions($this->pickNullableString($row, ['health_conditions']))
            ->setDietaryPreferences($this->pickNullableString($row, ['dietary_preferences']))
            ->setFitnessGoals($this->pickNullableString($row, ['fitness_goals']))
            ->setFaceIdEnabled($this->pickBool($row, ['face_id_enabled']))
            ->setFaceImagePath($this->pickNullableString($row, ['face_image_path']))
            ->setPasswordScore($this->pickInt($row, ['password_score']))
            ->setPasswordStrength($this->pickNullableString($row, ['password_strength']) ?? 'UNKNOWN')
            ->setCompromisedPassword($this->pickBool($row, ['compromised_password']))
            ->setCompromisedOccurrences($this->pickInt($row, ['compromised_occurrences']))
            ->setFailedLoginAttempts($this->pickInt($row, ['failed_login_attempts']))
            ->setRiskScore($this->pickInt($row, ['risk_score']))
            ->setAccountStatus($this->pickString($row, ['account_status']) ?: 'ACTIVE')
            ->setPasswordLastChangedAt($this->pickNullableString($row, ['password_last_changed_at']))
            ->setLockedUntil($this->pickNullableString($row, ['locked_until']))
            ->setLastLoginAt($this->pickNullableString($row, ['last_login_at']))
            ->setLastFailedLoginAt($this->pickNullableString($row, ['last_failed_login_at']))
            ->setArchived($this->pickBool($row, ['is_archived']))
            ->setArchivedAt($this->pickNullableString($row, ['archived_at']));

        return $user;
    }

    /**
     * @param list<string> $columns
     *
     * @return array<string, mixed>
     */
    private function buildLegacyPayload(User $user, array $columns): array
    {
        $payload = [];

        $this->putIfColumnExists($payload, $columns, 'username', $user->getUsername());
        $this->putIfColumnExists($payload, $columns, 'email', $user->getEmail());
        $this->putIfColumnExists($payload, $columns, 'password', $user->getPassword());
        $this->putIfColumnExists($payload, $columns, 'role', $user->getRole());
        $this->putIfColumnExists($payload, $columns, 'roles', json_encode($user->getRoles(), JSON_UNESCAPED_SLASHES));
        $this->putIfColumnExists($payload, $columns, 'first_name', $user->getFirstName());
        $this->putIfColumnExists($payload, $columns, 'last_name', $user->getLastName());
        $this->putIfColumnExists($payload, $columns, 'phone', $user->getPhone());
        $this->putIfColumnExists($payload, $columns, 'birth_date', $user->getBirthDate()?->format('Y-m-d'));
        $this->putIfColumnExists($payload, $columns, 'gender', $user->getGender());
        $this->putIfColumnExists($payload, $columns, 'avatar', $user->getAvatarPath());
        $this->putIfColumnExists($payload, $columns, 'professional_title', $user->getProfessionalTitle());
        $this->putIfColumnExists($payload, $columns, 'specialization', $user->getSpecializationRaw());
        $this->putIfColumnExists($payload, $columns, 'qualification', $user->getQualification());
        $this->putIfColumnExists($payload, $columns, 'years_of_experience', $user->getYearsExperience());
        $this->putIfColumnExists($payload, $columns, 'bio', $user->getBio());
        $this->putIfColumnExists($payload, $columns, 'license_number', $user->getLicenseNumber());
        $this->putIfColumnExists($payload, $columns, 'height', $user->getHeight());
        $this->putIfColumnExists($payload, $columns, 'weight', $user->getWeight());
        $this->putIfColumnExists($payload, $columns, 'target_weight', $user->getTargetWeight());
        $this->putIfColumnExists($payload, $columns, 'fitness_level', $user->getFitnessLevel());
        $this->putIfColumnExists($payload, $columns, 'health_conditions', $user->getHealthConditionsRaw());
        $this->putIfColumnExists($payload, $columns, 'dietary_preferences', $user->getDietaryPreferencesRaw());
        $this->putIfColumnExists($payload, $columns, 'fitness_goals', $user->getFitnessGoalsRaw());
        $this->putIfColumnExists($payload, $columns, 'face_id_enabled', $user->isFaceIdEnabled() ? 1 : 0);
        $this->putIfColumnExists($payload, $columns, 'face_image_path', $user->getFaceImagePath());

        return $payload;
    }

    private function findLegacyIdByIdentifier(string $email, string $username): ?int
    {
        $sql = 'SELECT id FROM users WHERE LOWER(email) = LOWER(:email) OR LOWER(COALESCE(username, \'\')) = LOWER(:username) LIMIT 1';
        $id = $this->connection->fetchOne($sql, [
            'email' => $email,
            'username' => $username,
        ]);

        return $id === false ? null : (int) $id;
    }

    private function legacyIdExists(int $id): bool
    {
        if (!$this->tableExists('users')) {
            return false;
        }

        $existing = $this->connection->fetchOne(
            'SELECT id FROM users WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return $existing !== false;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        try {
            $exists = (bool) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
                ['table' => $table]
            );
        } catch (\Throwable) {
            $exists = false;
        }

        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    /**
     * @return list<string>
     */
    private function tableColumns(string $table): array
    {
        if (array_key_exists($table, $this->columnCache)) {
            return $this->columnCache[$table];
        }

        if (!$this->tableExists($table)) {
            $this->columnCache[$table] = [];

            return [];
        }

        try {
            $columns = $this->connection->fetchFirstColumn(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
                ['table' => $table]
            );
        } catch (\Throwable) {
            $columns = [];
        }

        $this->columnCache[$table] = array_values(array_map('strval', $columns));

        return $this->columnCache[$table];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $columns
     */
    private function putIfColumnExists(array &$payload, array $columns, string $column, mixed $value): void
    {
        if (in_array($column, $columns, true)) {
            $payload[$column] = $value;
        }
    }

    private function usernameFromEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || !str_contains($email, '@')) {
            return $email;
        }

        return (string) strstr($email, '@', true);
    }

    private function resolveLegacyRole(array $row): string
    {
        $role = strtoupper($this->pickString($row, ['role']));
        if ($role !== '') {
            return match ($role) {
                'ADMIN', 'ROLE_ADMIN' => 'Admin',
                'COACH', 'ROLE_COACH' => 'Coach',
                'NUTRITIONIST', 'ROLE_NUTRITIONIST' => 'Nutritionist',
                default => 'Patient',
            };
        }

        $rolesValue = $this->pickString($row, ['roles']);
        if ($rolesValue !== '') {
            $upper = strtoupper($rolesValue);
            if (str_contains($upper, 'ROLE_ADMIN')) {
                return 'Admin';
            }
            if (str_contains($upper, 'ROLE_NUTRITIONIST')) {
                return 'Nutritionist';
            }
            if (str_contains($upper, 'ROLE_COACH')) {
                return 'Coach';
            }
        }

        return 'Patient';
    }

    private function pickString(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function pickNullableString(array $row, array $keys): ?string
    {
        $value = $this->pickString($row, $keys);

        return $value === '' ? null : $value;
    }

    private function pickInt(array $row, array $keys): int
    {
        $value = $this->pickString($row, $keys);

        return $value === '' ? 0 : (int) $value;
    }

    private function pickBool(array $row, array $keys): bool
    {
        $value = strtoupper($this->pickString($row, $keys));

        return in_array($value, ['1', 'TRUE', 'YES', 'ON'], true);
    }
}
