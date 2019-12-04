<?php

use Twig\Environment;
use Twig\Source;
use Twig\Template;

/* {{filename}} */
class PugTemplateTemplate extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);
        $this->source = $this->getSourceContext();
        $this->parent = false;
        $this->blocks = [];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        extract($context);
        // {{code}}
    }

    public function getTemplateName()
    {
        return "{{filename}}";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return [/* {{debugInfo}} */];
    }

    public function getSourceContext()
    {
        return new Source("", "{{filename}}", "{{path}}");
    }
}
