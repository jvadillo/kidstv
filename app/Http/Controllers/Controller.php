<?php

namespace App\Http\Controllers;

use App\Models\Family;

abstract class Controller
{
    /**
     * Familia del adulto identificado. Todas las consultas del panel parten de aquí,
     * de modo que nunca se resuelven registros de otra familia.
     */
    protected function family(): Family
    {
        return request()->user()->family;
    }
}
