<?php

namespace App\Http\Requests\Support;

use App\Domain\Shared\Text;
use App\Domain\Support\Actions\PostReportMessage;
use App\Domain\Support\ReportImage;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * A chat message (D-049): `body` up to 4000 characters, up to 3 `images` (PNG, JPG or WebP of 5 MB
 * each, by their bytes) and, from the team, `internal` for a team-only note (D-051). Something has
 * to be said or shown.
 */
final class ReportMessageRequest extends ReportFormRequest
{
    /** @var list<ReportImage> */
    private array $images = [];

    protected function maxBytes(): int
    {
        return 16 << 20; // 3 images of 5 MB plus the text
    }

    protected function tooLargeMessage(): string
    {
        return 'El mensaje pesa demasiado (máximo 3 imágenes de 5 MB).';
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $problems = [];
            foreach (Arr::wrap($this->file('images', [])) as $file) {
                [$image, $problem] = self::image($file, 'Cada imagen puede pesar hasta 5 MB.', 'Las imágenes tienen que ser PNG, JPG o WebP.');
                if (($image !== null || $problem !== null) && count($this->images) === PostReportMessage::MAX_IMAGES) {
                    [$image, $problem] = [null, 'Hasta 3 imágenes por mensaje.'];
                }
                if ($problem !== null) {
                    $problems[$problem] = true;
                } elseif ($image !== null) {
                    $this->images[] = $image;
                }
            }
            foreach (array_keys($problems) as $problem) {
                $validator->errors()->add('images', $problem);
            }
            if (mb_strlen($this->body()) > 4000) {
                $validator->errors()->add('body', 'El mensaje puede tener hasta 4000 caracteres.');
            }
            if ($this->body() === '' && $this->images === [] && $validator->errors()->isEmpty()) {
                $validator->errors()->add('body', 'Escribí un mensaje o adjuntá una imagen.');
            }
        }];
    }

    public function body(): string
    {
        return Text::clean($this->text('body'));
    }

    /** @return list<ReportImage> */
    public function images(): array
    {
        return $this->images;
    }

    public function internal(): bool
    {
        return in_array($this->text('internal'), ['1', 'true'], true);
    }
}
