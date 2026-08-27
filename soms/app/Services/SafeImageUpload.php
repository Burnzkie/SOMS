<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class SafeImageUpload
{
    /**
     * Validate real image content, re-encode, and store.
     *
     * @param  UploadedFile  $file
     * @param  string        $disk  e.g. 'public'
     * @param  string        $path  e.g. 'avatars'
     * @return string        stored filename/path
     */
    public static function store(UploadedFile $file, string $disk, string $path): string
    {
        // Real content check — not just extension/MIME sniffing
        $info = @getimagesize($file->getRealPath());
        abort_if($info === false, 422, 'Invalid image file.');

        abort_unless(
            in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP]),
            422,
            'Unsupported image type.'
        );

        abort_if($file->getSize() > 2 * 1024 * 1024, 422, 'File exceeds 2MB.');

        $manager = new ImageManager(new Driver());
        $encoded = $manager->read($file->getRealPath())->encode(new \Intervention\Image\Encoders\JpegEncoder(85));

        $filename = $path . '/' . Str::uuid() . '.jpg';

        Storage::disk($disk)->put($filename, $encoded);

        return $filename;
    }
}