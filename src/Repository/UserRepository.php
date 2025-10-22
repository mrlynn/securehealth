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
        try {
            return $this->repository->findOneBy(['email' => $email]);
        } catch (\Exception $e) {
            // MongoDB connection failed, return null
            return null;
        }
    }

    public function save(User $user, bool $flush = true): void
    {
        try {
            $this->documentManager->persist($user);
            
            if ($flush) {
                $this->documentManager->flush();
            }
        } catch (\Exception $e) {
            // MongoDB connection failed, ignore save
        }
    }

    public function findAll(): array
    {
        try {
            return $this->repository->findAll();
        } catch (\Exception $e) {
            // MongoDB connection failed, return empty array
            return [];
        }
    }

    public function count(array $criteria = []): int
    {
        try {
            return $this->repository->count($criteria);
        } catch (\Exception $e) {
            // MongoDB connection failed, return 0
            return 0;
        }
    }
}