<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Ignore;

#[UniqueEntity(fields: ['email'], message: 'An account already exists with this email.')]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken.')]
#[ORM\Entity]
#[ORM\Table(name: 'fitopia_users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'first_name', length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $username = '';

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column]
    #[Ignore]
    private string $password = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $role = 'Patient';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'birth_date', length: 20, nullable: true)]
    private ?string $birthDate = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(name: 'avatar_path', length: 255, nullable: true)]
    private ?string $avatarPath = null;

    #[ORM\Column(name: 'professional_title', length: 100, nullable: true)]
    private ?string $professionalTitle = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $specialization = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qualification = null;

    #[ORM\Column(name: 'years_experience', length: 10, nullable: true)]
    private ?string $yearsExperience = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(name: 'license_number', length: 50, nullable: true)]
    private ?string $licenseNumber = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $height = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $weight = null;

    #[ORM\Column(name: 'target_weight', length: 10, nullable: true)]
    private ?string $targetWeight = null;

    #[ORM\Column(name: 'fitness_level', length: 50, nullable: true)]
    private ?string $fitnessLevel = null;

    #[ORM\Column(name: 'health_conditions', type: 'text', nullable: true)]
    private ?string $healthConditions = null;

    #[ORM\Column(name: 'dietary_preferences', type: 'text', nullable: true)]
    private ?string $dietaryPreferences = null;

    #[ORM\Column(name: 'fitness_goals', type: 'text', nullable: true)]
    private ?string $fitnessGoals = null;

    #[ORM\Column(name: 'face_id_enabled', type: 'boolean', options: ['default' => false])]
    private bool $faceIdEnabled = false;

    #[ORM\Column(name: 'face_image_path', length: 255, nullable: true)]
    private ?string $faceImagePath = null;

    #[ORM\Column(name: 'password_score', type: 'integer', options: ['default' => 0])]
    private int $passwordScore = 0;

    #[ORM\Column(name: 'password_strength', length: 20, nullable: true)]
    private ?string $passwordStrength = 'UNKNOWN';

    #[ORM\Column(name: 'compromised_password', type: 'boolean', options: ['default' => false])]
    private bool $compromisedPassword = false;

    #[ORM\Column(name: 'compromised_occurrences', type: 'integer', options: ['default' => 0])]
    private int $compromisedOccurrences = 0;

    #[ORM\Column(name: 'failed_login_attempts', type: 'integer', options: ['default' => 0])]
    private int $failedLoginAttempts = 0;

    #[ORM\Column(name: 'risk_score', type: 'integer', options: ['default' => 0])]
    private int $riskScore = 0;

    #[ORM\Column(name: 'account_status', length: 20, options: ['default' => 'ACTIVE'])]
    private string $accountStatus = 'ACTIVE';

    #[ORM\Column(name: 'password_last_changed_at', length: 50, nullable: true)]
    private ?string $passwordLastChangedAt = null;

    #[ORM\Column(name: 'locked_until', length: 50, nullable: true)]
    private ?string $lockedUntil = null;

    #[ORM\Column(name: 'last_login_at', length: 50, nullable: true)]
    private ?string $lastLoginAt = null;

    #[ORM\Column(name: 'last_failed_login_at', length: 50, nullable: true)]
    private ?string $lastFailedLoginAt = null;

    #[ORM\Column(name: 'is_archived', type: 'boolean', options: ['default' => false])]
    private bool $archived = false;

    #[ORM\Column(name: 'archived_at', length: 50, nullable: true)]
    private ?string $archivedAt = null;

    private bool $twoFactorEnabled = false;
    private ?string $twoFactorSecret = null;
    private ?string $faceIdTokenHash = null;
    private ?string $faceIdReferenceToken = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = trim($email);

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = trim($username);

        return $this;
    }

    public function getRoles(): array
    {
        $normalized = $this->normalizeRoleLabel($this->role);
        $roles = ['ROLE_USER'];

        if ($normalized === 'ADMIN') {
            $roles[] = 'ROLE_ADMIN';
        } elseif ($normalized === 'COACH') {
            $roles[] = 'ROLE_COACH';
        } elseif ($normalized === 'NUTRITIONIST') {
            $roles[] = 'ROLE_NUTRITIONIST';
        } else {
            $roles[] = 'ROLE_PATIENT';
        }

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $roles = array_map(static fn (mixed $role): string => strtoupper(trim((string) $role)), $roles);

        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ADMIN', $roles, true)) {
            $this->role = 'Admin';
        } elseif (in_array('ROLE_NUTRITIONIST', $roles, true) || in_array('NUTRITIONIST', $roles, true)) {
            $this->role = 'Nutritionist';
        } elseif (in_array('ROLE_COACH', $roles, true) || in_array('COACH', $roles, true)) {
            $this->role = 'Coach';
        } else {
            $this->role = 'Patient';
        }

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $value): self
    {
        $this->firstName = $this->normalizeNullableString($value);

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $value): self
    {
        $this->lastName = $this->normalizeNullableString($value);

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $value): self
    {
        $normalized = $this->normalizeRoleLabel($value);
        $this->role = match ($normalized) {
            'ADMIN' => 'Admin',
            'COACH' => 'Coach',
            'NUTRITIONIST' => 'Nutritionist',
            default => 'Patient',
        };

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $value): self
    {
        $this->phone = $this->normalizeNullableString($value);

        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->parseDate($this->birthDate);
    }

    public function getBirthDateRaw(): ?string
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTimeInterface|string|null $value): self
    {
        if ($value instanceof \DateTimeInterface) {
            $this->birthDate = $value->format('Y-m-d');

            return $this;
        }

        $this->birthDate = $this->normalizeDateString($value);

        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $value): self
    {
        $this->gender = $this->normalizeNullableString($value);

        return $this;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $value): self
    {
        $this->avatarPath = $this->normalizeNullableString($value);

        return $this;
    }

    public function getProfessionalTitle(): ?string
    {
        return $this->professionalTitle;
    }

    public function setProfessionalTitle(?string $value): self
    {
        $this->professionalTitle = $this->normalizeNullableString($value);

        return $this;
    }

    public function getSpecialization(): array
    {
        return $this->decodeStringList($this->specialization);
    }

    public function getSpecializationRaw(): ?string
    {
        return $this->specialization;
    }

    public function setSpecialization(array|string|null $value): self
    {
        $this->specialization = $this->encodeStringList($value);

        return $this;
    }

    public function getQualification(): ?string
    {
        return $this->qualification;
    }

    public function setQualification(?string $value): self
    {
        $this->qualification = $this->normalizeNullableString($value);

        return $this;
    }

    public function getYearsExperience(): ?int
    {
        return $this->yearsExperience === null || trim($this->yearsExperience) === ''
            ? null
            : (int) $this->yearsExperience;
    }

    public function setYearsExperience(int|string|null $value): self
    {
        $this->yearsExperience = $this->normalizeNumericString($value);

        return $this;
    }

    public function getYearsOfExperience(): ?int
    {
        return $this->getYearsExperience();
    }

    public function setYearsOfExperience(int|string|null $value): self
    {
        return $this->setYearsExperience($value);
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $value): self
    {
        $this->bio = $this->normalizeNullableString($value);

        return $this;
    }

    public function getLicenseNumber(): ?string
    {
        return $this->licenseNumber;
    }

    public function setLicenseNumber(?string $value): self
    {
        $this->licenseNumber = $this->normalizeNullableString($value);

        return $this;
    }

    public function getHeight(): ?float
    {
        return $this->parseNullableFloat($this->height);
    }

    public function getHeightRaw(): ?string
    {
        return $this->height;
    }

    public function setHeight(float|int|string|null $value): self
    {
        $this->height = $this->normalizeNumericString($value);

        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->parseNullableFloat($this->weight);
    }

    public function getWeightRaw(): ?string
    {
        return $this->weight;
    }

    public function setWeight(float|int|string|null $value): self
    {
        $this->weight = $this->normalizeNumericString($value);

        return $this;
    }

    public function getTargetWeight(): ?float
    {
        return $this->parseNullableFloat($this->targetWeight);
    }

    public function getTargetWeightRaw(): ?string
    {
        return $this->targetWeight;
    }

    public function setTargetWeight(float|int|string|null $value): self
    {
        $this->targetWeight = $this->normalizeNumericString($value);

        return $this;
    }

    public function getFitnessLevel(): ?string
    {
        return $this->fitnessLevel;
    }

    public function setFitnessLevel(?string $value): self
    {
        $this->fitnessLevel = $this->normalizeNullableString($value);

        return $this;
    }

    public function getHealthConditions(): array
    {
        return $this->decodeStringList($this->healthConditions);
    }

    public function getHealthConditionsRaw(): ?string
    {
        return $this->healthConditions;
    }

    public function setHealthConditions(array|string|null $value): self
    {
        $this->healthConditions = $this->encodeStringList($value);

        return $this;
    }

    public function getDietaryPreferences(): array
    {
        return $this->decodeStringList($this->dietaryPreferences);
    }

    public function getDietaryPreferencesRaw(): ?string
    {
        return $this->dietaryPreferences;
    }

    public function setDietaryPreferences(array|string|null $value): self
    {
        $this->dietaryPreferences = $this->encodeStringList($value);

        return $this;
    }

    public function getFitnessGoals(): array
    {
        return $this->decodeStringList($this->fitnessGoals);
    }

    public function getFitnessGoalsRaw(): ?string
    {
        return $this->fitnessGoals;
    }

    public function setFitnessGoals(array|string|null $value): self
    {
        $this->fitnessGoals = $this->encodeStringList($value);

        return $this;
    }

    public function isFaceIdEnabled(): bool
    {
        return $this->faceIdEnabled;
    }

    public function setFaceIdEnabled(bool $value): self
    {
        $this->faceIdEnabled = $value;

        return $this;
    }

    public function getFaceImagePath(): ?string
    {
        return $this->faceImagePath;
    }

    public function setFaceImagePath(?string $value): self
    {
        $this->faceImagePath = $this->normalizeNullableString($value);

        return $this;
    }

    public function getPasswordScore(): int
    {
        return $this->passwordScore;
    }

    public function setPasswordScore(int $value): self
    {
        $this->passwordScore = $value;

        return $this;
    }

    public function getPasswordStrength(): ?string
    {
        return $this->passwordStrength;
    }

    public function setPasswordStrength(?string $value): self
    {
        $this->passwordStrength = $this->normalizeNullableString($value) ?? 'UNKNOWN';

        return $this;
    }

    public function isCompromisedPassword(): bool
    {
        return $this->compromisedPassword;
    }

    public function setCompromisedPassword(bool $value): self
    {
        $this->compromisedPassword = $value;

        return $this;
    }

    public function getCompromisedOccurrences(): int
    {
        return $this->compromisedOccurrences;
    }

    public function setCompromisedOccurrences(int $value): self
    {
        $this->compromisedOccurrences = $value;

        return $this;
    }

    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function setFailedLoginAttempts(int $value): self
    {
        $this->failedLoginAttempts = $value;

        return $this;
    }

    public function getRiskScore(): int
    {
        return $this->riskScore;
    }

    public function setRiskScore(int $value): self
    {
        $this->riskScore = $value;

        return $this;
    }

    public function getAccountStatus(): string
    {
        return $this->accountStatus;
    }

    public function setAccountStatus(string $value): self
    {
        $this->accountStatus = trim($value) === '' ? 'ACTIVE' : trim($value);

        return $this;
    }

    public function getPasswordLastChangedAt(): ?string
    {
        return $this->passwordLastChangedAt;
    }

    public function setPasswordLastChangedAt(?string $value): self
    {
        $this->passwordLastChangedAt = $this->normalizeNullableString($value);

        return $this;
    }

    public function getLockedUntil(): ?string
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?string $value): self
    {
        $this->lockedUntil = $this->normalizeNullableString($value);

        return $this;
    }

    public function getLastLoginAt(): ?string
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?string $value): self
    {
        $this->lastLoginAt = $this->normalizeNullableString($value);

        return $this;
    }

    public function getLastFailedLoginAt(): ?string
    {
        return $this->lastFailedLoginAt;
    }

    public function setLastFailedLoginAt(?string $value): self
    {
        $this->lastFailedLoginAt = $this->normalizeNullableString($value);

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $value): self
    {
        $this->archived = $value;

        return $this;
    }

    public function getArchivedAt(): ?string
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?string $value): self
    {
        $this->archivedAt = $this->normalizeNullableString($value);

        return $this;
    }

    public function getDisplayName(): string
    {
        $name = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));

        return $name !== '' ? $name : ($this->username !== '' ? $this->username : $this->email);
    }

    public function getAvatar(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatar(?string $value): self
    {
        return $this->setAvatarPath($value);
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $value): self
    {
        $this->twoFactorEnabled = $value;

        return $this;
    }

    public function getTwoFactorSecret(): ?string
    {
        return $this->twoFactorSecret;
    }

    public function setTwoFactorSecret(?string $value): self
    {
        $this->twoFactorSecret = $this->normalizeNullableString($value);

        return $this;
    }

    public function getFaceIdTokenHash(): ?string
    {
        return $this->faceIdTokenHash;
    }

    public function setFaceIdTokenHash(?string $value): self
    {
        $this->faceIdTokenHash = $this->normalizeNullableString($value);

        return $this;
    }

    public function getFaceIdReferenceToken(): ?string
    {
        return $this->faceIdReferenceToken;
    }

    public function setFaceIdReferenceToken(?string $value): self
    {
        $this->faceIdReferenceToken = $this->normalizeNullableString($value);

        return $this;
    }

    private function normalizeRoleLabel(?string $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            'ROLE_ADMIN', 'ADMIN' => 'ADMIN',
            'ROLE_COACH', 'COACH' => 'COACH',
            'ROLE_NUTRITIONIST', 'NUTRITIONIST' => 'NUTRITIONIST',
            default => 'PATIENT',
        };
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeNumericString(float|int|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        return $string;
    }

    private function parseNullableFloat(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (float) $value;
    }

    private function normalizeDateString(string|null $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function decodeStringList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $trimmed = trim($value);
        if (str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(
                    static fn (mixed $item): string => trim((string) $item),
                    $decoded
                )));
            }
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            preg_split('/[,;\n\r]+/', $trimmed) ?: []
        )));
    }

    private function encodeStringList(array|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        $items = array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value
        )));

        return $items === [] ? null : implode(', ', $items);
    }
}
