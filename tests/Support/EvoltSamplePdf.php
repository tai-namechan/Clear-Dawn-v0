<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * テスト用 EVOLT 風PDFの生成。
 */
final class EvoltSamplePdf
{
    /**
     * 仕様サンプル値を埋め込んだPDFバイナリ。
     *
     * @param  list<string>|null  $lines
     */
    public static function content(?array $lines = null): string
    {
        $lines ??= [
            'Name Taro',
            'Measured At 2026-08-16 03:07',
            'Height 178.0 cm',
            'Age 34',
            'Gender Male',
            'Weight 92.3 kg',
            'Skeletal Muscle 39.0 kg',
            'PBF 23.8 %',
            'Lean Body Mass 70.3 kg',
            'Protein Mass 14.8 kg',
            'Mineral Mass 4.2 kg',
            'Total Body Water 48.6 kg',
            'Body Fat Mass 22.0 kg',
            'Subcutaneous Fat 18.1 kg',
            'Visceral Fat Mass 3.9 kg',
            'Visceral Fat Area 112.0',
            'Visceral Fat Level 12',
            'BMR 1980',
            'TEE 2650',
            'Bio Age 31',
            'BWI Score 72.0',
            'Abdominal Circumference 95.7 cm',
            'Waist to Hip 0.91',
            'Recommended Calories 2200-2600',
            'Recommended Protein 140-180',
            'Recommended Carbohydrate 220-280',
            'Recommended Fat 55-75',
            'Left Arm Lean 3.92 Fat 1.36',
            'Right Arm Lean 3.79 Fat 1.48',
            'Torso Lean 29.78 Fat 12.71',
            'Left Leg Lean 10.55 Fat 3.16',
            'Right Leg Lean 10.73 Fat 3.29',
        ];

        $content = "BT\n/F1 12 Tf\n50 720 Td\n";

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $content .= "0 -18 Td\n";
            }

            $content .= '('.self::escape($line).") Tj\n";
        }

        $content .= 'ET';

        return self::wrap($content);
    }

    /**
     * アップロード用のPDFファイル。
     *
     * @param  list<string>|null  $lines
     */
    public static function uploadedFile(?array $lines = null, string $name = 'evolt.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'evolt-');

        if ($path === false) {
            throw new \RuntimeException('Failed to create a temporary PDF path.');
        }

        $pdfPath = $path.'.pdf';
        rename($path, $pdfPath);
        file_put_contents($pdfPath, self::content($lines));

        return new UploadedFile($pdfPath, $name, 'application/pdf', null, true);
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private static function wrap(string $stream): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            4 => '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($objects as $number => $_) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }
}
