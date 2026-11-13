<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LegacyVerificationDocumentController extends Controller
{
    public function __invoke(Request $request, string $document)
    {
        // Only a real signed-in administrator, never an SEO editor or a public URL.
        abort_unless($request->user()?->is_admin === true, 403);
        $record = VerificationDocument::findOrFail($document);
        abort_unless($record->storage_disk === 'legacy_verification', 404);
        $root = realpath(config('filesystems.disks.legacy_verification.root'));
        $path = realpath(Storage::disk('legacy_verification')->path($record->storage_path));
        abort_unless($root && $path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path), 404);
        // Never inline potentially active legacy content; never cache identity documents.
        return response()->download($path, 'verification-document-'.$record->id.'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
