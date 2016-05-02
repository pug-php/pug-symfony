<?php

namespace Jade;

use Symfony\Component\Templating\EngineInterface;
use Jade\Jade;

class JadeSymfonyEngine implements EngineInterface, \ArrayAccess
{
    protected $container;
    protected $jade;
    protected $helpers;

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
        foreach (func_get_args() as $helper) {
            $name = preg_replace('`^(?:.+\\\\)([^\\\\]+?)(?:Helper)?$`', '$1', get_class($helper));
            $name = strtolower(substr($name, 0, 1)) . substr($name, 1);
            $this->helpers[$name] = $helper;
        }
    }

    protected function getFileFromName($name)
    {
        global $kernel;
        $parts = explode(':', $name);
        $directory = $kernel->getRootDir();
        if (count($parts) > 1) {
            $name = $parts[2];
            if (!empty($parts[1])) {
                $name = $parts[1] . DIRECTORY_SEPARATOR . $name;
            }
            if ($bundle = $kernel->getBundle($parts[0])) {
                $directory = $bundle->getPath();
            }
        }

        return $directory .
            DIRECTORY_SEPARATOR . 'Resources' .
            DIRECTORY_SEPARATOR . 'views' .
            DIRECTORY_SEPARATOR . $name;
    }

    public function render($name, array $parameters = array())
    {
        foreach (array('view', 'this') as $forbiddenKey) {
            if (array_key_exists($forbiddenKey, $parameters)) {
                throw new \ArgumentException('The "' . $forbiddenKey . '" key is forbidden.');
            }
        }
        $parameters['view'] = $this;

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

    public function offsetGet($name)
    {
        return $this->helpers[$name];
    }

    public function offsetExists($name)
    {
        return isset($this->helpers[$name]);
    }

    public function offsetSet($name, $value)
    {
        $this->helpers[$name] = $value;
    }

    public function offsetUnset($name)
    {
        unset($this->helpers[$name]);
    }
}
