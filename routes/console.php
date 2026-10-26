<?php

use App\Models\AuthSession;
use App\Models\Content;
use App\Models\DevicePairing;
use App\Models\LoginToken;
use App\Models\Play;
use App\Models\Upload;
use App\Models\User;
use App\Services\YouTubeSearch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('kidstv:prune', function () {
    $tokens = LoginToken::where('expires_at', '<', now()->subDay())->delete();
    $sessions = AuthSession::where('expires_at', '<', now())->delete();
    $pairings = DevicePairing::where('expires_at', '<', now()->subHour())->delete();
    // Las estadísticas de uso se conservan un año (ver política de privacidad).
    $plays = Play::where('created_at', '<', now()->subMonths(12))->delete();

    $uploads = 0;
    foreach (Upload::where('expires_at', '<', now())->get() as $upload) {
        Storage::disk('local')->delete($upload->tempPath());
        $upload->delete();
        $uploads++;
    }

    $this->info("Eliminados: {$tokens} códigos, {$sessions} sesiones, {$pairings} vinculaciones, {$uploads} subidas abandonadas, {$plays} registros de uso antiguos.");
})->purpose('Elimina códigos, sesiones, vinculaciones, subidas caducadas y estadísticas de uso antiguas');

Artisan::command('kidstv:login-link {email}', function (string $email) {
    // Herramienta de administración (requiere acceso al servidor): enlace de acceso de un
    // solo uso para una cuenta existente, útil si el envío de correo no está configurado.
    $user = User::where('email', Str::lower($email))->first();
    if (! $user) {
        $this->error('No existe ninguna cuenta con ese correo.');

        return 1;
    }

    $plain = Str::random(48);
    LoginToken::create([
        'purpose' => LoginToken::PURPOSE_LOGIN,
        'email' => $user->email,
        'user_id' => $user->id,
        'link_hash' => hash('sha256', $plain),
        'code_hash' => bcrypt(Str::random(32)),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->line(route('login.link', $plain));
    $this->comment('Válido durante 15 minutos y un solo uso.');
})->purpose('Genera un enlace de acceso de un solo uso para una cuenta existente');

Artisan::command('kidstv:admin {email} {--revoke}', function (string $email) {
    // Herramienta de administración (requiere acceso al servidor): da o quita el acceso al
    // panel de administración de la plataforma.
    $user = User::where('email', Str::lower($email))->first();
    if (! $user) {
        $this->error('No existe ninguna cuenta con ese correo.');

        return 1;
    }

    $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();
    $this->info($user->is_admin ? "{$user->email} ya es administrador." : "{$user->email} ya no es administrador.");
})->purpose('Da (o quita con --revoke) permisos de administrador a una cuenta');

Artisan::command('kidstv:youtube-durations', function (YouTubeSearch $search) {
    // Los vídeos añadidos sin la API configurada (o antes de guardar la duración) no la tienen.
    $missing = Content::where('type', Content::TYPE_YOUTUBE)->whereNull('duration')->get(['id', 'youtube_id']);
    $durations = $search->durations($missing->pluck('youtube_id')->all());

    $updated = 0;
    foreach ($missing as $content) {
        if (isset($durations[$content->youtube_id])) {
            Content::whereKey($content->id)->update(['duration' => $durations[$content->youtube_id]]);
            $updated++;
        }
    }

    $this->info("Duración completada en {$updated} de {$missing->count()} vídeos de YouTube.");
})->purpose('Completa la duración de los vídeos de YouTube que no la tienen (requiere la API)');

Artisan::command('kidstv:backup {--keep=7}', function () {
    // Copia consistente de la base de datos SQLite (VACUUM INTO) dentro del volumen de datos.
    $dir = storage_path('backups');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // VACUUM INTO falla si el archivo ya existe: dos copias en el mismo segundo no deben chocar.
    $base = $dir.'/kidstv-'.now()->format('Ymd-His');
    $target = $base.'.sqlite';
    for ($i = 2; file_exists($target); $i++) {
        $target = $base.'-'.$i.'.sqlite';
    }
    DB::statement('VACUUM INTO ?', [$target]);

    $files = glob($dir.'/kidstv-*.sqlite');
    rsort($files);
    foreach (array_slice($files, (int) $this->option('keep')) as $old) {
        unlink($old);
    }

    $this->info('Copia creada: '.$target);
})->purpose('Copia de seguridad de la base de datos (conserva las últimas N)');

Schedule::command('kidstv:prune')->hourly();
// En UTC: con la hora de Madrid, el cambio de horario de octubre repite la franja de las 2-3 h.
Schedule::command('kidstv:backup')->timezone('UTC')->dailyAt('01:40')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('kidstv:youtube-durations')->dailyAt('04:10');
