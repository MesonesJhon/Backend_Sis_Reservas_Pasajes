8. Ejecutarlos en orden

Primero no ejecutes todo junto. Hazlo por bloques para detectar fácilmente dónde está un error.

1. Gestión
   php artisan test tests/Feature/Vehiculos/GestionVehiculosTest.php

Todo verde.

2. Autorización
   php artisan test tests/Feature/Vehiculos/AutorizacionVehiculosTest.php

Todo verde.

3. Asientos
   php artisan test tests/Feature/Vehiculos/ConfiguracionAsientosTest.php

Todo verde.

4. Integridad PostgreSQL
   php artisan test tests/Feature/Vehiculos/IntegridadAsientosTest.php

Todo verde.

5. RF-02 completo
   php artisan test tests/Feature/Vehiculos

Todo verde.
