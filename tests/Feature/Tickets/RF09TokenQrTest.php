<?php

use App\Domain\Tickets\GeneradorQrTicket;
use App\Exceptions\OperacionTicketInvalidaException;
use App\Models\Ticket;
use Illuminate\Support\Str;


/*
|--------------------------------------------------------------------------
| Configuración
|--------------------------------------------------------------------------
|
| Los tests NO dependen del secreto real de .env.
|
| Utilizamos una clave de prueba de exactamente
| 32 bytes.
|
*/

beforeEach(function () {

    config([
        'tickets.qr_secret' =>
            base64_encode(
                str_repeat(
                    'A',
                    32
                )
            ),
    ]);
});


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function crearTicketParaTokenQrRf09(): Ticket
{
    return new Ticket([
        'codigo' =>
            'TKT-'.Str::ulid(),
    ]);
}


/*
|--------------------------------------------------------------------------
| 1. Generación válida
|--------------------------------------------------------------------------
*/

test(
    'genera un token qr firmado para un ticket',
    function () {

        $ticket =
            crearTicketParaTokenQrRf09();


        $generador =
            app(
                GeneradorQrTicket::class
            );


        $token =
            $generador->generar(
                $ticket
            );


        expect(
            $token
        )
            ->toStartWith(
                'v1:'.$ticket->codigo.':'
            );


        /*
         * El token debe tener:
         *
         * version
         * codigo
         * firma
         */
        expect(
            explode(
                ':',
                $token
            )
        )->toHaveCount(3);
    }
);


/*
|--------------------------------------------------------------------------
| 2. Token legítimo
|--------------------------------------------------------------------------
*/

test(
    'token qr valido permite recuperar el codigo original',
    function () {

        $ticket =
            crearTicketParaTokenQrRf09();


        $generador =
            app(
                GeneradorQrTicket::class
            );


        $token =
            $generador->generar(
                $ticket
            );


        $codigo =
            $generador
                ->validarYExtraerCodigo(
                    $token
                );


        expect(
            $codigo
        )->toBe(
            $ticket->codigo
        );
    }
);


/*
|--------------------------------------------------------------------------
| 3. Código manipulado
|--------------------------------------------------------------------------
*/

test(
    'rechaza token cuando se manipula el codigo del ticket',
    function () {

        $ticket =
            crearTicketParaTokenQrRf09();


        $generador =
            app(
                GeneradorQrTicket::class
            );


        $token =
            $generador->generar(
                $ticket
            );


        [
            $version,
            ,
            $firma,
        ] =
            explode(
                ':',
                $token
            );


        /*
         * Código diferente pero con formato
         * perfectamente válido.
         *
         * Esto comprueba que no basta conocer
         * el formato TKT-ULID.
         */
        $codigoManipulado =
            'TKT-'.Str::ulid();


        $tokenManipulado =
            $version
            .':'
            .$codigoManipulado
            .':'
            .$firma;


        expect(
            fn () =>
                $generador
                    ->validarYExtraerCodigo(
                        $tokenManipulado
                    )
        )->toThrow(
            OperacionTicketInvalidaException::class
        );
    }
);


/*
|--------------------------------------------------------------------------
| 4. Firma manipulada
|--------------------------------------------------------------------------
*/

test(
    'rechaza token cuando se manipula la firma',
    function () {

        $ticket =
            crearTicketParaTokenQrRf09();


        $generador =
            app(
                GeneradorQrTicket::class
            );


        $token =
            $generador->generar(
                $ticket
            );


        [
            $version,
            $codigo,
            $firma,
        ] =
            explode(
                ':',
                $token
            );


        /*
         * Cambiamos solamente el primer carácter.
         */
        $primerCaracter =
            $firma[0] === 'a'
                ? 'b'
                : 'a';


        $firmaManipulada =
            $primerCaracter
            .substr(
                $firma,
                1
            );


        $tokenManipulado =
            $version
            .':'
            .$codigo
            .':'
            .$firmaManipulada;


        expect(
            fn () =>
                $generador
                    ->validarYExtraerCodigo(
                        $tokenManipulado
                    )
        )->toThrow(
            OperacionTicketInvalidaException::class
        );
    }
);


/*
|--------------------------------------------------------------------------
| 5. Token mal formado
|--------------------------------------------------------------------------
*/

test(
    'rechaza token qr mal formado',
    function () {

        $generador =
            app(
                GeneradorQrTicket::class
            );


        expect(
            fn () =>
                $generador
                    ->validarYExtraerCodigo(
                        'esto-no-es-un-token'
                    )
        )->toThrow(
            OperacionTicketInvalidaException::class
        );
    }
);


/*
|--------------------------------------------------------------------------
| 6. Versión manipulada
|--------------------------------------------------------------------------
*/

test(
    'rechaza una version de token no soportada',
    function () {

        $ticket =
            crearTicketParaTokenQrRf09();


        $generador =
            app(
                GeneradorQrTicket::class
            );


        $token =
            $generador->generar(
                $ticket
            );


        $tokenManipulado =
            preg_replace(
                '/^v1:/',
                'v2:',
                $token
            );


        expect(
            fn () =>
                $generador
                    ->validarYExtraerCodigo(
                        $tokenManipulado
                    )
        )->toThrow(
            OperacionTicketInvalidaException::class
        );
    }
);


/*
|--------------------------------------------------------------------------
| 7. Configuración insegura
|--------------------------------------------------------------------------
*/

test(
    'no genera tokens si el secreto qr no esta configurado',
    function () {

        config([
            'tickets.qr_secret' =>
                null,
        ]);


        $ticket =
            crearTicketParaTokenQrRf09();


        $generador =
            app(
                GeneradorQrTicket::class
            );


        expect(
            fn () =>
                $generador
                    ->generar(
                        $ticket
                    )
        )->toThrow(
            LogicException::class
        );
    }
);


/*
|--------------------------------------------------------------------------
| 8. Secreto demasiado corto
|--------------------------------------------------------------------------
*/

test(
    'rechaza un secreto qr criptograficamente insuficiente',
    function () {

        config([
            'tickets.qr_secret' =>
                base64_encode(
                    'clave-corta'
                ),
        ]);


        $ticket =
            crearTicketParaTokenQrRf09();


        $generador =
            app(
                GeneradorQrTicket::class
            );


        expect(
            fn () =>
                $generador
                    ->generar(
                        $ticket
                    )
        )->toThrow(
            LogicException::class
        );
    }
);
