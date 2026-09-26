# AGENTS.md

# Proyecto — Sistema de Reserva de Pasajes

Este repositorio contiene el backend de un sistema de reserva de pasajes para vehículos de transporte.

El backend expone una API REST que será consumida por:

- Frontend web Vue 3.
- Aplicación móvil futura.
- Integraciones externas como Mercado Pago.

El desarrollo se realiza requisito funcional por requisito funcional.

Antes de modificar cualquier archivo, inspeccionar el estado real del repositorio.

El código existente tiene prioridad sobre documentación histórica, conversaciones anteriores o suposiciones.

---

# 1. Stack del proyecto

Backend:

```text
Laravel 13
PHP 8.4.x en desarrollo
Requisito Composer: PHP ^8.3
PostgreSQL 17.x
Laravel Sanctum
API REST
Pest / PHPUnit
```

Testing automatizado:

```text
Pest
RefreshDatabase
SQLite :memory: definido mediante phpunit.xml
```

Frontend:

```text
Vue 3
```

Pagos:

```text
Mercado Pago
Checkout Pro / Orders API
```

No cambiar versiones de:

- PHP;
- Laravel;
- PostgreSQL;
- Sanctum;
- Pest;
- Mercado Pago SDK;

ni agregar dependencias sin comprobar primero que sea necesario.

No modificar `composer.json` solamente para instalar una librería.

---

# 2. Regla principal

Antes de generar código:

1. inspeccionar los archivos reales;
2. identificar cómo está resuelto actualmente el problema;
3. reutilizar la arquitectura existente;
4. verificar relaciones, columnas, enums y métodos reales;
5. revisar tests relacionados;
6. solamente después proponer cambios.

Nunca asumir que un archivo no existe sin buscarlo.

Nunca recrear una funcionalidad que ya está implementada.

Si esta documentación contradice al código actual:

```text
EL CÓDIGO ACTUAL GANA.
```

---

# 3. No inventar estructura

No inventar:

- columnas;
- tablas;
- relaciones Eloquent;
- factories;
- enums;
- estados;
- permisos;
- rutas;
- métodos;
- middleware;
- nombres de clases;
- helpers;
- eventos;
- comandos;
- servicios externos.

Antes de utilizarlos, comprobar que realmente existen.

Ejemplo incorrecto:

```php
$reserva->asientos
```

si no se ha comprobado previamente que esa relación existe.

---

# 4. Arquitectura general

Mantener separación de responsabilidades.

La arquitectura actual utiliza principalmente:

```text
app/
├── Actions/
├── Domain/
├── Enums/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Integrations/
├── Models/
└── Providers/
```

Principios:

```text
Controller
    ↓
Form Request
    ↓
Action / Domain
    ↓
Model / Integración
    ↓
Resource
```

Los Controllers deben mantenerse delgados.

La lógica de negocio no debe estar directamente en los Controllers.

Preferir:

```text
Actions
Domain
Enums
Excepciones de dominio
```

para representar comportamiento empresarial.

---

# 5. Actions

Los casos de uso importantes deben implementarse mediante Actions.

Ejemplos existentes:

```text
app/Actions/Reservas/
app/Actions/Pagos/
app/Actions/Asientos/
app/Actions/Tickets/
```

Una Action debe representar una operación clara.

Ejemplos:

```text
ConfirmarReserva
AplicarPagoAReserva
EmitirTicketsReserva
ValidarTicket
AnularTicketsReserva
```

Evitar Actions gigantes que resuelvan múltiples responsabilidades no relacionadas.

---

# 6. Transacciones

Utilizar:

```php
DB::transaction()
```

cuando una operación modifica varias entidades relacionadas y todas deben mantenerse consistentes.

Especialmente en:

- reservas;
- ocupaciones;
- pagos;
- tickets;
- validaciones;
- cancelaciones;
- reembolsos.

Si falla una operación crítica, no deben quedar cambios parciales.

---

# 7. Concurrencia

Para operaciones que puedan ejecutarse simultáneamente utilizar cuando corresponda:

```php
lockForUpdate()
```

Especialmente en:

- confirmación de reservas;
- ocupación de asientos;
- pagos;
- emisión de tickets;
- validación de tickets;
- procesos idempotentes.

Adquirir locks en orden estable cuando se bloqueen múltiples registros para reducir riesgo de deadlocks.

---

# 8. Idempotencia

Las operaciones que puedan repetirse por:

- retries;
- Webhooks;
- errores de red;
- doble clic;
- procesos concurrentes;
- scheduler;

deben ser idempotentes.

Ejemplos:

```text
Procesamiento de Webhook
Sincronización de Pago
Emisión de Tickets
Validación de estados externos
```

Nunca generar registros duplicados simplemente porque una operación se ejecutó más de una vez.

Cuando sea posible complementar la lógica con restricciones `UNIQUE` en base de datos.

---

# 9. Estados

Los estados de negocio deben representarse mediante Enums cuando el proyecto ya utilice este patrón.

No comparar estados mediante strings arbitrarios cuando exista un Enum.

Correcto:

```php
$reserva->estado === EstadoReserva::CONFIRMADA
```

Evitar:

```php
$reserva->estado === 'CONFIRMADA'
```

si existe `EstadoReserva`.

Las transiciones de estados deben ser explícitas y respetar las reglas del dominio.

No revivir estados terminales arbitrariamente.

---

# 10. Excepciones de dominio

Las reglas de negocio inválidas deben expresarse mediante excepciones específicas.

Ejemplos existentes:

```text
OperacionReservaInvalidaException
OperacionPagoInvalidaException
OperacionTicketInvalidaException
```

No utilizar excepciones genéricas si ya existe una excepción adecuada del dominio.

---

# 11. API REST

La API está versionada mediante:

```text
/api/v1
```

No colocar todas las rutas directamente dentro de:

```text
routes/api.php
```

`routes/api.php` funciona como agregador.

La estructura utilizada es:

```text
routes/
├── api.php
└── api/
    └── v1/
        ├── autenticacion.php
        ├── administracion.php
        ├── vehiculos.php
        ├── rutas.php
        ├── viajes.php
        ├── busqueda.php
        ├── asientos.php
        ├── reservas.php
        ├── pagos.php
        └── tickets.php
```

Cuando se implemente un módulo nuevo debe respetarse esta estructura.

Para RF-09 utilizar:

```text
routes/api/v1/tickets.php
```

y registrarlo desde:

```text
routes/api.php
```

---

# 12. Autenticación

La API utiliza:

```text
Laravel Sanctum
```

Las rutas privadas deben utilizar:

```php
auth:sanctum
```

cuando corresponda.

No desarrollar sistemas de autenticación paralelos.

---

# 13. Autorización

El proyecto utiliza:

```text
Rol
Permiso
Usuario
VerificarPermiso
```

No basta verificar únicamente el rol cuando existe una regla más específica.

Ejemplo:

```text
CONDUCTOR
+
asignado al viaje
```

puede ser necesario para una operación.

No asumir:

```text
CONDUCTOR = acceso automático a todos los viajes
```

Los permisos se registran mediante:

```text
database/seeders/RolesPermisosSeeder.php
```

Antes de crear un permiso comprobar si ya existe.

---

# 14. Roles actuales

Los roles principales son:

```text
ADMINISTRADOR
OPERADOR
CONDUCTOR
CLIENTE
```

No agregar nuevos roles sin una necesidad funcional explícita.

---

# 15. Seguridad

Nunca:

- solicitar secretos;
- mostrar secretos;
- registrar secretos completos;
- insertar credenciales en código;
- subir credenciales a Git;
- exponer `.env`;
- devolver secretos mediante Resources;
- confiar en datos financieros enviados desde frontend.

Especialmente proteger:

```text
MERCADO_PAGO_ACCESS_TOKEN
Webhook secrets
APP_KEY
contraseñas
tokens
claves privadas
```

No imprimirlos en logs ni tests.

---

# 16. Información financiera

El frontend no es una fuente confiable para:

```text
monto
moneda
estado de pago
payment_id
order_id
confirmación financiera
```

Toda decisión financiera debe comprobarse en backend.

No confirmar una reserva solamente porque el frontend informe que el pago fue exitoso.

---

# 17. Privacidad

No exponer innecesariamente:

- documentos de identidad;
- información financiera;
- identificadores internos del proveedor;
- tokens;
- firmas;
- claves de idempotencia;
- secretos.

Los API Resources deben devolver únicamente información necesaria para el consumidor.

---

# 18. Mercado Pago

La integración existente con Mercado Pago pertenece principalmente a:

```text
app/Integrations/MercadoPago/
app/Actions/Pagos/
```

La comunicación HTTP con Mercado Pago debe permanecer en la capa de integración correspondiente.

Los DTO deben transportar información.

Un DTO no debe realizar llamadas HTTP.

No cambiar RF-08 salvo que RF-09 revele un bug real o exista una integración estrictamente necesaria.

---

# 19. Tests

El proyecto utiliza Pest.

Los Feature Tests extienden el TestCase de Laravel y utilizan:

```text
RefreshDatabase
```

La configuración efectiva para los tests automatizados se encuentra en:

```text
phpunit.xml
```

Actualmente utiliza:

```text
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

No cambiar el motor de pruebas sin una razón demostrada.

---

# 20. Desarrollo guiado por tests

Por cada funcionalidad:

1. identificar tests existentes;
2. ejecutar los tests específicos;
3. implementar el cambio mínimo necesario;
4. volver a ejecutar los tests;
5. ejecutar tests de módulos afectados;
6. antes de cerrar el RF ejecutar la suite completa.

Ejemplo para RF-09:

```powershell
php artisan test tests/Feature/Tickets/RF09EmisionTicketTest.php
```

Después:

```powershell
php artisan test tests/Feature/Reservas
php artisan test tests/Feature/Pagos
```

Finalmente:

```powershell
php artisan test
```

No declarar una funcionalidad terminada solamente porque no presenta errores de sintaxis.

---

# 21. Regresiones

No modificar funcionalidades estables de RF anteriores sin comprobar regresiones.

Si RF-09 toca:

```text
Reservas
Pagos
Ocupaciones
Viajes
Usuarios
```

ejecutar también los tests correspondientes a esos módulos.

El objetivo no es solamente hacer funcionar RF-09.

El objetivo es:

```text
RF-09 funciona
+
RF-01 a RF-08 siguen funcionando
```

---

# 22. Factories y helpers de tests

No asumir que todos los Models poseen Factory.

Antes de utilizar:

```php
Model::factory()
```

comprobar:

```text
database/factories/
```

Reutilizar helpers existentes de:

```text
tests/Helpers/
```

antes de duplicar preparación compleja de escenarios.

---

# 23. Artisan

Cuando se proponga crear un archivo nuevo, indicar primero el comando Artisan adecuado cuando exista.

Ejemplo:

```powershell
php artisan make:class Actions/Tickets/ValidarTicket
```

Después indicar:

```text
Ruta:
app/Actions/Tickets/ValidarTicket.php
```

y posteriormente proporcionar el código.

Para cada archivo nuevo explicar:

1. por qué existe;
2. responsabilidad;
3. dónde se conecta;
4. tests relacionados.

---

# 24. Formato y Clean Code

Mantener el estilo existente del repositorio.

Usar:

```text
nombres descriptivos
métodos pequeños
responsabilidad única
tipado
Enums
Actions
Form Requests
Resources
excepciones del dominio
```

Los nombres de negocio se mantienen principalmente en español.

No traducir arbitrariamente clases existentes al inglés.

No renombrar clases solamente por preferencia estética.

---

# 25. Comentarios

Los comentarios deben explicar:

```text
POR QUÉ existe una decisión
```

y no repetir simplemente:

```text
QUÉ hace la siguiente línea
```

Conservar comentarios útiles existentes.

Evitar comentarios innecesarios o generados automáticamente.

---

# 26. Código existente

Antes de editar una clase:

1. leerla completa;
2. revisar quién la utiliza;
3. revisar sus tests;
4. buscar llamadas mediante el nombre de clase/método;
5. verificar posibles efectos secundarios.

No reemplazar archivos completos cuando un cambio localizado sea suficiente.

---

# 27. Base de datos

Antes de crear una migración:

```powershell
php artisan migrate:status
```

cuando el entorno lo permita.

No editar migraciones antiguas que ya hayan sido aplicadas en ambientes compartidos salvo que el proyecto todavía esté explícitamente en una fase donde sea seguro hacerlo.

Preferir nuevas migraciones para modificaciones posteriores.

Definir constraints que protejan reglas importantes cuando corresponda:

```text
FOREIGN KEY
UNIQUE
INDEX
NOT NULL
```

No depender solamente de validación de aplicación para integridad crítica.

---

# 28. RF-06 — Asientos

Estados de ocupación existentes:

```text
BLOQUEADO
RESERVADO
CONFIRMADO
LIBERADO
```

La disponibilidad puede trabajar por segmentos del viaje.

No simplificar esta lógica como si un asiento perteneciera únicamente a un viaje completo.

Existe lógica de expiración/liberación de bloqueos.

No duplicarla.

---

# 29. RF-07 — Reservas

Estados existentes:

```text
PENDIENTE_PAGO
CONFIRMADA
CANCELADA
EXPIRADA
```

Una reserva puede relacionarse con:

```text
cliente
viaje
pasajeros
ocupaciones
pago de confirmación
tickets
```

No revivir automáticamente una reserva:

```text
EXPIRADA
```

por recibir posteriormente un pago.

---

# 30. RF-08 — Pagos

RF-08 se considera cerrado salvo bugs o integración necesaria con RF posteriores.

Estados de Pago:

```text
CREADO
PENDIENTE
APROBADO
RECHAZADO
CANCELADO
ERROR
REEMBOLSADO
```

La lógica actual contempla:

- idempotencia;
- Webhooks;
- reconciliación;
- pagos tardíos;
- reembolsos;
- inconsistencias financieras;
- estado monotónico.

No duplicar ni simplificar esa lógica desde RF-09.

---

# 31. RF ACTUAL — RF-09

Requisito activo:

```text
RF-09 — Emisión y validación de tickets electrónicos
```

Una Reserva confirmada debe generar:

```text
1 pasajero = 1 ticket
```

Ejemplo:

```text
Reserva
├── Pasajero 1 → Ticket 1
├── Pasajero 2 → Ticket 2
└── Pasajero 3 → Ticket 3
```

Un Ticket pertenece a un:

```text
PasajeroReserva
```

No implementar:

```text
1 Reserva = 1 Ticket
```

---

# 32. Estado real inicial de RF-09

Antes de continuar, comprobar nuevamente estos archivos porque pueden haber cambiado.

En el estado actual del proyecto ya existen:

```text
app/Enums/EstadoTicket.php

app/Models/Ticket.php

app/Exceptions/
└── OperacionTicketInvalidaException.php

app/Actions/Tickets/
└── EmitirTicketsReserva.php

database/migrations/
└── *_create_tickets_table.php

tests/Feature/Tickets/
└── RF09EmisionTicketTest.php
```

También existen integraciones de emisión desde:

```text
app/Actions/Reservas/ConfirmarReserva.php

app/Actions/Pagos/AplicarPagoAReserva.php
```

Y relaciones en:

```text
Reserva::tickets()

PasajeroReserva::ticket()
```

No recrear estos componentes.

Verificar primero su implementación y tests.

---

# 33. Estados del Ticket

Estados actuales:

```text
VIGENTE
UTILIZADO
ANULADO
```

Transiciones previstas:

```text
VIGENTE → UTILIZADO
VIGENTE → ANULADO
```

No permitir automáticamente:

```text
UTILIZADO → VIGENTE
ANULADO → VIGENTE
```

---

# 34. Emisión de Ticket

`EmitirTicketsReserva` debe conservar estas invariantes:

```text
Reserva = CONFIRMADA

Pasajero pertenece a Reserva

Ocupación pertenece a Reserva

Ocupación pertenece al mismo Viaje

Ocupación = CONFIRMADO

1 Ticket por PasajeroReserva
```

La emisión debe ser idempotente.

Ejecutarla múltiples veces no debe generar nuevos tickets.

Nunca reemplazar un ticket existente `UTILIZADO` o `ANULADO` por uno nuevo `VIGENTE`.

---

# 35. Código público del Ticket

No utilizar el ID incremental como identificador público principal.

El proyecto utiliza conceptualmente códigos como:

```text
TKT-<ULID>
```

Los códigos deben ser:

```text
únicos
no secuenciales
inmutables
```

---

# 36. Consulta de Tickets — siguiente etapa

La siguiente etapa después de cerrar completamente la emisión es implementar consulta segura.

Componentes previstos:

```text
AutorizacionTicket

TicketController

TicketResource

routes/api/v1/tickets.php

RF09ConsultaTicketTest.php
```

Antes de crearlos inspeccionar cómo están implementados:

```text
Controllers
Resources
autorizaciones
rutas
```

de los RF anteriores.

No copiar una arquitectura teórica si el proyecto real utiliza otra convención.

---

# 37. Endpoints previstos para consulta

Evaluar según la arquitectura real:

```text
GET /api/v1/tickets/{ticket}
```

y:

```text
GET /api/v1/reservas/{reserva}/tickets
```

No registrar endpoints hasta comprobar que encajan con las convenciones actuales.

---

# 38. Autorización de consulta

CLIENTE:

```text
solamente tickets de sus propias reservas
```

OPERADOR:

```text
consulta operativa según permisos
```

ADMINISTRADOR:

```text
consulta administrativa según permisos
```

CONDUCTOR:

No darle acceso global a todos los tickets solamente por poseer el rol.

---

# 39. Contenido del Ticket

La información debe obtenerse reutilizando relaciones existentes.

No duplicar innecesariamente en `tickets`:

```text
nombre pasajero
DNI
asiento
viaje
origen
destino
monto
```

si esos datos ya existen en entidades relacionadas.

El Ticket Resource puede construir la representación a partir de:

```text
Ticket
↓
PasajeroReserva
↓
OcupacionAsiento
↓
Reserva
↓
Viaje
↓
Pago
```

---

# 40. Horario de embarque

Si existe lógica de dominio como:

```text
CalculadorHorarioSegmento
```

reutilizarla.

No volver a implementar manualmente el cálculo de horario dentro de `TicketResource` o `TicketController`.

---

# 41. QR seguro

El QR NO debe contener directamente:

```text
DNI
nombre
apellidos
monto
datos financieros
información sensible
```

No utilizar payloads como:

```json
{
    "dni": "12345678",
    "nombre": "Juan Pérez",
    "asiento": "A1",
    "monto": "50"
}
```

---

# 42. Token QR

Separar:

```text
Token/payload firmado
```

de:

```text
Imagen QR
```

Primero implementar y probar el token firmado.

Después integrar una librería gráfica de QR.

Conceptualmente:

```text
v1:TKT-<codigo>:<firma>
```

Utilizar una firma criptográfica segura, por ejemplo HMAC, utilizando un secreto controlado por backend.

Nunca confiar únicamente en el código recibido desde el QR.

---

# 43. Validación del QR

Flujo esperado:

```text
QR
↓
token firmado
↓
validar firma
↓
buscar Ticket
↓
lockForUpdate()
↓
comprobar reglas
↓
UTILIZADO
```

Debe comprobarse al menos:

1. firma válida;
2. ticket existente;
3. ticket `VIGENTE`;
4. reserva `CONFIRMADA`;
5. ocupación `CONFIRMADO`;
6. viaje correcto;
7. usuario autorizado;
8. ticket no utilizado previamente.

---

# 44. Validación concurrente

Escenario crítico:

```text
Scanner A
Scanner B
```

escanean simultáneamente el mismo ticket.

Debe utilizarse:

```php
DB::transaction()
```

y:

```php
lockForUpdate()
```

Resultado:

```text
Scanner A:
VIGENTE → UTILIZADO

Scanner B:
encuentra UTILIZADO
→ rechaza
```

Nunca permitir doble utilización.

---

# 45. Conductor y Viaje

Para validar tickets no basta:

```text
usuario tiene rol CONDUCTOR
```

Debe comprobarse su asociación real con el viaje mediante las relaciones existentes.

Conceptualmente:

```text
CONDUCTOR
+
asignado a Viaje X
=
puede validar tickets de Viaje X
```

pero:

```text
CONDUCTOR
+
no asignado a Viaje X
=
403
```

No inventar la relación; inspeccionar primero:

```text
PersonalViaje
Viaje
Usuario
FuncionPersonalViaje
```

---

# 46. Anulación de Tickets

Posteriormente crear una Action dedicada, por ejemplo:

```text
AnularTicketsReserva
```

cuando el flujo lo requiera.

Casos previstos:

```text
Reserva CANCELADA
Pago totalmente REEMBOLSADO
```

Un ticket:

```text
VIGENTE
```

puede pasar a:

```text
ANULADO
```

registrando:

```text
anulado_en
motivo_anulacion
```

No cambiar automáticamente:

```text
UTILIZADO → ANULADO
```

porque podría representar a un pasajero que ya realizó el viaje.

---

# 47. Permisos de Tickets

Permisos existentes:

```text
tickets.ver
tickets.emitir
tickets.validar
```

Antes de agregar nuevos permisos revisar:

```text
database/seeders/RolesPermisosSeeder.php
```

No duplicar permisos.

La emisión normal de tickets debe ser automática.

El CLIENTE no necesita iniciar manualmente la emisión de sus tickets.

---

# 48. Tests mínimos RF-09

RF-09 no debe cerrarse hasta cubrir al menos:

## Emisión

```text
✓ no emitir antes de confirmar
✓ emitir al confirmar
✓ un ticket por pasajero
✓ códigos únicos
✓ emisión idempotente
✓ pago aprobado genera tickets
```

## Consulta

```text
✓ cliente consulta ticket propio
✓ cliente no consulta ticket ajeno
✓ operador consulta
✓ anónimo recibe 401
```

## QR

```text
✓ token válido
✓ firma manipulada rechazada
✓ código inexistente rechazado
```

## Validación

```text
✓ operador valida ticket vigente
✓ conductor asignado valida
✓ conductor no asignado recibe 403
✓ cliente no valida
✓ ticket utilizado no se valida nuevamente
✓ ticket anulado no se valida
✓ reserva cancelada no se valida
✓ asiento no confirmado no se valida
✓ ticket de otro viaje no se valida
```

## Concurrencia

```text
✓ doble validación no produce doble utilización
```

## Anulación

```text
✓ cancelación anula ticket vigente
✓ reembolso total anula ticket vigente
✓ ticket anulado no vuelve a vigente
```

---

# 49. Orden de implementación RF-09

Continuar RF-09 en este orden:

```text
ETAPA 01
Modelo + Enum + migración + relaciones
[actualmente implementado]

ETAPA 02
Emisión automática + idempotencia
[actualmente implementada; verificar tests]

ETAPA 03
Consulta segura de tickets

ETAPA 04
Token QR firmado

ETAPA 05
Validación de tickets

ETAPA 06
Anulación automática

ETAPA 07
Representación gráfica QR

ETAPA 08
Suite completa y regresiones
```

No saltar directamente al QR gráfico.

Primero deben quedar correctos dominio, seguridad y validación.

---

# 50. Antes de continuar RF-09

Cada vez que se retome el requisito, inspeccionar:

```text
app/Enums/EstadoTicket.php
app/Models/Ticket.php
database/migrations/*tickets*
app/Actions/Tickets/
app/Exceptions/OperacionTicketInvalidaException.php
app/Actions/Reservas/ConfirmarReserva.php
app/Actions/Pagos/AplicarPagoAReserva.php
app/Models/Reserva.php
app/Models/PasajeroReserva.php
app/Models/OcupacionAsiento.php
app/Models/PersonalViaje.php
database/seeders/RolesPermisosSeeder.php
tests/Feature/Tickets/
routes/api.php
routes/api/v1/
```

Clasificar el estado:

```text
✅ implementado correctamente
⚠️ implementado pero incompleto/incorrecto
❌ pendiente
```

Continuar desde el primer punto realmente pendiente.

---

# 51. No mezclar todavía con RF posteriores

No implementar dentro de RF-09 políticas completas de:

```text
privacidad
cookies
términos y condiciones
penalidades comerciales
políticas comerciales de reembolso
tratamiento legal de datos
```

si pertenecen a RF posteriores.

RF-09 sí debe respetar desde ahora los principios técnicos de privacidad y seguridad.

---

# 52. Flujo final esperado

```text
CLIENTE
↓
selecciona viaje
↓
selecciona asiento
↓
crea reserva
↓
PENDIENTE_PAGO
↓
Mercado Pago
↓
Pago APROBADO
↓
Reserva CONFIRMADA
↓
Ocupación CONFIRMADO
↓
EmitirTicketsReserva
↓
Ticket VIGENTE
↓
Token QR firmado
↓
Cliente presenta QR
↓
Operador / Conductor autorizado escanea
↓
ValidarTicket
↓
Ticket UTILIZADO
↓
validado_en
validado_por_usuario_id
```

---

# 53. Forma de trabajar del agente

Cuando el usuario solicite implementar una nueva etapa:

No entregar diez archivos de golpe sin revisar el proyecto.

Trabajar progresivamente.

Orden recomendado:

```text
1. inspección
2. diagnóstico
3. archivos afectados
4. comandos Artisan
5. implementación
6. test específico
7. corrección
8. regresiones
9. siguiente etapa
```

Si se encuentra un problema durante la inspección, señalarlo antes de continuar.

---

# 54. Formato de respuesta esperado

Para cada cambio indicar:

```text
ARCHIVO
ruta/del/archivo.php

OBJETIVO
Qué resuelve.

COMANDO
php artisan ...

CÓDIGO
...

CONEXIÓN
Dónde y cómo se utiliza.

PRUEBA
php artisan test ...
```

No omitir la explicación de cómo se conecta el nuevo código con el flujo existente.

---

# 55. Definición de terminado

Un requisito funcional NO está terminado porque:

```text
compila
```

o porque:

```text
la ruta responde 200
```

Se considera terminado solamente cuando se comprueban:

```text
reglas de negocio
autenticación
autorización
integridad
concurrencia
idempotencia
seguridad
privacidad
tests
regresiones
integración real
```

y finalmente:

```powershell
php artisan test
```

debe permanecer verde.
