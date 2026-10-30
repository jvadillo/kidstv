<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Profile;
use App\Models\User;
use App\Models\VideoList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Primera experiencia: para quién es la tele (nombre y etapa de edad), qué va a ver
 * (listas públicas) y cómo llevarlo a la tele. Se puede hacer sin cuenta: el borrador
 * vive en la sesión y se guarda en la familia al crear la cuenta (o, con sesión, al final).
 */
class Onboarding
{
    public const DRAFT_KEY = 'onboarding';

    /** El correo se pidió desde el asistente (y se aceptaron las condiciones): al verificarlo se guarda el borrador. */
    public const CLAIM_KEY = 'onboarding_claim';

    public const INTENTS = ['connect', 'later'];

    /** @return array{name?: string, age?: string, avatar?: string, lists?: list<int>, intent?: string} */
    public function draft(Request $request): array
    {
        $draft = $request->session()->get(self::DRAFT_KEY);

        return is_array($draft) ? $draft : [];
    }

    public function update(Request $request, array $changes): void
    {
        $request->session()->put(self::DRAFT_KEY, array_merge($this->draft($request), $changes));
    }

    /** Listas públicas que se ofrecen: las de otras familias con algún vídeo listo, las más elegidas primero. */
    public function publicLists(?Family $family): Builder
    {
        return VideoList::public()
            ->when($family, fn (Builder $q) => $q->where('family_id', '!=', $family->id))
            ->whereHas('contents', fn ($q) => $q->ready())
            ->withCount(['contents' => fn ($q) => $q->ready(), 'links'])
            ->withSum(['contents' => fn ($q) => $q->ready()], 'duration')
            ->with(['contents' => fn ($q) => $q->ready()->ordered()])
            ->orderByDesc('links_count')
            ->orderBy('name');
    }

    /** Un avatar que la familia aún no usa (o uno al azar), para no pedirlo en el primer paso. */
    public function pickAvatar(?Family $family): string
    {
        $avatars = config('nokoplay.avatars');
        $free = array_values(array_diff($avatars, $family ? $family->profiles()->pluck('avatar')->all() : [])) ?: $avatars;

        return $free[array_rand($free)];
    }

    /** Familia nueva con sus listas propias de partida y su primer adulto. */
    public function createAccount(string $email, ?string $name = null): User
    {
        return DB::transaction(function () use ($email, $name) {
            if ($existing = User::where('email', $email)->first()) {
                return $existing;
            }

            $family = Family::create(['name' => null]);
            foreach (config('nokoplay.default_lists') as $i => $list) {
                $family->lists()->create(['name' => $list, 'position' => $i, 'languages' => [YouTubeSearch::defaultLanguage()]]);
            }

            return User::create([
                'family_id' => $family->id,
                'email' => $email,
                'name' => $name,
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
            ]);
        });
    }

    /**
     * Guarda el borrador en la familia: crea el perfil y le añade las listas elegidas
     * (las que ya estaban en la videoteca se le asignan sin duplicarlas).
     */
    public function apply(User $user, array $draft): ?Profile
    {
        return $this->applyToFamily($user->family, $draft);
    }

    public function applyToFamily(Family $family, array $draft): ?Profile
    {

        return DB::transaction(function () use ($family, $draft) {
            $profile = null;
            if (filled($draft['name'] ?? null) && $family->profiles()->count() < 12) {
                $profile = $family->profiles()->create([
                    'name' => $draft['name'],
                    'avatar' => in_array($draft['avatar'] ?? null, config('nokoplay.avatars'), true) ? $draft['avatar'] : $this->pickAvatar($family),
                    'age_range' => array_key_exists($draft['age'] ?? '', VideoList::AGE_RANGES) ? $draft['age'] : null,
                    'position' => (int) $family->profiles()->max('position') + 1,
                ]);
            }

            $chosen = array_map('intval', $draft['lists'] ?? []);
            $sources = VideoList::public()->where('family_id', '!=', $family->id)->whereIn('id', $chosen)->get()->keyBy('id');

            // Cada lista nueva se pone arriba del todo: se recorren al revés para que en la
            // tele queden en el orden en que se eligieron.
            foreach (array_reverse(array_values(array_unique(array_filter($chosen, fn ($id) => $sources->has($id))))) as $id) {
                $link = $family->lists()->where('source_id', $id)->first()
                    ?? $family->prependList(['source_id' => $id, 'name' => mb_substr($sources[$id]->name, 0, 40)]);
                if ($profile) {
                    $link->profiles()->syncWithoutDetaching([$profile->id]);
                }
            }

            return $profile;
        });
    }

    /** Último paso, ya con cuenta: se guarda el borrador y se sigue hacia la tele o al panel. */
    public function finish(Request $request, User $user): RedirectResponse
    {
        if ($request->session()->has('onboarding_pairing')) {
            return redirect()->route('onboarding.tv.confirm');
        }
        $draft = $this->draft($request);
        $request->session()->forget([self::DRAFT_KEY, self::CLAIM_KEY]);

        if (! filled($draft['name'] ?? null)) {
            return redirect()->route($user->family->profiles()->exists() ? 'panel' : 'onboarding.profile');
        }

        $profile = $this->apply($user, $draft);
        $name = $profile?->name ?? $draft['name'];

        if (($draft['intent'] ?? null) === 'connect') {
            return redirect()->route('onboarding.connect');
        }

        return redirect()->route('panel')->with('status', __('¡La tele de :name está preparada! Conéctala cuando quieras desde «Conectar televisión».', ['name' => $name]));
    }
}
