SearchOptimization for WebSpace Engine
====
_(Plugin)_

Search engine optimization plugin, generates XML files: SiteMap, Google Merchant Feed, Yandex Market

#### Install
Put in `plugin` folder and setup in `index.php` file:
```php
// searchengine plugin
$plugins->register(\Plugin\SearchOptimization\SearchOptimizationPlugin::class);
```

#### Template variables (feeds: GMF, Yandex YML, Hotline)
`shop_title`, `shop_description`, `company_title`, `site_address` (with a scheme, no trailing slash), `email`, `shop_id`,
`currency`, `catalog_address`, `delivery_cost`, `delivery_days`, `categories`, `products`.

Of a product also `url` (the path of its page, `?v=` for a variant, put it after `site_address`), `group_id` (the same number for the variants of one product, empty for the others; it follows the address, so it stays when a variant is removed) and `variant_label`. The default Yandex YML and Google templates use them (`group_id`, `item_group_id`) and name a variant `Title (label)`.

Of a category: `id`, `parent` (the id of the parent), `title`, `description`. Of a product, besides its catalog fields:
`id`, `category_id` and `images` (absolute addresses of its pictures, those of its category when it has none).
The ids are derived from the uuid, so they stay the same when something is added or removed.
Hidden categories and the products in them are not in the feeds or in the SiteMap; products that share an address
(variants) make one line of the SiteMap.

#### Upgrading to 10.0.0
- The ids of categories and offers in the feeds change once (they were the position in the list).
- A custom template that looked a category up with `categories.firstWhere('uuid', product.category)` must use
  `product.category_id`, and `file.path.middle` of `product.files` must become the `product.images` list.

#### License
Licensed under the MIT license. See [License File](LICENSE.md) for more information.
