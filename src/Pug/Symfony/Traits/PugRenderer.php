<?php

declare(strict_types=1);

namespace Pug\Symfony\Traits;

use Pug\PugSymfonyEngine;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\TemplateReferenceInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait PugRenderer
{
    protected PugSymfonyEngine $pug;

    #[Required]
    public function setPug(PugSymfonyEngine $pug): void
    {
        $this->pug = $pug;
    }

    public function render(
        string|TemplateReferenceInterface $view,
        array $parameters = [],
        ?Response $response = null,
    ): Response {
        return $this->pug->renderResponse($view, $parameters, $response);
    }
}
