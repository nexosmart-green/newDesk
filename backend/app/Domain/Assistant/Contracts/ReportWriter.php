<?php

namespace App\Domain\Assistant\Contracts;

use App\Domain\Assistant\Support\ReportTable;

/** Turns a ReportTable into a file's bytes. Adapters: app/Infrastructure/Reports (xlsx, pdf). */
interface ReportWriter
{
    public function format(): string;

    public function write(ReportTable $table): string;
}
