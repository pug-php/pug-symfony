<?php

namespace Pug\Symfony;

use Symfony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CssExtension extends AbstractExtension
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

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('css_url', [$this, 'getUrl']),
        ];
    }
}
