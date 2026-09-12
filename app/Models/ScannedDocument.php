<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScannedDocument extends Model
{
    protected $table = 'scanned_documents';

    protected $fillable = [
        'original_name',
        'file_name',
        'file_path',
        'file_url',
        'file_type',
        'scan_type',
        'file_size',
    ];
}