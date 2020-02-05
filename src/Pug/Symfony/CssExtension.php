<?php

namespace Pug\Symfony;

use Symfony\Bridge\Twig\Extension\AssetExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CssExtension extends AbstractExtension
{
    /**
     * @var AssetExtension
     */
    protected $assets;

    public function __construct(AssetExtension $assets = null)
    {
        $this->assets = $assets;
    }

    public function getUrl($url)
    {
        $url = $this->assets->getAssetUrl("$url");

        return sprintf('url(%s)', var_export("$url", true));
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
