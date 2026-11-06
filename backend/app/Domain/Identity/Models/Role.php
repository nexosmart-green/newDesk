<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property bool $is_system
 */
#[UseFactory(RoleFactory::class)]
class Role extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = ['company_id', 'name', 'is_system'];

    /** @var list<string>|null */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return list<string> */
    public function permissionValues(): array
    {
        return $this->permissionCache ??= DB::table('role_permissions')
            ->where('role_id', $this->id)
            ->orderBy('permission')
            ->pluck('permission')
            ->all();
    }

    public function grants(Permission $permission): bool
    {
        return in_array($permission->value, $this->permissionValues(), true);
    }

    /** @param  list<Permission>  $permissions */
    public function syncPermissions(array $permissions): void
    {
        $values = array_values(array_unique(array_map(fn (Permission $p) => $p->value, $permissions)));

        DB::transaction(function () use ($values) {
            DB::table('role_permissions')->where('role_id', $this->id)->delete();
            DB::table('role_permissions')->insert(
                array_map(fn (string $value) => ['role_id' => $this->id, 'permission' => $value], $values),
            );
        });

        $this->permissionCache = null;
    }
}
