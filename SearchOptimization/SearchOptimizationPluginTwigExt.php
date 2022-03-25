<?php declare(strict_types=1);

namespace Plugin\SearchOptimization;

use App\Domain\AbstractExtension;
use Twig\TwigFilter;

class SearchOptimizationPluginTwigExt extends AbstractExtension
{
    public function getName()
    {
        return 'so_plugin';
    }

    public function getFilters()
    {
        return [
            new TwigFilter('srm_tags', [$this, 'srm_tags']),
        ];
    }

    public function srm_tags($string)
    {
        if (!is_string($string)) {
            return $string;
        }

        $string = preg_replace ('/<[^>]*>/', ' ', $string);
        $string = str_replace("\r", '', $string);    // --- replace with empty space
        $string = str_replace("\n", ' ', $string);   // --- replace with space
        $string = str_replace("\t", ' ', $string);   // --- replace with space

        return trim(preg_replace('/ {2,}/', ' ', $string));
    }
}
