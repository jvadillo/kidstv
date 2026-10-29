<?php

namespace App\Http\Controllers\Tv;

use App\Http\Controllers\Controller;
use App\Models\DevicePairing;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Vinculación de televisores: la TV pide un código temporal, el adulto lo
 * autoriza desde su sesión web y la TV recoge su token (una sola vez).
 */
class PairingController extends Controller
{
    public function start(Request $request)
    {
        $data = $request->validate([
            'platform' => ['nullable', Rule::in(['web', 'webos', 'tizen', 'androidtv'])],
            'draft' => ['nullable', 'array:name,age,lists'],
            'draft.name' => ['nullable', 'string', 'max:40'],
            'draft.age' => ['nullable', Rule::in(array_keys(\App\Models\VideoList::AGE_RANGES))],
            'draft.lists' => ['nullable', 'array', 'max:60'],
            'draft.lists.*' => ['integer'],
        ]);

        $secret = Str::random(48);

        $pairing = DevicePairing::create([
            'code' => $this->uniqueCode(),
            'secret_hash' => hash('sha256', $secret),
            'platform' => $data['platform'] ?? 'web',
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'expires_at' => now()->addMinutes(config('kidstv.pairing_ttl_minutes')),
        ]);
        $device = $request->attributes->get('device');
        $draft = $data['draft'] ?? null;
        if ($device) {
            $profile = $device->family->profiles()->first();
            $draft = $device->family->provisional_until && $profile ? [
                'name' => $profile->name, 'age' => $profile->age_range,
                'lists' => $device->family->lists()->whereNotNull('source_id')->pluck('source_id')->all(),
            ] : null;
        }
        $pairing->forceFill(['draft' => $draft, 'setup_device_id' => $device?->id])->save();

        return response()->json([
            'id' => $pairing->id,
            'code' => $pairing->code,
            'code_display' => $pairing->formattedCode(),
            'secret' => $secret,
            'expires_in' => config('kidstv.pairing_ttl_minutes') * 60,
            'link_url' => route('onboarding.tv'),
            'link_display' => preg_replace('~^https?://~', '', route('onboarding.tv')),
            'qr_url' => route('tv.pairing.qr', $pairing->id),
        ], 201);
    }

    public function poll(Request $request, string $pairing)
    {
        $pairing = DevicePairing::find($pairing);
        $secret = (string) $request->header('X-Pairing-Secret');

        if (! $pairing || ! hash_equals($pairing->secret_hash, hash('sha256', $secret))) {
            return response()->json(['status' => 'invalid'], 404);
        }

        if ($pairing->claimed_at && ! $pairing->delivered_at && $pairing->device) {
            $token = $pairing->device_token;
            $pairing->update(['delivered_at' => now(), 'device_token' => null]);

            return response()->json([
                'status' => 'paired',
                'token' => $token,
                'device' => ['name' => $pairing->device->name],
            ]);
        }

        if ($pairing->claimed_at || $pairing->expires_at->isPast()) {
            return response()->json(['status' => 'expired'], 410);
        }

        return response()->json([
            'status' => 'pending',
            'expires_in' => max(0, now()->diffInSeconds($pairing->expires_at, false)),
        ]);
    }

    public function qr(string $pairing)
    {
        $pairing = DevicePairing::find($pairing);
        abort_unless($pairing && $pairing->isClaimable(), 404);

        $renderer = new ImageRenderer(new RendererStyle(320, 1), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString(route('onboarding.tv', ['code' => $pairing->code]));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function uniqueCode(): string
    {
        $alphabet = DevicePairing::CODE_ALPHABET;

        do {
            $code = '';
            for ($i = 0; $i < DevicePairing::CODE_LENGTH; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (DevicePairing::where('code', $code)->where('expires_at', '>', now())->exists());

        return $code;
    }
}
