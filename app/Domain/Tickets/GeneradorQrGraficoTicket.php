<?php

namespace App\Domain\Tickets;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use InvalidArgumentException;

/**
 * Convierte el token seguro de un ticket
 * en una representación gráfica SVG.
 *
 * Esta clase NO genera el token ni conoce
 * datos personales del pasajero.
 */
class GeneradorQrGraficoTicket
{
    private const TAMANO = 320;

    private const MARGEN = 16;


    public function generar(
        string $token
    ): string {

        $token =
            trim(
                $token
            );


        if ($token === '') {
            throw new InvalidArgumentException(
                'El token QR no puede estar vacío.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | QR
        |--------------------------------------------------------------------------
        |
        | El contenido ya viene firmado desde GeneradorQrTicket.
        |
        | El QR únicamente representa:
        |
        | v1:TKT-<ULID>:<firma>
        |
        */

        $qrCode =
            new QrCode(
                data: $token,

                /*
                 * Nuestro token utiliza únicamente caracteres ASCII.
                 *
                 * ISO-8859-1 evita añadir información ECI innecesaria
                 * y mejora compatibilidad con algunos scanners.
                 */
                encoding:
                    new Encoding(
                        'ISO-8859-1'
                    ),

                errorCorrectionLevel:
                    ErrorCorrectionLevel::Medium,

                size:
                    self::TAMANO,

                margin:
                    self::MARGEN,

                /*
                 * Para SVG no necesitamos redondear
                 * dimensiones de píxeles.
                 */
                roundBlockSizeMode:
                    RoundBlockSizeMode::None
            );


        /*
        |--------------------------------------------------------------------------
        | SVG
        |--------------------------------------------------------------------------
        */

        $writer =
            new SvgWriter();


        $resultado =
            $writer->write(
                $qrCode
            );


        return $resultado
            ->getString();
    }
}
