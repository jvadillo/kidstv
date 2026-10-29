<?php

namespace App\Http\Controllers;

use App\Models\VideoList;
use App\Services\Onboarding;
use App\Services\YouTubeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

/**
 * Asistente de primeros pasos en tres decisiones: para quién, qué quiere ver y cómo
 * llevarlo a la tele. Los tres primeros pasos no necesitan cuenta; el correo se pide
 * solo para guardar la tele y conectarla.
 */
class OnboardingController extends Controller
{
    public function __construct(private Onboarding $onboarding) {}

    /** Paso 1: nombre y etapa de edad en dos pantallas. */
    public function profile(Request $request)
    {
        return view('onboarding.profile', ['draft' => $this->onboarding->draft($request)]);
    }

    public function storeProfile(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:40']], [
            'name.required' => __('Escribe su nombre o un apodo.'),
        ]);

        $draft = $this->onboarding->draft($request);
        $this->onboarding->update($request, [
            'name' => trim($data['name']),
            'avatar' => $draft['avatar'] ?? $this->onboarding->pickAvatar($request->user()?->family),
        ]);

        return redirect()->route('onboarding.age');
    }

    public function age(Request $request)
    {
        $draft = $this->onboarding->draft($request);
        if (! filled($draft['name'] ?? null)) {
            return redirect()->route('onboarding.profile');
        }

        return view('onboarding.age', ['draft' => $draft, 'ages' => VideoList::AGE_RANGES]);
    }

    public function storeAge(Request $request)
    {
        if (! filled($this->onboarding->draft($request)['name'] ?? null)) {
            return redirect()->route('onboarding.profile');
        }

        $data = $request->validate(['age' => ['required', Rule::in(array_keys(VideoList::AGE_RANGES))]], [
            'age.required' => __('Elige su edad para proponerte series adecuadas.'),
        ]);
        $this->onboarding->update($request, ['age' => $data['age']]);

        return redirect()->route('onboarding.lists');
    }

    /** Paso 2: catálogo de listas públicas, filtrado de entrada por su edad y el idioma de la plataforma. */
    public function lists(Request $request)
    {
        $draft = $this->onboarding->draft($request);
        if (! filled($draft['name'] ?? null)) {
            return redirect()->route('onboarding.profile');
        }

        if (! array_key_exists($draft['age'] ?? '', VideoList::AGE_RANGES)) {
            return redirect()->route('onboarding.age');
        }

        $lists = $this->onboarding->publicLists($request->user()?->family)->get();
        $languages = collect(VideoList::languageOptions())->only($lists->flatMap->languageCodes()->unique()->all());

        $language = $request->query('idioma', $languages->has(YouTubeSearch::defaultLanguage()) ? YouTubeSearch::defaultLanguage() : '');
        $age = $request->query('edad', $draft['age']);

        return view('onboarding.lists', [
            'draft' => $draft,
            'lists' => $lists,
            'languages' => $languages,
            'filters' => [
                'idioma' => $languages->has((string) $language) ? $language : '',
                'edad' => array_key_exists((string) $age, VideoList::AGE_RANGES) ? $age : '',
            ],
            'selected' => $draft['lists'] ?? [],
        ]);
    }

    public function storeLists(Request $request)
    {
        if (! filled($this->onboarding->draft($request)['name'] ?? null)) {
            return redirect()->route('onboarding.profile');
        }

        if (! array_key_exists($this->onboarding->draft($request)['age'] ?? '', VideoList::AGE_RANGES)) {
            return redirect()->route('onboarding.age');
        }

        $available = $this->onboarding->publicLists($request->user()?->family)->pluck('id')->all();
        $data = $request->validate([
            'lists' => ['required', 'array', 'max:60'],
            'lists.*' => ['integer', Rule::in($available)],
        ], [
            'lists.required' => __('Elige al menos una serie.'),
        ]);

        $this->onboarding->update($request, ['lists' => array_values(array_unique(array_map('intval', $data['lists'])))]);

        return redirect()->route('onboarding.preview');
    }

    /** Paso 3: así se verá su tele, con las listas elegidas como filas. */
    public function preview(Request $request)
    {
        $draft = $this->onboarding->draft($request);
        if (! filled($draft['name'] ?? null)) {
            return redirect()->route('onboarding.profile');
        }
        if (empty($draft['lists'])) {
            return redirect()->route('onboarding.lists');
        }

        $lists = $this->onboarding->publicLists($request->user()?->family)->whereIn('id', $draft['lists'])->get()->keyBy('id');

        return view('onboarding.preview', [
            'draft' => $draft,
            // Las filas, en el orden en que quedarán en la tele.
            'rows' => collect($draft['lists'])->filter(fn ($id) => $lists->has($id))->map(fn ($id) => $lists[$id])->values(),
            'guest' => ! $request->user(),
        ]);
    }

    public function choose(Request $request)
    {
        $data = $request->validate(['next' => ['required', Rule::in(Onboarding::INTENTS)]]);
        $this->onboarding->update($request, ['intent' => $data['next']]);

        if ($user = $request->user()) {
            return $this->onboarding->finish($request, $user);
        }

        return redirect()->route('onboarding.account');
    }

    /** Guardar la tele: el correo (sin contraseña) y las condiciones, en una sola pantalla. */
    public function account(Request $request)
    {
        $draft = $this->onboarding->draft($request);
        if (! filled($draft['name'] ?? null)) {
            return redirect()->route('onboarding.profile');
        }

        return view('onboarding.account', [
            'draft' => $draft,
            'formToken' => Crypt::encryptString((string) now()->timestamp),
        ]);
    }

    public function code(Request $request)
    {
        $email = $request->session()->get('login_email');
        if (! $email || ! $request->session()->get(Onboarding::CLAIM_KEY)) {
            return redirect()->route('onboarding.account');
        }

        return view('onboarding.code', ['email' => $email, 'draft' => $this->onboarding->draft($request)]);
    }

    /** Con la cuenta ya creada: vincular la tele con el código que muestra. */
    public function connect()
    {
        return view('onboarding.connect', [
            'profile' => $this->family()->profiles()->latest('id')->first(),
        ]);
    }
}
