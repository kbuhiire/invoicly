<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PdfAsset
{
    public static function dataUriFromPublicPath(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }

        $full = Storage::disk('public')->path($relative);
        if (! is_file($full)) {
            return null;
        }

        $mime = @mime_content_type($full) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
    }
}
