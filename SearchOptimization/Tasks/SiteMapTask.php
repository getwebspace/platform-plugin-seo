<?php

namespace Plugin\SearchOptimization\Tasks;

use App\Domain\Tasks\Task;
use samdark\sitemap\Sitemap;

class SiteMapTask extends Task
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

    protected function action(array $args = [])
    {
        /**
         * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $pageRepository
         * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $publicationRepository
         * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $categoryRepository
         * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $productRepository
         */
        $pageRepository = $this->entityManager->getRepository(\App\Domain\Entities\Page::class);
        $publicationRepository = $this->entityManager->getRepository(\App\Domain\Entities\Publication::class);
        $publicationCategoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Publication\Category::class);
        $categoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class);
        $productRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);
        $data = [
            'page' => collect($pageRepository->findAll()),
            'publication' => collect($publicationRepository->findAll()),
            'publicationCategory' => collect($publicationCategoryRepository->findAll()),
            'category' => collect($categoryRepository->findBy(['status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK])),
            'product' => collect($productRepository->findBy(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK])),
        ];

        $url = $this->getParameter('common_homepage', '');
        $frequency = $this->getParameter('SearchOptimizationPlugin_frequency', Sitemap::WEEKLY);

        // create sitemap
        $sitemap = new Sitemap(XML_DIR . '/sitemap.xml');
        $sitemap->setUseIndent(true);

        // main page
        $sitemap->addItem($url, time(), $frequency, 0.5);

        // other pages
        foreach ($data['page'] as $model) {
            /** @var \App\Domain\Entities\Page $model */
            $sitemap->addItem($url . trim($model->address), $model->date->getTimestamp(), $frequency, 0.3);
        }

        // publications category
        foreach ($data['publicationCategory'] as $model) {
            /** @var \App\Domain\Entities\Publication\Category $model */
            $sitemap->addItem($url . trim($model->address), time(), $frequency, 0.3);
        }

        // publications
        foreach ($data['publication'] as $model) {
            /** @var \App\Domain\Entities\Publication $model */
            $sitemap->addItem($url . trim($model->address), $model->date->getTimestamp(), $frequency, 0.3);
        }

        if ($this->getParameter('catalog_is_enabled', 'no') === 'yes') {
            // main catalog
            $catalogPath = $url . $this->getParameter('catalog_address', 'catalog');
            $sitemap->addItem(trim($catalogPath), time(), $frequency, 0.4);

            // catalog category
            foreach ($data['category'] as $model) {
                /** @var \App\Domain\Entities\Catalog\Category $model */
                $sitemap->addItem($catalogPath . '/' . trim($model->address), time(), $frequency, 0.5);
            }

            // catalog products
            foreach ($data['product'] as $model) {
                $sitemap->addItem($catalogPath . '/' . trim($model->address), $model->date->getTimestamp(), $frequency, 0.7);
            }
        }

        $sitemap->write();

        $this->setStatusDone();
    }
}
