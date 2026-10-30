<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\MediaLibrary;

class DashboardController extends Controller
{
    public function __invoke(MediaLibrary $library)
    {
        $family = $this->family();

        return view('panel.dashboard', [
            'family' => $family,
            'profiles' => $family->profiles()->withCount('contents')->with(['lists.source' => fn ($q) => $q->withCount(['contents' => fn ($q) => $q->ready()])])->get(),
            'recent' => $family->contents()->with('list')->latest('id')->limit(6)->get(),
            'contentCount' => $family->contents()->count(),
            'linkedCount' => $family->lists()->whereNotNull('source_id')->count(),
            'devices' => $family->devices()->latest('last_seen_at')->get(),
            'storageUsed' => $family->storageUsed(),
            'storageQuota' => $library->quotaBytes(),
        ]);
    }
}
