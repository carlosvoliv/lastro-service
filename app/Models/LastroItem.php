<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LastroItem extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    // Relacionamento inverso: O Item pertence a um Lote
    public function batch()
    {
        return $this->belongsTo(LastroBatch::class, 'batch_id');
    }
}
