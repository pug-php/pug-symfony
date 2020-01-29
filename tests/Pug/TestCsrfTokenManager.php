<?php

namespace Pug\Tests;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

class TestCsrfTokenManager extends CsrfTokenManager
{
    public function getToken(string $tokenId)
    {
        if ($tokenId === 'special') {
            return new CsrfToken('special', 'the token');
        }

        return parent::getToken($tokenId);
    }
}
