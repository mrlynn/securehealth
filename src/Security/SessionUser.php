<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class SessionUser implements UserInterface
{
    private string $email;
    private string $username;
    private array $roles;
    private bool $isPatient;
    private ?string $patientId;

    public function __construct(string $email, string $username, array $roles, bool $isPatient = false, ?string $patientId = null)
    {
        $this->email = $email;
        $this->username = $username;
        $this->roles = $roles;
        $this->isPatient = $isPatient;
        $this->patientId = $patientId;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
        // Nothing to erase for session-based user
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function isPatient(): bool
    {
        return $this->isPatient;
    }

    public function getPatientId(): ?string
    {
        return $this->patientId;
    }
}
