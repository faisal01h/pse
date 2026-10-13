<?php

namespace App\Catalog;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PageMeta
{
    /**
     * @return array{current: int, last: int, prev: ?int, next: ?int}
     */
    public static function from(LengthAwarePaginator $page): array
    {
        $current = $page->currentPage();

        return [
            'current' => $current,
            'last' => max(1, $page->lastPage()),
            'prev' => $current > 1 ? $current - 1 : null,
            'next' => $page->hasMorePages() ? $current + 1 : null,
        ];
    }

    /**
     * @return array{current: int, last: int, prev: ?int, next: ?int}
     */
    public static function empty(): array
    {
        return [
            'current' => 1,
            'last' => 1,
            'prev' => null,
            'next' => null,
        ];
    }
}
