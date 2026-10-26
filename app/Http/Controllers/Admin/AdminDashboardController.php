<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Device;
use App\Models\User;
use App\Models\VideoList;
use App\Services\UsageStats;

/** Resumen del uso de toda la plataforma. */
class AdminDashboardController extends Controller
{
    public function __invoke(UsageStats $stats)
    {
        $publicLists = VideoList::public()->withCount('links')->get();
        $publicIds = $publicLists->pluck('id')->all();

        return view('admin.dashboard', [
            'users' => [
                'total' => User::count(),
                'new7' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'new30' => User::where('created_at', '>=', now()->subDays(30))->count(),
                'blocked' => User::whereNotNull('blocked_at')->count(),
            ],
            'activeFamilies7' => $stats->activeFamilies(now()->subDays(7)),
            'activeFamilies30' => $stats->activeFamilies(now()->subDays(30)),
            'minutes' => [
                'today' => $stats->minutes(today()),
                'week' => $stats->minutes(now()->subDays(7)),
                'month' => $stats->minutes(now()->subDays(30)),
            ],
            'daily' => $stats->daily(30),
            'byType' => $stats->byType(now()->subDays(30)),
            'devices' => [
                'total' => Device::count(),
                'active7' => Device::where('last_seen_at', '>=', now()->subDays(7))->count(),
                'byPlatform' => Device::selectRaw('platform, count(*) as total')->groupBy('platform')->pluck('total', 'platform'),
            ],
            'contents' => Content::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'storage' => (int) Content::sum('file_size'),
            'publicLists' => $publicLists,
            'topPublic' => $stats->topContents(now()->subDays(30), $publicIds),
        ]);
    }
}
