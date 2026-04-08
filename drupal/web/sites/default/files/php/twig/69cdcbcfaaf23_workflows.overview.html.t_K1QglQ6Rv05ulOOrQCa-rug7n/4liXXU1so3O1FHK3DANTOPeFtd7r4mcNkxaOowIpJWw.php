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

/* @help_topics/workflows.overview.html.twig */
class __TwigTemplate_b067e79cb09527e32af50c82c9462515 extends Template
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
        $context["configuring_workflows_topic"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getTopicLink("content_moderation.configuring_workflows"));
        // line 8
        $context["changing_states_topic"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getTopicLink("content_moderation.changing_states"));
        // line 9
        yield "<h2>";
        yield t("What is a content moderation workflow?", []);
        yield "</h2>
<p>";
        // line 10
        yield t("On some sites, new content and content revisions need to be <em>moderated</em>. That is, they need to pass through several <em>states</em> before becoming visible to site visitors. The collection of states and the definition of the transitions between states is known as a <em>workflow</em>. For example, new content might start out in a <em>Draft</em> state, and then might need to pass through several <em>Review</em> states before it becomes <em>Published</em> on the live site.", []);
        yield "</p>
<p>";
        // line 11
        yield t("The core software allows you to configure workflows in which each transition has an associated permission that can be granted to a particular role. See @configuring_workflows_topic for more information.", ["@configuring_workflows_topic" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["configuring_workflows_topic"] ?? null)), ]);
        yield "</p>
<p>";
        // line 12
        yield t("Users with sufficient permissions can change the workflow state of a particular entity. See @changing_states_topic for more information.", ["@changing_states_topic" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["changing_states_topic"] ?? null)), ]);
        yield "</p>
<h2>";
        // line 13
        yield t("Overview of content moderation workflows", []);
        yield "</h2>
<ul>
  <li>";
        // line 15
        yield t("The core Content Moderation module allows you to expand on core software's \"unpublished\" and \"published\" states for content. It allows you to have a published version that is live, but have a separate working copy that is undergoing review before it is published. This is achieved by using workflows to apply different states and transitions to entities as needed.", []);
        yield "</li>
  <li>";
        // line 16
        yield t("The core Workflows module allows you to manage workflows with states and transitions.", []);
        yield "</li>
</ul>
<p>";
        // line 18
        yield t("See the related topics listed below for specific tasks and background information.", []);
        yield "</p>
<h2>";
        // line 19
        yield t("Additional resources", []);
        yield "</h2>
<ul>
  <li><a href=\"https://www.drupal.org/docs/8/core/modules/content-moderation/overview\">";
        // line 21
        yield t("On-line documentation about Content Moderation", []);
        yield "</a>
  </li>
</ul>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/workflows.overview.html.twig";
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
        return array (  88 => 21,  83 => 19,  79 => 18,  74 => 16,  70 => 15,  65 => 13,  61 => 12,  57 => 11,  53 => 10,  48 => 9,  46 => 8,  44 => 7,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/workflows.overview.html.twig", "/var/www/html/web/core/modules/workflows/help_topics/workflows.overview.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 7, "trans" => 9];
        static $filters = ["escape" => 11];
        static $functions = ["render_var" => 7, "help_topic_link" => 7];

        try {
            $this->sandbox->checkSecurity(
                ['set', 'trans'],
                ['escape'],
                ['render_var', 'help_topic_link'],
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
