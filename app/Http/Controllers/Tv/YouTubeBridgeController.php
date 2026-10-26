<?php

namespace App\Http\Controllers\Tv;

use App\Http\Controllers\Controller;
use App\Models\Family;
use Illuminate\Http\Request;

/**
 * Página mínima, servida desde nuestro dominio, que carga el reproductor oficial de
 * YouTube. La app de TV la incrusta en un iframe: así YouTube recibe un origen web
 * válido incluso cuando la app está empaquetada (origen file:// en webOS/Tizen).
 */
class YouTubeBridgeController extends Controller
{
    public function __invoke(Request $request)
    {
        $id = (string) $request->query('v');
        abort_unless(preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1, 404);

        $controls = $request->query('controls', 'auto');

        return response()->view('tv.youtube', [
            'videoId' => $id,
            'controls' => in_array($controls, Family::YOUTUBE_CONTROLS, true) ? $controls : 'auto',
        ]);
    }
}
