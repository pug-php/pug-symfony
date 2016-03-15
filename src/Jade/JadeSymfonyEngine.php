<?php

namespace Jade;

use Symfony\Component\Templating\EngineInterface;
use Jade\Jade;

class JadeSymfonyEngine implements EngineInterface
{
    protected $container;
    protected $jade;

    public function __construct()
    {
        global $kernel;
        $cache = $kernel->getCacheDir() . DIRECTORY_SEPARATOR . 'jade';
        if (!file_exists($cache)) {
            mkdir($cache);
        }
        $this->jade = new Jade(array(
            'prettyprint' => true,
            'extension' => '.jade',
            'cache' => $cache
        ));
    }

    protected function getFileFromName($name)
    {
        global $kernel;
        return $kernel->getRootDir() .
            DIRECTORY_SEPARATOR . 'Resources' .
            DIRECTORY_SEPARATOR . 'views' .
            DIRECTORY_SEPARATOR . $name;
    }

    public function render($name, array $parameters = array())
    {
        return $this->jade->render($this->getFileFromName($name), $parameters);
    }

    public function exists($name)
    {
        return file_exists($this->getFileFromName($name));
    }

    public function supports($name)
    {
        return substr($name, -5) === '.jade';
    }
}
