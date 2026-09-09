<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Lead;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Descarga un adjunto verificando el acceso a la ficha (§2, §5).
     */
    public function download(Lead $lead, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $lead);

        abort_unless($attachment->lead_id === $lead->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
