<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected function resolveCompanyId(Request $request): int
    {
        $userCompanyId = (int) ($request->user()->company_id ?? 0);

        if ($userCompanyId === 0) {
            return (int) ($request->input('company_id') ?: 1);
        }

        return $userCompanyId;
    }
}
