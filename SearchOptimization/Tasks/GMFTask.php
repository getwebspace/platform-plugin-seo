<?php declare(strict_types=1);

namespace Plugin\SearchOptimization\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductService;
use Vitalybaev\GoogleMerchant\Feed;
use Vitalybaev\GoogleMerchant\Product;
use Vitalybaev\GoogleMerchant\Product\Availability\Availability;

class GMFTask extends AbstractTask
{
    public const TITLE = 'Генерация GMF файла';

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
        $homepage = rtrim($this->parameter('common_homepage', ''), '/');
        $catalog = $homepage . '/' . $this->parameter('catalog_address', 'catalog') . '/';

        $categoryService = CategoryService::getWithContainer($this->container);
        $productService = ProductService::getWithContainer($this->container);
        $data = [
            'category' => $categoryService->read(['status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]),
            'product' => $productService->read(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]),
        ];

        $feed = new Feed(
            $this->parameter('integration_merchant_shop_title', ''),
            $this->parameter('common_homepage', ''),
            $this->parameter('integration_merchant_shop_description', '')
        );

        // put products to the feed ($products - some data from database for example)
        foreach ($data['product'] as $model) {
            /** @var \App\Domain\Entities\Catalog\Category $category */
            /** @var \App\Domain\Entities\Catalog\Product $model */
            $category = $data['category']->firstWhere('uuid', $model->getCategory());

            $item = new Product();

            // set common product properties
            $item->setId($this->getCrc32($model->getUuid()));
            $item->setTitle($model->getTitle());
            $item->setDescription($model->getDescription());
            $item->setLink($catalog . $model->getAddress());
            if ($model->hasFiles()) {
                $item->setImage($homepage . $model->getFiles()->first()->getPublicPath());
            }
            if ($model->getStock()) {
                $item->setAvailability(Availability::IN_STOCK);
            } else {
                $item->setAvailability(Availability::OUT_OF_STOCK);
            }
            $item->setPrice("{$model->getPrice()} RUB");
            if ($category) {
                $item->setGoogleCategory($category->getTitle());
            }
            $item->setBrand($model->getManufacturer());
            $item->setGtin($model->getBarCode());
            $item->setCondition('new');

            // add this product to the feed
            $feed->addProduct($item);
        }

        file_put_contents(XML_DIR . '/gmf.xml', $feed->build());

        $this->setStatusDone();
    }

    protected function getCrc32(\Ramsey\Uuid\Uuid $uuid)
    {
        if ($uuid->toString() !== \Ramsey\Uuid\Uuid::NIL) {
            return crc32($uuid->getHex());
        }

        return null;
    }
}
