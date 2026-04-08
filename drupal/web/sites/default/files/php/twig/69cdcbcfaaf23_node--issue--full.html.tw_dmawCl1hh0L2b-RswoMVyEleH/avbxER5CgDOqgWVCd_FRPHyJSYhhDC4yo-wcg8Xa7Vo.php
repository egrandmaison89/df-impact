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

/* themes/custom/df_impact/templates/node/node--issue--full.html.twig */
class __TwigTemplate_7f9a20afcedf86991f04b9d553b3b6a0 extends Template
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
        yield "<div";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["attributes"] ?? null), "addClass", ["issue-page"], "method", false, false, true, 7), "html", null, true);
        yield ">

  ";
        // line 10
        yield "  ";
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_banner_image", [], "any", false, false, true, 10)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 11
            yield "    <div class=\"issue-page__banner\">
      ";
            // line 12
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_banner_image", [], "any", false, false, true, 12), "html", null, true);
            yield "
      <div class=\"issue-page__banner-overlay\">
        <h1 class=\"issue-page__banner-title\">";
            // line 14
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["label"] ?? null), "html", null, true);
            yield "</h1>
      </div>
    </div>
  ";
        } else {
            // line 18
            yield "    <div class=\"container\">
      <h1>";
            // line 19
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["label"] ?? null), "html", null, true);
            yield "</h1>
    </div>
  ";
        }
        // line 22
        yield "
  ";
        // line 24
        yield "  ";
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_description", [], "any", false, false, true, 24)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 25
            yield "    <div class=\"issue-page__description\">
      ";
            // line 26
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_description", [], "any", false, false, true, 26), "html", null, true);
            yield "
    </div>
  ";
        }
        // line 29
        yield "
  ";
        // line 31
        yield "  ";
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_leadership_message", [], "any", false, false, true, 31)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 32
            yield "    <div class=\"container\" style=\"text-align: center; margin-bottom: var(--space-lg);\">
      <p>
        <strong>";
            // line 34
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("A Message from Leadership"));
            yield ":</strong>
        ";
            // line 35
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_leadership_message", [], "any", false, false, true, 35), "html", null, true);
            yield "
      </p>
    </div>
  ";
        }
        // line 39
        yield "
  ";
        // line 41
        yield "  <hr class=\"divider-gradient\" aria-hidden=\"true\">

  ";
        // line 44
        yield "  <div class=\"issue-page__articles\">
    <h2 class=\"section-heading\">";
        // line 45
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Articles in This Issue"));
        yield "</h2>
    ";
        // line 52
        yield "    ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, views_embed_view("issue_articles", "block_1", CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "id", [], "any", false, false, true, 52)), "html", null, true);
        yield "
  </div>

</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["attributes", "content", "label", "node"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "themes/custom/df_impact/templates/node/node--issue--full.html.twig";
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
        return array (  127 => 52,  123 => 45,  120 => 44,  116 => 41,  113 => 39,  106 => 35,  102 => 34,  98 => 32,  95 => 31,  92 => 29,  86 => 26,  83 => 25,  80 => 24,  77 => 22,  71 => 19,  68 => 18,  61 => 14,  56 => 12,  53 => 11,  50 => 10,  44 => 7,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "themes/custom/df_impact/templates/node/node--issue--full.html.twig", "/var/www/html/web/themes/custom/df_impact/templates/node/node--issue--full.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 10];
        static $filters = ["escape" => 7, "trim" => 10, "render" => 10, "t" => 34];
        static $functions = ["drupal_view" => 52];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['escape', 'trim', 'render', 't'],
                ['drupal_view'],
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
