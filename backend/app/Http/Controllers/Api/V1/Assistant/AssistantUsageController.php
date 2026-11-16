<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Domain\Assistant\Enums\AssistantModel;
use App\Domain\Assistant\Queries\AssistantMonthlySpend;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** RF-13.10: Configuración → Asistente shows what the assistant used this month and the models to choose from. */
class AssistantUsageController extends Controller
{
    public function __invoke(Request $request, AssistantMonthlySpend $spend): JsonResponse
    {
        $company = $request->user()->company;

        return ApiResponse::ok([
            'month_spent_usd' => round($spend($company), 2),
            'monthly_budget_usd' => (float) $company->setting('assistant_monthly_budget_usd'),
            'models' => array_map(fn (AssistantModel $model) => ['value' => $model->value, 'label' => $model->label()], AssistantModel::cases()),
        ]);
    }
}
