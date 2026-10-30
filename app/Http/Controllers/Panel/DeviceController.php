<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DevicePairing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeviceController extends Controller
{
    public function index()
    {
        return view('panel.devices.index', [
            'devices' => $this->family()->devices()->latest('last_seen_at')->latest('id')->get(),
            'ipkAvailable' => is_file(public_path(config('nokoplay.webos_ipk'))),
        ]);
    }

    public function pairForm(Request $request)
    {
        return view('panel.devices.pair', [
            'code' => DevicePairing::normalizeCode((string) $request->query('code', '')),
        ]);
    }

    public function pair(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:12'],
            'name' => ['required', 'string', 'max:40'],
        ], [
            'code.required' => __('Escribe el código que aparece en el televisor.'),
            'name.required' => __('Pon un nombre al televisor (por ejemplo «Tele del salón»).'),
        ]);

        $code = DevicePairing::normalizeCode($data['code']);
        $family = $this->family();

        $device = DB::transaction(function () use ($code, $family, $data) {
            $pairing = DevicePairing::where('code', $code)
                ->whereNull('claimed_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $pairing) {
                throw ValidationException::withMessages(['code' => __('El código no es válido o ha caducado. Comprueba el que aparece ahora en el televisor.')]);
            }
            if ($pairing->setup_device_id) {
                throw ValidationException::withMessages(['code' => __('Abre /empezar/tv con este código para guardar esta tele.')]);
            }

            [$device, $token] = Device::issue($family, $data['name'], $pairing->platform, $pairing->user_agent);

            $pairing->update([
                'device_id' => $device->id,
                'device_token' => $token,
                'claimed_at' => now(),
            ]);

            return $device;
        });

        $message = __('«:name» vinculado. En unos segundos mostrará los perfiles de tu familia.', ['name' => $device->name]);

        // Al terminar los primeros pasos se vuelve al inicio del panel, no a la gestión de teles.
        return redirect()->route($request->boolean('from_onboarding') ? 'panel' : 'devices.index')->with('status', $message);
    }

    /** Convierte este navegador en una «tele» de prueba para usar la videoteca sin televisor. */
    public function thisBrowser(Request $request)
    {
        $family = $this->family();

        // Abrirlo de nuevo desde el mismo navegador sustituye a su anterior «tele» de prueba.
        $family->devices()
            ->where('platform', 'web')
            ->where('name', __('Navegador de prueba'))
            ->where('user_agent', Str::limit((string) $request->userAgent(), 490, ''))
            ->delete();

        [, $token] = Device::issue($family, __('Navegador de prueba'), 'web', $request->userAgent());

        // El token viaja en el fragmento (#), que el navegador nunca envía al servidor.
        return redirect()->to(url('/tv').'/#token='.$token);
    }

    public function update(Request $request, int $device)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:40']]);
        $this->family()->devices()->findOrFail($device)->update($data);

        return back()->with('status', __('Nombre actualizado.'));
    }

    public function destroy(int $device)
    {
        $device = $this->family()->devices()->findOrFail($device);
        $device->delete();

        return redirect()->route('devices.index')->with('status', __('«:name» desvinculado. Ya no puede acceder a la videoteca.', ['name' => $device->name]));
    }
}
