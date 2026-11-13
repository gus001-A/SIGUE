<?php

namespace App\Models\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Lista de archivos adjuntos en la columna JSON `files` ([{path, name, size}]), máximo MAX_FILES.
 * Los archivos se sirven por índice: route($ruta, [$modelo, $i]).
 */
trait HasFiles
{
    public const MAX_FILES = 5;

    public function initializeHasFiles(): void
    {
        $this->mergeCasts(['files' => 'array']);
    }

    /** @return array<int, array{path:string,name:string,size:?int}> */
    public function fileList(): array
    {
        return array_values($this->files ?? []);
    }

    /** Enlaces para la vista: nombre, url, tamaño. */
    public function fileLinks(string $routeName): array
    {
        return collect($this->fileList())->map(fn ($f, $i) => [
            'index' => $i, 'name' => $f['name'], 'size' => $f['size'] ?? null, 'url' => route($routeName, [$this, $i]),
        ])->all();
    }

    /** Quita los índices indicados (el archivo físico se conserva: eliminación lógica) y agrega los nuevos. */
    public function syncFiles(array $remove, array $uploads, string $dir): void
    {
        $list = collect($this->fileList())->reject(fn ($f, $i) => in_array($i, array_map('intval', $remove), true))->values();
        foreach ($uploads as $file) {
            if ($file instanceof UploadedFile) {
                $list->push(['path' => $file->store($dir, 'local'), 'name' => $file->getClientOriginalName(), 'size' => $file->getSize()]);
            }
        }
        $this->files = $list->take(self::MAX_FILES)->values()->all();
    }

    /** Cuántos archivos quedarían tras quitar y agregar (para validar el máximo). */
    public function countAfter(array $remove, int $adding): int
    {
        return count($this->fileList()) - count(array_intersect(array_keys($this->fileList()), array_map('intval', $remove))) + $adding;
    }
}
