<?php

namespace App\Services\Maintenance;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Photos attached to a maintenance report or one of its updates. */
class MaintenancePhotos
{
    /**
     * Store the valid uploads on the public disk under a random name.
     *
     * @param  array<int, UploadedFile>|null  $files
     * @return list<array{name: string, path: string, url: string, size: int|false}>
     */
    public function store(?array $files): array
    {
        $stored = [];

        foreach ($files ?? [] as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $path = $file->storeAs('maintenance/photos', Str::random(24).'.'.$file->getClientOriginalExtension(), 'public');
            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'url' => Storage::url($path),
                'size' => $file->getSize(),
            ];
        }

        return $stored;
    }
}
