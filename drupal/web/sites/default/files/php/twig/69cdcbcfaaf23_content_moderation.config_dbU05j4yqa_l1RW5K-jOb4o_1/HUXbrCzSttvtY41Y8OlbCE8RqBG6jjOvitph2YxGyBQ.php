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

/* @help_topics/content_moderation.configuring_workflows.html.twig */
class __TwigTemplate_53059b6fc6dac3e547d1b2f8d6f1ad1c extends Template
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
        // line 9
        $context["content_structure_topic"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getTopicLink("core.content_structure"));
        // line 10
        $context["user_overview_topic"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getTopicLink("user.overview"));
        // line 11
        $context["user_permissions_topic"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getTopicLink("user.permissions"));
        // line 12
        $context["workflows_overview_topic"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getTopicLink("workflows.overview"));
        // line 13
        $context["content_moderation_permissions_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            yield t("Content Moderation", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 14
        $context["content_moderation_permissions_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["content_moderation_permissions_link_text"] ?? null), "user.admin_permissions", [], ["fragment" => "module-content_moderation"]));
        // line 15
        $context["workflows_permissions_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            yield t("Administer workflows", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 16
        $context["workflows_permissions_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["workflows_permissions_link_text"] ?? null), "user.admin_permissions", [], ["fragment" => "module-workflows"]));
        // line 17
        $context["workflows_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            yield t("Workflows", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 18
        $context["workflows_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["workflows_link_text"] ?? null), "entity.workflow.collection"));
        // line 19
        yield "<h2>";
        yield t("Goal", []);
        yield "</h2>
<p>";
        // line 20
        yield t("Create or edit a workflow with various workflow states (for example <em>Concept</em>, <em>Archived</em>, etc.) for moderating content. See @workflows_overview_topic for more information on workflows.", ["@workflows_overview_topic" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["workflows_overview_topic"] ?? null)), ]);
        yield "</p>
<h2>";
        // line 21
        yield t("Who can configure a workflow?", []);
        yield "</h2>
<p>";
        // line 22
        yield t("Users with <em>workflows permissions</em> (typically administrators) can configure workflows. See Permissions &gt; <em>@workflows_permissions_link</em> to configure workflows permissions.", ["@workflows_permissions_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["workflows_permissions_link"] ?? null)), ]);
        yield "</p>
<h2>";
        // line 23
        yield t("Steps", []);
        yield "</h2>
<ol>
  <li>";
        // line 25
        yield t("Make a plan for the new workflow:", []);
        // line 26
        yield "    <ul>
      <li>";
        // line 27
        yield t("Decide which workflow states you need; for example, <em>Concept</em>, <em>Review</em>, and <em>Final</em>.", []);
        yield "</li>
      <li>";
        // line 28
        yield t("Decide on the settings for each state:", []);
        // line 29
        yield "        <ul>
          <li>";
        // line 30
        yield t("<em>Label</em>: the state name", []);
        yield "</li>
          <li>";
        // line 31
        yield t("<em>Published</em>: if checked, when content reaches this state it will be made visible on the site (to users with permission).", []);
        yield "</li>
          <li>";
        // line 32
        yield t("<em>Default revision</em>: if checked, when content reaches this state it will become the default revision of the content; published content is automatically the default revision.", []);
        yield "</li>
        </ul>
      </li>
      <li>";
        // line 35
        yield t("Decide which state content should be created in.", []);
        yield "</li>
      <li>";
        // line 36
        yield t("Decide on the list of allowed transitions between states. For example, you might want a transition between <em>Concept</em> and <em>Review</em>. Each transition has a label; for example, the Concept to Review transition might be labeled \"Review concept\".", []);
        yield "</li>
      <li>";
        // line 37
        yield t("Decide which roles should have permissions to make each transition; see @user_overview_topic for an overview of roles and permissions.", ["@user_overview_topic" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["user_overview_topic"] ?? null)), ]);
        yield "</li>
      <li>";
        // line 38
        yield t("Decide which <em>entity types</em> and subtypes the workflow should apply to. Only entity types that support revisions are possible to define workflows for. See @content_structure_topic for more information on content entities and fields.", ["@content_structure_topic" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["content_structure_topic"] ?? null)), ]);
        yield "</li>
    </ul>
  </li>
  <li>";
        // line 41
        yield t("To implement your plan, in the <em>Manage</em> administrative menu, navigate to <em>Configuration</em> &gt; <em>Workflow</em> &gt; <em>@workflows_link</em>. A list of workflows is shown, including the default workflow <em>Editorial</em> that you can adapt.", ["@workflows_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["workflows_link"] ?? null)), ]);
        yield "</li>
  <li>";
        // line 42
        yield t("Click <em>Add workflow</em>.", []);
        yield "</li>
  <li>";
        // line 43
        yield t("Enter a name in the <em>Label</em> field, select <em>Content moderation</em> from the <em>Workflow type</em> field, and click <em>Save</em>.", []);
        yield "</li>
  <li>";
        // line 44
        yield t("Verify that the <em>States</em> list matches your planned states. You can add missing states by clicking <em>Add a new state</em>. You can edit or delete states by clicking <em>Edit</em> or <em>Delete</em> under <em>Operations</em> (if the <em>Delete</em> option is not available, you will first need to delete any <em>Transitions</em> to or from this state).", []);
        yield "</li>
  <li>";
        // line 45
        yield t("Verify that the <em>Transitions</em> list matches your plan. You can add missing transitions by clicking <em>Add a new transition</em>. You can edit or delete transitions by clicking <em>Edit</em> or <em>Delete</em> under <em>Operations</em>.", []);
        yield "</li>
  <li>";
        // line 46
        yield t("Under <em>This workflow applies to:</em>, find the entity type that you want this workflow to apply to, such as Content revisions, Content block revisions, or Taxonomy term revisions. Click <em>Select</em>.", []);
        yield "</li>
  <li>";
        // line 47
        yield t("Check the entity subtypes that you want to apply the workflow to. For example, you might choose to apply your workflow to the <em>Page</em> content type, but not to <em>Article</em>.", []);
        yield "</li>
  <li>";
        // line 48
        yield t("Click <em>Save</em>.", []);
        yield "</li>
  <li>";
        // line 49
        yield t("Under <em>Workflow settings</em>, select the <em>Default moderation state</em> for new content.", []);
        yield "</li>
  <li>";
        // line 50
        yield t("Click <em>Save</em> to save your workflow.", []);
        yield "</li>
  <li>";
        // line 51
        yield t("Follow the steps in @user_permissions_topic to assign permissions for each transition to roles. The permissions are listed under the <em>@content_moderation_permissions_link</em> section; there is one permission for each transition in each workflow.", ["@user_permissions_topic" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["user_permissions_topic"] ?? null)), "@content_moderation_permissions_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["content_moderation_permissions_link"] ?? null)), ]);
        yield "</li>
  <li>";
        // line 52
        yield t("Optionally (recommended), create a view for your custom workflow, to provide a page for content editors to see what content needs to be moderated. You can do this if the Views UI module is installed, by following the steps in the related <em>Creating a new view</em> topic listed below under <em>Related topics</em>. When creating the view, under <em>View settings</em> &gt; <em>Show</em>, select the revision data type you configured the workflow for, and be sure to display the <em>Workflow State</em> field in your view.", []);
        yield "</li>
</ol>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/content_moderation.configuring_workflows.html.twig";
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
        return array (  185 => 52,  181 => 51,  177 => 50,  173 => 49,  169 => 48,  165 => 47,  161 => 46,  157 => 45,  153 => 44,  149 => 43,  145 => 42,  141 => 41,  135 => 38,  131 => 37,  127 => 36,  123 => 35,  117 => 32,  113 => 31,  109 => 30,  106 => 29,  104 => 28,  100 => 27,  97 => 26,  95 => 25,  90 => 23,  86 => 22,  82 => 21,  78 => 20,  73 => 19,  71 => 18,  66 => 17,  64 => 16,  59 => 15,  57 => 14,  52 => 13,  50 => 12,  48 => 11,  46 => 10,  44 => 9,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/content_moderation.configuring_workflows.html.twig", "/var/www/html/web/core/modules/content_moderation/help_topics/content_moderation.configuring_workflows.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 9, "trans" => 13];
        static $filters = ["escape" => 20];
        static $functions = ["render_var" => 9, "help_topic_link" => 9, "help_route_link" => 14];

        try {
            $this->sandbox->checkSecurity(
                ['set', 'trans'],
                ['escape'],
                ['render_var', 'help_topic_link', 'help_route_link'],
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
