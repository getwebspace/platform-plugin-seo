<?php declare(strict_types=1);

namespace Plugin\SearchOptimization\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductService;
use Illuminate\Support\Collection;

include_once PLUGIN_DIR . '/SearchOptimization/helper.php';

/**
 * Shared machinery for the catalog based feeds (GMF, Yandex YML, Hotline XML).
 *
 * Every concrete feed only differs in four things — the parameter that stores
 * its custom Twig template, the fallback template constant, the output file
 * name and the PubSub channel it announces — plus an optional handful of
 * feed specific template variables. Everything else (loading the catalog,
 * flattening the category tree into sequential ids, rendering, writing the
 * file atomically) lives here.
 */
abstract class AbstractFeedTask extends AbstractTask
{
    /** Parameter key holding the user defined Twig template. */
    protected const TEMPLATE_PARAM = '';

    /** Name of the fallback template constant defined in helper.php. */
    protected const DEFAULT_TEMPLATE = '';

    /** File name written inside XML_DIR. */
    protected const OUTPUT_FILE = '';

    /** Channel published once the file has been written. */
    protected const DONE_CHANNEL = '';

    protected function action(array $args = []): void
    {
        /** @var CategoryService $categoryService */
        $categoryService = $this->container->get(CategoryService::class);
        /** @var ProductService $productService */
        $productService = $this->container->get(ProductService::class);

        $categories = $categoryService->read(['status' => CatalogStatus::WORK]);
        $products = $productService->read(['status' => CatalogStatus::WORK]);

        $data = array_merge($this->commonData(), $this->feedData(), [
            'categories' => collect($this->prepareCategory($categories->sortBy('title'))),
            'products' => collect($this->prepareProduct($products)),
        ]);

        $template = trim((string) $this->parameter(static::TEMPLATE_PARAM, ''));
        $template = $template !== '' ? $template : (string) constant(static::DEFAULT_TEMPLATE);

        $renderer = $this->container->get('view');

        if (!$this->write(static::OUTPUT_FILE, $renderer->fetchFromString($template, $data))) {
            $this->setStatusFail('Unable to write ' . static::OUTPUT_FILE);

            return;
        }

        $this->container->get(\App\Application\PubSub::class)->publish(static::DONE_CHANNEL);
        $this->setStatusDone();
    }

    /**
     * Template variables shared by every feed.
     */
    protected function commonData(): array
    {
        return [
            'shop_title' => $this->parameter('SearchOptimizationPlugin_shop_title', ''),
            'site_address' => rtrim($this->parameter('common_homepage', ''), '/'),
            'catalog_address' => '/' . $this->parameter('catalog_address', 'catalog'),
            'email' => $this->parameter('mail_from', ''),
            'shop_id' => $this->parameter('SearchOptimizationPlugin_shop_id', ''),
            'currency' => $this->parameter('SearchOptimizationPlugin_currency', ''),
            'delivery_cost' => $this->parameter('SearchOptimizationPlugin_delivery_cost', ''),
            'delivery_days' => $this->parameter('SearchOptimizationPlugin_delivery_days', ''),
        ];
    }

    /**
     * Feed specific template variables, merged on top of {@see commonData()}.
     */
    protected function feedData(): array
    {
        return [];
    }

    /**
     * Write the rendered feed into XML_DIR without ever exposing a
     * half written file to a crawler: render to a sibling temp file first,
     * then rename() it into place (atomic on the same filesystem).
     */
    protected function write(string $file, string $content): bool
    {
        $dir = XML_DIR ?: (VAR_DIR . '/xml');

        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        $target = $dir . '/' . $file;
        $tmp = $target . '.' . uniqid('', true) . '.tmp';

        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            @unlink($tmp);

            return false;
        }

        return rename($tmp, $target);
    }

    protected int $indexCategory = 0;

    /**
     * Depth first walk of the category tree that rewrites the uuid based
     * relations into the sequential integer ids the feed formats expect.
     */
    protected function prepareCategory(Collection $categories, ?string $parent = null): array
    {
        $result = [];

        foreach ($categories->where('parent_uuid', $parent) as $model) {
            /** @var \App\Domain\Models\Catalog\Category $model */
            $item = $model->toArray();
            $item['parent'] = $item['parent_uuid'] = $categories->firstWhere('uuid', $parent)->buf ?? null;
            $item['description'] = str_replace('&nbsp;', '', strip_tags((string) $model->description));
            $model->buf = $item['id'] = $item['buf'] = ++$this->indexCategory;

            $result[] = $item;
            $result = array_merge($result, $this->prepareCategory($categories, $model->uuid));
        }

        return $result;
    }

    protected int $indexProduct = 0;

    protected function prepareProduct(Collection $products): array
    {
        $result = [];

        foreach ($products as $model) {
            /** @var \App\Domain\Models\Catalog\Product $model */
            $item = $model->toArray();
            $item['description'] = str_replace('&nbsp;', '', strip_tags((string) $model->description));
            $model->buf = $item['id'] = $item['buf'] = ++$this->indexProduct;

            $result[] = $item;
        }

        return $result;
    }
}
