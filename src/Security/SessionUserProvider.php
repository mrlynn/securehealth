<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SessionUserProvider implements UserProviderInterface
{
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SessionUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return SessionUser::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // This method is not used in our session-based authentication
        // The SessionAuthenticator creates the user directly from session data
        throw new UserNotFoundException('SessionUserProvider does not support loading users by identifier');
    }
}
