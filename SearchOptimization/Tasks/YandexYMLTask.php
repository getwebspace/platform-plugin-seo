<?php declare(strict_types=1);

namespace Plugin\SearchOptimization\Tasks;

class YandexYMLTask extends AbstractFeedTask
{
    public const TITLE = 'Generate Yandex YML';

    protected const TEMPLATE_PARAM = 'SearchOptimizationPlugin_yml_txt';

    protected const DEFAULT_TEMPLATE = 'DEFAULT_YANDEX_YML';

    protected const OUTPUT_FILE = 'yml.xml';

    protected const DONE_CHANNEL = 'task:seo:yml';

    protected function feedData(): array
    {
        return [
            'company_title' => $this->parameter('SearchOptimizationPlugin_company_title', ''),
        ];
    }
}
