<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OutreachImagesController
{
    /** Thumbnails are content-hashed, so they can be cached for good. */
    public function show(string $name): Response
    {
        $image = DB::table('outreach_images')->where('name', $name)->first();

        abort_unless($image, 404);

        return response(base64_decode($image->data), 200, [
            'Content-Type' => $image->mime_type,
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
