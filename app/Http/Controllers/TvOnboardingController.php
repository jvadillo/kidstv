<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DevicePairing;
use App\Services\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TvOnboardingController extends Controller
{
    public function entry(Request $request)
    {
        return view('onboarding.tv', ['code' => $request->query('code', '')]);
    }

    public function begin(Request $request, Onboarding $onboarding)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12'], 'mode' => ['required', Rule::in(['prepare', 'existing'])]]);
        $link = DevicePairing::where('code', DevicePairing::normalizeCode($data['code']))->whereNull('claimed_at')->where('expires_at', '>', now())->first();
        if (! $link) {
            throw ValidationException::withMessages(['code' => __('El código ha caducado o no es válido. Escribe el que aparece ahora en la tele; conservaremos lo que has preparado.')]);
        }
        // Una hora para completar el alta; el televisor recibe la nueva caducidad al consultar.
        $link->update(['expires_at' => now()->addHour()]);
        $previous = $request->session()->get('onboarding_pairing');
        $request->session()->put('onboarding_pairing', $link->id);
        if ($previous !== $link->id && $link->draft) {
            $request->session()->put(Onboarding::DRAFT_KEY, $link->draft);
        }
        if ($link->setup_device_id && ! $link->draft) {
            $request->session()->forget(Onboarding::DRAFT_KEY);
        }
        $request->session()->put('url.intended', route('onboarding.tv.confirm'));
        if ($data['mode'] === 'existing' || ($link->setup_device_id && ! $link->draft)) {
            return redirect()->route($request->user() ? 'onboarding.tv.confirm' : 'login');
        }
        $draft = $onboarding->draft($request);
        if ($link->setup_device_id && ! empty($draft['lists'])) {
            return redirect()->route($request->user() ? 'onboarding.tv.confirm' : 'onboarding.account');
        }

        return redirect()->route('onboarding.profile');
    }

    public function confirm(Request $request, Onboarding $onboarding)
    {
        $link = DevicePairing::find($request->session()->get('onboarding_pairing'));
        if (! $link || ! $link->isClaimable()) {
            return redirect()->route('onboarding.tv')->with('status', __('Escribe el código actual de la tele para continuar. Tu selección sigue guardada.'));
        }

        return view('onboarding.tv-confirm', [
            'draft' => $onboarding->draft($request), 'family' => $request->user()->family,
            'code' => $link->formattedCode(),
        ]);
    }

    public function attach(Request $request, Onboarding $onboarding)
    {
        $family = $request->user()->family;
        $data = $request->validate([
            'choice' => ['required', Rule::in(['add', 'existing'])],
            'pin' => $family->hasPin() ? ['nullable'] : ['required', 'digits_between:4,6', 'confirmed'],
        ]);
        DB::transaction(function () use ($request, $onboarding, $family, $data) {
            $link = DevicePairing::lockForUpdate()->find($request->session()->get('onboarding_pairing'));
            if (! $link || ! $link->isClaimable()) {
                throw ValidationException::withMessages(['code' => __('El código ha caducado. Vuelve a introducir el que muestra la tele.')]);
            }
            $device = $link->setup_device_id ? Device::lockForUpdate()->findOrFail($link->setup_device_id) : null;
            $oldFamily = $device?->family;
            // Una tele ya guardada no puede transferirse a otra cuenta a través del QR del PIN.
            if ($oldFamily && ! $oldFamily->provisional_until && $oldFamily->id !== $family->id) {
                throw ValidationException::withMessages(['code' => __('Entra con la cuenta que tiene vinculada esta tele.')]);
            }
            if ($oldFamily && ! $oldFamily->provisional_until && $link->draft) {
                throw ValidationException::withMessages(['code' => __('Esta tele ya se ha guardado. Vuelve a la tele para continuar.')]);
            }
            if ($data['choice'] === 'add') {
                $draft = $onboarding->draft($request);
                if (empty($draft['name']) || empty($draft['age']) || empty($draft['lists']) || $family->profiles()->count() >= 12) {
                    throw ValidationException::withMessages(['choice' => __('Completa el perfil y elige sus series antes de añadirlo. La familia admite hasta 12 perfiles.')]);
                }
                $available = $onboarding->publicLists($family)->whereIn('id', $draft['lists'])->exists();
                if (! $available) {
                    throw ValidationException::withMessages(['choice' => __('Estas series ya no están disponibles. Vuelve a elegirlas.')]);
                }
                $onboarding->apply($request->user(), $draft);
            } elseif (! $family->profiles()->exists()) {
                throw ValidationException::withMessages(['choice' => __('Esta cuenta aún no tiene perfiles. Prepara el primero antes de conectar la tele.')]);
            }
            if (! $family->hasPin()) {
                $family->setPin($data['pin']);
            }
            if ($device) {
                $token = null;
                $device->forceFill(['family_id' => $family->id])->save();
            } else {
                [$device, $token] = Device::issue($family, __('Mi tele'), $link->platform, $link->user_agent);
            }
            $link->update(['device_id' => $device->id, 'device_token' => $token, 'claimed_at' => now()]);
            if ($oldFamily && $oldFamily->id !== $family->id && $oldFamily->provisional_until && $oldFamily->users()->doesntExist()) {
                $oldFamily->delete();
            }
        });
        $request->session()->forget(['onboarding_pairing', Onboarding::DRAFT_KEY, Onboarding::CLAIM_KEY, 'url.intended']);

        return redirect()->route('panel')->with('status', __('Tele guardada y conectada. Usa tu PIN para entrar en la Zona de adultos.'));
    }
}
