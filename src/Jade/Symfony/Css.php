<?php

namespace Jade\Symfony;

use Symfony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper;

class Css
{
    protected $assetsHelper;

    public function __construct(AssetsHelper $assetsHelper)
    {
        $this->assetsHelper = $assetsHelper;
    }

    public function getUrl($url)
    {
        return sprintf('url(%s)', var_export($this->assetsHelper->getUrl($url), true));
    }
}
