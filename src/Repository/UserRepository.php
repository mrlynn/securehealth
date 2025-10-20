<?php

namespace App\Repository;

use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

class UserRepository
{
    private $documentManager;
    private $repository;

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
        $this->repository = $documentManager->getRepository(User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->repository->findOneBy(['email' => $email]);
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->documentManager->persist($user);
        
        if ($flush) {
            $this->documentManager->flush();
        }
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function count(array $criteria = []): int
    {
        return $this->repository->count($criteria);
    }
}