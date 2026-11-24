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

        // a hidden category takes its branch and its products with it, as on the site
        $categories = $categoryService->read(['status' => CatalogStatus::WORK, 'is_hidden' => false]);
        $products = $productService->read(['status' => CatalogStatus::WORK]);

        $data = $this->commonData();
        $preparedCategories = $this->prepareCategory($categories->sortBy('title'));
        $data = array_merge($data, $this->feedData(), [
            'categories' => collect($preparedCategories),
            'products' => collect($this->prepareProduct($products, $categories)),
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
            'site_address' => seo_site_address($this->parameter('common_homepage', '')),
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

    /**
     * @var array<string, int> category uuid => the id it has in the feeds
     */
    protected array $categoryIds = [];

    /**
     * A number that belongs to the record for good: a feed id that is the position in a list
     * moves every time something is added or removed, and the marketplaces take that for
     * another offer. Derived from the uuid; a clash takes the next free number.
     */
    protected function categoryId(string $uuid): int
    {
        $id = hexdec(substr(sha1($uuid), 0, 7)) ?: 1;

        while (in_array($id, $this->categoryIds, true)) {
            ++$id;
        }

        return $this->categoryIds[$uuid] = $id;
    }

    /**
     * Same idea for an offer: 16 characters of [0-9a-f], within the 20 of [a-zA-Z0-9] that
     * Yandex Market allows
     */
    protected function productId(string $uuid): string
    {
        return substr(sha1($uuid), 0, 16);
    }

    /**
     * Depth first walk of the category tree that rewrites the uuid based
     * relations into the integer ids the feed formats expect.
     */
    protected function prepareCategory(Collection $categories, ?string $parent = null): array
    {
        $result = [];

        foreach ($categories->where('parent_uuid', $parent) as $model) {
            /** @var \App\Domain\Models\CatalogCategory $model */
            $item = $model->toArray();
            $item['parent'] = $item['parent_uuid'] = $parent !== null ? ($this->categoryIds[$parent] ?? null) : null;
            $item['description'] = str_replace('&nbsp;', '', strip_tags((string) $model->description));
            $item['id'] = $item['buf'] = $this->categoryId((string) $model->uuid);

            $result[] = $item;
            $result = array_merge($result, $this->prepareCategory($categories, (string) $model->uuid));
        }

        return $result;
    }

    /**
     * Besides the fields of the product: `id` (stable), `url` (with `?v=` for a variant),
     * `group_id` (shared by the variants, null for the others), `variant_label`, `category_id`
     * (the id of its category in the feed) and `images`, absolute addresses of its pictures (those of its category when it
     * has none). Products of a category that is not in the feed are left out.
     */
    protected function prepareProduct(Collection $products, Collection $categories): array
    {
        $result = [];
        $site = seo_site_address($this->parameter('common_homepage', ''));
        $byUuid = $categories->keyBy('uuid');

        $pictures = static function ($model) use ($site): array {
            $urls = [];

            foreach ($model->files as $file) {
                if (str_starts_with((string) $file->type, 'image/')) {
                    $urls[] = $site . $file->public_path('middle');
                }
            }

            return $urls;
        };

        foreach ($products as $model) {
            /** @var \App\Domain\Models\CatalogProduct $model */
            $category = (string) $model->category_uuid;

            if (!isset($this->categoryIds[$category])) {
                continue;
            }

            $item = $model->toArray();
            $item['description'] = str_replace('&nbsp;', '', strip_tags((string) $model->description));
            $item['id'] = $item['buf'] = $this->productId((string) $model->uuid);
            $item['category_id'] = $this->categoryIds[$category];
            // variants (products of one address) form a group in the feeds: a number of at most 9
            // digits that follows the address, so it does not change when one of them is removed
            $item['group_id'] = $model->has_variants() ? hexdec(substr(sha1($model->canonical_url()), 0, 7)) : null;
            $item['images'] = $pictures($model) ?: $pictures($byUuid[$category]);

            $result[] = $item;
        }

        return $result;
    }
}
