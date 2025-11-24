<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Importante para UUID

class LastroBatch extends Model
{
    use HasFactory, HasUuids;

    // Protege apenas o ID, o resto pode ser preenchido
    protected $guarded = ['id'];

    // Relacionamento: Um Lote tem muitos Itens
    public function items()
    {
        return $this->hasMany(LastroItem::class, 'batch_id');
    }
}
