# Pug-Symfony
[![Latest Stable Version](https://poser.pugx.org/pug-php/pug-symfony/v/stable.png)](https://packagist.org/packages/pug-php/pug-symfony)
[![GitHub Actions](https://github.com/pug-php/pug-symfony/workflows/Tests/badge.svg)](https://github.com/pug-php/pug-symfony/actions)
[![StyleCI](https://styleci.io/repos/61784988/shield?style=flat)](https://styleci.io/repos/61784988)
[![Test Coverage](https://codecov.io/gh/pug-php/pug-symfony/branch/master/graph/badge.svg?token=yzjEnZzRNm)](https://codecov.io/github/pug-php/pug-symfony?branch=master)

[Pug template](https://phug-lang.com/) engine for Symfony

This is the documentation for the ongoing version 3.0. [Click here to load the documentation for 2.8](https://github.com/pug-php/pug-symfony/tree/2.8.0#pug-symfony)

## Install

In the root directory of your Symfony project, open a terminal and enter.
```shell
composer require pug-php/pug-symfony
```
When you are asked to install automatically needed settings, enter yes.

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
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MyController extends AbstractController
{
    #[Route('/contact')]
    public function contactAction()
    {
        return $this->render('contact/contact.pug', [
            'name' => 'Us',
        ]);
    }
}
```

## Configure

You can inject `Pug\PugSymfonyEngine` to change options, share values, add plugins to Pug
at route level:

```php
// In a controller method
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

Same can be run globally on a given event such as `onKernelView` to apply customization before any
view rendering.

See the options in the pug-php documentation: https://phug-lang.com/#options

Initial options can also be passed in parameters in your **config/services.yaml**:
```yaml
# config/services.yaml
parameters:
    # ...
    pug:
        expressionLanguage: php
```

Note: you can also create a **config/packages/pug.yaml** to store the Pug settings.

Globals of Twig are available in Pug views (such as the `app` variable to get `app.token` or `app.environment`)
and any custom global value or service you will add to **twig.yaml**:
```yaml
# config/packages/twig.yaml
twig:
    # ...
    globals:
        translator: '@translator'

```

Make the translator available in every view:
```pug
p=translator.trans('Hello %name%', {'%name%': 'Jack'})
```

Keys (left) passed to `globals` are the variable name to be used in the view, values (right) are
the class name (can be `'@\App\MyService'`) or the alias to resolve the dependency injection. It
can also be static values such as `ga_tracking: 'UA-xxxxx-x'`.

If you need more advanced customizations to be applied for every Pug rendering,
you can use interceptor services.
```yaml
# config/services.yaml
parameters:
    # ...
    pug:
        interceptors:
            - App\Service\PugInterceptor
            # You can add more interceptors

services:
    # ...

    # They all need to be public
    App\Service\PugInterceptor:
        public: true
```

Then the interceptor would look like this:
```php
// src/Service/PugInterceptor.php
namespace App\Service;

use Pug\Symfony\Contracts\InterceptorInterface;
use Pug\Symfony\RenderEvent;
use Symfony\Contracts\EventDispatcher\Event;

class PugInterceptor implements InterceptorInterface
{
    public function intercept(Event $event)
    {
        if ($event instanceof RenderEvent) {
            // Here you can any method on the engine or the renderer:
            $event->getEngine()->getRenderer()->addKeyword('customKeyword', $bar);
            $event->getEngine()->getRenderer()->addExtension(MyPlugin::class);

            // Or/and manipulate the local variables passed to the view:
            $locals = $event->getLocals();
            $locals['foo']++;
            $event->setLocals($locals);

            // Or/and get set the name of the view that is about to be rendered:
            if ($event->getName() === 'profile.pug') {
                // if user variable is missing
                if (!isset($event->getLocals()['user'])) {
                    $event->setName('search-user.pug');
                    // Render the search-user.pug instead of profile.pug
                }
            }
        }
    }
}
```

As services, interceptors can inject any dependency in their constructor to
use it in the `intercept` method:
```php
class PugInterceptor implements InterceptorInterface
{
    private $service;

    public function __construct(MyOtherService $service)
    {
        $this->service = $service;
    }

    public function intercept(Event $event)
    {
        if ($event instanceof RenderEvent) {
            $event->getEngine()->share('anwser', $this->service->getAnwser());
        }
    }
}
```

And interceptors are lazy-loaded, it means in the example above, neither `PugInterceptor`
nor `MyOtherService` will be loaded if they are not used elsewhere and if the current request
does not end with a pug rendering (pure-Twig view, API response, websocket, etc.) so it's a
good way to optimize things you only need to do before pug rendering.

## Deployment

In production, you better have to pre-render all your templates to improve performances using the
command below:
```shell
php bin/console assets:publish --env=prod
```

## Security contact information

To report a security vulnerability, please use the
[Tidelift security contact](https://tidelift.com/security).
Tidelift will coordinate the fix and disclosure.
