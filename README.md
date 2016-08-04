# Pug-Symfony
Pug template engine for Symfony

## Install
In the root directory of your Symfony project, open a terminal and enter:
```shell
composer require pug-php/pug-symfony
```

Add in **app/config/services.yml**:
```yml
services:
    templating.engine.pug:
        class: Pug\PugSymfonyEngine
        arguments: ["@kernel"]
```

Add jade in the templating.engines setting in **app/config/config.yml**:
```yml
...
    templating:
        engines: ['pug', 'twig', 'php']
```

## Usage
Create jade views by creating files with .pug extension
in **app/Resources/views** such as contact.html.pug with
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
    return $this->render('contact/contact.html.pug', [
        'name' => 'Bob',
    ]);
}
```

## Deployment

In production, you better have to pre-render all your templates to improve performances. To do that, you have to add Pug\PugSymfonyBundle\PugSymfonyBundle in your registered bundles.

In **app/AppKernel.php**, in the ```registerBundles()``` method, add the Pug bundle:
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
