<?php

namespace App\Actions\Viajes;

use App\Models\AsientoViaje;
use App\Models\Viaje;

/**
 * Genera el inventario de asientos de un viaje.
 *
 * Copia la configuración de los asientos activos del vehículo
 * hacia asientos_viaje para evitar que cambios posteriores
 * del vehículo alteren viajes que ya fueron programados.
 */
class ConsolidarAsientosViaje
{
    public function ejecutar(Viaje $viaje): void
    {
        /*
         * Obtenemos únicamente los asientos activos porque
         * son los que forman parte del inventario comercial.
         */
        $asientos = $viaje->vehiculo
            ->asientos()
            ->where('activo', true)
            ->orderBy('piso')
            ->orderBy('fila')
            ->orderBy('columna')
            ->get();

        /*
         * Esta acción se ejecuta durante la transición
         * BORRADOR -> PROGRAMADO.
         *
         * Eliminamos cualquier snapshot previo para garantizar
         * que la consolidación sea consistente.
         */
        $viaje->asientosViaje()->delete();

        foreach ($asientos as $asiento) {
            AsientoViaje::create([
                'viaje_id' => $viaje->id,
                'asiento_id' => $asiento->id,

                /*
                 * Copiamos los datos físicos.
                 * A partir de este momento pertenecen al
                 * contexto histórico del viaje.
                 */
                'codigo' => $asiento->codigo,
                'numero' => $asiento->numero,
                'piso' => $asiento->piso,
                'fila' => $asiento->fila,
                'columna' => $asiento->columna,
                'tipo' => $asiento->tipo,
                'caracteristicas' => $asiento->caracteristicas,
                'activo' => true,
            ]);
        }
    }
}
