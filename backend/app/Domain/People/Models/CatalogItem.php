<?php

namespace App\Domain\People\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for the administrable name-only catalogs (RF-01.08): puestos, áreas, tipos de empleo, habilidades.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 */
abstract class CatalogItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name'];
}
