<?php

namespace App\Console\Commands;

use App\Models\CodigoPostal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga el Catálogo Nacional de Códigos Postales (SEPOMEX) en la tabla `codigos_postales`
 * a partir del script SQL comprimido incluido en el proyecto.
 */
class CargarCodigosPostales extends Command
{
    protected $signature = 'sigue:codigos-postales
        {archivo=database/sql-produccion/04_codigos_postales.sql.gz : Script .sql o .sql.gz con los INSERT del catálogo}';

    protected $description = 'Carga el catálogo SEPOMEX de códigos postales (reemplaza el contenido de la tabla)';

    public function handle(): int
    {
        $ruta = base_path($this->argument('archivo'));
        if (! is_file($ruta)) {
            $this->error("No existe el archivo: {$this->argument('archivo')}");
            return self::FAILURE;
        }

        $fh = str_ends_with($ruta, '.gz') ? gzopen($ruta, 'r') : fopen($ruta, 'r');
        $read = str_ends_with($ruta, '.gz') ? fn () => gzgets($fh) : fn () => fgets($fh);

        DB::table('codigos_postales')->truncate();

        $stmt = '';
        $inserts = 0;
        while (($line = $read()) !== false) {
            $trim = trim($line);
            if ($stmt === '' && ($trim === '' || str_starts_with($trim, '--'))) {
                continue;
            }
            $stmt .= $line;
            if (! str_ends_with($trim, ';')) {
                continue;
            }
            // Solo los INSERT del catálogo; se ignoran CREATE/TRUNCATE/SET y el registro en `migrations`.
            if (str_starts_with(ltrim($stmt), 'INSERT INTO `codigos_postales`')) {
                DB::unprepared($stmt);
                $inserts++;
                $this->output->write('.');
            }
            $stmt = '';
        }
        str_ends_with($ruta, '.gz') ? gzclose($fh) : fclose($fh);

        $this->newLine();
        $this->info('Bloques insertados: ' . $inserts);
        $this->info('Códigos postales cargados: ' . number_format(CodigoPostal::count()) . ' colonias en '
            . number_format(CodigoPostal::distinct('cp')->count('cp')) . ' códigos postales.');

        return self::SUCCESS;
    }
}
