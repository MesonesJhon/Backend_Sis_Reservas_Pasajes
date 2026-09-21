<?php

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory utilizada para generar usuarios durante
 * las pruebas automatizadas del sistema.
 *
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    /**
     * Modelo asociado a esta Factory.
     */
    protected $model = Usuario::class;

    /**
     * Contraseña reutilizada durante la ejecución
     * de las pruebas para evitar generar el hash
     * repetidamente.
     */
    protected static ?string $password = null;

    /**
     * Define los valores predeterminados de un usuario
     * utilizado durante las pruebas.
     */
    public function definition(): array
    {
        return [
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),

            'correo' => fake()
                ->unique()
                ->safeEmail(),

            'correo_verificado_en' => now(),

            'contrasena' => static::$password ??=
                Hash::make('Password123!'),

            'activo' => true,

            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Permite crear fácilmente un usuario inactivo
     * cuando una prueba lo necesite.
     */
    public function inactivo(): static
    {
        return $this->state(fn () => [
            'activo' => false,
        ]);
    }
}
