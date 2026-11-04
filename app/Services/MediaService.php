<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MediaItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaService
{
    private const MAP_WIDTH = 260;
    private const MAP_HEIGHT = 160;
    private const ZOOM = 16;

    public function store(Event $event, UploadedFile $file, array $data, User $user): MediaItem
    {
        $mime = $file->getMimeType();
        $allowed = ['image/jpeg', 'image/png'];

        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['photo' => 'Foto harus JPG/JPEG atau PNG.']);
        }

        if ($file->getSize() > 15 * 1024 * 1024) {
            throw ValidationException::withMessages(['photo' => 'Ukuran foto maksimal 15 MB.']);
        }

        if (empty($data['gps_lat']) || empty($data['gps_lng']) || empty($data['captured_at'])) {
            throw ValidationException::withMessages(['photo' => 'GPS dan waktu foto wajib diisi.']);
        }

        $uuid = (string) Str::uuid();
        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $originalPath = "events/{$event->id}/originals/{$uuid}.{$extension}";
        $watermarkedPath = "events/{$event->id}/watermarked/{$uuid}.jpg";

        Storage::disk('local')->putFileAs(
            "events/{$event->id}/originals",
            $file,
            "{$uuid}.{$extension}"
        );

        $absoluteOriginal = Storage::disk('local')->path($originalPath);
        $size = @getimagesize($absoluteOriginal);

        $address = $this->reverseGeocode((float) $data['gps_lat'], (float) $data['gps_lng']);
        $mapPath = $this->mapPath((float) $data['gps_lat'], (float) $data['gps_lng']);

        $this->watermark(
            $absoluteOriginal,
            Storage::disk('local')->path($watermarkedPath),
            $event,
            [
                'captured_at' => $data['captured_at'],
                'lat' => (float) $data['gps_lat'],
                'lng' => (float) $data['gps_lng'],
                'accuracy' => $data['gps_accuracy'] ?? null,
                'address' => $address,
            ],
            $mapPath
        );

        return MediaItem::create([
            'event_id' => $event->id,
            'event_phase_id' => $data['event_phase_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'path' => $originalPath,
            'watermarked_path' => $watermarkedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => $file->getSize(),
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'caption' => $data['caption'] ?? null,
            'captured_at' => $data['captured_at'],
            'gps_lat' => $data['gps_lat'],
            'gps_lng' => $data['gps_lng'],
            'gps_accuracy' => $data['gps_accuracy'] ?? null,
            'address' => $address,
            'checksum' => hash_file('sha256', $absoluteOriginal),
            'status' => 'pending',
            'uploaded_by' => $user->id,
        ]);
    }

    private function watermark(string $source, string $destination, Event $event, array $meta, ?string $mapPath): void
    {
        $image = @imagecreatefromstring((string) file_get_contents($source));

        if (! $image) {
            return;
        }

        $image = $this->orient($image, $source);

        $maxWidth = 1600;
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width > $maxWidth) {
            $newHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagescale($image, $maxWidth, $newHeight);
            if ($resized !== false) {
                imagedestroy($image);
                $image = $resized;
                $width = $maxWidth;
                $height = $newHeight;
            }
        }

        $band = 210;
        $canvas = imagecreatetruecolor($width, $height + $band);
        $navy = imagecolorallocate($canvas, 10, 37, 64);
        imagefill($canvas, 0, 0, $navy);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $muted = imagecolorallocate($canvas, 190, 205, 220);
        $red = imagecolorallocate($canvas, 204, 0, 0);
        $font = resource_path('fonts/PlusJakartaSans.ttf');
        $hasFont = is_file($font);

        $textLeft = 24;
        $textTop = $height + 30;
        $maxTextWidth = max(400, $width - self::MAP_WIDTH - 70);

        $this->text($canvas, $hasFont, $font, 19, $textLeft, $textTop, $white, 'STAR — Supported by Alenkosa');
        $this->text($canvas, $hasFont, $font, 14, $textLeft, $textTop + 34, $muted, $event->name.' · '.($event->dealer?->name ?? 'Dealer'));

        $time = date('d/m/Y H:i:s', strtotime((string) $meta['captured_at'])).' WIB';
        $accuracy = $meta['accuracy'] ? ' · ±'.round((float) $meta['accuracy']).' m' : '';
        $coords = number_format((float) $meta['lat'], 6, '.', '').', '.number_format((float) $meta['lng'], 6, '.', '');
        $this->text($canvas, $hasFont, $font, 12, $textLeft, $textTop + 60, $white, $time.' · '.$coords.$accuracy);

        $address = $this->wrap($meta['address'] ?: 'Lokasi GPS', $hasFont, $font, 12, $maxTextWidth);
        $address = array_slice($address, 0, 2);
        foreach ($address as $i => $line) {
            $this->text($canvas, $hasFont, $font, 12, $textLeft, $textTop + 82 + ($i * 20), $muted, $line);
        }

        $mapX = $width - self::MAP_WIDTH - 24;
        $mapY = $height + 24;
        if ($mapPath && is_file($mapPath)) {
            $map = @imagecreatefrompng($mapPath);
            if ($map) {
                imagecopy($canvas, $map, $mapX, $mapY, 0, 0, self::MAP_WIDTH, self::MAP_HEIGHT);
                imagedestroy($map);
            }
        } else {
            $fallback = imagecolorallocate($canvas, 30, 60, 90);
            imagefilledrectangle($canvas, $mapX, $mapY, $mapX + self::MAP_WIDTH, $mapY + self::MAP_HEIGHT, $fallback);
            $this->text($canvas, $hasFont, $font, 11, $mapX + 12, $mapY + 70, $white, 'Peta tidak tersedia');
        }

        imagerectangle($canvas, $mapX, $mapY, $mapX + self::MAP_WIDTH, $mapY + self::MAP_HEIGHT, $white);
        imagefilledellipse($canvas, $mapX + 22, $mapY + 22, 12, 12, $red);

        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }

        imagejpeg($canvas, $destination, 88);
        imagedestroy($canvas);
    }

    private function text($canvas, bool $hasFont, string $font, int $size, int $x, int $y, int $color, string $text): void
    {
        if ($hasFont) {
            imagettftext($canvas, $size, 0, $x, $y, $color, $font, $text);

            return;
        }

        imagestring($canvas, 3, $x, $y - 12, $text, $color);
    }

    private function wrap(string $text, bool $hasFont, string $font, int $size, int $maxWidth): array
    {
        if (! $hasFont) {
            return [$text];
        }

        $words = preg_split('/\s+/', trim($text));
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $test = trim($line.' '.$word);
            $box = imagettfbbox($size, 0, $font, $test);
            $width = abs($box[2] - $box[0]);

            if ($width > $maxWidth && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $test;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function orient($image, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function reverseGeocode(float $lat, float $lng): string
    {
        $key = 'geo:'.round($lat, 5).','.round($lng, 5);

        return Cache::remember($key, now()->addDays(30), function () use ($lat, $lng) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'STAR/1.0 (https://star.alk-tech.my.id)',
                    'Accept' => 'application/json',
                ])->timeout(6)->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);

                if ($response->ok()) {
                    return Str::limit($response->json('display_name', 'Lokasi GPS'), 160);
                }
            } catch (\Throwable) {
                // Fall back to coordinates.
            }

            return 'Koordinat '.number_format($lat, 6, '.', '').', '.number_format($lng, 6, '.', '');
        });
    }

    private function mapPath(float $lat, float $lng): ?string
    {
        $dir = storage_path('app/private/maps');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.'/'.md5($lat.','.$lng).'.png';
        if (is_file($path)) {
            return $path;
        }

        [$centerX, $centerY] = $this->latLngToPixel($lat, $lng, self::ZOOM);
        $left = $centerX - self::MAP_WIDTH / 2;
        $top = $centerY - self::MAP_HEIGHT / 2;
        $startTileX = (int) floor($left / 256);
        $startTileY = (int) floor($top / 256);
        $endTileX = (int) floor(($left + self::MAP_WIDTH) / 256);
        $endTileY = (int) floor(($top + self::MAP_HEIGHT) / 256);

        $canvas = imagecreatetruecolor(self::MAP_WIDTH, self::MAP_HEIGHT);
        $gray = imagecolorallocate($canvas, 210, 216, 222);
        imagefill($canvas, 0, 0, $gray);
        $loaded = false;

        for ($x = $startTileX; $x <= $endTileX; $x++) {
            for ($y = $startTileY; $y <= $endTileY; $y++) {
                $tile = $this->tile($x, $y, self::ZOOM);
                if (! $tile) {
                    continue;
                }

                $dstX = (int) round($x * 256 - $left);
                $dstY = (int) round($y * 256 - $top);
                imagecopy($canvas, $tile, $dstX, $dstY, 0, 0, 256, 256);
                imagedestroy($tile);
                $loaded = true;
            }
        }

        if (! $loaded) {
            imagedestroy($canvas);

            return null;
        }

        $red = imagecolorallocate($canvas, 204, 0, 0);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $cx = (int) (self::MAP_WIDTH / 2);
        $cy = (int) (self::MAP_HEIGHT / 2);
        imagefilledellipse($canvas, $cx, $cy, 14, 14, $white);
        imagefilledellipse($canvas, $cx, $cy, 10, 10, $red);

        $attribution = imagecolorallocate($canvas, 60, 60, 60);
        imagestring($canvas, 1, 4, self::MAP_HEIGHT - 14, '© OpenStreetMap', $attribution);

        imagepng($canvas, $path);
        imagedestroy($canvas);

        return $path;
    }

    private function tile(int $x, int $y, int $z)
    {
        $dir = storage_path("app/private/osm-tiles/{$z}/{$x}");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$y}.png";
        if (! is_file($path)) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'STAR/1.0 (https://star.alk-tech.my.id)',
                ])->timeout(8)->get("https://tile.openstreetmap.org/{$z}/{$x}/{$y}.png");

                if (! $response->ok()) {
                    return null;
                }

                file_put_contents($path, $response->body());
            } catch (\Throwable) {
                return null;
            }
        }

        $image = @imagecreatefrompng($path);

        return $image ?: null;
    }

    private function latLngToPixel(float $lat, float $lng, int $zoom): array
    {
        $n = 2 ** $zoom;
        $x = ($lng + 180) / 360 * $n * 256;
        $latRad = deg2rad($lat);
        $y = (1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * $n * 256;

        return [$x, $y];
    }
}
