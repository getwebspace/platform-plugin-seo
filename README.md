SearchOptimization для WebSpace Engine
====
_(Плагин)_

Плагин поисковой оптимизации, генерирует XML файлы: SiteMap, Google Merchant Feed, Yandex Market

#### Установка
Поместить в папку `plugin` и подключить в `index.php` добавив строку:
```php
// clearcache plugin
$plugins->register(new \Plugin\SearchOptimization\SearchOptimizationPlugin($container));
```

#### License
Licensed under the MIT license. See [License File](LICENSE.md) for more information.
