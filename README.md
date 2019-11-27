# Pug-Symfony
[![Latest Stable Version](https://poser.pugx.org/pug-php/pug-symfony/v/stable.png)](https://packagist.org/packages/pug-php/pug-symfony)
[![Build Status](https://travis-ci.org/pug-php/pug-symfony.svg?branch=master)](https://travis-ci.org/pug-php/pug-symfony)
[![StyleCI](https://styleci.io/repos/61784988/shield?style=flat)](https://styleci.io/repos/61784988)
[![Test Coverage](https://codeclimate.com/github/pug-php/pug-symfony/badges/coverage.svg)](https://codecov.io/github/pug-php/pug-symfony?branch=master)

Pug template engine for Symfony

## Install

In the root directory of your Symfony project, open a terminal and enter.
```shell
composer require pug-php/pug-symfony
```
When your are asked to install automatically needed settings, enter yes.

Note: Since the version 2.5.0, running the command with the `--no-interaction`
option will install all settings automatically if possible.

It for any reason, you do not can or want to use it, here is how to do
a manual installation:

- [Symfony 4+ manual installation](https://github.com/pug-php/pug-symfony/wiki/Symfony-4-manual-installation)
- [Symfony 2 and 3 manual installation](https://github.com/pug-php/pug-symfony/wiki/Symfony-2-and-3-manual-installation)

If you installed Symfony in a custom way, you might be warned about
missing "templating.engine.twig" service. We highly recommend you to
install it (`composer require twig/twig`) to get Twig functions such
as `css_url`, `form_start` and so on available from Pug templates.

If you're sure you don't need Twig utils, you can simply remove
"templating.engine.twig" from your "templating" services settings.

## Configure

You can set pug options by accessing the container (from controller or from the kernel) in Symfony.
```php
$services = $kernel->getContainer();
$pug = $services->get('templating.engine.pug');
$pug->setOptions(array(
  'pretty' => true,
  'pugjs' => true,
  // ...
));
// You can get the Pug engine to call any method available in pug-php
$pug->getEngine()->share('globalVar', 'foo');
$pug->getEngine()->addKeyword('customKeyword', $bar);
```
See the options in the pug-php README: https://github.com/pug-php/pug
And methods directly available on the service: https://github.com/pug-php/pug-symfony/blob/master/src/Jade/JadeSymfonyEngine.php

Initial options can also be passed in parameters in your **config/services.yaml** in Symfony 4,
**config.yml** in older versions:
```yaml
parameters:
    pug:
        expressionLanguage: php
```

## Usage

Create jade views by creating files with .pug extension
in **app/Resources/views** such as contact.pug with
some Jade like this:
```pug
h1
  | Hello
  =name
```
Then call it in your controller:
```php
/**
 * @Route("/contact")
 */
public function contactAction()
{
    return $this->render('contact/contact.pug', [
        'name' => 'Bob',
    ]);
}
```

## Deployment

In production, you better have to pre-render all your templates to improve performances. To do that, you have
`Pug\PugSymfonyBundle\PugSymfonyBundle` in your registered bundles. It should be already done if you
followed the automated install with success. Else check installation instructions ([add bundle in Symfony 4](https://github.com/pug-php/pug-symfony/wiki/Symfony-4-manual-installation#configbundlesphp),
[add bundle in Symfony 2 and 3](https://github.com/pug-php/pug-symfony/wiki/Symfony-2-and-3-manual-installation#appappkenelphp))

This will make the `assets:publish` command available, now each time you deploy your app, enter the command below:
```shell
php bin/console assets:publish --env=prod
```
