<?php

namespace Pug\Symfony;

use Symfony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper;

class Css
{
    protected $assetsHelper;

    public function __construct(AssetsHelper $assetsHelper = null)
    {
        $this->assetsHelper = $assetsHelper;
    }

    public function getUrl($url)
    {
        if ($this->assetsHelper) {
            $url = $this->assetsHelper->getUrl($url);
        }

        return sprintf('url(%s)', var_export($url, true));
    }
}
