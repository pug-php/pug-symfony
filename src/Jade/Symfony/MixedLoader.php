<?php

namespace Jade\Symfony;

// @codeCoverageIgnoreStart

if (PugEngine::getTwigVersion() > 2) {
    class_alias('Twig\\Loader\\LoaderInterface', 'Twig_LoaderInterface');
    class_alias('Twig\\Environment', 'Twig_Environment');
    class_alias('Twig\\Error\\LoaderError', 'Twig_Error_Loader');

    require __DIR__ . '/../../../polyfill/Jade/Symfony/MixedLoaderTwig3.php';

    class_alias('Jade\\Symfony\\MixedLoaderTwig3', 'Jade\\Symfony\\MixedLoader');

    return;
}

// @codeCoverageIgnoreEnd

require __DIR__ . '/../../../polyfill/Jade/Symfony/MixedLoaderTwig2.php';

class_alias('Jade\\Symfony\\MixedLoaderTwig2', 'Jade\\Symfony\\MixedLoader');
