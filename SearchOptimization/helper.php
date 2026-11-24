<?php

/**
 * The site address as the feeds need it: with a scheme and without the trailing slash,
 * whatever was typed into the "common_homepage" parameter ("shop.com/" -> "https://shop.com")
 */
if (!function_exists('seo_site_address')) {
    function seo_site_address(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return rtrim(preg_match('#^https?://#i', $value) ? $value : 'https://' . ltrim($value, '/'), '/');
    }
}

// Содержимое SiteMap конфига по-умолчанию
const DEFAULT_SITEMAP = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ site_address }}</loc>
        <lastmod>{{ df('now', 'Y-m-d') }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.95</priority>
    </url>
    {% for page in pages %}
        <url>
            <loc>{{ site_address }}/{{ page.address }}</loc>
            <lastmod>{{ page.date|df('Y-m-d') }}</lastmod>
            <changefreq>monthly</changefreq>
            <priority>0.55</priority>
        </url>
    {% endfor %}
    <url>
        <loc>{{ site_address }}/guestbook</loc>
        <lastmod>{{ df('now', 'Y-m-d') }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>
    {% for category in publicationCategories %}
        <url>
            <loc>{{ site_address }}/{{ category.address }}</loc>
            {% if category.date %}<lastmod>{{ category.date|df('Y-m-d') }}</lastmod>{% endif %}
            <changefreq>monthly</changefreq>
            <priority>0.5</priority>
        </url>
    {% endfor %}
    {% for publication in publications %}
        <url>
            <loc>{{ site_address }}/{{ publication.address }}</loc>
            <lastmod>{{ publication.date|df('Y-m-d') }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.6</priority>
        </url>
    {% endfor %}
    <url>
        <loc>{{ site_address }}/{{ catalog_address }}</loc>
        <lastmod>{{ df('now', 'Y-m-d') }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.75</priority>
    </url>
    {% for category in catalogCategories %}
        <url>
            <loc>{{ site_address }}/{{ catalog_address }}/{{ category.address }}</loc>
            {% if category.date %}<lastmod>{{ category.date|df('Y-m-d') }}</lastmod>{% endif %}
            <changefreq>weekly</changefreq>
            <priority>0.7</priority>
        </url>
    {% endfor %}
    {% for product in catalogProducts %}
        <url>
            <loc>{{ site_address }}/{{ catalog_address }}/{{ product.address }}</loc>
            <lastmod>{{ product.date|df('Y-m-d') }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.85</priority>
        </url>
    {% endfor %}
</urlset>
EOD;

// Содержимое GMF конфига по-умолчанию
const DEFAULT_GMF = <<<EOD
<?xml version="1.0"?>
<rss xmlns:g="http://base.google.com/ns/1.0">
    <channel>
        <title>{{ shop_title }}</title>
        <link>{{ site_address }}</link>
        <description>{{ shop_description }}</description>
        {% for product in products %}
            <item>
                <g:id>{{ product.id }}</g:id>
                {% if product.group_id %}<g:item_group_id>{{ product.group_id }}</g:item_group_id>{% endif %}
                <g:title>{{ product.title }}{% if product.group_id and product.variant_label %} ({{ product.variant_label }}){% endif %}</g:title>
                <g:description>{{ product.description|striptags }}</g:description>
                <g:link>{{ site_address }}{{ product.url }}</g:link>
    
                {% for url in product.images %}
                    {% if loop.first %}
                        <g:image_link>{{ url }}</g:image_link>
                    {% else %}
                        <g:additional_image_link>{{ url }}</g:additional_image_link>
                    {% endif %}
                {% endfor %}
    
                <g:condition>new</g:condition>
                <g:availability>{{ product.stock > 0 ? 'in stock' : 'out of stock' }}</g:availability>
                <g:price>{{ product.price }} {{ currency }}</g:price>
                <g:brand>{{ product.manufacturer }}</g:brand>
                <g:gtin>{{ product.barcode ? product.barcode : '' }}</g:gtin>
            </item>
        {% endfor %}
    </channel>
</rss>
EOD;

// Содержимое Yandex YML конфига по-умолчанию
const DEFAULT_YANDEX_YML = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<yml_catalog date="{{ df('now', 'Y-m-d H:i') }}">
    <shop>
        <name>{{ shop_title }}</name>
        <company>{{ company_title }}</company>
        <email>{{ email }}</email>
        <url>{{ site_address }}</url>
        <currencies>
            <currency id="{{ currency }}" rate="1"/>
        </currencies>
        <categories>
            {% for category in categories %}
                <category id="{{ category.id }}" {{ category.parent ? ('parentId="' ~ category.parent ~ '"')|raw }}>{{ category.title }}</category>
            {% endfor %}
        </categories>
        {% if delivery_days %}
            <delivery-options>
                <option cost="{{ delivery_cost }}" days="{{ delivery_days }}"/>
            </delivery-options>
        {% endif %}
        <offers>
            {% for product in products %}
                <offer id="{{ product.id }}"{% if product.group_id %} group_id="{{ product.group_id }}"{% endif %}>
                    <name>{{ product.title }}{% if product.group_id and product.variant_label %} ({{ product.variant_label }}){% endif %}</name>
                    <url>{{ site_address }}{{ product.url }}</url>
                    <categoryId>{{ product.category_id }}</categoryId>

                    {% for url in product.images %}
                        <picture>{{ url }}</picture>
                    {% endfor %}
                    
                    <description>{{ product.description }}</description>
                    <price>{{ product.price }}</price>
                    <currencyId>{{ currency }}</currencyId>
                    <vendor>{{ product.manufacturer }}</vendor>
                    <vendorCode>{{ product.vendorcode }}</vendorCode>
                    <barcode>{{ product.barcode }}</barcode>
                    <country_of_origin>{{ product.country }}</country_of_origin>
                    {% if product.dimension.weight %}<weight>{{ product.dimension.weight }}</weight>{% endif %}
                </offer>
            {% endfor %}
        </offers>
    </shop>
</yml_catalog>
EOD;

// Содержимое Hotline конфига по-умолчанию
const DEFAULT_HLI_XML = <<<EOD
<?xml version="1.0" encoding="UTF-8" ?>
<price>
    <date>{{ df('now', 'Y-m-d H:i') }}</date>
    <firmName>{{ company_title }}</firmName>
    <firmId>{{ shop_id }}</firmId>
    <categories>
        {% for category in categories %}
            <category>
                <id>{{ category.id }}</id>
                <parentId>{{ category.parent }}</parentId>
                <name>{{ category.title }}</name>
            </category>
        {% endfor %}
    </categories>
    <items>
        {% for product in products %}
            <item>
                <id>{{ product.id }}</id>
                <categoryId>{{ product.category_id }}</categoryId>
                <code>{{ product.vendorcode }}</code>
                <barcode>{{ product.barcode }}</barcode>
                <vendor>{{ product.manufacturer }}</vendor>
                <name>{{ product.title }}{% if product.group_id and product.variant_label %} ({{ product.variant_label }}){% endif %}</name>
                <description>{{ product.description }}</description>
                <url>{{ site_address }}{{ product.url }}</url>

                {% for url in product.images %}
                    <picture>{{ url }}</picture>
                {% endfor %}
                
                <priceRUAH>{{ product.price }}</priceRUAH>
                <stock>{{ product.stock ? product.stock : 'Під замовлення' }}</stock>
                <param name="Країна виготовлення">{{ product.country }}</param>
            </item>
        {% endfor %}
    </items>
</price> 
EOD;

// Содержимое robots.txt по-умолчанию
const DEFAULT_ROBOTS = <<<EOD
User-agent: *
Host: {{ site_address }}
Disallow: /cart/
Disallow: /search/
Disallow: /cup/
Sitemap: {{ site_address }}/xml/sitemap
EOD;
