<?php

namespace App\Support;

use App\Enums\Gender;
use Illuminate\Support\HtmlString;

/** Compact gender symbol for tables and detail pages (the label stays available as a tooltip). */
class GenderIcon
{
    public static function html(Gender|string|null $gender): HtmlString
    {
        $gender = $gender instanceof Gender ? $gender : Gender::tryFrom((string) $gender);

        if (! $gender) {
            return new HtmlString('<span aria-hidden="true">-</span>');
        }

        [$symbol, $color] = $gender === Gender::Male ? ['♂', '#2563eb'] : ['♀', '#db2777'];

        return new HtmlString(sprintf(
            '<span role="img" aria-label="%s" style="font-size:1.5rem;line-height:1;font-weight:700;color:%s">%s</span>',
            e($gender->label()), $color, $symbol
        ));
    }
}
