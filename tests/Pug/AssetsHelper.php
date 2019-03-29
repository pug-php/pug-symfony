<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

class AssetsHelper
{
    public function getUrl($url)
    {
        return "fake:$url";
    }
}
