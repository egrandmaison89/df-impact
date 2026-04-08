<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* themes/custom/df_impact/templates/views/views-view-fields--homepage-digital-exclusives.html.twig */
class __TwigTemplate_4f12612ef685a58816dfaa8ff7c145f0 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 7
        yield "<div class=\"card\">
  ";
        // line 8
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "field_featured_image", [], "any", true, true, true, 8) && Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "field_featured_image", [], "any", false, false, true, 8), "content", [], "any", false, false, true, 8))))) {
            // line 9
            yield "    <div class=\"card__image\">
      ";
            // line 10
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "field_featured_image", [], "any", false, false, true, 10), "content", [], "any", false, false, true, 10), "html", null, true);
            yield "
    </div>
  ";
        }
        // line 13
        yield "  <div class=\"card__body\">
    ";
        // line 14
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "field_topics", [], "any", true, true, true, 14) && Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "field_topics", [], "any", false, false, true, 14), "content", [], "any", false, false, true, 14))))) {
            // line 15
            yield "      <div class=\"card__category\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "field_topics", [], "any", false, false, true, 15), "content", [], "any", false, false, true, 15), "html", null, true);
            yield "</div>
    ";
        }
        // line 17
        yield "    ";
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "title", [], "any", true, true, true, 17)) {
            // line 18
            yield "      <h3 class=\"card__title\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "title", [], "any", false, false, true, 18), "content", [], "any", false, false, true, 18), "html", null, true);
            yield "</h3>
    ";
        }
        // line 20
        yield "    ";
        if ((CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "field_excerpt", [], "any", true, true, true, 20) && Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "field_excerpt", [], "any", false, false, true, 20), "content", [], "any", false, false, true, 20))))) {
            // line 21
            yield "      <div class=\"card__excerpt\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["fields"] ?? null), "field_excerpt", [], "any", false, false, true, 21), "content", [], "any", false, false, true, 21), "html", null, true);
            yield "</div>
    ";
        }
        // line 23
        yield "  </div>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["fields"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "themes/custom/df_impact/templates/views/views-view-fields--homepage-digital-exclusives.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  87 => 23,  81 => 21,  78 => 20,  72 => 18,  69 => 17,  63 => 15,  61 => 14,  58 => 13,  52 => 10,  49 => 9,  47 => 8,  44 => 7,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "themes/custom/df_impact/templates/views/views-view-fields--homepage-digital-exclusives.html.twig", "/var/www/html/web/themes/custom/df_impact/templates/views/views-view-fields--homepage-digital-exclusives.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 8];
        static $filters = ["trim" => 8, "render" => 8, "escape" => 10];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['trim', 'render', 'escape'],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
