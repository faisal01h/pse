<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CaptchaController extends Controller
{
    public function image(Request $request): Response
    {
        $code = '';
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = strlen($alphabet);

        for ($i = 0; $i < 5; $i++) {
            $code .= $alphabet[random_int(0, $length - 1)];
        }

        session(['report_captcha' => $code]);

        $width = 180;
        $height = 56;
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 11, 12, 12);
        $red = imagecolorallocate($image, 244, 63, 94);
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        for ($i = 0; $i < 8; $i++) {
            imageline(
                $image,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                $i % 2 === 0 ? $red : $ink
            );
        }

        $x = 16;
        foreach (str_split($code) as $character) {
            imagestring($image, 5, $x, random_int(12, 24), $character, $ink);
            $x += 32;
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
