# Jade-Symfony
Jade template engine for Symfony

## Install
In the root directory of your Symfony project, open a
terminal and enter:
```shell
composer require kylekatarnls/jade-symfony
```

Add in **app/config/services.yml**:
```yml
services:
    templating.engine.jade:
        class: Jade\JadeSymfonyEngine
```

## Usage
Create jade views by creating files with .jade extension
in **app/Resources/views** such as contact.html.jade with
some Jade like this:
```jade
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
    return $this->render('contact/contact.html.jade', [
        'name' => 'Bob',
    ]);
}
```
