<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Spreadsheets;

use App\Services\Spreadsheets\EnterpriseSpreadsheetService;
use Illuminate\Foundation\Http\FormRequest;

class QueueSpreadsheetExportApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $blueprint = (string) $this->route('blueprint');
        $service = app(EnterpriseSpreadsheetService::class);
        $blueprints = $service->getExportBlueprints();

        if (! isset($blueprints[$blueprint])) {
            return true;
        }

        return $user->can($service->exportGate($blueprint));
    }

    public function rules(): array
    {
        return [
            'disk' => ['nullable', 'in:local'],
            'format' => ['nullable', 'string', 'in:xlsx,csv'],
        ];
    }
}
