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

/* themes/custom/df_impact/templates/layout/page.html.twig */
class __TwigTemplate_d6c3ae6c2e4706893777988d10e803fd extends Template
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
        yield "<div class=\"page\">

  ";
        // line 10
        yield "  <header class=\"header\" role=\"banner\">
    <div class=\"header__inner\">
      <div class=\"header__logo\">
        <a href=\"";
        // line 13
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\Core\Template\TwigExtension']->getPath("<front>"));
        yield "\" rel=\"home\" aria-label=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dana-Farber Impact Magazine Home"));
        yield "\">
          <img src=\"/";
        // line 14
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["directory"] ?? null), "html", null, true);
        yield "/images/logo.svg\" alt=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dana-Farber Impact Magazine"));
        yield "\" width=\"300\" height=\"50\">
        </a>
      </div>

      <button class=\"header__menu-toggle\" aria-label=\"";
        // line 18
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Toggle navigation"));
        yield "\" aria-expanded=\"false\" aria-controls=\"main-nav\">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <nav class=\"header__nav\" id=\"main-nav\" aria-label=\"";
        // line 24
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Main navigation"));
        yield "\">
        ";
        // line 25
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "header", [], "any", false, false, true, 25), "html", null, true);
        yield "
        <a href=\"";
        // line 26
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\Core\Template\TwigExtension']->getPath("view.df_impact_search.page_1"));
        yield "\" class=\"header__search-link\" aria-label=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Search"));
        yield "\">
          <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\">
            <circle cx=\"11\" cy=\"11\" r=\"8\"></circle>
            <line x1=\"21\" y1=\"21\" x2=\"16.65\" y2=\"16.65\"></line>
          </svg>
          <span class=\"visually-hidden\">";
        // line 31
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Search"));
        yield "</span>
        </a>
        <a href=\"https://danafarber.jimmyfund.org/give/dana-farber-donate\" class=\"header__donate\" target=\"_blank\" rel=\"noopener\">
          ";
        // line 34
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Donate"));
        yield "
        </a>
      </nav>
    </div>
  </header>

  ";
        // line 41
        yield "  <hr class=\"divider-gradient\" aria-hidden=\"true\">

  ";
        // line 44
        yield "  ";
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "hero", [], "any", false, false, true, 44)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 45
            yield "    <section class=\"hero\">
      ";
            // line 46
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "hero", [], "any", false, false, true, 46), "html", null, true);
            yield "
    </section>
  ";
        }
        // line 49
        yield "
  ";
        // line 51
        yield "  ";
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "highlighted", [], "any", false, false, true, 51)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 52
            yield "    <div class=\"highlighted\">
      <div class=\"container\">
        ";
            // line 54
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "highlighted", [], "any", false, false, true, 54), "html", null, true);
            yield "
      </div>
    </div>
  ";
        }
        // line 58
        yield "
  ";
        // line 60
        yield "  <main id=\"main-content\" class=\"page__content\" role=\"main\">
    <a id=\"main-content-anchor\" tabindex=\"-1\"></a>

    ";
        // line 63
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "sidebar", [], "any", false, false, true, 63)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 64
            yield "      <div class=\"container\">
        <div class=\"layout-with-sidebar\">
          <div class=\"layout-with-sidebar__content\">
            ";
            // line 67
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "content", [], "any", false, false, true, 67), "html", null, true);
            yield "
          </div>
          <aside class=\"layout-with-sidebar__sidebar\" role=\"complementary\">
            ";
            // line 70
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "sidebar", [], "any", false, false, true, 70), "html", null, true);
            yield "
          </aside>
        </div>
      </div>
    ";
        } else {
            // line 75
            yield "      ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "content", [], "any", false, false, true, 75), "html", null, true);
            yield "
    ";
        }
        // line 77
        yield "  </main>

  ";
        // line 80
        yield "  <footer class=\"footer\" role=\"contentinfo\">
    <div class=\"footer__inner\">
      <div class=\"footer__top\">
        <div class=\"footer__logos\">
          <a href=\"https://www.dana-farber.org\" target=\"_blank\" rel=\"noopener\" aria-label=\"";
        // line 84
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dana-Farber Cancer Institute"));
        yield "\">
            <img src=\"/";
        // line 85
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["directory"] ?? null), "html", null, true);
        yield "/images/dfci-logo-white.svg\" alt=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dana-Farber Cancer Institute"));
        yield "\" class=\"footer__logo-img\" width=\"250\" height=\"40\">
          </a>
          <a href=\"https://www.jimmyfund.org\" target=\"_blank\" rel=\"noopener\" aria-label=\"";
        // line 87
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("The Jimmy Fund"));
        yield "\">
            <img src=\"/";
        // line 88
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["directory"] ?? null), "html", null, true);
        yield "/images/jimmy-fund-logo-white.svg\" alt=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("The Jimmy Fund"));
        yield "\" class=\"footer__logo-img\" width=\"200\" height=\"40\">
          </a>
        </div>

        ";
        // line 92
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_first", [], "any", false, false, true, 92)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 93
            yield "          <div class=\"footer__column\">
            ";
            // line 94
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_first", [], "any", false, false, true, 94), "html", null, true);
            yield "
          </div>
        ";
        }
        // line 97
        yield "
        ";
        // line 98
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_second", [], "any", false, false, true, 98)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 99
            yield "          <div class=\"footer__column\">
            ";
            // line 100
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_second", [], "any", false, false, true, 100), "html", null, true);
            yield "
          </div>
        ";
        }
        // line 103
        yield "
        ";
        // line 104
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_third", [], "any", false, false, true, 104)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 105
            yield "          <div class=\"footer__column\">
            ";
            // line 106
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_third", [], "any", false, false, true, 106), "html", null, true);
            yield "
          </div>
        ";
        }
        // line 109
        yield "      </div>

      <div class=\"footer__bottom\">
        <p>&copy; ";
        // line 112
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Twig\Extension\CoreExtension']->formatDate("now", "Y"), "html", null, true);
        yield " ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dana-Farber Cancer Institute. All rights reserved."));
        yield "</p>
        <p>";
        // line 113
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Impact Magazine | Division of Philanthropy"));
        yield "</p>
        <p>";
        // line 114
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("10 Brookline Place West, Brookline, MA 02445-7295"));
        yield "</p>
      </div>
    </div>
  </footer>

</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["directory", "page"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "themes/custom/df_impact/templates/layout/page.html.twig";
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
        return array (  266 => 114,  262 => 113,  256 => 112,  251 => 109,  245 => 106,  242 => 105,  240 => 104,  237 => 103,  231 => 100,  228 => 99,  226 => 98,  223 => 97,  217 => 94,  214 => 93,  212 => 92,  203 => 88,  199 => 87,  192 => 85,  188 => 84,  182 => 80,  178 => 77,  172 => 75,  164 => 70,  158 => 67,  153 => 64,  151 => 63,  146 => 60,  143 => 58,  136 => 54,  132 => 52,  129 => 51,  126 => 49,  120 => 46,  117 => 45,  114 => 44,  110 => 41,  101 => 34,  95 => 31,  85 => 26,  81 => 25,  77 => 24,  68 => 18,  59 => 14,  53 => 13,  48 => 10,  44 => 7,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "themes/custom/df_impact/templates/layout/page.html.twig", "/var/www/html/web/themes/custom/df_impact/templates/layout/page.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 44];
        static $filters = ["t" => 13, "escape" => 14, "date" => 112];
        static $functions = ["path" => 13];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['t', 'escape', 'date'],
                ['path'],
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
