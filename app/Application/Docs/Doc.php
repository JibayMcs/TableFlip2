<?php

declare(strict_types=1);

namespace App\Application\Docs;

/**
 * One rendered documentation page.
 *
 * `html` is the body. `toc` is a separate HTML fragment with the heading
 * outline (rendered side-by-side in the layout). Both are already
 * sanitised by CommonMark — safe to drop straight into Blade.
 */
final class Doc
{
    /**
     * @param  array{title?: string, order?: int, description?: string}  $meta
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $html,
        public readonly string $toc,
        public readonly array $meta = [],
    ) {
    }
}
