<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[UniqueEntity(fields: ['email'], message: 'An account already exists with this email.')]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken.')]
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 100, unique: true)]
    private string $username;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(length: 20)]
    private string $phone;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $birthDate;

    #[ORM\Column(length: 10)]
    private string $gender;

    #[ORM\Column(nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column]
    #[Ignore]
    private string $password;

    #[ORM\Column(options: ['default' => false])]
    private bool $faceIdEnabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Ignore]
    private ?string $faceIdTokenHash = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Ignore]
    private ?string $faceIdReferenceToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $faceIdUpdatedAt = null;

    #[ORM\Column(length: 20, options: ['default' => 'standard'])]
    #[Ignore]
    private string $passwordPolicyLevel = 'standard';

    #[ORM\Column(options: ['default' => false])]
    #[Ignore]
    private bool $passwordMustChange = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Ignore]
    private ?\DateTimeImmutable $passwordLastChangedAt = null;

    // USER
    #[ORM\Column(nullable: true)]
    private ?float $height = null;

    #[ORM\Column(nullable: true)]
    private ?float $weight = null;

    #[ORM\Column(nullable: true)]
    private ?float $targetWeight = null;

    #[ORM\Column(nullable: true)]
    private ?string $fitnessLevel = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $healthConditions = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $dietaryPreferences = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $fitnessGoals = [];

    // PROFESSIONAL
    #[ORM\Column(nullable: true)]
    private ?string $professionalTitle = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $specialization = [];

    #[ORM\Column(nullable: true)]
    private ?string $qualification = null;

    #[ORM\Column(nullable: true)]
    private ?int $yearsOfExperience = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(nullable: true)]
    private ?string $licenseNumber = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $onboardingCompleted = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $onboardingCompletedSteps = [];

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $onboardingStartedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $onboardingCompletedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $riskScore = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(length: 64, nullable: true)]
    #[Ignore]
    private ?string $twoFactorSecret = null;

    // ---------------- SECURITY ----------------

    public function getId(): ?int { return $this->id; }

    public function getUserIdentifier(): string { return $this->email; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): self { $this->username = $username; return $this; }

    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $v): self { $this->firstName = $v; return $this; }

    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $v): self { $this->lastName = $v; return $this; }

    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $v): self { $this->phone = $v; return $this; }

    public function getBirthDate(): \DateTimeInterface { return $this->birthDate; }
    public function setBirthDate(\DateTimeInterface $v): self { $this->birthDate = $v; return $this; }

    public function getGender(): string { return $this->gender; }
    public function setGender(string $v): self { $this->gender = $v; return $this; }

    public function getAvatar(): ?string { return $this->avatar; }
    public function setAvatar(?string $avatar): self { $this->avatar = $avatar; return $this; }

    public function getRoles(): array {
        return array_unique(array_merge($this->roles, ['ROLE_USER']));
    }

    public function setRoles(array $roles): self {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string { return $this->password; }
    public function setPassword(#[\SensitiveParameter] string $password): self { $this->password = $password; return $this; }

    public function isFaceIdEnabled(): bool { return $this->faceIdEnabled; }
    public function setFaceIdEnabled(bool $faceIdEnabled): self { $this->faceIdEnabled = $faceIdEnabled; return $this; }

    public function getFaceIdTokenHash(): ?string { return $this->faceIdTokenHash; }
    public function setFaceIdTokenHash(#[\SensitiveParameter] ?string $faceIdTokenHash): self { $this->faceIdTokenHash = $faceIdTokenHash; return $this; }

    public function getFaceIdReferenceToken(): ?string { return $this->faceIdReferenceToken; }
    public function setFaceIdReferenceToken(#[\SensitiveParameter] ?string $faceIdReferenceToken): self { $this->faceIdReferenceToken = $faceIdReferenceToken; return $this; }

    public function getFaceIdUpdatedAt(): ?\DateTimeImmutable { return $this->faceIdUpdatedAt; }
    public function setFaceIdUpdatedAt(?\DateTimeImmutable $faceIdUpdatedAt): self { $this->faceIdUpdatedAt = $faceIdUpdatedAt; return $this; }

    public function getPasswordPolicyLevel(): string { return $this->passwordPolicyLevel; }
    public function setPasswordPolicyLevel(string $passwordPolicyLevel): self { $this->passwordPolicyLevel = $passwordPolicyLevel; return $this; }

    public function isPasswordMustChange(): bool { return $this->passwordMustChange; }
    public function setPasswordMustChange(bool $passwordMustChange): self { $this->passwordMustChange = $passwordMustChange; return $this; }

    public function getPasswordLastChangedAt(): ?\DateTimeImmutable { return $this->passwordLastChangedAt; }
    public function setPasswordLastChangedAt(?\DateTimeImmutable $passwordLastChangedAt): self { $this->passwordLastChangedAt = $passwordLastChangedAt; return $this; }

    public function getHeight(): ?float { return $this->height; }
    public function setHeight(?float $height): self { $this->height = $height; return $this; }

    public function getWeight(): ?float { return $this->weight; }
    public function setWeight(?float $weight): self { $this->weight = $weight; return $this; }

    public function getTargetWeight(): ?float { return $this->targetWeight; }
    public function setTargetWeight(?float $targetWeight): self { $this->targetWeight = $targetWeight; return $this; }

    public function getFitnessLevel(): ?string { return $this->fitnessLevel; }
    public function setFitnessLevel(?string $fitnessLevel): self { $this->fitnessLevel = $fitnessLevel; return $this; }

    public function getHealthConditions(): ?array { return $this->healthConditions; }
    public function setHealthConditions(?array $healthConditions): self { $this->healthConditions = $healthConditions; return $this; }

    public function getDietaryPreferences(): ?array { return $this->dietaryPreferences; }
    public function setDietaryPreferences(?array $dietaryPreferences): self { $this->dietaryPreferences = $dietaryPreferences; return $this; }

    public function getFitnessGoals(): ?array { return $this->fitnessGoals; }
    public function setFitnessGoals(?array $fitnessGoals): self { $this->fitnessGoals = $fitnessGoals; return $this; }

    public function getProfessionalTitle(): ?string { return $this->professionalTitle; }
    public function setProfessionalTitle(?string $professionalTitle): self { $this->professionalTitle = $professionalTitle; return $this; }

    public function getSpecialization(): ?array { return $this->specialization; }
    public function setSpecialization(?array $specialization): self { $this->specialization = $specialization; return $this; }

    public function getQualification(): ?string { return $this->qualification; }
    public function setQualification(?string $qualification): self { $this->qualification = $qualification; return $this; }

    public function getYearsOfExperience(): ?int { return $this->yearsOfExperience; }
    public function setYearsOfExperience(?int $yearsOfExperience): self { $this->yearsOfExperience = $yearsOfExperience; return $this; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): self { $this->bio = $bio; return $this; }

    public function getLicenseNumber(): ?string { return $this->licenseNumber; }
    public function setLicenseNumber(?string $licenseNumber): self { $this->licenseNumber = $licenseNumber; return $this; }

    public function isOnboardingCompleted(): bool { return $this->onboardingCompleted; }
    public function setOnboardingCompleted(bool $onboardingCompleted): self { $this->onboardingCompleted = $onboardingCompleted; return $this; }

    public function getOnboardingCompletedSteps(): ?array { return $this->onboardingCompletedSteps; }
    public function setOnboardingCompletedSteps(?array $onboardingCompletedSteps): self { $this->onboardingCompletedSteps = $onboardingCompletedSteps; return $this; }

    public function getOnboardingStartedAt(): ?\DateTimeImmutable { return $this->onboardingStartedAt; }
    public function setOnboardingStartedAt(?\DateTimeImmutable $onboardingStartedAt): self { $this->onboardingStartedAt = $onboardingStartedAt; return $this; }

    public function getOnboardingCompletedAt(): ?\DateTimeImmutable { return $this->onboardingCompletedAt; }
    public function setOnboardingCompletedAt(?\DateTimeImmutable $onboardingCompletedAt): self { $this->onboardingCompletedAt = $onboardingCompletedAt; return $this; }

    public function getRiskScore(): int { return $this->riskScore; }
    public function setRiskScore(int $riskScore): self
    {
        $this->riskScore = max(0, min(100, $riskScore));

        return $this;
    }

    public function isTwoFactorEnabled(): bool { return $this->twoFactorEnabled; }
    public function setTwoFactorEnabled(bool $enabled): self { $this->twoFactorEnabled = $enabled; return $this; }

    public function getTwoFactorSecret(): ?string { return $this->twoFactorSecret; }
    public function setTwoFactorSecret(#[\SensitiveParameter] ?string $secret): self { $this->twoFactorSecret = $secret; return $this; }

    public function eraseCredentials(): void {}
}
