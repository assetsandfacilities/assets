<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Pulls plain text out of an uploaded program flow document.
 *
 * Deliberately written with no Composer dependencies, so the feature works on a
 * fresh clone without anyone having to run `composer require` first:
 *
 *   - DOCX  -> a DOCX is a ZIP; word/document.xml is read with the standard
 *              ZipArchive extension and the paragraph markup is turned back
 *              into line breaks. This is exact and reliable.
 *   - TXT   -> read as-is.
 *   - PDF   -> best effort. Text drawing operators are pulled out of the
 *              content streams, including Flate-compressed ones. This handles
 *              the ordinary case of a PDF exported from Word, but it cannot
 *              read scanned images and will not always recover an exotic
 *              layout. When it fails the caller keeps the attachment so the
 *              FMO can still open the real document.
 *
 * If `smalot/pdfparser` happens to be installed, it is used for PDFs instead,
 * because it is far more accurate than the built-in fallback.
 */
class DocumentTextExtractor
{
    public const MAX_CHARS = 20000;

    /** @return array{text: ?string, note: ?string} */
    public static function fromUpload(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if ($path === false || !is_readable($path)) {
            return ['text' => null, 'note' => 'The uploaded file could not be read.'];
        }

        return match ($extension) {
            'docx' => self::fromDocx($path),
            'txt'  => self::clean(file_get_contents($path)),
            'pdf'  => self::fromPdf($path),
            default => ['text' => null, 'note' => 'Only PDF, Word (.docx) and plain text files can be read automatically.'],
        };
    }

    /** DOCX is a ZIP archive; the body lives in word/document.xml. */
    private static function fromDocx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return ['text' => null, 'note' => 'The PHP zip extension is not enabled on this server, so Word files cannot be read automatically.'];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return ['text' => null, 'note' => 'That Word file could not be opened. It may be corrupted or saved in the older .doc format.'];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return ['text' => null, 'note' => 'That file is not a valid .docx document.'];
        }

        // Preserve the document's line structure: paragraph and line-break tags
        // become newlines, tab tags become tabs, everything else is stripped.
        $xml = preg_replace('/<w:tab\b[^>]*\/?>/', "\t", $xml);
        $xml = preg_replace('/<w:br\b[^>]*\/?>/', "\n", $xml);
        $xml = preg_replace('/<\/w:p>/', "\n", $xml);
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return self::clean($text);
    }

    private static function fromPdf(string $path): array
    {
        // Prefer the proper parser when the project happens to have it.
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                return self::clean($parser->parseFile($path)->getText());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return ['text' => null, 'note' => 'That PDF could not be read.'];
        }

        try {
            $text = self::parsePdf($raw);
        } catch (\Throwable $e) {
            report($e);
            $text = '';
        }

        if (trim($text) === '') {
            return [
                'text' => null,
                'note' => 'The text in this PDF could not be read automatically — it may be a scan or an image rather than real text. The file is still attached and can be opened by the Facilities Management Office. Uploading a Word (.docx) file instead will extract the text reliably.',
            ];
        }

        return self::clean($text);
    }

    /**
     * Minimal PDF text reader.
     *
     * Documents exported from Word embed subsetted fonts and write text as hex
     * glyph IDs (e.g. `[<03F40357>] TJ`) rather than readable characters, so
     * pulling the strings out is not enough — each font's /ToUnicode CMap has
     * to be parsed and the glyph IDs mapped back to real characters. Line
     * breaks are recovered from the vertical position in the text matrix.
     */
    private static function parsePdf(string $raw): string
    {
        // Index every indirect object so references can be resolved.
        $objects = [];
        if (preg_match_all('/(\d+)\s+0\s+obj\b(.*?)endobj/s', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $objects[(int) $match[1]] = $match[2];
            }
        }

        // Font resource name (F1, F2, ...) -> its ToUnicode character map.
        $fontMaps = [];
        foreach ($objects as $body) {
            if (!preg_match('/\/Font\s*<<(.*?)>>/s', $body, $fontDict)) {
                continue;
            }
            if (!preg_match_all('/\/(\w+)\s+(\d+)\s+0\s+R/', $fontDict[1], $refs, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($refs as $ref) {
                $fontObject = $objects[(int) $ref[2]] ?? '';
                if (preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $fontObject, $toUnicode)) {
                    $cmap = self::decodeStream($objects[(int) $toUnicode[1]] ?? '');
                    if ($cmap !== null) {
                        $fontMaps[$ref[1]] = self::parseCMap($cmap);
                    }
                }
            }
        }

        $lines = [];
        foreach ($objects as $body) {
            if (!str_contains($body, '/Type/Page') && !str_contains($body, '/Type /Page')) {
                continue;
            }
            if (!preg_match_all('/\/Contents\s+(\d+)\s+0\s+R/', $body, $contentRefs)) {
                continue;
            }
            foreach ($contentRefs[1] as $contentRef) {
                $content = self::decodeStream($objects[(int) $contentRef] ?? '');
                if ($content === null) {
                    continue;
                }
                self::readContentStream($content, $fontMaps, $lines);
            }
        }

        return implode("\n", $lines);
    }

    /** Walks a content stream, tracking the active font and the text position. */
    private static function readContentStream(string $content, array $fontMaps, array &$lines): void
    {
        $pattern = '/\/(\w+)\s+[\d.]+\s+Tf'          // font selection
                 . '|1\s+0\s+0\s+1\s+[\d.]+\s+([\d.]+)\s+Tm'  // text matrix (y)
                 . '|\[(.*?)\]\s*TJ'                    // array show
                 . '|\(((?:\\\\.|[^\\\\()])*)\)\s*Tj/s';   // simple show

        if (!preg_match_all($pattern, $content, $tokens, PREG_SET_ORDER)) {
            return;
        }

        $font = null;
        $lastY = null;
        $line = '';

        foreach ($tokens as $token) {
            if (($token[1] ?? '') !== '') {
                $font = $token[1];
                continue;
            }

            if (($token[2] ?? '') !== '') {
                $y = (float) $token[2];
                // A meaningful vertical jump means a new line of text.
                if ($lastY !== null && abs($y - $lastY) > 1.5 && trim($line) !== '') {
                    $lines[] = trim($line);
                    $line = '';
                }
                $lastY = $y;
                continue;
            }

            $map = $fontMaps[$font] ?? [];

            if (isset($token[3]) && $token[3] !== '') {
                // Hex-encoded glyph IDs mapped through the font's CMap.
                if (preg_match_all('/<([0-9A-Fa-f]+)>/', $token[3], $hexes)) {
                    foreach ($hexes[1] as $hex) {
                        foreach (str_split($hex, 4) as $code) {
                            $line .= $map[hexdec($code)] ?? '';
                        }
                    }
                }
                // Plain literal strings inside the same array.
                if (preg_match_all('/\(((?:\\\\.|[^\\\\()])*)\)/', $token[3], $literals)) {
                    foreach ($literals[1] as $literal) {
                        $line .= self::unescapePdf($literal);
                    }
                }
                continue;
            }

            if (isset($token[4]) && $token[4] !== '') {
                $line .= self::unescapePdf($token[4]);
            }
        }

        if (trim($line) !== '') {
            $lines[] = trim($line);
        }
    }

    /** Parses a ToUnicode CMap into a glyph-code => character lookup. */
    private static function parseCMap(string $data): array
    {
        $map = [];

        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $data, $charBlocks)) {
            foreach ($charBlocks[1] as $block) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
                    foreach ($pairs as $pair) {
                        $map[hexdec($pair[1])] = self::utf16HexToString($pair[2]);
                    }
                }
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $data, $rangeBlocks)) {
            foreach ($rangeBlocks[1] as $block) {
                // <lo> <hi> <base>
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $ranges, PREG_SET_ORDER)) {
                    foreach ($ranges as $range) {
                        $lo = hexdec($range[1]);
                        $hi = hexdec($range[2]);
                        $base = hexdec($range[3]);
                        for ($i = 0; $i <= $hi - $lo && $i < 65536; $i++) {
                            $map[$lo + $i] = mb_chr($base + $i, 'UTF-8');
                        }
                    }
                }
                // <lo> <hi> [<a> <b> ...]
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[(.*?)\]/s', $block, $arrays, PREG_SET_ORDER)) {
                    foreach ($arrays as $array) {
                        $lo = hexdec($array[1]);
                        $hi = hexdec($array[2]);
                        if (preg_match_all('/<([0-9A-Fa-f]+)>/', $array[3], $items)) {
                            foreach ($items[1] as $i => $item) {
                                if ($lo + $i <= $hi) {
                                    $map[$lo + $i] = self::utf16HexToString($item);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $map;
    }

    private static function utf16HexToString(string $hex): string
    {
        $out = '';
        foreach (str_split($hex, 4) as $unit) {
            if ($unit !== '') {
                $out .= mb_chr(hexdec($unit), 'UTF-8');
            }
        }

        return $out;
    }

    /** Returns the decompressed stream inside an object body, if there is one. */
    private static function decodeStream(string $body): ?string
    {
        if (!preg_match('/stream\r?\n(.*?)\r?\nendstream/s', $body, $match)) {
            return null;
        }

        $raw = $match[1];
        $decoded = @gzuncompress($raw);
        if ($decoded === false) {
            $decoded = @gzinflate(substr($raw, 2));
        }
        if ($decoded === false) {
            $decoded = @gzinflate($raw);
        }

        return $decoded === false ? $raw : $decoded;
    }

    private static function unescapePdf(string $value): string
    {
        return strtr($value, [
            '\\n' => "\n", '\\r' => "\r", '\\t' => "\t",
            '\\(' => '(', '\\)' => ')', '\\\\' => '\\',
        ]);
    }

    /** @return array{text: ?string, note: ?string} */
    private static function clean(string|false $text): array
    {
        if ($text === false) {
            return ['text' => null, 'note' => 'The uploaded file could not be read.'];
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim(preg_replace('/^[ \t]+|[ \t]+$/m', '', $text));

        if ($text === '') {
            return ['text' => null, 'note' => 'No readable text was found in that file.'];
        }

        $note = null;
        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS);
            $note = 'The document was long, so only the first ' . number_format(self::MAX_CHARS) . ' characters were copied into the Program Flow. The complete file is still attached.';
        }

        return ['text' => $text, 'note' => $note];
    }
}
