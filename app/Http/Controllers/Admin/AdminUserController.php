<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthSession;
use App\Models\Content;
use App\Models\Device;
use App\Models\Play;
use App\Models\Profile;
use App\Models\User;
use App\Services\MediaLibrary;
use App\Services\UsageStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Gestión de cuentas de adulto (cada una con su familia). */
class AdminUserController extends Controller
{
    public const SORTS = ['recientes', 'actividad', 'uso', 'espacio'];

    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'orden' => ['nullable', Rule::in(self::SORTS)],
            'estado' => ['nullable', Rule::in(['bloqueadas', 'administradores'])],
        ]);
        $sort = $data['orden'] ?? 'recientes';
        $since = now()->subDays(30);

        $users = User::query()
            ->select('users.*')
            ->addSelect([
                'last_seen_at' => AuthSession::selectRaw('max(last_used_at)')->whereColumn('user_id', 'users.id'),
                'minutes_30d' => Play::selectRaw('coalesce(sum(seconds), 0) / 60')->whereColumn('family_id', 'users.family_id')->where('created_at', '>=', $since),
                'storage' => Content::selectRaw('coalesce(sum(file_size), 0)')->whereColumn('family_id', 'users.family_id'),
                'profiles_count' => Profile::selectRaw('count(*)')->whereColumn('family_id', 'users.family_id'),
                'contents_count' => Content::selectRaw('count(*)')->whereColumn('family_id', 'users.family_id'),
                'devices_count' => Device::selectRaw('count(*)')->whereColumn('family_id', 'users.family_id'),
            ])
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('email', 'like', '%'.$term.'%')
                ->orWhere('name', 'like', '%'.$term.'%')))
            ->when(($data['estado'] ?? null) === 'bloqueadas', fn ($q) => $q->whereNotNull('blocked_at'))
            ->when(($data['estado'] ?? null) === 'administradores', fn ($q) => $q->where('is_admin', true))
            ->when($sort === 'actividad', fn ($q) => $q->orderByRaw('last_seen_at is null')->orderByDesc('last_seen_at'))
            ->when($sort === 'uso', fn ($q) => $q->orderByDesc('minutes_30d'))
            ->when($sort === 'espacio', fn ($q) => $q->orderByDesc('storage'))
            ->orderByDesc('users.id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'query' => $data['q'] ?? '',
            'sort' => $sort,
            'state' => $data['estado'] ?? '',
        ]);
    }

    public function show(User $user, UsageStats $stats, MediaLibrary $library)
    {
        $family = $user->family;
        $since = now()->subDays(30);

        return view('admin.users.show', [
            'user' => $user,
            'family' => $family,
            'lastSeen' => $user->authSessions()->max('last_used_at'),
            'sessions' => $user->authSessions()->where('expires_at', '>', now())->count(),
            'profiles' => $family->profiles()->withCount('contents')->get(),
            'devices' => $family->devices()->latest('last_seen_at')->get(),
            'contents' => $family->contents()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'lists' => $family->lists()->with('source')->get(),
            'storage' => $family->storageUsed(),
            'quota' => $library->quotaBytes(),
            'minutes' => [
                'week' => $stats->minutes(now()->subDays(7), $family->id),
                'month' => $stats->minutes($since, $family->id),
                'total' => intdiv((int) $family->plays()->sum('seconds'), 60),
            ],
            'daily' => $stats->daily(30, $family->id),
            'byType' => $stats->byType($since, $family->id),
        ]);
    }

    /** Bloquear: sin acceso al panel y con los televisores de la familia sin servicio. */
    public function block(Request $request, User $user)
    {
        $this->guardSelf($request, $user);
        $user->forceFill(['blocked_at' => now()])->save();
        $user->authSessions()->delete();

        return back()->with('status', __('Cuenta de :email bloqueada. Se han cerrado sus sesiones y sus teles dejan de funcionar.', ['email' => $user->email]));
    }

    public function unblock(User $user)
    {
        $user->forceFill(['blocked_at' => null])->save();

        return back()->with('status', __('Cuenta de :email desbloqueada.', ['email' => $user->email]));
    }

    public function admin(Request $request, User $user)
    {
        $this->guardSelf($request, $user);
        $user->forceFill(['is_admin' => $request->boolean('is_admin')])->save();

        return back()->with('status', $user->is_admin
            ? __(':email ahora es administrador.', ['email' => $user->email])
            : __(':email ya no es administrador.', ['email' => $user->email]));
    }

    public function destroy(Request $request, User $user, MediaLibrary $library)
    {
        $this->guardSelf($request, $user);
        $request->validate(['confirmation' => ['required', Rule::in([$user->email])]], [
            'confirmation.required' => __('Escribe el correo de la cuenta para confirmar.'),
            'confirmation.in' => __('Escribe el correo de la cuenta para confirmar.'),
        ]);

        $family = $user->family;
        $library->deleteFamilyFiles($family);
        // El borrado en cascada elimina usuarios, perfiles, contenidos, televisores y sesiones.
        DB::transaction(fn () => $family->delete());

        return redirect()->route('admin.users.index')->with('status', __('Cuenta de :email y todos los datos de su familia eliminados.', ['email' => $user->email]));
    }

    /** Sobre la propia cuenta se actúa desde «Cuenta», nunca desde aquí. */
    private function guardSelf(Request $request, User $user): void
    {
        abort_if($request->user()->is($user), 422, __('No puedes hacer esto con tu propia cuenta.'));
    }
}
