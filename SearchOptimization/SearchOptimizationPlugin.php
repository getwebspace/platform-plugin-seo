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
    const TITLE = 'Поисковая оптимизация';
    const DESCRIPTION = 'Плагин поисковой оптимизации, генерирует XML файлы: ' .
                        '<a href="/xml/sitemap" target="_blank">SiteMap</a>, ' .
                        '<a href="/xml/gmf" target="_blank">Google Merchant Feed</a>, ' .
                        '<a href="/xml/yml" target="_blank">Yandex Market</a>, ' .
                        '<a href="/robots.txt" target="_blank">robots.txt</a>';
    const AUTHOR = 'Aleksey Ilyin';
    const AUTHOR_SITE = 'https://getwebspace.org';
    const VERSION = '5.0';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $self = $this;

        $this->setTemplateFolder(__DIR__ . '/templates');
        $this->addTwigExtension(\Plugin\SearchOptimization\SearchOptimizationPluginTwigExt::class);
        $this->addToolbarItem(['twig' => 'seo.twig']);
        $this->addSettingsField([
            'label' => 'Автоматический запуск',
            'description' => 'Генерировать XML файлы автоматически после каждого изменения в страницах, публикациях и продуктах каталога',
            'type' => 'select',
            'name' => 'enable',
            'args' => [
                'option' => [
                    'off' => 'Нет',
                    'on' => 'Да',
                ],
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Название компании',
            'description' => '<span class="text-muted">Значение переменной: <i>company_title</i></span>',
            'type' => 'text',
            'name' => 'company_title',
        ]);
        $this->addSettingsField([
            'label' => 'Название магазина',
            'description' => '<span class="text-muted">Значение переменной: <i>shop_title</i></span>',
            'type' => 'text',
            'name' => 'shop_title',
        ]);
        $this->addSettingsField([
            'label' => 'Описание магазина',
            'description' => '<span class="text-muted">Значение переменной: <i>shop_description</i></span>',
            'type' => 'text',
            'name' => 'shop_description',
        ]);
        $this->addSettingsField([
            'label' => 'Валюта',
            'description' => '<span class="text-muted">Значение переменной: <i>currency</i></span>',
            'type' => 'text',
            'name' => 'currency',
        ]);
        $this->addSettingsField([
            'label' => 'Стоимость доставки',
            'description' => 'Указывается в валюте указанной полем выше<br>' .
                             '<span class="text-muted">Значение переменной: <i>delivery_cost</i></span>',
            'type' => 'number',
            'name' => 'delivery_cost',
        ]);
        $this->addSettingsField([
            'label' => 'Срок доставки',
            'description' => 'Указывается в днях<br>' .
                             '<span class="text-muted">Значение переменной: <i>delivery_days</i></span>',
            'type' => 'number',
            'name' => 'delivery_days',
        ]);
        $this->addSettingsField([
            'label' => 'Twig шаблон SiteMap файла',
            'description' => 'Документация по <a href="https://en.wikipedia.org/wiki/Sitemaps" target="_blank">формату</a><sup><small>[en]</small></sup><br>' .
                             '<span class="text-muted">Возможные переменные: <i>site_address, catalog_address, pages, publications, publicationCategories, categories, products</i></span>',
            'type' => 'textarea',
            'name' => 'sitemap_txt',
            'args' => [
                'value' => DEFAULT_SITEMAP,
                'style' => 'height: 200px!important;',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Twig шаблон GMF файла',
            'description' => 'Документация по <a href="https://support.google.com/merchants/answer/7052112?hl=ru" target="_blank">формату</a><br>' .
                             '<span class="text-muted">Возможные переменные: <i>shop_title, shop_description, site_address, email, currency, catalog_address, delivery_cost, delivery_days, categories, products</i></span>',
            'type' => 'textarea',
            'name' => 'gmf_txt',
            'args' => [
                'value' => DEFAULT_GMF,
                'style' => 'height: 200px!important;',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Twig шаблон YML файла',
            'description' => 'Документация по <a href="https://yandex.ru/support/partnermarket/export/yml.html" target="_blank">формату</a><br>' .
                             '<span class="text-muted">Возможные переменные: <i>shop_title, company_title, site_address, email, currency, catalog_address, delivery_cost, delivery_days, categories, products</i></span>',
            'type' => 'textarea',
            'name' => 'yml_txt',
            'args' => [
                'value' => DEFAULT_YML,
                'style' => 'height: 200px!important;',
            ],
        ]);
        $this->addSettingsField([
            'label' => 'Twig шаблон robots.txt файла',
            'description' => 'Документация по <a href="https://ru.wikipedia.org/wiki/Стандарт_исключений_для_роботов" target="_blank">формату</a><br>' .
                             '<span class="text-muted">Возможные переменные: <i>site_address, catalog_address</i></span>',
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
                [
                    'cup:catalog:data:import',
                    'cup:catalog:category:add',
                    'cup:catalog:category:edit',
                    'cup:catalog:category:delete',
                    'cup:catalog:product:add',
                    'cup:catalog:product:edit',
                    'cup:catalog:product:delete'
                ],
                function () {
                    $task = new \Plugin\SearchOptimization\Tasks\GMFTask($this->container);
                    $task->execute();
                    \App\Domain\AbstractTask::worker($task);

                    $task = new \Plugin\SearchOptimization\Tasks\YMLTask($this->container);
                    $task->execute();
                    \App\Domain\AbstractTask::worker($task);

                    $task = new \Plugin\SearchOptimization\Tasks\SiteMapTask($this->container);
                    $task->execute();
                    \App\Domain\AbstractTask::worker($task);
                }
            )
            ->subscribe(
                [
                    'cup:page:add',
                    'cup:page:edit',
                    'cup:page:delete',
                    'cup:publication:add',
                    'cup:publication:edit',
                    'cup:publication:delete',
                    'cup:publication:category:add',
                    'cup:publication:category:edit',
                    'cup:publication:category:delete'
                ],
                function () {
                    $task = new \Plugin\SearchOptimization\Tasks\SiteMapTask($this->container);
                    $task->execute();
                    \App\Domain\AbstractTask::worker($task);
                }
            );
    }
}
