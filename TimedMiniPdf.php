<?php
declare(strict_types=1);

/**
 * TimedMiniPdf: MiniPdf that records how long each call takes.
 *
 * Use it in place of MiniPdf (new TimedMiniPdf(), or pdf_new() after
 * defining MINIPDF_TIMED), then call timing_report() at the end:
 *
 *     $p = new TimedMiniPdf();
 *     ... build the report as usual ...
 *     error_log($p->timing_report());   // or echo it
 *
 * The report lists calls, total and slowest time per method, and the time
 * spent OUTSIDE the library (your own code: queries, loops, formatting).
 * Calls made inside other calls (show_xy calling show) count once, under the outer call.
 */
require_once __DIR__ . '/MiniPdf.php';

class TimedMiniPdf extends MiniPdf
{
    private float $t0;
    private int $depth = 0;
    private array $stats = [];   // method => [calls, totalSeconds, maxSeconds]
    private array $routes = [];  // load_image route => [calls, totalSeconds, maxSeconds, maxPixels, maxBytes]

    public function __construct()
    {
        $this->t0 = microtime(true);
    }

    public function timing_report(): string
    {
        $wall = microtime(true) - $this->t0;
        $inside = 0.0;
        foreach ($this->stats as $s) {
            $inside += $s[1];
        }
        uasort($this->stats, fn($a, $b) => $b[1] <=> $a[1]);
        $lines = [sprintf('%-16s %8s %11s %11s', 'method', 'calls', 'total ms', 'max ms')];
        foreach ($this->stats as $name => [$calls, $total, $max]) {
            $lines[] = sprintf('%-16s %8d %11.1f %11.2f', $name, $calls, $total * 1000, $max * 1000);
        }
        if ($this->routes) {
            $lines[] = '';
            $lines[] = sprintf('%-20s %6s %10s %9s %11s %9s', 'load_image route', 'calls', 'total ms', 'max ms', 'max pixels', 'max KB');
            foreach ($this->routes as $route => [$calls, $total, $max, $px, $bytes]) {
                $lines[] = sprintf('%-20s %6d %10.1f %9.2f %11d %9d', $route, $calls, $total * 1000, $max * 1000, $px, intdiv($bytes, 1024));
            }
            $lines[] = '';
        }
        $lines[] = sprintf('inside MiniPdf:  %.1f ms', $inside * 1000);
        $lines[] = sprintf('outside MiniPdf: %.1f ms  (since new TimedMiniPdf)', ($wall - $inside) * 1000);
        return implode("\n", $lines) . "\n";
    }

    private function enter(): float
    {
        $this->depth++;
        return microtime(true);
    }

    private function leave(string $name, float $start): void
    {
        $this->depth--;
        if ($this->depth > 0) {
            return;
        }
        $dt = microtime(true) - $start;
        $s = $this->stats[$name] ?? [0, 0.0, 0.0];
        $this->stats[$name] = [$s[0] + 1, $s[1] + $dt, max($s[2], $dt)];
    }

    public function begin_document(string $filename = '', string $optlist = ''): int
    {
        $t = $this->enter();
        try {
            return parent::begin_document($filename, $optlist);
        } finally {
            $this->leave('begin_document', $t);
        }
    }

    public function set_info(string $key, string $value): void
    {
        $t = $this->enter();
        try {
            parent::set_info($key, $value);
        } finally {
            $this->leave('set_info', $t);
        }
    }

    public function end_document(string $optlist = ''): void
    {
        $t = $this->enter();
        try {
            parent::end_document($optlist);
        } finally {
            $this->leave('end_document', $t);
        }
    }

    public function get_buffer(): string
    {
        $t = $this->enter();
        try {
            return parent::get_buffer();
        } finally {
            $this->leave('get_buffer', $t);
        }
    }

    public function begin_page_ext(float $width, float $height, string $optlist = ''): void
    {
        $t = $this->enter();
        try {
            parent::begin_page_ext($width, $height, $optlist);
        } finally {
            $this->leave('begin_page_ext', $t);
        }
    }

    public function begin_page(float $width, float $height): void
    {
        $t = $this->enter();
        try {
            parent::begin_page($width, $height);
        } finally {
            $this->leave('begin_page', $t);
        }
    }

    public function end_page_ext(string $optlist = ''): void
    {
        $t = $this->enter();
        try {
            parent::end_page_ext($optlist);
        } finally {
            $this->leave('end_page_ext', $t);
        }
    }

    public function end_page(): void
    {
        $t = $this->enter();
        try {
            parent::end_page();
        } finally {
            $this->leave('end_page', $t);
        }
    }

    public function load_font(string $fontname, string $encoding = 'winansi', string $optlist = ''): int
    {
        $t = $this->enter();
        try {
            return parent::load_font($fontname, $encoding, $optlist);
        } finally {
            $this->leave('load_font', $t);
        }
    }

    public function setfont(int $font, float $fontsize): void
    {
        $t = $this->enter();
        try {
            parent::setfont($font, $fontsize);
        } finally {
            $this->leave('setfont', $t);
        }
    }

    public function set_value(string $key, float $value): void
    {
        $t = $this->enter();
        try {
            parent::set_value($key, $value);
        } finally {
            $this->leave('set_value', $t);
        }
    }

    public function get_option(string $keyword, string $optlist = ''): float
    {
        $t = $this->enter();
        try {
            return parent::get_option($keyword, $optlist);
        } finally {
            $this->leave('get_option', $t);
        }
    }

    public function get_value(string $key, float $modifier = 0): float
    {
        $t = $this->enter();
        try {
            return parent::get_value($key, $modifier);
        } finally {
            $this->leave('get_value', $t);
        }
    }

    public function stringwidth(string $text, int $font, float $fontsize): float
    {
        $t = $this->enter();
        try {
            return parent::stringwidth($text, $font, $fontsize);
        } finally {
            $this->leave('stringwidth', $t);
        }
    }

    public function set_text_pos(float $x, float $y): void
    {
        $t = $this->enter();
        try {
            parent::set_text_pos($x, $y);
        } finally {
            $this->leave('set_text_pos', $t);
        }
    }

    public function show(string $text): void
    {
        $t = $this->enter();
        try {
            parent::show($text);
        } finally {
            $this->leave('show', $t);
        }
    }

    public function show_xy(string $text, float $x, float $y): void
    {
        $t = $this->enter();
        try {
            parent::show_xy($text, $x, $y);
        } finally {
            $this->leave('show_xy', $t);
        }
    }

    public function continue_text(string $text): void
    {
        $t = $this->enter();
        try {
            parent::continue_text($text);
        } finally {
            $this->leave('continue_text', $t);
        }
    }

    public function show_boxed(string $text, float $x, float $y, float $width, float $height, string $mode, string $feature = ''): int
    {
        $t = $this->enter();
        try {
            return parent::show_boxed($text, $x, $y, $width, $height, $mode, $feature);
        } finally {
            $this->leave('show_boxed', $t);
        }
    }

    public function setlinewidth(float $width): void
    {
        $t = $this->enter();
        try {
            parent::setlinewidth($width);
        } finally {
            $this->leave('setlinewidth', $t);
        }
    }

    public function rect(float $x, float $y, float $width, float $height): void
    {
        $t = $this->enter();
        try {
            parent::rect($x, $y, $width, $height);
        } finally {
            $this->leave('rect', $t);
        }
    }

    public function moveto(float $x, float $y): void
    {
        $t = $this->enter();
        try {
            parent::moveto($x, $y);
        } finally {
            $this->leave('moveto', $t);
        }
    }

    public function lineto(float $x, float $y): void
    {
        $t = $this->enter();
        try {
            parent::lineto($x, $y);
        } finally {
            $this->leave('lineto', $t);
        }
    }

    public function closepath(): void
    {
        $t = $this->enter();
        try {
            parent::closepath();
        } finally {
            $this->leave('closepath', $t);
        }
    }

    public function stroke(): void
    {
        $t = $this->enter();
        try {
            parent::stroke();
        } finally {
            $this->leave('stroke', $t);
        }
    }

    public function fill(): void
    {
        $t = $this->enter();
        try {
            parent::fill();
        } finally {
            $this->leave('fill', $t);
        }
    }

    public function fill_stroke(): void
    {
        $t = $this->enter();
        try {
            parent::fill_stroke();
        } finally {
            $this->leave('fill_stroke', $t);
        }
    }

    public function load_image(string $imagetype, string $filename, string $optlist = ''): int
    {
        $t = $this->enter();
        try {
            $handle = parent::load_image($imagetype, $filename, $optlist);
        } finally {
            $this->leave('load_image', $t);
        }
        $dt = microtime(true) - $t;
        $info = $this->last_image_info();
        $route = $info['route'] ?? '?';
        $r = $this->routes[$route] ?? [0, 0.0, 0.0, 0, 0];
        $this->routes[$route] = [
            $r[0] + 1, $r[1] + $dt, max($r[2], $dt),
            max($r[3], ($info['w'] ?? 0) * ($info['h'] ?? 0)), max($r[4], $info['bytes'] ?? 0),
        ];
        return $handle;
    }
    }

    public function fit_image(int $image, float $x, float $y, string $optlist = ''): void
    {
        $t = $this->enter();
        try {
            parent::fit_image($image, $x, $y, $optlist);
        } finally {
            $this->leave('fit_image', $t);
        }
    }

    public function place_image(int $image, float $x, float $y, float $scale): void
    {
        $t = $this->enter();
        try {
            parent::place_image($image, $x, $y, $scale);
        } finally {
            $this->leave('place_image', $t);
        }
    }

    public function close_image(int $image): void
    {
        $t = $this->enter();
        try {
            parent::close_image($image);
        } finally {
            $this->leave('close_image', $t);
        }
    }
}
