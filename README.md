# Pug-Symfony
Pug template engine for Symfony

## Install
In the root directory of your Symfony project, open a
terminal and enter:
```shell
composer require pug-php/pug-symfony
```

Add in **app/config/services.yml**:
```yml
services:
    templating.engine.pug:
        class: Pug\PugSymfonyEngine
        arguments: ["@kernel", "@templating.helper.assets", "@templating.helper.router"]
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
