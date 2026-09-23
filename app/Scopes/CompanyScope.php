<?php

namespace App\Scopes;

use App\Constants\RoleConstants;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (!$user) {
            return;
        }

        if (($user->role ?? '') === RoleConstants::SUPER_ADMIN) {
            return;
        }

        $companyId = $user->company_id ?? null;
        if ($companyId === null || $companyId === '') {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $companyId);
    }
}
