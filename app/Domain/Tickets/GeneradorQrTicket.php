<?php

namespace App\Domain\Tickets;

use App\Exceptions\OperacionTicketInvalidaException;
use App\Models\Ticket;
use LogicException;

/**
 * Genera y verifica el token seguro
 * utilizado dentro del código QR de un ticket.
 *
 * El token NO contiene datos personales.
 *
 * Formato:
 *
 * v1:TKT-<ULID>:<firma-hmac>
 */
class GeneradorQrTicket
{
    private const VERSION =
        'v1';


    /**
     * Genera el token firmado correspondiente
     * a un ticket.
     */
    public function generar(
        Ticket $ticket
    ): string {

        $codigo =
            $ticket->codigo;


        $this->validarFormatoCodigo(
            $codigo
        );


        /*
         * Solamente esta parte es firmada.
         *
         * Ejemplo:
         *
         * v1:TKT-01K...
         */
        $payload =
            self::VERSION
            .':'
            .$codigo;


        $firma =
            $this->firmar(
                $payload
            );


        return
            $payload
            .':'
            .$firma;
    }


    /**
     * Verifica criptográficamente un token
     * y devuelve el código del ticket.
     *
     * IMPORTANTE:
     *
     * Esto solamente demuestra que el token
     * fue generado por nuestro backend.
     *
     * Todavía NO significa que el ticket pueda
     * utilizarse para embarque.
     *
     * Esa validación corresponde a la Etapa 05.
     */
    public function validarYExtraerCodigo(
        string $token
    ): string {

        $partes =
            explode(
                ':',
                $token
            );


        /*
         * Formato obligatorio:
         *
         * version
         * codigo
         * firma
         */
        if (
            count(
                $partes
            ) !== 3
        ) {
            throw $this
                ->tokenInvalido();
        }


        [
            $version,
            $codigo,
            $firma,
        ] = $partes;


        /*
        |--------------------------------------------------------------------------
        | Versión
        |--------------------------------------------------------------------------
        */

        if (
            $version
            !== self::VERSION
        ) {
            throw $this
                ->tokenInvalido();
        }


        /*
        |--------------------------------------------------------------------------
        | Código
        |--------------------------------------------------------------------------
        */

        $this->validarFormatoCodigo(
            $codigo
        );


        /*
        |--------------------------------------------------------------------------
        | Firma
        |--------------------------------------------------------------------------
        |
        | SHA-256 produce 32 bytes.
        |
        | En hexadecimal son exactamente 64 caracteres.
        |
        */

        if (
            ! preg_match(
                '/^[a-f0-9]{64}$/',
                $firma
            )
        ) {
            throw $this
                ->tokenInvalido();
        }


        /*
        |--------------------------------------------------------------------------
        | Reconstruir payload original
        |--------------------------------------------------------------------------
        */

        $payload =
            $version
            .':'
            .$codigo;


        $firmaEsperada =
            $this->firmar(
                $payload
            );


        /*
        |--------------------------------------------------------------------------
        | Comparación segura
        |--------------------------------------------------------------------------
        |
        | hash_equals evita comparaciones vulnerables
        | a ataques de temporización.
        |
        */

        if (
            ! hash_equals(
                $firmaEsperada,
                $firma
            )
        ) {
            throw $this
                ->tokenInvalido();
        }


        return $codigo;
    }


    /**
     * Firma un payload utilizando HMAC-SHA256.
     */
    private function firmar(
        string $payload
    ): string {

        return hash_hmac(
            'sha256',
            $payload,
            $this->obtenerSecreto()
        );
    }


    /**
     * Obtiene y valida el secreto configurado.
     *
     * Si no existe una configuración segura,
     * el sistema debe fallar cerrado.
     */
    private function obtenerSecreto(): string
    {
        $secretoCodificado =
            config(
                'tickets.qr_secret'
            );


        if (
            ! is_string(
                $secretoCodificado
            )
            ||
            trim(
                $secretoCodificado
            ) === ''
        ) {
            throw new LogicException(
                'TICKETS_QR_SECRET no está configurado.'
            );
        }


        $secreto =
            base64_decode(
                $secretoCodificado,
                true
            );


        /*
         * Exigimos al menos 256 bits.
         *
         * 32 bytes × 8 = 256 bits.
         */
        if (
            $secreto === false
            ||
            strlen(
                $secreto
            ) < 32
        ) {
            throw new LogicException(
                'TICKETS_QR_SECRET debe contener al menos 32 bytes codificados en Base64.'
            );
        }


        return $secreto;
    }


    /**
     * Los tickets actuales utilizan:
     *
     * TKT- + ULID
     *
     * Un ULID posee 26 caracteres.
     */
    private function validarFormatoCodigo(
        string $codigo
    ): void {

        if (
            ! preg_match(
                '/^TKT-[0-9A-HJKMNP-TV-Z]{26}$/',
                $codigo
            )
        ) {
            throw $this
                ->tokenInvalido();
        }
    }


    private function tokenInvalido():
        OperacionTicketInvalidaException {

        return new OperacionTicketInvalidaException(
            'El token QR del ticket no es válido.'
        );
    }
}
