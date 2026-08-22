<?php

namespace App\Http\Controllers;

use App\Models\ImprovementCase;
use App\Models\OfficialDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialDocumentController extends Controller
{
    public function store(Request $request, ImprovementCase $case): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:25600', 'mimes:xlsx,xls,docx,doc,pdf'],
            'document_stage' => ['required', Rule::in(['original', 'working', 'final'])],
            'document_type' => ['required', Rule::in(['finding_report', 'nonconformity', 'minutes', 'other'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $file = $request->file('file');
        $path = $file->store("official-documents/{$case->id}");
        $case->documents()->create([
            'uploaded_by' => $request->user()->id,
            'document_stage' => $data['document_stage'], 'document_type' => $data['document_type'],
            'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Documento institucional almacenado sin modificar el archivo original.');
    }

    public function download(OfficialDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
