<?php

namespace Jade\Symfony;

use Symfony\Bundle\SecurityBundle\Templating\Helper\LogoutUrlHelper;

class Logout
{
    protected $logoutUrlHelper;

    public function __construct(LogoutUrlHelper $logoutUrlHelper)
    {
        $this->logoutUrlHelper = $logoutUrlHelper;
    }

    public function url($key = null)
    {
        return $this->logoutUrlHelper->getLogoutUrl($key);
    }

    public function path($key = null)
    {
        return $this->logoutUrlHelper->getLogoutPath($key);
    }
}
