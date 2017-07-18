<?php

namespace Pug\Tests;

use Composer\IO\NullIO;

class CaptureIO extends NullIO
{
    protected $lastMsgs = [];
    protected $errored;
    protected $interactive = false;
    protected $permissive = false;

    public function reset()
    {
        $this->lastMsgs = [];
    }

    public function setInteractive($interactive)
    {
        $this->interactive = $interactive;
    }

    public function setPermissive($permissive)
    {
        $this->permissive = $permissive;
    }

    public function askConfirmation($question, $default = true)
    {
        return $this->permissive;
    }

    public function isInteractive()
    {
        return $this->interactive;
    }

    public function write($msg, $newline = true, $verbosity = self::NORMAL)
    {
        $this->lastMsgs[] = $msg;
        $this->errored = false;
    }

    public function writeError($msg, $newline = true, $verbosity = self::NORMAL)
    {
        $this->lastMsgs[] = $msg;
        $this->errored = true;
    }

    public function isErrored()
    {
        return $this->errored;
    }

    public function getLastOutput()
    {
        return $this->lastMsgs;
    }
}
