<?php
require_once __DIR__.'/Helpers/UsuariosTest.php';
/*
|--------------------------------------------------------------------------
| Configuración global de Pest
|--------------------------------------------------------------------------
|
| Los tests ubicados en Feature deben extender el TestCase de Laravel.
| Esto permite utilizar helpers como postJson(), getJson(), seed(),
| assertDatabaseHas() y demás herramientas de testing del framework.
|
*/

pest()
    ->extend(Tests\TestCase::class)
    ->in('Feature');
