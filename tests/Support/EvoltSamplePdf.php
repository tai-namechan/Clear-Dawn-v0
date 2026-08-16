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
     * コアは揃うが体脂肪率と体水分が一致する要確認PDF。
     */
    public static function uploadedInconsistentFile(string $name = 'evolt-inconsistent.pdf'): UploadedFile
    {
        return self::uploadedFile([
            'Weight 92.3 kg',
            'Skeletal Muscle 39.0 kg',
            'PBF 50.6 %',
            'Total Body Water 50.6 kg',
            'Lean Body Mass 70.3 kg',
            'Body Fat Mass 22.0 kg',
        ], $name);
    }

    /**
     * 同一グリフコードをフォントごとに別文字へ割り当てるPDF。
     */
    public static function conflictingToUnicodeContent(): string
    {
        $content = "BT\n/F1 12 Tf\n1 0 0 1 50 700 Tm\n<0001> Tj\n/F2 12 Tf\n1 0 0 1 50 680 Tm\n<0001> Tj\nET";
        $cmapA = self::toUnicodeStream(['0001' => '0041']);
        $cmapB = self::toUnicodeStream(['0001' => '0042']);

        return self::wrapObjects([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 7 0 R /F2 8 0 R >> >> >>',
            4 => '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
            7 => '<< /Type /Font /Subtype /Type0 /BaseFont /AAAAAA+F1 /Encoding /Identity-H /ToUnicode 10 0 R >>',
            8 => '<< /Type /Font /Subtype /Type0 /BaseFont /BBBBBB+F2 /Encoding /Identity-H /ToUnicode 11 0 R >>',
            10 => '<< /Length '.strlen($cmapA)." >>\nstream\n{$cmapA}\nendstream",
            11 => '<< /Length '.strlen($cmapB)." >>\nstream\n{$cmapB}\nendstream",
        ]);
    }

    /**
     * 圧縮ストリームPDFのアップロード用ファイル。
     */
    public static function uploadedFlateFile(string $name = 'evolt-flate.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'evolt-flate-');

        if ($path === false) {
            throw new \RuntimeException('Failed to create a temporary PDF path.');
        }

        $pdfPath = $path.'.pdf';
        rename($path, $pdfPath);
        file_put_contents($pdfPath, self::flateEncodedContent());

        return new UploadedFile($pdfPath, $name, 'application/pdf', null, true);
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

    /**
     * FlateDecode 本文を持つPDF。フォント辞書の Height を含めて誤抽出しないことを見る。
     */
    public static function flateEncodedContent(): string
    {
        $stream = "BT\n/F1 12 Tf\n1 0 0 1 21 740 Tm\n(173 cm) Tj\n1 0 0 1 164 740 Tm\n(92.3 kg) Tj\n1 0 0 1 21 660 Tm\n(70.3 / High) Tj\n1 0 0 1 164 660 Tm\n(22.0 / High) Tj\n1 0 0 1 307 660 Tm\n(8 / Balanced) Tj\n1 0 0 1 21 620 Tm\n(39.0 / High) Tj\n1 0 0 1 164 620 Tm\n(19.1) Tj\n1 0 0 1 307 620 Tm\n(1888 kCal) Tj\n1 0 0 1 21 580 Tm\n(14.4 / High) Tj\n1 0 0 1 164 580 Tm\n(2.9) Tj\n1 0 0 1 307 580 Tm\n(2907 kCal) Tj\n1 0 0 1 21 540 Tm\n(5.3 / High) Tj\n1 0 0 1 164 540 Tm\n(75 / Optimal) Tj\n1 0 0 1 21 500 Tm\n(50.6 / High) Tj\n1 0 0 1 164 500 Tm\n(23.8% / High) Tj\nET";
        $compressed = zlib_encode($stream, ZLIB_ENCODING_DEFLATE);

        if ($compressed === false) {
            throw new \RuntimeException('Failed to compress the sample PDF stream.');
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            4 => '<< /Filter /FlateDecode /Length '.strlen($compressed)." >>\nstream\n{$compressed}\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /FontDescriptor 6 0 R >>',
            6 => '<< /Type /FontDescriptor /FontName /Helvetica /CapHeight 763 /Flags 32 /FontBBox [0 -200 1000 900] /ItalicAngle 0 /Ascent 800 /Descent -200 /StemV 80 >>',
        ];

        return self::wrapObjects($objects);
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

        return self::wrapObjects($objects);
    }

    /**
     * @param  array<string, string>  $pairs
     */
    private static function toUnicodeStream(array $pairs): string
    {
        $chars = '';

        foreach ($pairs as $src => $dst) {
            $chars .= "<{$src}> <{$dst}>\n";
        }

        $count = count($pairs);

        return "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n{$count} beginbfchar\n{$chars}endbfchar\nendcmap\nend\nend";
    }

    /**
     * @param  array<int, string>  $objects
     */
    private static function wrapObjects(array $objects): string
    {
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
