<?php

namespace App\Enums;

enum IncomeCategory: string
{
    case Jasa = 'jasa';
    case Material = 'material';

    /**
     * Cuci/Service AC = jasa; layanan lain (pengadaan, dll.) = material.
     */
    public static function untukLayanan(?ServiceType $jenis): self
    {
        return in_array($jenis, [ServiceType::CuciAc, ServiceType::ServiceAc], true) ? self::Jasa : self::Material;
    }
}
