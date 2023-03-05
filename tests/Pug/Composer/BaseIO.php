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

use Composer\Config;
use Composer\Util\ProcessExecutor;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

abstract class BaseIO implements IOInterface, LoggerInterface
{
    protected $authentications = [];

    /**
     * {@inheritdoc}
     */
    public function getAuthentications()
    {
        return $this->authentications;
    }

    /**
     * {@inheritdoc}
     */
    public function resetAuthentications()
    {
        $this->authentications = [];
    }

    /**
     * {@inheritdoc}
     */
    public function hasAuthentication($repositoryName)
    {
        return isset($this->authentications[$repositoryName]);
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthentication($repositoryName)
    {
        if (isset($this->authentications[$repositoryName])) {
            return $this->authentications[$repositoryName];
        }

        return ['username' => null, 'password' => null];
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthentication($repositoryName, $username, $password = null)
    {
        $this->authentications[$repositoryName] = ['username' => $username, 'password' => $password];
    }

    /**
     * {@inheritdoc}
     */
    public function writeRaw($messages, $newline = true, $verbosity = self::NORMAL)
    {
        $this->write($messages, $newline, $verbosity);
    }

    /**
     * {@inheritdoc}
     */
    public function writeErrorRaw($messages, $newline = true, $verbosity = self::NORMAL)
    {
        $this->writeError($messages, $newline, $verbosity);
    }

    /**
     * Check for overwrite and set the authentication information for the repository.
     *
     * @param string $repositoryName The unique name of repository
     * @param string $username       The username
     * @param string $password       The password
     */
    protected function checkAndSetAuthentication($repositoryName, $username, $password = null)
    {
        if ($this->hasAuthentication($repositoryName)) {
            $auth = $this->getAuthentication($repositoryName);
            if ($auth['username'] === $username && $auth['password'] === $password) {
                return;
            }

            $this->writeError(
                sprintf(
                    '<warning>Warning: You should avoid overwriting already defined auth settings for %s.</warning>',
                    $repositoryName
                )
            );
        }
        $this->setAuthentication($repositoryName, $username, $password);
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfiguration(Config $config)
    {
        $bitbucketOauth = $config->get('bitbucket-oauth') ?: [];
        $githubOauth = $config->get('github-oauth') ?: [];
        $gitlabOauth = $config->get('gitlab-oauth') ?: [];
        $gitlabToken = $config->get('gitlab-token') ?: [];
        $httpBasic = $config->get('http-basic') ?: [];

        // reload oauth tokens from config if available

        foreach ($bitbucketOauth as $domain => $cred) {
            $this->checkAndSetAuthentication($domain, $cred['consumer-key'], $cred['consumer-secret']);
        }

        foreach ($githubOauth as $domain => $token) {
            if (!preg_match('{^[.a-z0-9]+$}', $token)) {
                throw new \UnexpectedValueException('Your github oauth token for '.$domain.' contains invalid characters: "'.$token.'"');
            }
            $this->checkAndSetAuthentication($domain, $token, 'x-oauth-basic');
        }

        foreach ($gitlabOauth as $domain => $token) {
            $this->checkAndSetAuthentication($domain, $token, 'oauth2');
        }

        foreach ($gitlabToken as $domain => $token) {
            $this->checkAndSetAuthentication($domain, $token, 'private-token');
        }

        // reload http basic credentials from config if available
        foreach ($httpBasic as $domain => $cred) {
            $this->checkAndSetAuthentication($domain, $cred['username'], $cred['password']);
        }

        // setup process timeout
        ProcessExecutor::setTimeout((int) $config->get('process-timeout'));
    }

    /**
     * System is unusable.
     */
    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     */
    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     */
    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     */
    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     */
    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     */
    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        if (in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR])) {
            $this->writeError('<error>'.$message.'</error>', true, self::NORMAL);
        } elseif ($level === LogLevel::WARNING) {
            $this->writeError('<warning>'.$message.'</warning>', true, self::NORMAL);
        } elseif ($level === LogLevel::NOTICE) {
            $this->writeError('<info>'.$message.'</info>', true, self::VERBOSE);
        } elseif ($level === LogLevel::INFO) {
            $this->writeError('<info>'.$message.'</info>', true, self::VERY_VERBOSE);
        } else {
            $this->writeError($message, true, self::DEBUG);
        }
    }
}
