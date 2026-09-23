<?php

namespace App\Models\Concerns;

use App\Scopes\CompanyScope;

trait HasCompanyScope
{
    protected static function bootHasCompanyScope(): void
    {
        static::addGlobalScope(new CompanyScope);
    }
}
