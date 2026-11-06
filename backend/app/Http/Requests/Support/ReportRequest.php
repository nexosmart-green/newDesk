<?php

namespace App\Http\Requests\Support;

use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Text;
use App\Domain\StatusPage\Queries\StatusPage;
use App\Domain\StatusPage\Status\Parts;
use App\Domain\Support\Models\Report;
use App\Domain\Support\ReportImage;
use Illuminate\Validation\Validator;

/**
 * "Reportar un problema" (D-041): what happens (10–2000 characters), where (one of the project's
 * parts, or "No sé"), how urgent, an optional screenshot up to 5 MB, and what the browser adds.
 * Invalid forms never reach the live check nor count for the budget.
 */
final class ReportRequest extends ReportFormRequest
{
    private ?Project $project = null;

    private ?ReportImage $screenshot = null;

    public function project(): Project
    {
        return $this->project ??= StatusPage::clientProject($this->user(), (string) $this->route('slug'));
    }

    protected function maxBytes(): int
    {
        return 6 << 20;
    }

    protected function tooLargeMessage(): string
    {
        return 'El reporte pasa de 6 MB. Probá con una captura más chica.';
    }

    protected function prepareForValidation(): void
    {
        $this->project(); // an unknown project is a 404 before anything else
        parent::prepareForValidation();
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $length = mb_strlen($this->description());
            if ($length < 10 || $length > 2000) {
                $validator->errors()->add('description', 'Contanos qué pasa (entre 10 y 2000 caracteres).');
            }
            if (! array_key_exists($this->text('urgency'), Report::URGENCY_LABELS)) {
                $validator->errors()->add('urgency', 'Elegí qué tan urgente es.');
            }
            if ($this->part() !== '' && ! in_array($this->part(), Parts::present(StatusPage::components($this->project())), true)) {
                $validator->errors()->add('part', 'Elegí una opción de la lista.');
            }
            [$this->screenshot, $problem] = self::image($this->file('screenshot'), 'La captura no puede pasar de 5 MB.', 'La captura tiene que ser PNG, JPG o WebP.');
            if ($problem !== null) {
                $validator->errors()->add('screenshot', $problem);
            }
        }];
    }

    public function description(): string
    {
        return Text::clean($this->text('description'));
    }

    public function part(): string
    {
        return trim($this->text('part'));
    }

    public function screenshot(): ?ReportImage
    {
        return $this->screenshot;
    }

    /** @return array{part: string, urgency: string, description: string, browser: array<string, string>, ip: string} */
    public function report(): array
    {
        $clip = fn (string $text, int $limit) => mb_substr(Text::clean($text), 0, $limit);

        return [
            'part' => $this->part(),
            'urgency' => $this->text('urgency'),
            'description' => $this->description(),
            'browser' => [
                'user_agent' => $clip((string) $this->userAgent(), 500),
                'page_url' => $clip($this->text('page_url'), 1000),
                'screen' => $clip($this->text('screen'), 20),
                'timezone' => $clip($this->text('timezone'), 64),
                'language' => $clip($this->text('language'), 35),
            ],
            'ip' => (string) $this->ip(),
        ];
    }
}
