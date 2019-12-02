<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

class FakeAssetsHelper extends AssetsHelper
{
    public function getUrl($url, $packageName = null, $version = null)
    {
        return "fake:$url";
    }
}
