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

/* themes/custom/df_impact/templates/layout/page--front.html.twig */
class __TwigTemplate_346e98192451da083465683ac39cc508 extends Template
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
        // line 10
        yield "<div class=\"page\">

  ";
        // line 13
        yield "  <header class=\"header\" role=\"banner\">
    <div class=\"header__inner\">
      <div class=\"header__logo\">
        <a href=\"";
        // line 16
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\Core\Template\TwigExtension']->getPath("<front>"));
        yield "\" rel=\"home\" aria-label=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dana-Farber Impact Magazine Home"));
        yield "\">
          <img src=\"/";
        // line 17
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["directory"] ?? null), "html", null, true);
        yield "/images/logo.svg\" alt=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dana-Farber Impact Magazine"));
        yield "\" width=\"300\" height=\"50\">
        </a>
      </div>

      <button class=\"header__menu-toggle\" aria-label=\"";
        // line 21
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Toggle navigation"));
        yield "\" aria-expanded=\"false\" aria-controls=\"main-nav\">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <nav class=\"header__nav\" id=\"main-nav\" aria-label=\"";
        // line 27
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Main navigation"));
        yield "\">
        ";
        // line 28
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "header", [], "any", false, false, true, 28), "html", null, true);
        yield "
        <a href=\"";
        // line 29
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\Core\Template\TwigExtension']->getPath("view.df_impact_search.page_1"));
        yield "\" class=\"header__search-link\" aria-label=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Search"));
        yield "\">
          <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\">
            <circle cx=\"11\" cy=\"11\" r=\"8\"></circle>
            <line x1=\"21\" y1=\"21\" x2=\"16.65\" y2=\"16.65\"></line>
          </svg>
          <span class=\"visually-hidden\">";
        // line 34
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Search"));
        yield "</span>
        </a>
        <a href=\"https://danafarber.jimmyfund.org/give/dana-farber-donate\" class=\"header__donate\" target=\"_blank\" rel=\"noopener\">
          ";
        // line 37
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Donate"));
        yield "
        </a>
      </nav>
    </div>
  </header>

  <hr class=\"divider-gradient\" aria-hidden=\"true\">

  ";
        // line 46
        yield "  ";
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "hero", [], "any", false, false, true, 46)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 47
            yield "    <section class=\"hero\">
      ";
            // line 48
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "hero", [], "any", false, false, true, 48), "html", null, true);
            yield "
    </section>
  ";
        }
        // line 51
        yield "
  <main id=\"main-content\" class=\"page__content\" role=\"main\">

    ";
        // line 55
        yield "    <section class=\"homepage-section\">
      <div class=\"container\">
        <div class=\"homepage-section__header\">
          <h2 class=\"homepage-section__title\">";
        // line 58
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Featured Stories"));
        yield "</h2>
        </div>
        <div class=\"grid grid--featured\">
          ";
        // line 61
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, views_embed_view("homepage_featured", "block_1"), "html", null, true);
        yield "
        </div>
      </div>
    </section>

    <hr class=\"divider-gradient\" aria-hidden=\"true\">

    ";
        // line 69
        yield "    <section class=\"homepage-section homepage-section--gray\">
      <div class=\"container\">
        <div class=\"homepage-section__header\">
          <h2 class=\"homepage-section__title\">";
        // line 72
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Recent Highlights"));
        yield "</h2>
        </div>
        <div class=\"grid grid--3\">
          ";
        // line 75
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, views_embed_view("homepage_highlights", "block_1"), "html", null, true);
        yield "
        </div>
      </div>
    </section>

    ";
        // line 81
        yield "    <section class=\"homepage-section\">
      <div class=\"container\">
        <div class=\"homepage-section__header\">
          <h2 class=\"homepage-section__title\">";
        // line 84
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Digital Exclusives"));
        yield "</h2>
          <a href=\"/in-brief\" class=\"homepage-section__more\">";
        // line 85
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("View All"));
        yield "</a>
        </div>
        <div class=\"grid grid--4\">
          ";
        // line 88
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, views_embed_view("homepage_digital_exclusives", "block_1"), "html", null, true);
        yield "
        </div>
      </div>
    </section>

    ";
        // line 94
        yield "    ";
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "content", [], "any", false, false, true, 94)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 95
            yield "      ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "content", [], "any", false, false, true, 95), "html", null, true);
            yield "
    ";
        }
        // line 97
        yield "
  </main>

  ";
        // line 101
        yield "  <footer class=\"footer\" role=\"contentinfo\">
    <div class=\"footer__inner\">
      <div class=\"footer__top\">
        <div class=\"footer__logos\">
          <a href=\"https://www.dana-farber.org\" target=\"_blank\" rel=\"noopener\" aria-label=\"";
        // line 105
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dana-Farber Cancer Institute"));
        yield "\">
            <img src=\"/";
        // line 106
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["directory"] ?? null), "html", null, true);
        yield "/images/dfci-logo-white.svg\" alt=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dana-Farber Cancer Institute"));
        yield "\" class=\"footer__logo-img\" width=\"250\" height=\"40\">
          </a>
          <a href=\"https://www.jimmyfund.org\" target=\"_blank\" rel=\"noopener\" aria-label=\"";
        // line 108
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("The Jimmy Fund"));
        yield "\">
            <img src=\"/";
        // line 109
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["directory"] ?? null), "html", null, true);
        yield "/images/jimmy-fund-logo-white.svg\" alt=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("The Jimmy Fund"));
        yield "\" class=\"footer__logo-img\" width=\"200\" height=\"40\">
          </a>
        </div>

        ";
        // line 113
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_first", [], "any", false, false, true, 113)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 114
            yield "          <div class=\"footer__column\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_first", [], "any", false, false, true, 114), "html", null, true);
            yield "</div>
        ";
        }
        // line 116
        yield "        ";
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_second", [], "any", false, false, true, 116)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 117
            yield "          <div class=\"footer__column\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_second", [], "any", false, false, true, 117), "html", null, true);
            yield "</div>
        ";
        }
        // line 119
        yield "        ";
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_third", [], "any", false, false, true, 119)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 120
            yield "          <div class=\"footer__column\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer_third", [], "any", false, false, true, 120), "html", null, true);
            yield "</div>
        ";
        }
        // line 122
        yield "      </div>

      <div class=\"footer__bottom\">
        <p>&copy; ";
        // line 125
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Twig\Extension\CoreExtension']->formatDate("now", "Y"), "html", null, true);
        yield " ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Dana-Farber Cancer Institute. All rights reserved."));
        yield "</p>
        <p>";
        // line 126
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Impact Magazine | Division of Philanthropy"));
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
        return "themes/custom/df_impact/templates/layout/page--front.html.twig";
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
        return array (  273 => 126,  267 => 125,  262 => 122,  256 => 120,  253 => 119,  247 => 117,  244 => 116,  238 => 114,  236 => 113,  227 => 109,  223 => 108,  216 => 106,  212 => 105,  206 => 101,  201 => 97,  195 => 95,  192 => 94,  184 => 88,  178 => 85,  174 => 84,  169 => 81,  161 => 75,  155 => 72,  150 => 69,  140 => 61,  134 => 58,  129 => 55,  124 => 51,  118 => 48,  115 => 47,  112 => 46,  101 => 37,  95 => 34,  85 => 29,  81 => 28,  77 => 27,  68 => 21,  59 => 17,  53 => 16,  48 => 13,  44 => 10,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "themes/custom/df_impact/templates/layout/page--front.html.twig", "/var/www/html/web/themes/custom/df_impact/templates/layout/page--front.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 46];
        static $filters = ["t" => 16, "escape" => 17, "date" => 125];
        static $functions = ["path" => 16, "drupal_view" => 61];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['t', 'escape', 'date'],
                ['path', 'drupal_view'],
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
