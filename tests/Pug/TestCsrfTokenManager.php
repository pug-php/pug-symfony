<?php

namespace Pug\Tests;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

class TestCsrfTokenManager extends CsrfTokenManager
{
    public function __construct(TokenGeneratorInterface $generator = null, TokenStorageInterface $storage = null, $namespace = null)
    {
    }

    public function getToken(string $tokenId): CsrfToken
    {
        if ($tokenId === 'special') {
            return new CsrfToken('special', 'the token');
        }

        return new CsrfToken($tokenId, sha1($tokenId));
    }
}
