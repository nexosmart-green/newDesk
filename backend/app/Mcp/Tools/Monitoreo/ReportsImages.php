<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\Support\ReportImage;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('reports_images')]
#[Description('Devuelve como imágenes la captura del reporte y las imágenes que se mandaron en el chat (hasta 6 por llamada).')]
#[IsReadOnly]
final class ReportsImages extends MonitoreoTool
{
    private const MAX = 6;

    protected function properties(): array
    {
        return ['id' => self::ID, 'images' => ['type' => 'array', 'items' => ['type' => 'string'],
            'description' => 'Cuáles: "screenshot" y/o ids de adjuntos del chat (de reports_get). Vacío = todas, hasta 6.']];
    }

    protected function required(): array
    {
        return ['id'];
    }

    /** A summary, then the images in that order: one answer (a plain array would go out as a stream). */
    public function answer(Request $request): Response|ResponseFactory
    {
        $report = self::report($request->get('id'));
        $all = $report->screenshot_path !== null && $report->screenshot_type !== null ? [['screenshot', $report->screenshot_path, $report->screenshot_type]] : [];
        foreach ($report->chat(withInternal: true) as $message) {
            foreach ($message->attachments as $attachment) {
                $all[] = [(string) $attachment->id, $attachment->path, $attachment->content_type];
            }
        }
        $wanted = array_flip(array_map('trim', (array) $request->get('images', [])));
        $disk = Storage::disk(ReportImage::DISK);
        $labels = [];
        $images = [];
        foreach ($all as [$label, $path, $type]) {
            if ($wanted !== [] && ! isset($wanted[$label])) {
                continue;
            }
            if (count($labels) === self::MAX) {
                break;
            }
            if (! $disk->exists($path)) {
                continue; // purged after 180 days
            }
            $images[] = Response::image((string) $disk->get($path), $type);
            $labels[] = $label;
        }
        if ($labels === []) {
            return Response::text("El reporte #{$report->id} no tiene imágenes disponibles (o ya se borraron: se guardan 180 días).");
        }
        $summary = sprintf('Reporte #%d: %d imagen(es), en orden: %s.', $report->id, count($labels), implode(', ', $labels));
        if ($wanted === [] && count($all) > count($labels)) {
            $summary .= sprintf(' Hay %d en total; pedí las demás por id.', count($all));
        }

        return Response::make([Response::text($summary), ...$images]);
    }
}
