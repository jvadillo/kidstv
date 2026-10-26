<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessUploadedMedia;
use App\Models\Content;
use App\Models\Upload;
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Subida de vídeos y audios por fragmentos: cada petición es pequeña (no choca con
 * límites del proxy) y una conexión móvil inestable puede reintentar el último trozo.
 */
class UploadController extends Controller
{
    use ValidatesContentMetadata;

    public function __construct(private MediaLibrary $library) {}

    public function start(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in([Content::TYPE_VIDEO, Content::TYPE_AUDIO])],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $family = $this->family();
        $extension = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
        $allowed = $this->library->extensions($data['kind']);

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages(['file' => __('Formato no admitido. Usa :formats.', ['formats' => strtoupper(implode(', ', $allowed))])]);
        }
        if ($data['size'] > $this->library->maxBytes($data['kind'])) {
            throw ValidationException::withMessages(['file' => __('El archivo supera el máximo de :max.', ['max' => format_bytes($this->library->maxBytes($data['kind']))])]);
        }
        if ($error = $this->library->capacityError($family, $data['size'])) {
            throw ValidationException::withMessages(['file' => $error]);
        }

        $upload = $family->uploads()->create([
            'kind' => $data['kind'],
            'original_name' => $data['name'],
            'size' => $data['size'],
            'expires_at' => now()->addDay(),
        ]);
        Storage::disk('local')->put($upload->tempPath(), '');

        return response()->json([
            'id' => $upload->id,
            'chunk_size' => config('kidstv.upload_chunk_mb') * 1024 * 1024,
            'chunk_url' => route('uploads.chunk', $upload->id),
            'complete_url' => route('uploads.complete', $upload->id),
        ], 201);
    }

    public function chunk(Request $request, string $upload)
    {
        $upload = $this->findUpload($upload);
        $offset = (int) $request->header('X-Upload-Offset', -1);
        $length = (int) $request->header('Content-Length', 0);
        $maxChunk = config('kidstv.upload_chunk_mb') * 1024 * 1024;

        // Reintento de un fragmento ya recibido: se confirma sin volver a escribirlo.
        if ($offset >= 0 && $offset + $length <= $upload->received) {
            return response()->json(['received' => $upload->received]);
        }

        if ($offset !== $upload->received || $length < 1 || $length > $maxChunk || $offset + $length > $upload->size) {
            return response()->json(['error' => 'bad_offset', 'received' => $upload->received], 409);
        }

        $target = fopen(Storage::disk('local')->path($upload->tempPath()), 'c');
        fseek($target, $offset);
        $written = stream_copy_to_stream($request->getContent(true), $target, $length);
        fclose($target);

        if ($written !== $length) {
            return response()->json(['error' => 'incomplete_chunk', 'received' => $upload->received], 409);
        }

        $upload->update(['received' => $offset + $written]);

        return response()->json(['received' => $upload->received]);
    }

    public function complete(Request $request, string $upload)
    {
        $upload = $this->findUpload($upload);
        $data = $this->validatedMetadata($request, titleRequired: true);

        if (! $upload->isComplete()) {
            throw ValidationException::withMessages(['file' => __('La subida no se ha completado. Vuelve a intentarlo.')]);
        }

        $tempPath = Storage::disk('local')->path($upload->tempPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tempPath) ?: 'application/octet-stream';
        $allowedMimes = config($upload->kind === Content::TYPE_AUDIO ? 'kidstv.audio_mimes' : 'kidstv.video_mimes');

        if (! in_array($mime, $allowedMimes, true)) {
            $this->discard($upload);

            throw ValidationException::withMessages(['file' => $upload->kind === Content::TYPE_AUDIO
                ? __('El archivo no parece un audio MP3 o M4A.')
                : __('El archivo no parece un vídeo MP4.')]);
        }

        $family = $this->family();
        $path = $this->library->finalizeUpload($upload);

        $content = $family->contents()->create([
            'type' => $upload->kind,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'list_id' => $data['list_id'] ?? null,
            'status' => Content::STATUS_PROCESSING,
            'file_path' => $path,
            'mime' => $upload->kind === Content::TYPE_AUDIO
                ? ($upload->extension() === 'm4a' ? 'audio/mp4' : 'audio/mpeg')
                : 'video/mp4',
            'file_size' => $upload->size,
        ]);
        $content->profiles()->sync($data['profile_ids'] ?? []);

        if ($request->hasFile('cover')) {
            $content->update(['cover_path' => $this->library->storeCover($family, $request->file('cover'))]);
        }

        $upload->delete();
        ProcessUploadedMedia::dispatch($content->id);

        $request->session()->flash('status', __('«:title» subido. Lo estamos preparando; en unos momentos estará listo.', ['title' => $content->title]));

        return response()->json([
            'redirect' => $request->boolean('from_welcome') ? route('welcome') : route('contents.edit', $content),
        ]);
    }

    public function cancel(string $upload)
    {
        $this->discard($this->findUpload($upload));

        return response()->json(['ok' => true]);
    }

    private function findUpload(string $id): Upload
    {
        return $this->family()->uploads()->whereKey($id)->where('expires_at', '>', now())->firstOrFail();
    }

    private function discard(Upload $upload): void
    {
        Storage::disk('local')->delete($upload->tempPath());
        $upload->delete();
    }
}
