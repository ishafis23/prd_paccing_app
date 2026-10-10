<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Terkirim = 'terkirim';
    case Lunas = 'lunas';
    case Batal = 'batal';
}
