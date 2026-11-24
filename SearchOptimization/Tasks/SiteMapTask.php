<?php declare(strict_types=1);

namespace Plugin\SearchOptimization\Tasks;

use App\Domain\AbstractTask;

include_once PLUGIN_DIR . '/SearchOptimization/helper.php';

class SiteMapTask extends AbstractTask
{
    public const TITLE = 'Generate SiteMap';

    protected function action(array $args = []): void
    {
        $pageService = $this->container->get(\App\Domain\Service\Page\PageService::class);
        $publicationService = $this->container->get(\App\Domain\Service\Publication\PublicationService::class);
        $publicationCategoryService = $this->container->get(\App\Domain\Service\Publication\CategoryService::class);
        $categoryService = $this->container->get(\App\Domain\Service\Catalog\CategoryService::class);
        $productService = $this->container->get(\App\Domain\Service\Catalog\ProductService::class);

        // what the site shows: hidden categories and their products stay out
        $catalogCategories = $categoryService->read(['status' => \App\Domain\Casts\Catalog\Status::WORK, 'is_hidden' => false]);

        $template = trim((string) $this->parameter('SearchOptimizationPlugin_sitemap_txt', ''));
        $data = [
            'site_address' => seo_site_address($this->parameter('common_homepage', '')),
            'catalog_address' => $this->parameter('catalog_address', 'catalog'),
            'pages' => $pageService->read(),
            'publications' => $publicationService->read(),
            'publicationCategories' => $publicationCategoryService->read(),
            'catalogCategories' => $catalogCategories,
            // one line per page: variants of a product share its address, and so its page
            'catalogProducts' => $productService
                ->read(['status' => \App\Domain\Casts\Catalog\Status::WORK])
                ->filter(fn ($product) => $catalogCategories->contains('uuid', $product->category_uuid))
                ->unique('address')
                ->values(),
        ];

        $renderer = $this->container->get('view');
        $content = $renderer->fetchFromString($template !== '' ? $template : DEFAULT_SITEMAP, $data);

        $dir = XML_DIR ?: (VAR_DIR . '/xml');
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        $target = $dir . '/sitemap.xml';
        $tmp = $target . '.' . uniqid('', true) . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            @unlink($tmp);
            $this->setStatusFail('Unable to write sitemap.xml');

            return;
        }
        rename($tmp, $target);

        $this->container->get(\App\Application\PubSub::class)->publish('task:seo:sitemap');
        $this->setStatusDone();
    }
}
