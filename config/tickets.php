<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secreto del token QR
    |--------------------------------------------------------------------------
    |
    | Se utiliza exclusivamente para firmar los tokens de los tickets.
    |
    | Debe ser una clave aleatoria codificada en Base64.
    |
    | Nunca debe exponerse al frontend ni almacenarse dentro del QR.
    |
    */

    'qr_secret' =>
        env(
            'TICKETS_QR_SECRET'
        ),

];
