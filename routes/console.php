<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cirugias:check-reprogramar')->hourly();

/*
 * Sólo el modo reporte. Borrar archivos clínicos es una decisión que alguien
 * tiene que mirar y confirmar a mano —`php artisan documentos:limpiar-huerfanos
 * --borrar`—, no algo que corra sola en un cron.
 *
 * Un comando programado no imprime a ninguna pantalla que alguien mire, así
 * que sin appendOutputTo() el reporte semanal se perdería en el aire. Queda en
 * su propio archivo, no en storage/logs/laravel.log, para no mezclar una tabla
 * de texto con el resto de las entradas del log.
 */
Schedule::command('documentos:limpiar-huerfanos')
    ->weekly()
    ->appendOutputTo(storage_path('logs/documentos-huerfanos.log'));
