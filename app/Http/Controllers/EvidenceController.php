<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    public function download(Evidence $evidence): StreamedResponse
    {
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name);
    }
}
