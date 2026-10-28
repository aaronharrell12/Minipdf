<?php
declare(strict_types=1);

/**
 * MiniPdf: a dependency-free PDF writer with a PDFlib-shaped API.
 *
 * Method names and argument order follow PDFlib's PHP object API, so existing
 * code can usually switch by replacing `new PDFlib()` with `new MiniPdf()`.
 *
 * Supported:
 *   begin_document / end_document / get_buffer
 *   begin_page_ext / end_page_ext   (plus begin_page / end_page)
 *   set_info
 *   load_font / setfont / stringwidth / set_value('leading', ...)
 *   set_text_pos / show / show_xy / continue_text
 *   show_boxed   (left, right, center, justify, fulljustify; "blind")
 *   load_image / fit_image / place_image / close_image   (JPEG, PNG; GIF via GD)
 *
 * Fonts: the 12 Latin PDF standard fonts (Helvetica, Times, Courier families),
 * WinAnsi (Windows-1252) encoding. Nothing is embedded. Characters outside
 * Windows-1252 print as "?".
 *
 * Text input: valid UTF-8 is decoded as UTF-8; anything else is treated as
 * Windows-1252 bytes (common in older MySQL latin1 columns).
 *
 * Coordinates: points (1/72 inch), origin at the bottom-left of the page,
 * same as PDFlib's default.
 */
final class MiniPdfException extends RuntimeException {}

final class MiniPdf
{
    /** Glyph widths (1/1000 em) for WinAnsi codes 32..255. Courier is a fixed 600. */
    private const WIDTHS = [
        'Helvetica' => '278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,350,556,350,222,556,333,1000,556,556,333,1000,667,333,1000,350,611,350,350,222,222,333,333,350,556,1000,333,1000,500,333,944,350,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500',
        'Helvetica-Bold' => '278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,350,556,350,278,556,500,1000,556,556,333,1000,667,333,1000,350,611,350,350,278,278,500,500,350,556,1000,333,1000,556,333,944,350,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556',
        'Times-Roman' => '250,333,408,500,500,833,778,180,333,333,500,564,250,333,250,278,500,500,500,500,500,500,500,500,500,500,278,278,564,564,564,444,921,722,667,667,722,611,556,722,722,333,389,722,611,889,722,722,556,722,667,556,611,722,722,944,722,722,611,333,278,333,469,500,333,444,500,444,500,444,333,500,500,278,278,500,278,778,500,500,500,500,333,389,278,500,500,722,500,500,444,480,200,480,541,350,500,350,333,500,444,1000,500,500,333,1000,556,333,889,350,611,350,350,333,333,444,444,350,500,1000,333,980,389,333,722,350,444,722,250,333,500,500,500,500,200,500,333,760,276,500,564,333,760,333,400,564,300,300,333,500,453,250,333,300,310,500,750,750,750,444,722,722,722,722,722,722,889,667,611,611,611,611,333,333,333,333,722,722,722,722,722,722,722,564,722,722,722,722,722,722,556,500,444,444,444,444,444,444,667,444,444,444,444,444,278,278,278,278,500,500,500,500,500,500,500,564,500,500,500,500,500,500,500,500',
        'Times-Bold' => '250,333,555,500,500,1000,833,278,333,333,500,570,250,333,250,278,500,500,500,500,500,500,500,500,500,500,333,333,570,570,570,500,930,722,667,722,722,667,611,778,778,389,500,778,667,944,722,778,611,778,722,556,667,722,722,1000,722,722,667,333,278,333,581,500,333,500,556,444,556,444,333,500,556,278,333,556,278,833,556,500,556,556,444,389,333,556,500,722,500,500,444,394,220,394,520,350,500,350,333,500,500,1000,500,500,333,1000,556,333,1000,350,667,350,350,333,333,500,500,350,500,1000,333,1000,389,333,722,350,444,722,250,333,500,500,500,500,220,500,333,747,300,500,570,333,747,333,400,570,300,300,333,556,540,250,333,300,330,500,750,750,750,500,722,722,722,722,722,722,1000,722,667,667,667,667,389,389,389,389,722,722,778,778,778,778,778,570,778,722,722,722,722,722,611,556,500,500,500,500,500,500,722,444,444,444,444,444,278,278,278,278,500,556,500,500,500,500,500,570,500,556,556,556,556,500,556,500',
        'Times-Italic' => '250,333,420,500,500,833,778,214,333,333,500,675,250,333,250,278,500,500,500,500,500,500,500,500,500,500,333,333,675,675,675,500,920,611,611,667,722,611,611,722,722,333,444,667,556,833,667,722,611,722,611,500,556,722,611,833,611,556,556,389,278,389,422,500,333,500,500,444,500,444,278,500,500,278,278,444,278,722,500,500,500,500,389,389,278,500,444,667,444,444,389,400,275,400,541,350,500,350,333,500,556,889,500,500,333,1000,500,333,944,350,556,350,350,333,333,556,556,350,500,889,333,980,389,333,667,350,389,556,250,389,500,500,500,500,275,500,333,760,276,500,675,333,760,333,400,675,300,300,333,500,523,250,333,300,310,500,750,750,750,500,611,611,611,611,611,611,889,667,611,611,611,611,333,333,333,333,722,667,722,722,722,722,722,675,722,722,722,722,722,556,611,500,500,500,500,500,500,500,667,444,444,444,444,444,278,278,278,278,500,500,500,500,500,500,500,675,500,500,500,500,500,444,500,444',
        'Times-BoldItalic' => '250,389,555,500,500,833,778,278,333,333,500,570,250,333,250,278,500,500,500,500,500,500,500,500,500,500,333,333,570,570,570,500,832,667,667,667,722,667,667,722,778,389,500,667,611,889,722,722,611,722,667,556,611,722,667,889,667,611,611,333,278,333,570,500,333,500,500,444,500,444,333,500,556,278,278,500,278,778,556,500,500,500,389,389,278,556,444,667,500,444,389,348,220,348,570,350,500,350,333,500,500,1000,500,500,333,1000,556,333,944,350,611,350,350,333,333,500,500,350,500,1000,333,1000,389,333,722,350,389,611,250,389,500,500,500,500,220,500,333,747,266,500,606,333,747,333,400,570,300,300,333,576,500,250,333,300,300,500,750,750,750,500,667,667,667,667,667,667,944,667,667,667,667,667,389,389,389,389,722,722,722,722,722,722,722,570,722,722,722,722,722,611,611,500,500,500,500,500,500,500,722,444,444,444,444,444,278,278,278,278,500,556,500,500,500,500,500,570,500,556,556,556,556,444,500,444',    ];

    ];

    private const FONT_ALIASES = [
        'Arial' => 'Helvetica',
        'Arial-Bold' => 'Helvetica-Bold',
        'Arial-Italic' => 'Helvetica-Oblique',
        'Arial-BoldItalic' => 'Helvetica-BoldOblique',
        'TimesNewRoman' => 'Times-Roman',
        'TimesNewRoman-Bold' => 'Times-Bold',
        'TimesNewRoman-Italic' => 'Times-Italic',
        'TimesNewRoman-BoldItalic' => 'Times-BoldItalic',
        'CourierNew' => 'Courier',
        'CourierNew-Bold' => 'Courier-Bold',
    ];

    /** Fonts that share another font's widths. */
    private const WIDTH_SOURCE = [
        'Helvetica' => 'Helvetica',
        'Helvetica-Oblique' => 'Helvetica',
        'Helvetica-Bold' => 'Helvetica-Bold',
        'Helvetica-BoldOblique' => 'Helvetica-Bold',
        'Times-Roman' => 'Times-Roman',
        'Times-Bold' => 'Times-Bold',
        'Times-Italic' => 'Times-Italic',
        'Times-BoldItalic' => 'Times-BoldItalic',
        'Courier' => null,
        'Courier-Bold' => null,
        'Courier-Oblique' => null,
        'Courier-BoldOblique' => null,
    ];

    /** Unicode code points for Windows-1252 bytes 0x80..0x9F (0 = undefined). */
    private const CP1252_HIGH = [
        0x20AC, 0, 0x201A, 0x0192, 0x201E, 0x2026, 0x2020, 0x2021,
        0x02C6, 0x2030, 0x0160, 0x2039, 0x0152, 0, 0x017D, 0,
        0, 0x2018, 0x2019, 0x201C, 0x201D, 0x2022, 0x2013, 0x2014,
        0x02DC, 0x2122, 0x0161, 0x203A, 0x0153, 0, 0x017E, 0x0178,
    ];

    private const PAGE_SIZES = [
        'letter' => [612, 792],
        'legal'  => [612, 1008],
        'a4'     => [595, 842],
        'a3'     => [842, 1190],
    ];

    /** @var array<int, ?string> object id => serialized body */
    private array $objects = [];
    private array $pageIds = [];
    private array $info = [];
    private array $fonts = [];        // handle => [name, objId, resName, widths]
    private array $images = [];       // handle => [objId, resName, wPx, hPx, dpiX, dpiY]
    private array $imageCache = [];   // file identity => handle, so repeated loads embed once
    private ?array $page = null;      // open page state
    private ?int $font = null;
    private float $fontSize = 0.0;
    private ?float $leading = null;   // null = follow font size
    private float $textX = 0.0;
    private float $textY = 0.0;
    private float $lineStartX = 0.0;
    private bool $docOpen = false;
    private string $filename = '';
    private ?string $buffer = null;
    private ?array $cp1252Reverse = null;

    // ------------------------------------------------------------------
    // Document
    // ------------------------------------------------------------------

    /** $filename '' builds the PDF in memory; fetch it with get_buffer(). */
    public function begin_document(string $filename = '', string $optlist = ''): int
    {
        $this->objects = [1 => null, 2 => null]; // 1 catalog, 2 page tree
        $this->pageIds = [];
        $this->info = [];
        $this->fonts = [];
        $this->images = [];
        $this->imageCache = [];
        $this->page = null;
        $this->font = null;
        $this->leading = null;
        $this->buffer = null;
        $this->filename = $filename;
        $this->docOpen = true;
        return 1;
    }

    public function set_info(string $key, string $value): void
    {
        $this->info[$key] = $value;
    }

    public function end_document(string $optlist = ''): void
    {
        $this->requireDoc();
        if ($this->page !== null) {
            throw new MiniPdfException('end_document called with a page still open');
        }
        if (!$this->pageIds) {
            throw new MiniPdfException('document has no pages');
        }

        $kids = implode(' ', array_map(fn($id) => "$id 0 R", $this->pageIds));
        $this->objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($this->pageIds) . ' >>';
        $this->objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $info = $this->info + ['Producer' => 'MiniPdf'];
        $infoDict = '<< /CreationDate (D:' . date('YmdHis') . ')';
        foreach ($info as $k => $v) {
            $infoDict .= ' /' . $this->pdfName((string)$k) . ' ' . $this->utf16Hex($v);
        }
        $infoId = $this->addObject($infoDict . ' >>');

        $out = "%PDF-1.5\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        $count = count($this->objects);
        for ($id = 1; $id <= $count; $id++) {
            if (!isset($this->objects[$id])) {
                throw new MiniPdfException("internal error: object $id never written");
            }
            $offsets[$id] = strlen($out);
            $out .= "$id 0 obj\n" . $this->objects[$id] . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . ($count + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $count; $id++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $out .= "trailer\n<< /Size " . ($count + 1) . " /Root 1 0 R /Info $infoId 0 R >>\n";
        $out .= "startxref\n$xref\n%%EOF\n";

        $this->docOpen = false;
        if ($this->filename !== '') {
            if (file_put_contents($this->filename, $out) === false) {
                throw new MiniPdfException("cannot write {$this->filename}");
            }
            $this->buffer = null;
        } else {
            $this->buffer = $out;
        }
    }

    public function get_buffer(): string
    {
        if ($this->buffer === null) {
            throw new MiniPdfException('no buffer: call begin_document("") and end_document() first');
        }
        return $this->buffer;
    }

    // ------------------------------------------------------------------
    // Pages
    // ------------------------------------------------------------------

    /** Width/height in points. Pass 0, 0 with "width=letter.width height=letter.height" style options if preferred. */
    public function begin_page_ext(float $width, float $height, string $optlist = ''): void
    {
        $this->requireDoc();
        if ($this->page !== null) {
            throw new MiniPdfException('begin_page_ext called while a page is open');
        }
        $opts = $this->parseOptlist($optlist);
        if ($width == 0 && isset($opts['width'])) {
            $width = $this->pageDimension($opts['width']);
        }
        if ($height == 0 && isset($opts['height'])) {
            $height = $this->pageDimension($opts['height']);
        }
        if ($width <= 0 || $height <= 0) {
            throw new MiniPdfException('page width and height must be positive');
        }
        $this->page = ['w' => $width, 'h' => $height, 'ops' => [], 'fonts' => [], 'images' => []];
        $this->textX = $this->textY = $this->lineStartX = 0.0;
    }

    public function begin_page(float $width, float $height): void
    {
        $this->begin_page_ext($width, $height);
    }

    public function end_page_ext(string $optlist = ''): void
    {
        $p = $this->requirePage();
        $content = implode("\n", $p['ops']);
        $contentId = $this->addObject($this->streamBody('', $content, true));

        $res = '';
        if ($p['fonts']) {
            $res .= ' /Font <<';
            foreach (array_keys($p['fonts']) as $h) {
                $res .= ' /' . $this->fonts[$h]['res'] . ' ' . $this->fonts[$h]['id'] . ' 0 R';
            }
            $res .= ' >>';
        }
        if ($p['images']) {
            $res .= ' /XObject <<';
            foreach (array_keys($p['images']) as $h) {
                $res .= ' /' . $this->images[$h]['res'] . ' ' . $this->images[$h]['id'] . ' 0 R';
            }
            $res .= ' >>';
        }

        $this->pageIds[] = $this->addObject(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $this->num($p['w']) . ' ' . $this->num($p['h']) . ']'
            . ' /Resources << /ProcSet [/PDF /Text /ImageB /ImageC /ImageI]' . $res . ' >>'
            . " /Contents $contentId 0 R >>"
        );
        $this->page = null;
    }

    public function end_page(): void
    {
        $this->end_page_ext();
    }

    // ------------------------------------------------------------------
    // Fonts and text
    // ------------------------------------------------------------------

    /** $encoding is accepted for compatibility; text is always set in WinAnsi. */
    public function load_font(string $fontname, string $encoding = 'winansi', string $optlist = ''): int
    {
        $this->requireDoc();
        $name = self::FONT_ALIASES[$fontname] ?? $fontname;
        if (!array_key_exists($name, self::WIDTH_SOURCE)) {
            throw new MiniPdfException("font '$fontname' is not a supported standard font");
        }
        foreach ($this->fonts as $h => $f) {
            if ($f['name'] === $name) {
                return $h;
            }
        }
        $source = self::WIDTH_SOURCE[$name];
        $widths = $source === null
            ? array_fill(0, 224, 600)
            : array_map('intval', explode(',', self::WIDTHS[$source]));

        $handle = count($this->fonts) + 1;
        $this->fonts[$handle] = [
            'name' => $name,
            'res' => 'F' . $handle,
            'widths' => $widths,
            'id' => $this->addObject("<< /Type /Font /Subtype /Type1 /BaseFont /$name /Encoding /WinAnsiEncoding >>"),
        ];
        return $handle;
    }

    public function setfont(int $font, float $fontsize): void
    {
        if (!isset($this->fonts[$font])) {
            throw new MiniPdfException("invalid font handle $font");
        }
        $this->font = $font;
        $this->fontSize = $fontsize;
    }

    /** Supports 'leading'. */
    public function set_value(string $key, float $value): void
    {
        if ($key !== 'leading') {
            throw new MiniPdfException("set_value: unsupported key '$key'");
        }
        $this->leading = $value;
    }

    public function stringwidth(string $text, int $font, float $fontsize): float
    {
        if (!isset($this->fonts[$font])) {
            throw new MiniPdfException("invalid font handle $font");
        }
        return $this->bytesWidth($this->toWinAnsi($text), $font, $fontsize);
    }

    public function set_text_pos(float $x, float $y): void
    {
        $this->textX = $this->lineStartX = $x;
        $this->textY = $y;
    }

    /** Draws at the current text position and advances it. */
    public function show(string $text): void
    {
        $this->requireFont();
        $bytes = $this->toWinAnsi(str_replace(["\r", "\n"], ' ', $text));
        $this->emitText($bytes, $this->textX, $this->textY, 0.0);
        $this->textX += $this->bytesWidth($bytes, $this->font, $this->fontSize);
    }

    public function show_xy(string $text, float $x, float $y): void
    {
        $this->set_text_pos($x, $y);
        $this->show($text);
    }

    /** Moves down one line (leading) back to the line's start x, then shows. */
    public function continue_text(string $text): void
    {
        $this->requireFont();
        $this->textY -= $this->currentLeading();
        $this->textX = $this->lineStartX;
        $this->show($text);
    }

    /**
     * Formats text into a box whose lower-left corner is ($x, $y).
     * $mode: left | right | center | justify | fulljustify.
     * With $width = $height = 0, places one line aligned on the point ($x, $y).
     * $feature "blind" measures without drawing.
     * Returns the number of characters that did not fit (0 = everything placed).
     *
     * The first baseline sits one font size below the box top; a line fits
     * while its baseline is at or above the box bottom. Lines are $leading apart.
     */
    public function show_boxed(string $text, float $x, float $y, float $width, float $height, string $mode, string $feature = ''): int
    {
        $this->requireFont();
        $mode = strtolower($mode);
        if (!in_array($mode, ['left', 'right', 'center', 'justify', 'fulljustify'], true)) {
            throw new MiniPdfException("show_boxed: unknown mode '$mode'");
        }
        $blind = strtolower(trim($feature)) === 'blind';
        $s = $this->toWinAnsi(str_replace("\r\n", "\n", $text));

        if ($width == 0 && $height == 0) {
            if ($mode === 'justify' || $mode === 'fulljustify') {
                throw new MiniPdfException('show_boxed: justify modes need a box');
            }
            $s = str_replace(["\r", "\n"], ' ', $s);
            $w = $this->bytesWidth($s, $this->font, $this->fontSize);
            $lx = match ($mode) { 'right' => $x - $w, 'center' => $x - $w / 2, default => $x };
            if (!$blind) {
                $this->emitText($s, $lx, $y, 0.0);
            }
            return 0;
        }

        $len = strlen($s);
        $pos = 0;
        $leading = $this->currentLeading();
        $baseline = $y + $height - $this->fontSize;

        while ($pos < $len && $baseline >= $y - 1e-6) {
            [$end, $next, $paraEnd] = $this->fitLine($s, $pos, $width);
            $line = substr($s, $pos, $end - $pos);
            if (!$blind && $line !== '') {
                $lw = $this->bytesWidth($line, $this->font, $this->fontSize);
                $lx = $x;
                $tw = 0.0;
                if ($mode === 'right') {
                    $lx = $x + $width - $lw;
                } elseif ($mode === 'center') {
                    $lx = $x + ($width - $lw) / 2;
                } elseif ($mode === 'fulljustify' || ($mode === 'justify' && !$paraEnd)) {
                    $spaces = substr_count($line, ' ');
                    if ($spaces > 0) {
                        $tw = ($width - $lw) / $spaces;
                    }
                }
                $this->emitText($line, $lx, $baseline, $tw);
            }
            $pos = $next;
            $baseline -= $leading;
        }
        return $len - $pos;
    }

    // ------------------------------------------------------------------
    // Lines and shapes
    // ------------------------------------------------------------------
    // Build a path (rect, moveto/lineto, closepath), then paint it with
    // stroke, fill or fill_stroke. Line width lasts until changed or the page ends.

    public function setlinewidth(float $width): void
    {
        $this->requirePage();
        $this->page['ops'][] = $this->num($width) . ' w';
    }

    /** Adds a rectangle with its lower-left corner at ($x, $y) to the path. */
    public function rect(float $x, float $y, float $width, float $height): void
    {
        $this->requirePage();
        $this->page['ops'][] = $this->num($x) . ' ' . $this->num($y) . ' '
            . $this->num($width) . ' ' . $this->num($height) . ' re';
    }

    public function moveto(float $x, float $y): void
    {
        $this->requirePage();
        $this->page['ops'][] = $this->num($x) . ' ' . $this->num($y) . ' m';
    }

    public function lineto(float $x, float $y): void
    {
        $this->requirePage();
        $this->page['ops'][] = $this->num($x) . ' ' . $this->num($y) . ' l';
    }

    public function closepath(): void
    {
        $this->requirePage();
        $this->page['ops'][] = 'h';
    }

    /** Draws the outline of the current path, then clears it. */
    public function stroke(): void
    {
        $this->requirePage();
        $this->page['ops'][] = 'S';
    }

    public function fill(): void
    {
        $this->requirePage();
        $this->page['ops'][] = 'f';
    }

    public function fill_stroke(): void
    {
        $this->requirePage();
        $this->page['ops'][] = 'B';
    }

    // ------------------------------------------------------------------
    // Images
    // ------------------------------------------------------------------

    /** $imagetype: auto | jpeg | png | gif. Returns an image handle. */
    public function load_image(string $imagetype, string $filename, string $optlist = ''): int
    {
        $this->requireDoc();
        // Reports often call load_image for the same logo on every page; decode and embed it once.
        clearstatcache(true, $filename);
        $cacheKey = (realpath($filename) ?: $filename) . '|' . @filesize($filename) . '|' . @filemtime($filename);
        if (isset($this->imageCache[$cacheKey])) {
            return $this->imageCache[$cacheKey];
        }
        $data = @file_get_contents($filename);
        if ($data === false) {
            throw new MiniPdfException("cannot read image $filename");
        }
        $type = strtolower($imagetype);
        if ($type === 'auto') {
            if (str_starts_with($data, "\xFF\xD8")) {
                $type = 'jpeg';
            } elseif (str_starts_with($data, "\x89PNG\r\n\x1A\n")) {
                $type = 'png';
            } elseif (str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a')) {
                $type = 'gif';
            } else {
                throw new MiniPdfException("unrecognized image format: $filename");
            }
        }
        $img = match ($type) {
            'jpeg', 'jpg' => $this->parseJpeg($data, $filename),
            'png' => $this->parsePng($data, $filename),
            'gif' => $this->parsePng($this->gifToPng($data, $filename), $filename, true),
            default => throw new MiniPdfException("unsupported image type '$imagetype'"),
        };
        $handle = count($this->images) + 1;
        $img['res'] = 'Im' . $handle;
        $this->images[$handle] = $img;
        $this->imageCache[$cacheKey] = $handle;
        return $handle;
    }

    /**
     * Places an image with its lower-left corner at ($x, $y).
     * Options: scale=s | scale={sx sy}, dpi=n | dpi={x y} | dpi=none,
     *          boxsize={w h}, fitmethod=nofit|meet|entire, position={px py}|center.
     * Default size uses the resolution stored in the file, else 72 dpi.
     */
    public function fit_image(int $image, float $x, float $y, string $optlist = ''): void
    {
        $p = $this->requirePage();
        if (!isset($this->images[$image])) {
            throw new MiniPdfException("invalid image handle $image");
        }
        $img = $this->images[$image];
        $o = $this->parseOptlist($optlist);

        [$dx, $dy] = [$img['dpiX'], $img['dpiY']];
        if (isset($o['dpi'])) {
            if (strtolower($o['dpi']) === 'none') {
                [$dx, $dy] = [72.0, 72.0];
            } else {
                $d = $this->numList($o['dpi']);
                [$dx, $dy] = [$d[0] ?: $dx, ($d[1] ?? $d[0]) ?: $dy];
            }
        }
        $w = $img['w'] * 72 / $dx;
        $h = $img['h'] * 72 / $dy;

        if (isset($o['scale'])) {
            $sc = $this->numList($o['scale']);
            $w *= $sc[0];
            $h *= $sc[1] ?? $sc[0];
        }

        $fit = strtolower($o['fitmethod'] ?? 'nofit');
        if (isset($o['boxsize']) && $fit !== 'nofit') {
            [$bw, $bh] = $this->numList($o['boxsize']) + [0, 0];
            if ($fit === 'meet') {
                $k = min($bw / $w, $bh / $h);
                $w *= $k;
                $h *= $k;
            } elseif ($fit === 'entire') {
                [$w, $h] = [$bw, $bh];
            } else {
                throw new MiniPdfException("fitmethod '$fit' not supported");
            }
            $pos = strtolower($o['position'] ?? '0 0');
            $pp = $pos === 'center' ? [50, 50] : $this->numList($pos);
            $px = $pp[0] ?? 0;
            $py = $pp[1] ?? $px;
            $x += ($bw - $w) * $px / 100;
            $y += ($bh - $h) * $py / 100;
        }

        $this->page['images'][$image] = true;
        $this->page['ops'][] = 'q ' . $this->num($w) . ' 0 0 ' . $this->num($h) . ' '
            . $this->num($x) . ' ' . $this->num($y) . ' cm /' . $img['res'] . ' Do Q';
    }

    /** Legacy PDFlib call. */
    public function place_image(int $image, float $x, float $y, float $scale): void
    {
        $this->fit_image($image, $x, $y, 'scale=' . $this->num($scale));
    }

    /** Images stay usable until end_document; kept for API compatibility. */
    public function close_image(int $image): void
    {
    }

    // ------------------------------------------------------------------
    // Internals: text
    // ------------------------------------------------------------------

    private function emitText(string $bytes, float $x, float $y, float $wordSpacing): void
    {
        if ($bytes === '') {
            return;
        }
        $this->requirePage();
        $f = $this->fonts[$this->font];
        $this->page['fonts'][$this->font] = true;
        $op = 'BT /' . $f['res'] . ' ' . $this->num($this->fontSize) . ' Tf ';
        if ($wordSpacing != 0.0) {
            $op .= $this->num($wordSpacing) . ' Tw ';
        }
        $op .= $this->num($x) . ' ' . $this->num($y) . ' Td <' . strtoupper(bin2hex($bytes)) . '> Tj';
        if ($wordSpacing != 0.0) {
            $op .= ' 0 Tw';
        }
        $this->page['ops'][] = $op . ' ET';
    }

    /**
     * Greedy line fit starting at $pos.
     * Returns [end of line text, start of next line, ends a paragraph?].
     */
    private function fitLine(string $s, int $pos, float $width): array
    {
        $len = strlen($s);
        $widths = $this->fonts[$this->font]['widths'];
        $scale = $this->fontSize / 1000;
        $w = 0.0;
        $lastSpace = -1;
        for ($i = $pos; $i < $len; $i++) {
            $c = $s[$i];
            if ($c === "\n") {
                return [$i, $i + 1, true];
            }
            $code = ord($c);
            $w += ($code >= 32 ? $widths[$code - 32] : 0) * $scale;
            if ($w > $width + 1e-6) {
                if ($c === ' ') {
                    return [$i, $i + 1, false];
                }
                if ($lastSpace >= $pos) {
                    return [$lastSpace, $lastSpace + 1, false];
                }
                $cut = max($i, $pos + 1); // word wider than the box: break inside it
                return [$cut, $cut, false];
            }
            if ($c === ' ') {
                $lastSpace = $i;
            }
        }
        return [$len, $len, true];
    }

    private function bytesWidth(string $bytes, int $font, float $size): float
    {
        $widths = $this->fonts[$font]['widths'];
        $total = 0;
        $n = strlen($bytes);
        for ($i = 0; $i < $n; $i++) {
            $code = ord($bytes[$i]);
            if ($code >= 32) {
                $total += $widths[$code - 32];
            }
        }
        return $total * $size / 1000;
    }

    private function currentLeading(): float
    {
        return $this->leading ?? $this->fontSize;
    }

    /** Text to Unicode code points: UTF-8 if valid, else Windows-1252 bytes. */
    private function codepoints(string $text): array
    {
        if (preg_match('//u', $text)) {
            $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            return array_map(fn($c) => $this->utf8Codepoint($c), $chars);
        }
        $out = [];
        $n = strlen($text);
        for ($i = 0; $i < $n; $i++) {
            $b = ord($text[$i]);
            $out[] = ($b >= 0x80 && $b <= 0x9F) ? (self::CP1252_HIGH[$b - 0x80] ?: 0xFFFD) : $b;
        }
        return $out;
    }

    private function utf8Codepoint(string $ch): int
    {
        $b = ord($ch[0]);
        return match (true) {
            $b < 0x80 => $b,
            $b < 0xE0 => (($b & 0x1F) << 6) | (ord($ch[1]) & 0x3F),
            $b < 0xF0 => (($b & 0x0F) << 12) | ((ord($ch[1]) & 0x3F) << 6) | (ord($ch[2]) & 0x3F),
            default => (($b & 0x07) << 18) | ((ord($ch[1]) & 0x3F) << 12) | ((ord($ch[2]) & 0x3F) << 6) | (ord($ch[3]) & 0x3F),
        };
    }

    /** Text to WinAnsi bytes, one byte per character; unmappable characters become '?'. */
    private function toWinAnsi(string $text): string
    {
        if (!preg_match('/[^\x20-\x7E\n]/', $text)) {
            return $text; // plain ASCII is already WinAnsi
        }
        if ($this->cp1252Reverse === null) {
            $this->cp1252Reverse = [];
            foreach (self::CP1252_HIGH as $i => $cp) {
                if ($cp) {
                    $this->cp1252Reverse[$cp] = chr(0x80 + $i);
                }
            }
        }
        $out = '';
        foreach ($this->codepoints($text) as $cp) {
            if ($cp === 10) {
                $out .= "\n";
            } elseif ($cp < 32) {
                $out .= ($cp === 9) ? ' ' : '';
            } elseif ($cp < 0x80 || ($cp >= 0xA0 && $cp <= 0xFF)) {
                $out .= chr($cp);
            } else {
                $out .= $this->cp1252Reverse[$cp] ?? '?';
            }
        }
        return $out;
    }

    /** Text string for the Info dictionary: UTF-16BE with byte-order mark, as hex. */
    private function utf16Hex(string $text): string
    {
        $hex = 'FEFF';
        foreach ($this->codepoints($text) as $cp) {
            if ($cp > 0xFFFF) {
                $cp -= 0x10000;
                $hex .= sprintf('%04X%04X', 0xD800 | ($cp >> 10), 0xDC00 | ($cp & 0x3FF));
            } else {
                $hex .= sprintf('%04X', $cp);
            }
        }
        return '<' . $hex . '>';
    }

    // ------------------------------------------------------------------
    // Internals: images
    // ------------------------------------------------------------------

    private function parseJpeg(string $d, string $file): array
    {
        $n = strlen($d);
        $i = 2;
        $dpiX = $dpiY = 72.0;
        while ($i + 4 <= $n) {
            if (ord($d[$i]) !== 0xFF) {
                throw new MiniPdfException("corrupt JPEG: $file");
            }
            $m = ord($d[$i + 1]);
            if ($m === 0xFF) { // fill byte
                $i++;
                continue;
            }
            $segLen = unpack('n', substr($d, $i + 2, 2))[1];
            if ($m === 0xE0 && substr($d, $i + 4, 5) === "JFIF\0") {
                $j = unpack('Cunit/nx/ny', substr($d, $i + 11, 5));
                if ($j['unit'] === 1 && $j['x'] > 0 && $j['y'] > 0) {
                    [$dpiX, $dpiY] = [(float)$j['x'], (float)$j['y']];
                } elseif ($j['unit'] === 2 && $j['x'] > 0 && $j['y'] > 0) {
                    [$dpiX, $dpiY] = [$j['x'] * 2.54, $j['y'] * 2.54];
                }
            }
            if ($m >= 0xC0 && $m <= 0xCF && !in_array($m, [0xC4, 0xC8, 0xCC], true)) {
                $sof = unpack('Cbits/nh/nw/Cc', substr($d, $i + 4, 6));
                $cs = match ($sof['c']) {
                    1 => '/DeviceGray',
                    3 => '/DeviceRGB',
                    4 => '/DeviceCMYK',
                    default => throw new MiniPdfException("unsupported JPEG channel count in $file"),
                };
                $dict = "/Type /XObject /Subtype /Image /Width {$sof['w']} /Height {$sof['h']} /ColorSpace $cs"
                    . " /BitsPerComponent {$sof['bits']} /Filter /DCTDecode"
                    . ($sof['c'] === 4 ? ' /Decode [1 0 1 0 1 0 1 0]' : '');
                return [
                    'id' => $this->addObject($this->streamBody($dict, $d, false)),
                    'w' => $sof['w'], 'h' => $sof['h'], 'dpiX' => $dpiX, 'dpiY' => $dpiY,
                ];
            }
            $i += 2 + $segLen;
        }
        throw new MiniPdfException("no image size found in JPEG: $file");
    }

    /**
     * Converts a GIF to an RGBA PNG in memory using GD, keeping transparency.
     * Animated GIFs give their first frame. GIFs carry no resolution, so they
     * size at 72 dpi (one pixel per point) unless fit_image says otherwise.
     */
    private function gifToPng(string $data, string $file): string
    {
        if (!function_exists('imagecreatefromstring') || !(imagetypes() & IMG_GIF)) {
            throw new MiniPdfException("GIF support needs the PHP GD extension with GIF enabled: $file");
        }
        $im = @imagecreatefromstring($data);
        if ($im === false) {
            throw new MiniPdfException("cannot decode GIF: $file");
        }
        imagepalettetotruecolor($im);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        ob_start();
        $ok = imagepng($im, null, 1, PNG_FILTER_NONE); // unfiltered rows decode fast
        $png = (string)ob_get_clean();
        if (!$ok || $png === '') {
            throw new MiniPdfException("cannot convert GIF to PNG: $file");
        }
        return $png;
    }

    /** $gdOutput: the data was written by GD with row filters off (no re-encode needed). */
    private function parsePng(string $d, string $file, bool $gdOutput = false): array
    {
        if (!function_exists('gzcompress')) {
            throw new MiniPdfException('PNG support needs the PHP zlib extension');
        }
        $pos = 8;
        $n = strlen($d);
        $hdr = null;
        $pal = '';
        $idat = '';
        $dpiX = $dpiY = 72.0;
        while ($pos + 8 <= $n) {
            $len = unpack('N', substr($d, $pos, 4))[1];
            $type = substr($d, $pos + 4, 4);
            $body = substr($d, $pos + 8, $len);
            $pos += 12 + $len;
            if ($type === 'IHDR') {
                $hdr = unpack('Nw/Nh/Cbits/Ccolor/Ccomp/Cfilter/Cinterlace', $body);
            } elseif ($type === 'PLTE') {
                $pal = $body;
            } elseif ($type === 'IDAT') {
                $idat .= $body;
            } elseif ($type === 'pHYs') {
                $ph = unpack('Nx/Ny/Cunit', $body);
                if ($ph['unit'] === 1 && $ph['x'] > 0 && $ph['y'] > 0) {
                    [$dpiX, $dpiY] = [$ph['x'] * 0.0254, $ph['y'] * 0.0254];
                }
            } elseif ($type === 'IEND') {
                break;
            }
        }
        if ($hdr === null || $idat === '') {
            throw new MiniPdfException("corrupt PNG: $file");
        }
        if ($hdr['interlace'] !== 0) {
            throw new MiniPdfException("interlaced PNG not supported (re-save without interlacing): $file");
        }
        [$w, $h, $bits, $ct] = [$hdr['w'], $hdr['h'], $hdr['bits'], $hdr['color']];
        $base = ['w' => $w, 'h' => $h, 'dpiX' => $dpiX, 'dpiY' => $dpiY];

        // No alpha: hand the compressed data straight to the PDF with PNG predictors.
        if ($ct === 0 || $ct === 2 || $ct === 3) {
            if ($ct === 3) {
                if ($pal === '') {
                    throw new MiniPdfException("palette PNG without PLTE: $file");
                }
                $cs = '[/Indexed /DeviceRGB ' . (strlen($pal) / 3 - 1) . ' <' . bin2hex($pal) . '>]';
                $colors = 1;
            } else {
                $cs = $ct === 0 ? '/DeviceGray' : '/DeviceRGB';
                $colors = $ct === 0 ? 1 : 3;
            }
            $dict = "/Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace $cs /BitsPerComponent $bits"
                . " /Filter /FlateDecode /DecodeParms << /Predictor 15 /Colors $colors /BitsPerComponent $bits /Columns $w >>";
            return $base + ['id' => $this->addObject($this->streamBody($dict, $idat, false))];
        }

        // Alpha channel: colour and alpha must be separated into an image plus a soft mask.
        // Undoing PNG row filters byte by byte in PHP is slow, so when GD is available let it
        // decode the file (in C) and re-write it with filters off; then the split is cheap.
        if (!$gdOutput && function_exists('imagecreatefromstring') && (imagetypes() & IMG_PNG)) {
            $im = @imagecreatefromstring($d);
            if ($im !== false) {
                imagealphablending($im, false);
                imagesavealpha($im, true);
                ob_start();
                $ok = imagepng($im, null, 1, PNG_FILTER_NONE);
                $png = (string)ob_get_clean();
                if ($ok && $png !== '') {
                    $img = $this->parsePng($png, $file, true);
                    return ['dpiX' => $dpiX, 'dpiY' => $dpiY] + $img; // GD drops the resolution
                }
            }
        }
        if ($bits !== 8) {
            throw new MiniPdfException("only 8-bit PNGs with transparency are supported without GD: $file");
        }
        $raw = gzuncompress($idat);
        if ($raw === false) {
            throw new MiniPdfException("cannot decompress PNG data: $file");
        }
        $bpp = $ct === 6 ? 4 : 2;
        $pixels = $this->pngUnfilter($raw, $w, $h, $bpp);
        unset($raw);
        // Split interleaved pixels with regex replacements, which run in C rather than a PHP loop.
        if ($ct === 6) {
            $color = preg_replace('/(...)./s', '$1', $pixels);
            $alpha = preg_replace('/...(.)/s', '$1', $pixels);
        } else {
            $color = preg_replace('/(.)./s', '$1', $pixels);
            $alpha = preg_replace('/.(.)/s', '$1', $pixels);
        }
        unset($pixels);
        $cs = $ct === 6 ? '/DeviceRGB' : '/DeviceGray';
        $dict = "/Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace $cs /BitsPerComponent 8";
        // Fully opaque (common for GIFs and many PNGs): no mask needed.
        if (strspn($alpha, "\xFF") !== strlen($alpha)) {
            $maskId = $this->addObject($this->streamBody(
                "/Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace /DeviceGray /BitsPerComponent 8",
                $alpha, true
            ));
            $dict .= " /SMask $maskId 0 R";
        }
        $id = $this->addObject($this->streamBody($dict, $color, true));
        return $base + ['id' => $id];
    }

    private function pngUnfilter(string $raw, int $w, int $h, int $bpp): string
    {
        $stride = $w * $bpp;
        if (strlen($raw) < $h * ($stride + 1)) {
            throw new MiniPdfException('truncated PNG data');
        }
        $prev = str_repeat("\0", $stride);
        $out = '';
        $pos = 0;
        for ($y = 0; $y < $h; $y++) {
            $filter = ord($raw[$pos]);
            $line = substr($raw, $pos + 1, $stride);
            $pos += $stride + 1;
            if ($filter !== 0) {
                for ($i = 0; $i < $stride; $i++) {
                    $a = $i >= $bpp ? ord($line[$i - $bpp]) : 0; // already reconstructed
                    $b = ord($prev[$i]);
                    $c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;
                    $pred = match ($filter) {
                        1 => $a,
                        2 => $b,
                        3 => ($a + $b) >> 1,
                        4 => $this->paeth($a, $b, $c),
                        default => throw new MiniPdfException("bad PNG filter type $filter"),
                    };
                    $line[$i] = chr((ord($line[$i]) + $pred) & 0xFF);
                }
            }
            $out .= $line;
            $prev = $line;
        }
        return $out;
    }

    private function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);
        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }
        return $pb <= $pc ? $b : $c;
    }

    // ------------------------------------------------------------------
    // Internals: objects, options, guards
    // ------------------------------------------------------------------

    private function addObject(string $body): int
    {
        $id = count($this->objects) + 1;
        $this->objects[$id] = $body;
        return $id;
    }

    private function streamBody(string $dictEntries, string $data, bool $compress): string
    {
        if ($compress && function_exists('gzcompress')) {
            $data = gzcompress($data);
            $dictEntries .= ' /Filter /FlateDecode';
        }
        return '<< ' . trim($dictEntries) . ' /Length ' . strlen($data) . " >>\nstream\n" . $data . "\nendstream";
    }

    /** Parses "key=value key={a b} key value" into [key => value]. */
    private function parseOptlist(string $optlist): array
    {
        preg_match_all('/\{[^}]*\}|[^\s={}]+/', $optlist, $m);
        $tokens = $m[0];
        $opts = [];
        for ($i = 0; $i + 1 < count($tokens); $i += 2) {
            $opts[strtolower($tokens[$i])] = trim($tokens[$i + 1], '{} ');
        }
        return $opts;
    }

    private function numList(string $v): array
    {
        return array_map('floatval', preg_split('/\s+/', trim($v)) ?: []);
    }

    private function pageDimension(string $v): float
    {
        if (preg_match('/^(\w+)\.(width|height)$/i', $v, $m)) {
            $size = self::PAGE_SIZES[strtolower($m[1])] ?? null;
            if ($size === null) {
                throw new MiniPdfException("unknown page size '{$m[1]}'");
            }
            return (float)$size[strtolower($m[2]) === 'width' ? 0 : 1];
        }
        return (float)$v;
    }

    private function pdfName(string $s): string
    {
        return preg_replace_callback('/[^A-Za-z0-9_.\-]/', fn($m) => sprintf('#%02X', ord($m[0])), $s);
    }

    private function num(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
        return ($s === '-0' || $s === '') ? '0' : $s;
    }

    private function requireDoc(): void
    {
        if (!$this->docOpen) {
            throw new MiniPdfException('no document open: call begin_document() first');
        }
    }

    private function requirePage(): array
    {
        $this->requireDoc();
        if ($this->page === null) {
            throw new MiniPdfException('no page open: call begin_page_ext() first');
        }
        return $this->page;
    }

    private function requireFont(): void
    {
        if ($this->font === null) {
            throw new MiniPdfException('no font set: call setfont() first');
        }
    }
}
