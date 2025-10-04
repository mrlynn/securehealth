<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use MongoDB\BSON\ObjectId;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[MongoDB\Document(collection: "users")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[MongoDB\Id]
    protected $id;

    #[MongoDB\Field(type: "string")]
    private string $email;

    #[MongoDB\Field(type: "string")]
    private string $username;

    #[MongoDB\Field(type: "collection")]
    private array $roles = [];

    #[MongoDB\Field(type: "string")]
    private string $password;

    #[MongoDB\Field(type: "bool")]
    private bool $isAdmin = false;

    #[MongoDB\Field(type: "bool")]
    private bool $isPatient = false;

    #[MongoDB\Field(type: "object_id", nullable: true)]
    private $patientId = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
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

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): self
    {
        $this->isAdmin = $isAdmin;
        return $this;
    }

    public function isPatient(): bool
    {
        return $this->isPatient;
    }

    public function setIsPatient(bool $isPatient): self
    {
        $this->isPatient = $isPatient;
        return $this;
    }

    public function getPatientId()
    {
        return $this->patientId;
    }

    public function setPatientId($patientId): self
    {
        if (is_string($patientId)) {
            $this->patientId = new ObjectId($patientId);
        } else {
            $this->patientId = $patientId;
        }
        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }
}