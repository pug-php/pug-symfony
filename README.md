# Pug-Symfony
[![Latest Stable Version](https://poser.pugx.org/pug-php/pug-symfony/v/stable.png)](https://packagist.org/packages/pug-php/pug-symfony)
[![Build Status](https://travis-ci.org/pug-php/pug-symfony.svg?branch=master)](https://travis-ci.org/pug-php/pug-symfony)
[![StyleCI](https://styleci.io/repos/61784988/shield?style=flat)](https://styleci.io/repos/61784988)
[![Test Coverage](https://codeclimate.com/github/pug-php/pug-symfony/badges/coverage.svg)](https://codecov.io/github/pug-php/pug-symfony?branch=master)

Pug template engine for Symfony

This is the documentation for the ongoing version 3.0. [Click here to load the documentation for 2.8](https://github.com/pug-php/pug-symfony/tree/2.8.0#pug-symfony)

## Install

In the root directory of your Symfony project, open a terminal and enter.
```shell
composer require pug-php/pug-symfony
```
When your are asked to install automatically needed settings, enter yes.

Note: Since the version 2.5.0, running the command with the `--no-interaction`
option will install all settings automatically if possible.

It for any reason, you do not can or want to use it, you will have to add to
your **config/bundles.php** file:

```php
Pug\PugSymfonyBundle\PugSymfonyBundle::class => ['all' => true],
```

## Usage

Create Pug views by creating files with .pug extension
in **app/Resources/views** such as contact.pug:
```pug
h1
  | Contact
  =name
```

Note: standard Twig functions are also available in your pug templates, for instance:
```pug
!=form_start(form, {method: 'GET'})
```

Then call it in your controller:
```php
/**
 * @Route("/contact")
 */
public function contactAction()
{
    return $this->render('contact/contact.pug', [
        'name' => 'Us',
    ]);
}
```

## Configure

You can inject `Pug\PugSymfonyEngine` to change options, share values, add plugins to Pug:

```php
public function contactAction(\Pug\PugSymfonyEngine $pug)
{
    $pug->setOptions(array(
      'pretty' => true,
      'pugjs' => true,
      // ...
    ));
    $pug->share('globalVar', 'foo');
    $pug->getRenderer()->addKeyword('customKeyword', $bar);
    
    return $this->render('contact/contact.pug', [
        'name' => 'Us',
    ]);
}
```

Same can be ran globally on a given event such as `onKernelView` to apply customization before any
view rendering.

See the options in the pug-php documentation: https://phug-lang.com/#options

Initial options can also be passed in parameters in your **config/services.yaml**:
```yaml
parameters:
    pug:
        expressionLanguage: php
```

Services can also be injected in the view using the option `shared_services`:
```yaml
parameters:
    pug:
        shared_services:
            translator: translator
```

Make the translator available in every views:
```pug
p=translator.trans('Hello %name%', {'%name%': 'Jack'})
```

Keys (left) passed to `shared_services` are the variable name to be used in the view, values (right) are
the class name (can be `\App\MyService`) or the alias to resolve the dependency injection.

## Deployment

In production, you better have to pre-render all your templates to improve performances using the
command below:
```shell
php bin/console assets:publish --env=prod
```
