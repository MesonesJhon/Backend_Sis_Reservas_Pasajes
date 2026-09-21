<?php

namespace App\Enums;

enum FuncionPersonalViaje: string
{
    case CONDUCTOR = 'CONDUCTOR';
    case CONDUCTOR_AUXILIAR = 'CONDUCTOR_AUXILIAR';
    case PERSONAL_APOYO = 'PERSONAL_APOYO';
}
