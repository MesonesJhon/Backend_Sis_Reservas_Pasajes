<?php

namespace Tests\Helpers;

use App\Enums\EstadoVehiculo;
use App\Enums\FuncionPersonalViaje;
use App\Enums\TipoAsiento;
use App\Enums\TipoPunto;
use App\Models\Asiento;
use App\Models\Punto;
use App\Models\PuntoRuta;
use App\Models\Rol;
use App\Models\Ruta;
use App\Models\TarifaViaje;
use App\Models\TipoVehiculo;
use App\Models\Usuario;
use App\Models\Vehiculo;
use App\Models\Viaje;
use Carbon\Carbon;

class CrearViajeProgramable
{
    public static function ejecutar(
        ?Usuario $conductor = null,
        ?Vehiculo $vehiculo = null,
        ?string $salida = null
    ): Viaje {
        /*
         * Creamos los puntos físicos.
         */
        $origen = Punto::factory()->create([
            'nombre' => 'Terminal Chiclayo',
            'tipo' => TipoPunto::TERMINAL,
            'activo' => true,
        ]);

        $destino = Punto::factory()->create([
            'nombre' => 'Terminal Chota',
            'tipo' => TipoPunto::TERMINAL,
            'activo' => true,
        ]);

        /*
         * Creamos la ruta maestra.
         */
        $ruta = Ruta::factory()->create([
            'duracion_estimada_minutos' => 360,
            'activo' => true,
        ]);

        PuntoRuta::create([
            'ruta_id' => $ruta->id,
            'punto_id' => $origen->id,
            'orden' => 1,
            'permite_embarque' => true,
            'permite_desembarque' => false,
            'minutos_desde_origen' => 0,
        ]);

        PuntoRuta::create([
            'ruta_id' => $ruta->id,
            'punto_id' => $destino->id,
            'orden' => 2,
            'permite_embarque' => false,
            'permite_desembarque' => true,
            'minutos_desde_origen' => 360,
        ]);

        /*
         * Si no recibimos vehículo, creamos uno operativo.
         */
        if (! $vehiculo) {
            $tipoVehiculo = TipoVehiculo::query()->firstOrFail();

            $vehiculo = Vehiculo::factory()->create([
                'tipo_vehiculo_id' => $tipoVehiculo->id,
                'estado' => EstadoVehiculo::OPERATIVO,
                'activo' => true,
                'capacidad' => 30,
                'numero_pisos' => 1,
            ]);

            Asiento::create([
                'vehiculo_id' => $vehiculo->id,
                'codigo' => 'A1',
                'numero' => 1,
                'piso' => 1,
                'fila' => 1,
                'columna' => 1,
                'tipo' => TipoAsiento::NORMAL,
                'caracteristicas' => [],
                'activo' => true,
            ]);
        }

        /*
         * Creamos un conductor si el test no proporciona uno.
         */
        if (! $conductor) {
            $conductor = Usuario::factory()->create([
                'activo' => true,
            ]);

            $rolConductor = Rol::query()
                ->where('nombre', 'CONDUCTOR')
                ->firstOrFail();

            $conductor->roles()->attach($rolConductor->id);
        }

        $salida = $salida
            ? Carbon::parse($salida)
            : now()->addDays(5)->setTime(8, 0);

        $viaje = Viaje::factory()->create([
            'ruta_id' => $ruta->id,
            'vehiculo_id' => $vehiculo->id,
            'salida_programada' => $salida,
            'llegada_estimada' =>
                $salida->copy()->addMinutes(360),
        ]);

        $viaje->personal()->create([
            'usuario_id' => $conductor->id,
            'funcion' => FuncionPersonalViaje::CONDUCTOR,
        ]);

        TarifaViaje::create([
            'viaje_id' => $viaje->id,
            'punto_origen_id' => $origen->id,
            'punto_destino_id' => $destino->id,
            'precio' => 50.00,
            'activo' => true,
        ]);

        return $viaje->fresh();
    }
}
