<?php

namespace App\Domain\Monitoring\Queries;

/**
 * monitoreo's pagination (D-032, CA-13.25): 10 rows per page, but a list shorter than 20 goes
 * whole on one page (per_page 20). The page is clamped to 1..last, so a page that emptied answers
 * the last one. Clients compute the pages as ceil(total / per_page).
 */
final readonly class ListPage
{
    public int $page;

    public int $perPage;

    public function __construct(int $requested, public int $total)
    {
        $this->perPage = $total < 20 ? 20 : 10;
        $this->page = min(max($requested, 1), max(1, intdiv($total + $this->perPage - 1, $this->perPage)));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** @return array{page: int, per_page: int, total: int} */
    public function meta(): array
    {
        return ['page' => $this->page, 'per_page' => $this->perPage, 'total' => $this->total];
    }
}
