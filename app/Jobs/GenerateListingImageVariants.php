<?php

namespace App\Jobs;

use App\Models\ListingImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateListingImageVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 50;

    public function __construct(public int $imageId) {}

    public function backoff(): array
    {
        return [10];
    }

    public function handle(): void
    {
        $image = ListingImage::find($this->imageId);
        $disk = Storage::disk('public');
        if (! $image || ! $disk->exists($image->path)) {
            return;
        }
        $source = @imagecreatefromstring($disk->get($image->path));
        if (! $source) {
            throw new \RuntimeException('Listing image could not be decoded.');
        }
        $generated = [];
        try {
            if (function_exists('exif_read_data') && str_ends_with(strtolower($image->path), '.jpg')) {
                $exif = @exif_read_data($disk->path($image->path));
                $orientation = (int) ($exif['Orientation'] ?? 1);
                if (in_array($orientation, [2, 4, 5, 7], true)) {
                    imageflip($source, in_array($orientation, [4, 5], true) ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
                }
                $angle = match ($orientation) {
                    3 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
                };
                if ($angle) {
                    $rotated = imagerotate($source, $angle, 0);
                    if ($rotated) {
                        imagedestroy($source);
                        $source = $rotated;
                    }
                }
            }
            $width = imagesx($source);
            $height = imagesy($source);
            foreach (['thumbnail_path' => 640, 'display_path' => 1600] as $column => $edge) {
                $ratio = min(1, $edge / max($width, $height));
                $targetWidth = max(1, (int) round($width * $ratio));
                $targetHeight = max(1, (int) round($height * $ratio));
                $target = imagecreatetruecolor($targetWidth, $targetHeight);
                try {
                    imagealphablending($target, false);
                    imagesavealpha($target, true);
                    imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                    ob_start();
                    try {
                        if (! imagewebp($target, null, 82)) {
                            throw new \RuntimeException('WebP encoding failed.');
                        }
                        $content = ob_get_contents();
                    } finally {
                        ob_end_clean();
                    }
                    $path = preg_replace('/\.[^.]+$/', '', $image->path).'.'.$edge.'.webp';
                    if (! $disk->put($path, $content)) {
                        throw new \RuntimeException('Image variant could not be saved.');
                    }
                    $generated[$column] = $path;
                } finally {
                    imagedestroy($target);
                }
            }
            if (! ListingImage::whereKey($image->id)->where('path', $image->path)->update($generated + ['width' => $width, 'height' => $height])) {
                $disk->delete(array_values($generated));
            }
        } catch (\Throwable $exception) {
            $disk->delete(array_values($generated));
            throw $exception;
        } finally {
            imagedestroy($source);
        }
    }
}
