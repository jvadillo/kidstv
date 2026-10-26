<?php

use App\Models\AuthSession;
use App\Models\DevicePairing;
use App\Models\LoginToken;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('kidstv:prune', function () {
    $tokens = LoginToken::where('expires_at', '<', now()->subDay())->delete();
    $sessions = AuthSession::where('expires_at', '<', now())->delete();
    $pairings = DevicePairing::where('expires_at', '<', now()->subHour())->delete();

    $uploads = 0;
    foreach (Upload::where('expires_at', '<', now())->get() as $upload) {
        Storage::disk('local')->delete($upload->tempPath());
        $upload->delete();
        $uploads++;
    }

    $this->info("Eliminados: {$tokens} códigos, {$sessions} sesiones, {$pairings} vinculaciones, {$uploads} subidas abandonadas.");
})->purpose('Elimina códigos, sesiones, vinculaciones y subidas caducadas');

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
