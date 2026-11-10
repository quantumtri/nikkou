<?php

namespace App\Http\Controllers;

use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves private unit files (spec section 10). Routes are signed and
 * short-lived, and the viewer must still be allowed to see the unit.
 */
class UnitFileController extends Controller
{
    public function document(UnitDocument $document): StreamedResponse
    {
        // Import documents are inventory data: admin and warehouse only.
        Gate::authorize('update', $document->unit);

        return Storage::disk('local')->response($document->file_path, $document->original_name);
    }

    public function photo(UnitPhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $photo->unit);

        return Storage::disk('local')->response($photo->file_path);
    }
}
