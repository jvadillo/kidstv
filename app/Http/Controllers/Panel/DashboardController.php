<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\MediaLibrary;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(MediaLibrary $library)
    {
        $family = $this->family();

        return view('panel.dashboard', [
            'family' => $family,
            'profiles' => $family->profiles()->withCount('contents')->get(),
            'recent' => $family->contents()->with('list')->latest('id')->limit(6)->get(),
            'contentCount' => $family->contents()->count(),
            'devices' => $family->devices()->latest('last_seen_at')->get(),
            'storageUsed' => $family->storageUsed(),
            'storageQuota' => $library->quotaBytes(),
        ]);
    }

    /** Asistente de primeros pasos: perfil infantil → primer contenido → probar o conectar TV. */
    public function welcome(Request $request)
    {
        $family = $this->family();
        $hasProfile = $family->profiles()->exists();
        $hasContent = $family->contents()->exists();

        $step = match (true) {
            ! $hasProfile => 1,
            ! $hasContent && $request->query('paso') !== '3' => 2,
            default => 3,
        };

        return view('panel.welcome', [
            'step' => $step,
            'avatars' => config('kidstv.avatars'),
            'profiles' => $family->profiles,
            'hasPin' => $family->hasPin(),
        ]);
    }
}
