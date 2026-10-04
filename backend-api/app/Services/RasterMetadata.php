<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

// Metadata stripping without GD; retain image data, never accept client-supplied SVG.
class RasterMetadata
{
    public function clean(UploadedFile $file): array
    {
        $data = file_get_contents($file->getRealPath());
        if (substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP') {
            $chunks = '';
            $image = false;
            if (strlen($data) < 20 || unpack('V', substr($data, 4, 4))[1] + 8 !== strlen($data)) {
                throw ValidationException::withMessages(['image' => ['Image WebP invalide.']]);
            }
            for ($p = 12; $p + 8 <= strlen($data); $p += 8 + $length + ($length % 2)) {
                $type = substr($data, $p, 4);
                $length = unpack('V', substr($data, $p + 4, 4))[1];
                if ($length > strlen($data) - $p - 8 || in_array($type, ['ANIM', 'ANMF'])) {
                    throw ValidationException::withMessages(['image' => ['Image WebP animée ou invalide.']]);
                }
                $body = substr($data, $p + 8, $length);
                if ($type === 'VP8X') {
                    if ($length !== 10 || (ord($body[0]) & 2)) {
                        throw ValidationException::withMessages(['image' => ['Image WebP invalide.']]);
                    }
                    $body[0] = chr(ord($body[0]) & ~44);
                }
                if (in_array($type, ['VP8 ', 'VP8L', 'ALPH', 'VP8X'])) {
                    $chunks .= $type.pack('V', $length).$body.($length % 2 ? "\0" : '');
                    $image = $image || in_array($type, ['VP8 ', 'VP8L']);
                }
            }
            if ($image && $p === strlen($data)) {
                return ['RIFF'.pack('V', strlen($chunks) + 4).'WEBP'.$chunks, 'webp'];
            }
            throw ValidationException::withMessages(['image' => ['Image WebP invalide.']]);
        }
        if (str_starts_with($data, "\x89PNG\r\n\x1a\n")) {
            $out = substr($data, 0, 8);
            for ($p = 8; $p + 12 <= strlen($data); $p += $length + 12) {
                $length = unpack('N', substr($data, $p, 4))[1];
                if ($length > strlen($data) - $p - 12) {
                    break;
                }
                $type = substr($data, $p + 4, 4);
                $chunk = substr($data, $p + 4, $length + 4);
                if (! hash_equals(hash('crc32b', $chunk, true), substr($data, $p + 8 + $length, 4))) {
                    break;
                }
                if (in_array($type, ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'sRGB', 'gAMA', 'cHRM'], true)) {
                    $out .= substr($data, $p, $length + 12);
                }
                if ($type === 'IEND') {
                    return [$out, 'png'];
                }
            }
        } elseif (str_starts_with($data, "\xff\xd8")) {
            $out = "\xff\xd8";
            $p = 2;
            while ($p < strlen($data)) {
                if ($data[$p] !== "\xff") {
                    $next = strpos($data, "\xff", $p);
                    if ($next === false) {
                        break;
                    }
                    $out .= substr($data, $p, $next - $p);
                    $p = $next;
                }
                $start = $p++;
                while ($p < strlen($data) && $data[$p] === "\xff") {
                    $p++;
                }
                if ($p >= strlen($data)) {
                    break;
                }
                $marker = ord($data[$p++]);
                if ($marker === 0xD9) {
                    return [$out."\xff\xd9", 'jpg'];
                }
                if ($marker === 0 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                    $out .= substr($data, $start, $p - $start);

                    continue;
                }
                if ($p + 2 > strlen($data)) {
                    break;
                }
                $length = unpack('n', substr($data, $p, 2))[1];
                if ($length < 2 || $p + $length > strlen($data)) {
                    break;
                }
                if (! (($marker >= 0xE1 && $marker <= 0xEF && $marker !== 0xEE) || $marker === 0xFE)) {
                    $out .= substr($data, $start, $p + $length - $start);
                }
                $p += $length;
            }
        }
        throw ValidationException::withMessages(['image' => ['Image PNG/JPEG corrompue ou non prise en charge.']]);
    }
}
