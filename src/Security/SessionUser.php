<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class SessionUser implements UserInterface
{
    private string $email;
    private string $username;
    private array $roles;

    public function __construct(string $email, string $username, array $roles)
    {
        $this->email = $email;
        $this->username = $username;
        $this->roles = $roles;
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
}
