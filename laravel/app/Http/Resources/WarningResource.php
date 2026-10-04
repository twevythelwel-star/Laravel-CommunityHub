<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Warning;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Warning
 */
class WarningResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'authorName' => $this->author_name,
            'issuedAt' => $this->issued_at?->toIso8601String(),
            'responsesCount' => $this->whenCounted('responses'),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
                'role' => $this->author?->role?->value,
            ]),
        ];
    }
}
