<?php

namespace App\Http\Controllers;

use App\Models\ImprovementCase;
use App\Models\OfficialDocument;
use App\Services\InstitutionalFindingWorkCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialDocumentController extends Controller
{
    public function generate(Request $request, ImprovementCase $case, InstitutionalFindingWorkCopy $generator): RedirectResponse
    {
        $this->authorize('view', $case);
        $original = $case->documents()->where('document_stage', 'original')
            ->where(fn ($documents) => $documents
                ->whereRaw('LOWER(original_name) LIKE ?', ['%.xlsx'])
                ->orWhereRaw('LOWER(original_name) LIKE ?', ['%.xls']))
            ->latest()->first();
        if (! $original) {
            return back()->withErrors(['document' => 'Adjunta primero el Excel institucional original.']);
        }
        $version = $case->documents()->where('document_stage', 'working')->count() + 1;
        $generated = $generator->generate($case, $original, $version);
        $case->documents()->create([
            'uploaded_by' => $request->user()->id, 'document_stage' => 'working',
            'document_type' => $original->document_type, 'disk' => 'local',
            'path' => $generated['path'], 'original_name' => $generated['name'],
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => Storage::disk('local')->size($generated['path']),
            'notes' => "Copia de trabajo generada desde {$original->original_name}; el original no fue modificado.",
        ]);

        return back()->with('status', "Copia de trabajo v{$version} generada correctamente.");
    }

    public function store(Request $request, ImprovementCase $case): RedirectResponse
    {
        $this->authorize('view', $case);
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
        $this->authorize('view', $document->improvementCase);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
