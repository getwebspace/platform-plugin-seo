<?php declare(strict_types=1);

namespace Plugin\SearchOptimization\Tasks;

use App\Domain\AbstractTask;

class SiteMapTask extends AbstractTask
{
    public const TITLE = 'Generate SiteMap';

    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            // nothing
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    protected function action(array $args = []): void
    {
        $pageService = $this->container->get(\App\Domain\Service\Page\PageService::class);
        $publicationService = $this->container->get(\App\Domain\Service\Publication\PublicationService::class);
        $publicationCategoryService = $this->container->get(\App\Domain\Service\Publication\CategoryService::class);
        $categoryService = $this->container->get(\App\Domain\Service\Catalog\CategoryService::class);
        $productService = $this->container->get(\App\Domain\Service\Catalog\ProductService::class);

        $template = $this->parameter('SearchOptimizationPlugin_sitemap_txt', '');
        $data = [
            'site_address' => rtrim($this->parameter('common_homepage', ''), '/'),
            'catalog_address' => $this->parameter('catalog_address', 'catalog'),
            'pages' => $pageService->read(),
            'publications' => $publicationService->read(),
            'publicationCategories' => $publicationCategoryService->read(),
            'catalogCategories' => $categoryService->read(['status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]),
            'catalogProducts' => $productService->read(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]),
        ];

        $renderer = $this->container->get('view');
        file_put_contents(XML_DIR . '/sitemap.xml', $renderer->fetchFromString(trim($template) ? $template : DEFAULT_SITEMAP, $data));

        $this->container->get(\App\Application\PubSub::class)->publish('task:seo:sitemap');
        $this->setStatusDone();
    }
}
