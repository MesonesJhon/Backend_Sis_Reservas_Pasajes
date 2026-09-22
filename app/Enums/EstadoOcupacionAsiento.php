<?php

namespace App\Enums;


/**
 * Estados posibles de una ocupación de asiento.
 *
 * No representa el estado físico del asiento.
 *
 * Representa una ocupación comercial.
 */
enum EstadoOcupacionAsiento:string
{

    /*
     * Bloqueo temporal mientras el cliente
     * completa una operación.
     */
    case BLOQUEADO = 'BLOQUEADO';



    /*
     * Reserva creada pero pendiente
     * de completar flujo.
     */
    case RESERVADO = 'RESERVADO';



    /*
     * Reserva confirmada.
     */
    case CONFIRMADO = 'CONFIRMADO';



    /*
     * Ocupación liberada.
     */
    case LIBERADO = 'LIBERADO';

}
