<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Spreadsheets;

use App\Services\Spreadsheets\EnterpriseSpreadsheetService;
use Illuminate\Foundation\Http\FormRequest;

class ImportSpreadsheetApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $blueprint = (string) $this->route('blueprint');
        $service = app(EnterpriseSpreadsheetService::class);
        $blueprints = $service->getImportBlueprints();

        if (! isset($blueprints[$blueprint])) {
            return true;
        }

        return $user->can($service->importGate($blueprint));
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ];
    }
}
