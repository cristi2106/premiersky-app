<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'company_name',
        'address',
        'vat_code',
        'iban',
        'bank_name',
    ];
}
