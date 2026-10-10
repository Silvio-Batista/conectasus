<?php

namespace App\Models;

use Database\Factories\HealthUnitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HealthUnit extends Model
{
    /** @use HasFactory<HealthUnitFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'city',
    ];

    /**
     * O UUID é gerado automaticamente pelo Laravel na coluna "uuid".
     * A chave primária continua sendo o "id" auto-incremental.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Uma unidade possui muitos pacientes.
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
