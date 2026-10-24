<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\LoginToken;
use App\Models\User;
use App\Services\AdultSessions;
use App\Services\EmailVerification;
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function __construct(private AdultSessions $sessions) {}

    public function show(Request $request)
    {
        $user = $request->user();

        return view('panel.account.show', [
            'user' => $user,
            'family' => $user->family,
            'sessions' => $user->authSessions()->latest('last_used_at')->get(),
            'currentSessionId' => $this->sessions->currentId($request),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'family_name' => ['nullable', 'string', 'max:60'],
            'autoplay_next' => ['boolean'],
        ]);

        $request->user()->update(['name' => $data['name'] ?? null]);
        $request->user()->family->update([
            'name' => $data['family_name'] ?? null,
            'autoplay_next' => $request->boolean('autoplay_next'),
        ]);

        return back()->with('status', __('Preferencias guardadas.'));
    }

    public function revokeSession(Request $request, int $session)
    {
        $session = $request->user()->authSessions()->findOrFail($session);

        if ($session->id === $this->sessions->currentId($request)) {
            $this->sessions->terminate($request);

            return redirect()->route('home')->with('status', __('Has cerrado la sesión en este navegador.'));
        }

        $session->delete();

        return back()->with('status', __('Sesión cerrada en «:device».', ['device' => $session->deviceLabel()]));
    }

    public function revokeOtherSessions(Request $request)
    {
        $count = $this->sessions->revokeOthers($request, $request->user());

        return back()->with('status', trans_choice('Se ha cerrado :count sesión en otros dispositivos.|Se han cerrado :count sesiones en otros dispositivos.', $count));
    }

    public function editPin(Request $request)
    {
        return view('panel.account.pin', ['hasPin' => $request->user()->family->hasPin()]);
    }

    public function updatePin(Request $request)
    {
        $data = $request->validate([
            'pin' => ['required', 'digits_between:4,6', 'confirmed'],
        ], [
            'pin.required' => __('Escribe un PIN de 4 a 6 cifras.'),
            'pin.digits_between' => __('El PIN debe tener entre 4 y 6 cifras (solo números).'),
            'pin.confirmed' => __('Los dos PIN no coinciden.'),
        ]);

        $family = $request->user()->family;
        $family->setPin($data['pin']);
        // Un PIN nuevo desbloquea los televisores que estuvieran en espera por intentos fallidos.
        $family->devices()->update(['pin_failures' => 0, 'pin_locked_until' => null]);

        return redirect()->route('account.show')->with('status', __('PIN parental guardado. Úsalo en la televisión para entrar en la Zona de adultos.'));
    }

    public function editEmail(Request $request)
    {
        return view('panel.account.email', [
            'user' => $request->user(),
            'pendingEmail' => $request->session()->get('pending_new_email'),
        ]);
    }

    public function sendEmailChange(Request $request, EmailVerification $verification)
    {
        $data = $request->validate([
            'email' => [
                'required', 'string', 'max:254',
                config('kidstv.email_dns_check') ? 'email:rfc,dns' : 'email:rfc',
                Rule::unique('users', 'email'),
            ],
        ], [
            'email.email' => __('Esta dirección de correo no parece válida o su dominio no existe.'),
            'email.unique' => __('Esa dirección ya está en uso en otra cuenta.'),
        ]);

        $email = EmailVerification::normalize($data['email']);
        $verification->send($email, LoginToken::PURPOSE_CHANGE_EMAIL, $request->user(), $request->ip());
        $request->session()->put('pending_new_email', $email);

        return back()->with('status', __('Te hemos enviado un código a :email.', ['email' => $email]));
    }

    public function confirmEmailChange(Request $request, EmailVerification $verification)
    {
        $email = $request->session()->get('pending_new_email');
        abort_unless($email, 404);

        $request->validate(['code' => ['required', 'string', 'max:12']]);
        $verification->verifyCode($email, LoginToken::PURPOSE_CHANGE_EMAIL, $request->input('code'));

        if (User::where('email', $email)->whereKeyNot($request->user()->id)->exists()) {
            return back()->withErrors(['code' => __('Esa dirección ya está en uso en otra cuenta.')]);
        }

        $request->user()->update(['email' => $email, 'email_verified_at' => now()]);
        $request->session()->forget('pending_new_email');

        return redirect()->route('account.show')->with('status', __('Correo actualizado. A partir de ahora entrarás con :email.', ['email' => $email]));
    }

    /** Exportación en JSON de todos los datos de la familia (los archivos se descargan desde cada contenido). */
    public function export(Request $request)
    {
        $user = $request->user();
        $family = $user->family->load(['profiles.contents:id', 'categories', 'contents.profiles:id', 'devices']);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'email' => $user->email,
                'name' => $user->name,
                'created_at' => $user->created_at?->toIso8601String(),
                'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
            ],
            'family' => ['name' => $family->name, 'autoplay_next' => $family->autoplay_next, 'pin_configured' => $family->hasPin()],
            'profiles' => $family->profiles->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'avatar' => $p->avatar,
                'content_ids' => $p->contents->pluck('id'),
            ]),
            'categories' => $family->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'contents' => $family->contents->map(fn (Content $c) => [
                'id' => $c->id, 'type' => $c->type, 'title' => $c->title, 'description' => $c->description,
                'category_id' => $c->category_id, 'status' => $c->status,
                'youtube_url' => $c->youtube_id ? 'https://www.youtube.com/watch?v='.$c->youtube_id : null,
                'file_size' => $c->file_size, 'duration' => $c->duration,
                'download_url' => $c->file_path ? route('contents.file', $c->id) : null,
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
            'devices' => $family->devices->map(fn ($d) => [
                'name' => $d->name, 'platform' => $d->platform,
                'linked_at' => $d->created_at?->toIso8601String(),
                'last_seen_at' => $d->last_seen_at?->toIso8601String(),
            ]),
        ];

        return response()->streamDownload(
            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'videoteca-familiar-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function confirmDelete(Request $request)
    {
        $family = $request->user()->family;

        return view('panel.account.delete', [
            'contentCount' => $family->contents()->count(),
            'profileCount' => $family->profiles()->count(),
            'deviceCount' => $family->devices()->count(),
        ]);
    }

    public function destroy(Request $request, MediaLibrary $library)
    {
        $request->validate(['confirmation' => ['required', 'in:ELIMINAR']], [
            'confirmation.in' => __('Escribe ELIMINAR en mayúsculas para confirmar.'),
            'confirmation.required' => __('Escribe ELIMINAR en mayúsculas para confirmar.'),
        ]);

        $family = $request->user()->family;
        $library->deleteFamilyFiles($family);

        $this->sessions->terminate($request);

        // El borrado en cascada elimina usuarios, perfiles, contenidos, televisores y sesiones.
        DB::transaction(fn () => $family->delete());

        return redirect()->route('home')->with('status', __('Tu cuenta y todos los datos de tu familia se han eliminado.'));
    }
}
