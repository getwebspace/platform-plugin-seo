<?php declare(strict_types=1);

namespace Plugin\SearchOptimization\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductService;
use Bukashk0zzz\YmlGenerator\Generator;
use Bukashk0zzz\YmlGenerator\Model\Category;
use Bukashk0zzz\YmlGenerator\Model\Currency;
use Bukashk0zzz\YmlGenerator\Model\Delivery;
use Bukashk0zzz\YmlGenerator\Model\Offer\OfferSimple;
use Bukashk0zzz\YmlGenerator\Model\ShopInfo;
use Bukashk0zzz\YmlGenerator\Settings;
use Illuminate\Support\Collection;

class YMLTask extends AbstractTask
{
    public const TITLE = 'Генерация YML файла';

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
        $categoryService = CategoryService::getWithContainer($this->container);
        $productService = ProductService::getWithContainer($this->container);
        $data = [
            'category' => $categoryService->read(['status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]),
            'product' => $productService->read(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]),
        ];

        $settings = new Settings();
        $settings
            ->setOutputFile(XML_DIR . '/yml.xml')
            ->setEncoding('UTF-8');

        $shopInfo = new ShopInfo();
        $shopInfo
            ->setName($this->parameter('integration_merchant_shop_title', ''))
            ->setCompany($this->parameter('integration_merchant_company_title', ''))
            ->setUrl($this->parameter('common_homepage', ''))
            ->setEmail($this->parameter('smtp_from', null))
            ->setPlatform('WebSpace Engine CMS');

        $currencies = [];
        $currencies[] = (new Currency())->setId($this->parameter('integration_merchant_currency', 'RUB'))->setRate(1);

        $categories = [];
        foreach ($this->prepareCategory($data['category']->sortBy('title')) as $index => $item) {
            $categories[$item['id']] = (new Category())
                ->setId($item['id'])
                ->setParentId($item['parent'])
                ->setName($item['title']);
        }

        $offers = [];
        foreach ($this->prepareProduct($data['product']) as $index => $model) {
            /**
             * @var \App\Domain\Entities\Catalog\Category $category
             * @var \App\Domain\Entities\Catalog\Product  $model
             */
            $category = $data['category']->firstWhere('uuid', $model->getCategory());

            $homepage = rtrim($this->parameter('common_homepage', ''), '/');
            $url = $homepage . '/' . $this->parameter('catalog_address', 'catalog') . '/' . $model->getAddress();
            $pictures = [];

            foreach ($model->hasFiles() ? $model->getFiles() : ($category && $category->hasFiles() ? $category->getFiles() : []) as $file) {
                /** @var \App\Domain\Entities\File $file */
                $pictures[] = $homepage . $file->getPublicPath();
            }

            $offers[$model->buf] = (new OfferSimple())
                ->setId($model->buf)
                ->setVendor($model->getManufacturer() ? $model->getManufacturer() : null)
                ->setVendorCode($model->getVendorCode() ? $model->getVendorCode() : null)
                ->setAvailable((bool) $model->getStock())
                ->setUrl($url)
                ->setPrice($model->getPrice())
                ->setCurrencyId($this->parameter('integration_merchant_currency', 'RUB'))
                ->setCategoryId($category->buf)
                ->setName($model->getTitle())
                ->setDescription(
                    trim(
                        strip_tags(
                            $model->getDescription() ?
                                $model->getDescription() :
                                (
                                    $model->getExtra() ?
                                        $model->getExtra() :
                                        $model->getTitle()
                                )
                        )
                    )
                )
                ->setPictures($pictures);
        }

        $deliveries = [];
        $deliveries[] = (new Delivery())
            ->setCost($this->parameter('integration_merchant_delivery_cost', '0'))
            ->setDays($this->parameter('integration_merchant_delivery_days', '0'));

        (new Generator($settings))->generate($shopInfo, $currencies, $categories, $offers, $deliveries);

        $this->setStatusDone();
    }

    protected $indexCategory = 0;

    protected function prepareCategory(Collection &$categories, $parent = \Ramsey\Uuid\Uuid::NIL)
    {
        $result = [];

        foreach ($categories->where('parent', $parent) as $model) {
            /** @var \App\Domain\Entities\Catalog\Category $model */
            $result[] = [
                'id' => $model->buf = ++$this->indexCategory,
                'parent' => $categories->firstWhere('uuid', $model->getParent())->buf ?? null,
                'title' => $model->getTitle(),
            ];

            $result = array_merge($result, $this->prepareCategory($categories, $model->getUuid()));
        }

        return $result;
    }

    protected $indexProduct = 0;

    protected function prepareProduct(Collection $products)
    {
        foreach ($products as $model) {
            /** @var \App\Domain\Entities\Catalog\Product $model */
            $model->buf = ++$this->indexProduct;
        }

        return $products;
    }
}
