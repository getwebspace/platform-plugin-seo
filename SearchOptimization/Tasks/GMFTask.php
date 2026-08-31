<?php declare(strict_types=1);

namespace Plugin\SearchOptimization\Tasks;

class GMFTask extends AbstractFeedTask
{
    public const TITLE = 'Generate Google XML';

    protected const TEMPLATE_PARAM = 'SearchOptimizationPlugin_gmf_txt';

    protected const DEFAULT_TEMPLATE = 'DEFAULT_GMF';

    protected const OUTPUT_FILE = 'gmf.xml';

    protected const DONE_CHANNEL = 'task:seo:gmf';

    protected function feedData(): array
    {
        return [
            'shop_description' => $this->parameter('SearchOptimizationPlugin_shop_description', ''),
        ];
    }
}
