<?php

namespace App\Http\Requests\Support;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Support\ReportImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

/**
 * The multipart forms of the reports (D-041, D-049): a ceiling for the whole body, checked before
 * anything else, and images judged by their bytes like monitoreo (never by name or declared type).
 */
abstract class ReportFormRequest extends FormRequest
{
    /** The whole form (nginx allows the same on these routes). */
    abstract protected function maxBytes(): int;

    abstract protected function tooLargeMessage(): string;

    public function authorize(): bool
    {
        return true;
    }

    /** Rules live in after(): each check is monitoreo's, with its own message. */
    public function rules(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $files = array_sum(array_map(fn (UploadedFile $file) => (int) $file->getSize(), Arr::flatten($this->allFiles())));
        if (max((int) $this->server('CONTENT_LENGTH'), $files + strlen((string) http_build_query($this->input()))) > $this->maxBytes()) {
            throw new BusinessRuleViolation($this->tooLargeMessage(), 'too_large', 413);
        }
    }

    /** A text field as sent ('' when missing or not text). */
    protected function text(string $key): string
    {
        $value = $this->input($key);

        return is_string($value) ? $value : '';
    }

    /**
     * An uploaded image, or why it is refused (null + no problem: nothing was sent).
     *
     * @return array{0: ReportImage|null, 1: string|null}
     */
    protected static function image(mixed $file, string $tooBig, string $notImage): array
    {
        if (! $file instanceof UploadedFile) {
            return [null, null];
        }
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return [null, $tooBig];
        }
        $bytes = $file->isValid() ? (string) file_get_contents($file->getRealPath()) : '';

        return match (true) {
            $bytes === '' => [null, null],
            strlen($bytes) > ReportImage::MAX_BYTES => [null, $tooBig],
            ($type = ReportImage::sniff($bytes)) === null => [null, $notImage],
            default => [new ReportImage($bytes, $type), null],
        };
    }
}
