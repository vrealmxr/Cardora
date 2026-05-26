<?php

namespace App\Http\Controllers\Api;

use App\Services\ImageUploadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $request, ImageUploadService $imageUploadService)
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'max:12288', 'mimes:jpg,jpeg,png,webp,gif,pdf'],
            'collection' => ['nullable', 'string', 'max:50'],
        ]);

        $collection = $validated['collection'] ?? 'general';
        $directory = sprintf('uploads/%s/%s', $collection, now()->format('Y/m'));
        $disk = config('filesystems.default', 'public');

        $uploads = collect($request->file('files'))
            ->map(fn ($file) => $imageUploadService->store($file, $directory, $disk))
            ->values()
            ->all();

        return response()->json([
            'message' => app()->getLocale() === 'en'
                ? 'Files uploaded successfully.'
                : 'Τα αρχεία ανέβηκαν επιτυχώς.',
            'data' => $uploads,
        ], 201);
    }
}
