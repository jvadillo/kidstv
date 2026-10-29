<?php

namespace App\Http\Controllers\Tv;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DevicePairing;
use App\Models\Family;
use App\Models\VideoList;
use App\Services\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SetupController extends Controller
{
    public function catalog(Onboarding $onboarding)
    {
        return response()->json(['lists' => $onboarding->publicLists(null)->get()->map(fn ($list) => [
            'id' => $list->id, 'name' => $list->name,
            'cover' => $list->contents->first()?->youtubeThumbnail(),
            'ages' => collect(VideoList::AGE_RANGES)->filter(fn ($range) => $list->suitsAges(...$range))->keys()->values(),
            'languages' => $list->languageCodes(),
        ])]);
    }

    public function finish(Request $request, string $pairing, Onboarding $onboarding)
    {
        return DB::transaction(function () use ($request, $pairing, $onboarding) {
            $link = DevicePairing::lockForUpdate()->findOrFail($pairing);
            abort_unless(hash_equals($link->secret_hash, hash('sha256', (string) $request->header('X-Pairing-Secret'))), 404);
            abort_unless($link->isClaimable() && ! $link->setup_device_id, 410);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:40'],
                'age' => ['required', Rule::in(array_keys(VideoList::AGE_RANGES))],
                'lists' => ['required', 'array', 'min:1', 'max:60'],
                'lists.*' => ['integer', Rule::in($onboarding->publicLists(null)->pluck('id')->all())],
            ]);
            $family = new Family;
            $family->forceFill(['provisional_until' => now()->addDays(30)])->save();
            $onboarding->applyToFamily($family, $data);
            [$device, $token] = Device::issue($family, __('Mi tele'), $link->platform, $link->user_agent);
            // El polling permite recuperar el resultado si se pierde esta respuesta.
            $link->update(['device_id' => $device->id, 'device_token' => $token, 'claimed_at' => now()]);

            return response()->json(['token' => $token], 201);
        });
    }
}
