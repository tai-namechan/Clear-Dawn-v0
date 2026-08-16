<?php

namespace Tests\Unit;

use App\Services\BodyComposition\PdfTextExtractor;
use Tests\Support\EvoltSamplePdf;
use Tests\TestCase;

class PdfTextExtractorTest extends TestCase
{
    public function test_decodes_hex_strings_with_the_selected_font_cmap(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'evolt-cmap-');
        $this->assertNotFalse($path);
        $pdfPath = $path.'.pdf';
        rename($path, $pdfPath);
        file_put_contents($pdfPath, EvoltSamplePdf::conflictingToUnicodeContent());

        $document = (new PdfTextExtractor)->extractDocument($pdfPath);
        $texts = array_map(
            fn ($item): string => $item->text,
            $document->items,
        );

        $this->assertContains('A', $texts);
        $this->assertContains('B', $texts);
        $this->assertNotSame(['A', 'A'], $texts);
        $this->assertNotSame(['B', 'B'], $texts);

        unlink($pdfPath);
    }
}
