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

/* themes/custom/df_impact/templates/node/node--article--full.html.twig */
class __TwigTemplate_94efc8d5eebe601c9c460eaf79b76b38 extends Template
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
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("df_impact/global"), "html", null, true);
        yield "

<article";
        // line 9
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["attributes"] ?? null), "addClass", ["article"], "method", false, false, true, 9), "html", null, true);
        yield ">

  ";
        // line 12
        yield "  ";
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_featured_image", [], "any", false, false, true, 12)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 13
            yield "    <div class=\"article__hero\">
      ";
            // line 14
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_featured_image", [], "any", false, false, true, 14), "html", null, true);
            yield "
      ";
            // line 15
            if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_photo_credit", [], "any", false, false, true, 15)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 16
                yield "        <div class=\"article__hero-caption\">
          ";
                // line 17
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_photo_credit", [], "any", false, false, true, 17), "html", null, true);
                yield "
        </div>
      ";
            }
            // line 20
            yield "    </div>
  ";
        }
        // line 22
        yield "
  ";
        // line 24
        yield "  <header class=\"article__header\">
    ";
        // line 25
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title_prefix"] ?? null), "html", null, true);
        yield "
    <h1 class=\"article__title\">";
        // line 26
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["label"] ?? null), "html", null, true);
        yield "</h1>
    ";
        // line 27
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title_suffix"] ?? null), "html", null, true);
        yield "

    ";
        // line 29
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_subtitle", [], "any", false, false, true, 29)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 30
            yield "      <p class=\"article__subtitle\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_subtitle", [], "any", false, false, true, 30), "html", null, true);
            yield "</p>
    ";
        }
        // line 32
        yield "
    <div class=\"article__meta\">
      ";
        // line 34
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_issue", [], "any", false, false, true, 34)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 35
            yield "        <span class=\"article__meta-item\">
          ";
            // line 36
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_issue", [], "any", false, false, true, 36), "html", null, true);
            yield "
        </span>
      ";
        }
        // line 39
        yield "
      ";
        // line 40
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_byline", [], "any", false, false, true, 40)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 41
            yield "        <span class=\"article__meta-item\">
          ";
            // line 42
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_byline", [], "any", false, false, true, 42), "html", null, true);
            yield "
        </span>
      ";
        }
        // line 45
        yield "
      ";
        // line 46
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_photo_credit", [], "any", false, false, true, 46)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 47
            yield "        <span class=\"article__meta-item\">
          ";
            // line 48
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_photo_credit", [], "any", false, false, true, 48), "html", null, true);
            yield "
        </span>
      ";
        }
        // line 51
        yield "    </div>
  </header>

  ";
        // line 55
        yield "  <div class=\"article__body\">
    ";
        // line 56
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "body", [], "any", false, false, true, 56), "html", null, true);
        yield "
  </div>

  ";
        // line 60
        yield "  ";
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_content_sections", [], "any", false, false, true, 60)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 61
            yield "    <div class=\"article__sections\">
      ";
            // line 62
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_content_sections", [], "any", false, false, true, 62), "html", null, true);
            yield "
    </div>
  ";
        }
        // line 65
        yield "
  ";
        // line 67
        yield "  <div class=\"article__tags\">
    ";
        // line 68
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_topics", [], "any", false, false, true, 68)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 69
            yield "      ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_topics", [], "any", false, false, true, 69), "html", null, true);
            yield "
    ";
        }
        // line 71
        yield "    ";
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_cancer_types", [], "any", false, false, true, 71)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 72
            yield "      ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_cancer_types", [], "any", false, false, true, 72), "html", null, true);
            yield "
    ";
        }
        // line 74
        yield "  </div>

  ";
        // line 77
        yield "  <div class=\"social-share\">
    <span class=\"social-share__label\">";
        // line 78
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Share"));
        yield "</span>
    <a href=\"https://www.facebook.com/sharer/sharer.php?u=";
        // line 79
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\Core\Template\TwigExtension']->getUrl("<current>"));
        yield "\" class=\"social-share__link\" target=\"_blank\" rel=\"noopener\" aria-label=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Share on Facebook"));
        yield "\">FB</a>
    <a href=\"https://twitter.com/intent/tweet?url=";
        // line 80
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\Core\Template\TwigExtension']->getUrl("<current>"));
        yield "&text=";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["label"] ?? null), "html", null, true);
        yield "\" class=\"social-share__link\" target=\"_blank\" rel=\"noopener\" aria-label=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Share on X"));
        yield "\">X</a>
    <a href=\"https://www.linkedin.com/sharing/share-offsite/?url=";
        // line 81
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\Core\Template\TwigExtension']->getUrl("<current>"));
        yield "\" class=\"social-share__link\" target=\"_blank\" rel=\"noopener\" aria-label=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Share on LinkedIn"));
        yield "\">LI</a>
    <a href=\"mailto:?subject=";
        // line 82
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["label"] ?? null), "html", null, true);
        yield "&body=";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\Core\Template\TwigExtension']->getUrl("<current>"));
        yield "\" class=\"social-share__link\" aria-label=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Share via Email"));
        yield "\">✉</a>
  </div>

  ";
        // line 86
        yield "  ";
        if ((($tmp = Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_related_articles", [], "any", false, false, true, 86)))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 87
            yield "    <section class=\"related-articles\">
      <h2 class=\"related-articles__title\">";
            // line 88
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("More Stories"));
            yield "</h2>
      <div class=\"grid grid--3\">
        ";
            // line 90
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_related_articles", [], "any", false, false, true, 90), "html", null, true);
            yield "
      </div>
    </section>
  ";
        }
        // line 94
        yield "
</article>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["attributes", "content", "title_prefix", "label", "title_suffix"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "themes/custom/df_impact/templates/node/node--article--full.html.twig";
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
        return array (  257 => 94,  250 => 90,  245 => 88,  242 => 87,  239 => 86,  229 => 82,  223 => 81,  215 => 80,  209 => 79,  205 => 78,  202 => 77,  198 => 74,  192 => 72,  189 => 71,  183 => 69,  181 => 68,  178 => 67,  175 => 65,  169 => 62,  166 => 61,  163 => 60,  157 => 56,  154 => 55,  149 => 51,  143 => 48,  140 => 47,  138 => 46,  135 => 45,  129 => 42,  126 => 41,  124 => 40,  121 => 39,  115 => 36,  112 => 35,  110 => 34,  106 => 32,  100 => 30,  98 => 29,  93 => 27,  89 => 26,  85 => 25,  82 => 24,  79 => 22,  75 => 20,  69 => 17,  66 => 16,  64 => 15,  60 => 14,  57 => 13,  54 => 12,  49 => 9,  44 => 7,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "themes/custom/df_impact/templates/node/node--article--full.html.twig", "/var/www/html/web/themes/custom/df_impact/templates/node/node--article--full.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 12];
        static $filters = ["escape" => 7, "trim" => 12, "render" => 12, "t" => 78];
        static $functions = ["attach_library" => 7, "url" => 79];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['escape', 'trim', 'render', 't'],
                ['attach_library', 'url'],
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
