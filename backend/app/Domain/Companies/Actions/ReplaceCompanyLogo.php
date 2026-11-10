<?php

namespace App\Domain\Companies\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ReplaceCompanyLogo
{
    private const DISK = 'public';

    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, Company $company, UploadedFile $logo): Company
    {
        $previous = $company->logo_path;
        $path = $logo->store("companies/{$company->id}", self::DISK);

        $company->forceFill(['logo_path' => $path])->save();
        if ($previous !== null) {
            Storage::disk(self::DISK)->delete($previous);
        }

        ($this->recordActivity)('company.logo_replaced', $company, $actor, ['logo_path' => $previous], ['logo_path' => $path]);

        return $company;
    }
}
