<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\ActivityReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Pantalla «Actividad»: qué y cuánto ven los niños, por día, semana o mes. */
class ActivityController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'perfil' => ['nullable', 'integer'],
            'periodo' => ['nullable', Rule::in(ActivityReport::PERIODS)],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $family = $this->family();
        $profiles = $family->profiles;
        $profile = isset($data['perfil']) ? $profiles->firstWhere('id', (int) $data['perfil']) : null;

        // Nunca en el futuro ni más atrás de lo que se conservan los datos.
        $date = isset($data['fecha']) ? Carbon::createFromFormat('!Y-m-d', $data['fecha']) : today();
        $date = $date->max(today()->subMonths(ActivityReport::RETENTION_MONTHS))->min(today());

        $report = new ActivityReport($family, $profile, $data['periodo'] ?? 'semana', $date);

        return view('panel.activity.show', [
            'report' => $report,
            'profiles' => $profiles,
            'profile' => $profile,
            'watching' => ActivityReport::watchingNow($family, $profile),
            // Enlaces que conservan el resto de filtros.
            'query' => array_filter([
                'perfil' => $profile?->id,
                'periodo' => $report->period === 'semana' ? null : $report->period,
                'fecha' => $report->isCurrent() ? null : $report->start->toDateString(),
            ]),
        ]);
    }
}
