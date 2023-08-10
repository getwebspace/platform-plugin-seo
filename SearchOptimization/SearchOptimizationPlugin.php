<?php declare(strict_types=1);

namespace Plugin\SearchOptimization;

include_once PLUGIN_DIR . '/SearchOptimization/helper.php';

use App\Domain\AbstractPlugin;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class SearchOptimizationPlugin extends AbstractPlugin
{
    const NAME = 'SearchOptimizationPlugin';
    const TITLE = 'Search optimization';
    const DESCRIPTION = 'Plugin for generation XML/YML files: ' .
                        '<a href="/xml/sitemap" target="_blank">SiteMap</a>, ' .
                        '<a href="/xml/gmf" target="_blank">Google Merchant Feed</a>, ' .
                        '<a href="/xml/yml" target="_blank">Yandex Market</a>, ' .
                        '<a href="/xml/htl" target="_blank">Hotline</a>, ' .
                        '<a href="/robots.txt" target="_blank">robots.txt</a>';
    const AUTHOR = 'Aleksey Ilyin';
    const AUTHOR_SITE = 'https://getwebspace.org';
    const VERSION = '6.2';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $self = $this;

        $this->setHandledRoute('common:main');
        $this->setTemplateFolder(__DIR__ . '/templates');
        $this->addToolbarItem(['twig' => 'seo.twig']);
        $this->addSettingsField([
            'label' => 'Autorun',
            'description' => 'Generate XML files automatically after every change in pages, posts and catalog products',
            'type' => 'select',
            'name' => 'enable',
            'args' => [
                'option' => [
                    'off' => 'Off',
                    'on' => 'On',
                ],
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Company name',
            'description' => '<span class="text-muted">Variable value: <i>company_title</i></span>',
            'type' => 'text',
            'name' => 'company_title',
        ]);
        $this->addSettingsField([
            'label' => 'Name of shop',
            'description' => '<span class="text-muted">Variable value: <i>shop_title</i></span>',
            'type' => 'text',
            'name' => 'shop_title',
        ]);
        $this->addSettingsField([
            'label' => 'Store Description',
            'description' => '<span class="text-muted">Variable value: <i>shop_description</i></span>',
            'type' => 'text',
            'name' => 'shop_description',
        ]);
        $this->addSettingsField([
            'label' => 'ID (code) of the store on Hotline',
            'description' => '<span class="text-muted">Variable value: <i>shop_id</i></span>',
            'type' => 'text',
            'name' => 'shop_id',
        ]);
        $this->addSettingsField([
            'label' => 'Currency',
            'description' => '<span class="text-muted">Variable value: <i>currency</i></span>',
            'type' => 'text',
            'name' => 'currency',
        ]);
        $this->addSettingsField([
            'label' => 'Cost of delivery',
            'description' => 'Indicated in the currency indicated by the field above<br>' .
                '<span class="text-muted">Variable value: <i>delivery_cost</i></span>',
            'type' => 'number',
            'name' => 'delivery_cost',
        ]);
        $this->addSettingsField([
            'label' => 'Delivery period',
            'description' => 'Specified in days<br>' .
                '<span class="text-muted">Variable value: <i>delivery_days</i></span>',
            'type' => 'number',
            'name' => 'delivery_days',
        ]);
        $this->addSettingsField([
            'label' => 'Twig template file SiteMap',
            'description' => 'Documentation <a href="https://en.wikipedia.org/wiki/Sitemaps" target="_blank">format</a><br>' .
                '<span class="text-muted">Possible variables: <i>site_address, catalog_address, pages, publications, publicationCategories, categories, products</i></span>',
            'type' => 'textarea',
            'name' => 'sitemap_txt',
            'args' => [
                'value' => DEFAULT_SITEMAP,
                'style' => 'height: 200px!important;',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Twig template file GMF',
            'description' => 'Documentation <a href="https://support.google.com/merchants/answer/7052112?hl=en" target="_blank">format</a><br>' .
                '<span class="text-muted">Possible variables: <i>shop_title, shop_description, site_address, email, shop_id, currency, catalog_address, delivery_cost, delivery_days, categories, products</i></span>',
            'type' => 'textarea',
            'name' => 'gmf_txt',
            'args' => [
                'value' => DEFAULT_GMF,
                'style' => 'height: 200px!important;',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Twig template file Yandex YML',
            'description' => 'Documentation <a href="https://yandex.ru/support/partnermarket/export/yml.html" target="_blank">format</a><sup><small>[ru]</small></sup><br>' .
                '<span class="text-muted">Possible variables: <i>shop_title, company_title, site_address, email, shop_id, currency, catalog_address, delivery_cost, delivery_days, categories, products</i></span>',
            'type' => 'textarea',
            'name' => 'yml_txt',
            'args' => [
                'value' => DEFAULT_YANDEX_YML,
                'style' => 'height: 200px!important;',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Twig template file Hotline YML',
            'description' => 'Documentation <a href="https://hotline.ua/about/pricelists_specs/" target="_blank">format</a><sup><small>[ua]</small></sup><br>' .
                '<span class="text-muted">Possible variables: <i>shop_title, company_title, site_address, email, shop_id, currency, catalog_address, delivery_cost, delivery_days, categories, products</i></span>',
            'type' => 'textarea',
            'name' => 'htl_txt',
            'args' => [
                'value' => DEFAULT_YANDEX_YML,
                'style' => 'height: 200px!important;',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Twig template file robots.txt',
            'description' => 'Documentation <a href="https://en.wikipedia.org/wiki/Robots.txt" target="_blank">format</a><br>' .
                '<span class="text-muted">Possible variables: <i>site_address, catalog_address</i></span>',
            'type' => 'textarea',
            'name' => 'robots_txt',
            'args' => [
                'value' => DEFAULT_ROBOTS,
                'style' => 'height: 200px!important;',
            ],
        ]);

        $this->map([
            'methods' => ['get'],
            'pattern' => '/robots.txt',
            'handler' => function (Request $req, Response $res) use ($container, $self) {
                $renderer = $container->get('view');
                $clob = $self->parameter('SearchOptimizationPlugin_robots_txt', '');
                $data = [
                    'site_address' => rtrim($self->parameter('common_homepage', ''), '/'),
                    'catalog_address' => $self->parameter('catalog_address', 'catalog'),
                ];

                $res->getBody()->write(
                    $renderer->fetchFromString(trim($clob) ? $clob : DEFAULT_ROBOTS, $data)
                );

                return $res->withHeader('Content-Type', 'text/plain');
            },
        ])->setName('common:seo:robots');

        $this
            ->subscribe(
                ['task:catalog:import', 'cup:catalog:category:create', 'cup:catalog:category:edit', 'cup:catalog:category:delete', 'cup:catalog:product:create', 'cup:catalog:product:edit', 'cup:catalog:product:delete'],
                function () {
                    $task = new \Plugin\SearchOptimization\Tasks\GMFTask($this->container);
                    $task->execute();
                    \App\Domain\AbstractTask::worker($task);

                    $task = new \Plugin\SearchOptimization\Tasks\YandexYMLTask($this->container);
                    $task->execute();
                    \App\Domain\AbstractTask::worker($task);

                    $task = new \Plugin\SearchOptimization\Tasks\SiteMapTask($this->container);
                    $task->execute();
                    \App\Domain\AbstractTask::worker($task);
                }
            )
            ->subscribe(
                ['cup:page:add', 'cup:page:edit', 'cup:page:delete', 'cup:publication:add', 'cup:publication:edit', 'cup:publication:delete', 'cup:publication:category:add', 'cup:publication:category:edit', 'cup:publication:category:delete'],
                function () {
                    $task = new \Plugin\SearchOptimization\Tasks\SiteMapTask($this->container);
                    $task->execute();
                    \App\Domain\AbstractTask::worker($task);
                }
            );
    }
}
