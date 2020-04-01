<?php

namespace Plugin\SearchOptimization;

include_once __DIR__ . '/vendor/autoload.php';

use App\Application\Plugin;
use Psr\Container\ContainerInterface;
use samdark\sitemap\Sitemap;

class SearchOptimizationPlugin extends Plugin
{
    const NAME        = "SearchOptimizationPlugin";
    const TITLE       = "Search optimization";
    const DESCRIPTION = "Плагин поисковой оптимизации, генерирует XML файлы: SiteMap, Google Merchant Feed, Yandex Market";
    const AUTHOR      = "Aleksey Ilyin";
    const AUTHOR_SITE = "https://site.0x12f.com";

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->setTemplateFolder(__DIR__ . '/templates');
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
            'label' => 'Частота обновления контента',
            'type' => 'select',
            'name' => 'frequency',
            'args' => [
                'selected' => Sitemap::WEEKLY,
                'option' => [
                    Sitemap::ALWAYS => Sitemap::ALWAYS,
                    Sitemap::HOURLY => Sitemap::HOURLY,
                    Sitemap::DAILY => Sitemap::DAILY,
                    Sitemap::WEEKLY => Sitemap::WEEKLY,
                    Sitemap::MONTHLY => Sitemap::MONTHLY,
                    Sitemap::YEARLY => Sitemap::YEARLY,
                    Sitemap::NEVER => Sitemap::NEVER
                ],
            ],
        ]);

        $this->addSettingsField([
            'label' => 'Название компании',
            'type' => 'text',
            'name' => 'company_title',
        ]);
        $this->addSettingsField([
            'label' => 'Название магазина',
            'type' => 'text',
            'name' => 'shop_title',
        ]);
        $this->addSettingsField([
            'label' => 'Описание магазина',
            'type' => 'text',
            'name' => 'shop_description',
        ]);
        $this->addSettingsField([
            'label' => 'Валюта',
            'type' => 'text',
            'name' => 'currency',
        ]);
        $this->addSettingsField([
            'label' => 'Стоимость доставки',
            'description' => 'Указывается в валюте указанной полем выше',
            'type' => 'number',
            'name' => 'delivery_cost',
        ]);
        $this->addSettingsField([
            'label' => 'Срок доставки',
            'description' => 'Указывается в днях',
            'type' => 'number',
            'name' => 'delivery_days',
        ]);

        $this->setHandledRoute(
            'cup:catalog:data:import',
            'cup:catalog:category:add',
            'cup:catalog:category:edit',
            'cup:catalog:category:delete',
            'cup:catalog:product:add',
            'cup:catalog:product:edit',
            'cup:catalog:product:delete',
            'cup:page:add',
            'cup:page:edit',
            'cup:page:delete',
            'cup:publication:add',
            'cup:publication:edit',
            'cup:publication:delete',
            'cup:publication:category:add',
            'cup:publication:category:edit',
            'cup:publication:category:delete',
        );
    }

    /** @inheritDoc */
    public function after(\Slim\Http\Request $request, \Slim\Http\Response $response, string $routeName): \Slim\Http\Response
    {
        if ($request->isPost()) {
            switch ($routeName) {
                case 'cup:catalog:data:import':
                case 'cup:catalog:category:add':
                case 'cup:catalog:category:edit':
                case 'cup:catalog:category:delete':
                case 'cup:catalog:product:add':
                case 'cup:catalog:product:edit':
                case 'cup:catalog:product:delete':
                    // add task generate GMF
                    $task = new \Plugin\SearchOptimization\Tasks\GMFTask($this->container);
                    $task->execute();

                    // add task generate YML
                    $task = new \Plugin\SearchOptimization\Tasks\YMLTask($this->container);
                    $task->execute();

                    // add task generate SiteMap
                    $task = new \Plugin\SearchOptimization\Tasks\SiteMapTask($this->container);
                    $task->execute();
                    break;

                case 'cup:page:add':
                case 'cup:page:edit':
                case 'cup:page:delete':
                case 'cup:publication:add':
                case 'cup:publication:edit':
                case 'cup:publication:delete':
                case 'cup:publication:category:add':
                case 'cup:publication:category:edit':
                case 'cup:publication:category:delete':
                    // add task generate SiteMap
                    $task = new \Plugin\SearchOptimization\Tasks\SiteMapTask($this->container);
                    $task->execute();
                    break;
            }

            $this->entityManager->flush();

            // run worker
            \App\Domain\Tasks\Task::worker();
        }

        return $response;
    }
}
