<?php

namespace App\Security;

use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class DocumentUserProvider implements UserProviderInterface
{
    private DocumentManager $documentManager;

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        // Reload the user from the database
        $refreshedUser = $this->documentManager->find(User::class, $user->getId());
        if (null === $refreshedUser) {
            throw new UserNotFoundException(sprintf('User with ID "%s" not found.', $user->getId()));
        }

        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Try to find user by email first
        $user = $this->documentManager->getRepository(User::class)->findOneBy(['email' => $identifier]);
        
        if (!$user) {
            // Try to find user by username
            $user = $this->documentManager->getRepository(User::class)->findOneBy(['username' => $identifier]);
        }

        if (!$user) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $user;
    }
}
