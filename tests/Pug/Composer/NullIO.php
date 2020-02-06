<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\IO;

/**
 * IOInterface that is not interactive and never writes the output.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class NullIO extends BaseIO
{
    /**
     * {@inheritdoc}
     */
    public function isInteractive()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isVerbose()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isVeryVerbose()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDebug()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDecorated()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function write($messages, $newline = true, $verbosity = self::NORMAL)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function writeError($messages, $newline = true, $verbosity = self::NORMAL)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function overwrite($messages, $newline = true, $size = 80, $verbosity = self::NORMAL)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function overwriteError($messages, $newline = true, $size = 80, $verbosity = self::NORMAL)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function ask($question, $default = null)
    {
        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function askConfirmation($question, $default = true)
    {
        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function askAndValidate($question, $validator, $attempts = false, $default = null)
    {
        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function askAndHideAnswer($question)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function select($question, $choices, $default, $attempts = false, $errorMessage = 'Value "%s" is invalid', $multiselect = false)
    {
        return $default;
    }
}
