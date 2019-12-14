<?php

use Twig\Environment;
use Twig\Source;
use Twig\Template;

/* {{filename}} */
class PugDebugTemplateTemplate extends Template
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

        if (isset($this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"])) {
            $__internal_1 = $this->extensions["Symfony\\Bundle\\WebProfilerBundle\\Twig\\WebProfilerExtension"];
            $__internal_1->enter($__internal_1_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "{{filename}}"));
        }

        if (isset($this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"])) {
            $__internal_2 = $this->extensions["Symfony\\Bridge\\Twig\\Extension\\ProfilerExtension"];
            $__internal_2->enter($__internal_2_prof = new \Twig\Profiler\Profile($this->getTemplateName(), "template", "{{filename}}"));
        }

        // {{code}}

        if (isset($__internal_1)) {
            $__internal_1->leave($__internal_1_prof);
        }

        if (isset($__internal_2)) {
            $__internal_2->leave($__internal_2_prof);
        }
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
        return new Source("{{source}}", "{{filename}}", "{{path}}");
    }
}
