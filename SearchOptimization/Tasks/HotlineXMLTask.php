<?php declare(strict_types=1);

namespace Plugin\SearchOptimization\Tasks;

class HotlineXMLTask extends AbstractFeedTask
{
    public const TITLE = 'Generate Hotline XML';

    protected const TEMPLATE_PARAM = 'SearchOptimizationPlugin_htl_txt';

    protected const DEFAULT_TEMPLATE = 'DEFAULT_HLI_XML';

    protected const OUTPUT_FILE = 'htl.xml';

    protected const DONE_CHANNEL = 'task:seo:htl';

    protected function feedData(): array
    {
        return [
            'company_title' => $this->parameter('SearchOptimizationPlugin_company_title', ''),
        ];
    }
}
