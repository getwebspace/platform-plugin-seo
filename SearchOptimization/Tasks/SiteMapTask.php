<?php declare(strict_types=1);

namespace Plugin\SearchOptimization\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use samdark\sitemap\Sitemap;

class SiteMapTask extends AbstractTask
{
    public const TITLE = 'Генерация карты сайта';

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
        $pageService = PageService::getWithContainer($this->container);
        $publicationService = PublicationService::getWithContainer($this->container);
        $publicationCategoryService = PublicationCategoryService::getWithContainer($this->container);
        $categoryService = CategoryService::getWithContainer($this->container);
        $productService = ProductService::getWithContainer($this->container);
        $data = [
            'page' => $pageService->read(),
            'publication' => $publicationService->read(),
            'publicationCategory' => $publicationCategoryService->read(),
            'category' => $categoryService->read(['status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]),
            'product' => $productService->read(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]),
        ];

        $url = $this->parameter('common_homepage', '');
        $frequency = $this->parameter('SearchOptimizationPlugin_frequency', Sitemap::WEEKLY);

        // create sitemap
        $sitemap = new Sitemap(XML_DIR . '/sitemap.xml');
        $sitemap->setUseIndent(true);

        // main page
        $sitemap->addItem($url, time(), $frequency, 0.5);

        // other pages
        foreach ($data['page'] as $model) {
            /** @var \App\Domain\Entities\Page $model */
            $sitemap->addItem($url . $model->getAddress(), $model->getDate()->getTimestamp(), $frequency, 0.3);
        }

        // publications category
        foreach ($data['publicationCategory'] as $model) {
            /** @var \App\Domain\Entities\Publication\Category $model */
            $sitemap->addItem($url . $model->getAddress(), time(), $frequency, 0.3);
        }

        // publications
        foreach ($data['publication'] as $model) {
            /** @var \App\Domain\Entities\Publication $model */
            $sitemap->addItem($url . $model->getAddress(), $model->getDate()->getTimestamp(), $frequency, 0.3);
        }

        if ($this->parameter('catalog_is_enabled', 'no') === 'yes') {
            // main catalog
            $catalogPath = $url . $this->parameter('catalog_address', 'catalog');
            $sitemap->addItem($catalogPath, time(), $frequency, 0.4);

            // catalog category
            foreach ($data['category'] as $model) {
                /** @var \App\Domain\Entities\Catalog\Category $model */
                $sitemap->addItem($catalogPath . '/' . $model->getAddress(), time(), $frequency, 0.5);
            }

            // catalog products
            foreach ($data['product'] as $model) {
                $sitemap->addItem($catalogPath . '/' . $model->getAddress(), $model->getDate()->getTimestamp(), $frequency, 0.7);
            }
        }

        $sitemap->write();

        $this->setStatusDone();
    }
}
