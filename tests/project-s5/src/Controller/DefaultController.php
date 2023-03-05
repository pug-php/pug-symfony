<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

class DefaultController extends AbstractController
{
    protected $twig;

    public function index()
    {
        $defaultFormTheme = 'form_div_layout.html.twig';
        $csrfManager = new CsrfTokenManager();
        $formEngine = new TwigRendererEngine([$defaultFormTheme], $this->twig);

        $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => function () use ($formEngine, $csrfManager) {
                return new FormRenderer($formEngine, $csrfManager);
            },
        ]));

        return $this->render('p.pug');
    }
}
