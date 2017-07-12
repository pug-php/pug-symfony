# Pug-Symfony
[![Latest Stable Version](https://poser.pugx.org/pug-php/pug-symfony/v/stable.png)](https://packagist.org/packages/pug-php/pug-symfony)
[![Build Status](https://travis-ci.org/pug-php/pug-symfony.svg?branch=master)](https://travis-ci.org/pug-php/pug-symfony)
[![StyleCI](https://styleci.io/repos/61784988/shield?style=flat)](https://styleci.io/repos/61784988)
[![Test Coverage](https://codeclimate.com/github/pug-php/pug-symfony/badges/coverage.svg)](https://codecov.io/github/pug-php/pug-symfony?branch=master)
[![Code Climate](https://codeclimate.com/github/pug-php/pug-symfony/badges/gpa.svg)](https://codeclimate.com/github/pug-php/pug-symfony)

Pug template engine for Symfony

## Install
In the root directory of your Symfony project, open a terminal and enter:
```shell
composer require pug-php/pug-symfony
```
When your are asked to install automatically needed settings, enter yes ;
or if you prefer, follow the manual installation steps below.

## Manual install

Add pug in the templating.engines setting in **app/config/config.yml**
by merging the following to your settings:
```yml
services:
    templating.engine.pug:
        class: Pug\PugSymfonyEngine
        arguments: ["@kernel"]

framework:
    templating:
        engines: ['pug', 'twig', 'php']
```

In order to use pug cli commands, you will also need to add
`Pug\PugSymfonyBundle\PugSymfonyBundle()`
to your **AppKenel.php**.

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

In production, you better have to pre-render all your templates to improve performances. To do that, you have to add Pug\PugSymfonyBundle\PugSymfonyBundle in your registered bundles.

In **app/AppKernel.php**, in the ```registerBundles()``` method, add the Pug bundle (this
has been done automatically if you installed pug-symfony 2.3 or above with automated script):
```php
public function registerBundles()
{
    $bundles = [
        ...
        new Pug\PugSymfonyBundle\PugSymfonyBundle(),
    ];
```

This will make the ```assets:publish``` command available, now each time you deploy your app, enter the command below:
```php bin/console assets:publish --env=prod```
