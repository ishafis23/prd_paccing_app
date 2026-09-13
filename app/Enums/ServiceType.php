<?php

namespace App\Enums;

enum ServiceType: string
{
    case CuciAc = 'cuci_ac';
    case ServiceAc = 'service_ac';
    case PengadaanAc = 'pengadaan_ac';
    case TambahFreon = 'tambah_freon';
    case Instalasi = 'instalasi';
    case Relokasi = 'relokasi';
    case Bongkar = 'bongkar';
}
