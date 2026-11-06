<?php

namespace App\Domain\Clients\Models;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property string $legal_name
 * @property string|null $trade_name
 * @property ClientStatus $status
 */
#[UseFactory(ClientFactory::class)]
class Client extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use SoftDeletes;

    public const EDITABLE = ['legal_name', 'trade_name', 'tax_id', 'contact_name', 'email', 'phone', 'notes'];

    protected $fillable = ['company_id', 'status', ...self::EDITABLE];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['status' => ClientStatus::class];
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function displayName(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }
}
