<?php
declare(strict_types=1);

/**
 * Procedural PDF_* wrappers so code written against the old PDFlib
 * function API (pdf_new, PDF_show_xy($p, ...), ...) runs on MiniPdf unchanged.
 * Only defined when the real PDFlib extension is not loaded.
 */
require_once __DIR__ . '/MiniPdf.php';

if (!function_exists('pdf_new')) {
    function pdf_new(): MiniPdf { return new MiniPdf(); }
    function pdf_delete(MiniPdf $p): void {}

    function pdf_begin_document(MiniPdf $p, string $filename, string $optlist = ''): int { return $p->begin_document($filename, $optlist); }
    function pdf_end_document(MiniPdf $p, string $optlist = ''): void { $p->end_document($optlist); }
    function pdf_get_buffer(MiniPdf $p): string { return $p->get_buffer(); }
    function pdf_set_info(MiniPdf $p, string $key, string $value): void { $p->set_info($key, $value); }

    function pdf_begin_page_ext(MiniPdf $p, float $w, float $h, string $optlist = ''): void { $p->begin_page_ext($w, $h, $optlist); }
    function pdf_end_page_ext(MiniPdf $p, string $optlist = ''): void { $p->end_page_ext($optlist); }
    function pdf_begin_page(MiniPdf $p, float $w, float $h): void { $p->begin_page($w, $h); }
    function pdf_end_page(MiniPdf $p): void { $p->end_page(); }

    function pdf_load_font(MiniPdf $p, string $name, string $encoding, string $optlist = ''): int { return $p->load_font($name, $encoding, $optlist); }
    function pdf_setfont(MiniPdf $p, int $font, float $size): void { $p->setfont($font, $size); }
    function pdf_set_value(MiniPdf $p, string $key, float $value): void { $p->set_value($key, $value); }
    function pdf_get_option(MiniPdf $p, string $keyword, string $optlist = ''): float { return $p->get_option($keyword, $optlist); }
    function pdf_get_value(MiniPdf $p, string $key, float $modifier = 0): float { return $p->get_value($key, $modifier); }
    function pdf_stringwidth(MiniPdf $p, string $text, int $font, float $size): float { return $p->stringwidth($text, $font, $size); }

    function pdf_set_text_pos(MiniPdf $p, float $x, float $y): void { $p->set_text_pos($x, $y); }
    function pdf_show(MiniPdf $p, string $text): void { $p->show($text); }
    function pdf_show_xy(MiniPdf $p, string $text, float $x, float $y): void { $p->show_xy($text, $x, $y); }
    function pdf_continue_text(MiniPdf $p, string $text): void { $p->continue_text($text); }
    function pdf_show_boxed(MiniPdf $p, string $text, float $x, float $y, float $w, float $h, string $mode, string $feature = ''): int
    {
        return $p->show_boxed($text, $x, $y, $w, $h, $mode, $feature);
    }

    function pdf_load_image(MiniPdf $p, string $type, string $filename, string $optlist = ''): int { return $p->load_image($type, $filename, $optlist); }
    function pdf_fit_image(MiniPdf $p, int $image, float $x, float $y, string $optlist = ''): void { $p->fit_image($image, $x, $y, $optlist); }
    function pdf_place_image(MiniPdf $p, int $image, float $x, float $y, float $scale): void { $p->place_image($image, $x, $y, $scale); }
    function pdf_close_image(MiniPdf $p, int $image): void { $p->close_image($image); }

    function pdf_setlinewidth(MiniPdf $p, float $width): void { $p->setlinewidth($width); }
    function pdf_rect(MiniPdf $p, float $x, float $y, float $w, float $h): void { $p->rect($x, $y, $w, $h); }
    function pdf_moveto(MiniPdf $p, float $x, float $y): void { $p->moveto($x, $y); }
    function pdf_lineto(MiniPdf $p, float $x, float $y): void { $p->lineto($x, $y); }
    function pdf_closepath(MiniPdf $p): void { $p->closepath(); }
    function pdf_stroke(MiniPdf $p): void { $p->stroke(); }
    function pdf_fill(MiniPdf $p): void { $p->fill(); }
    function pdf_fill_stroke(MiniPdf $p): void { $p->fill_stroke(); }

    // Older PDFlib names
    function pdf_open_file(MiniPdf $p, string $filename = ''): int { return $p->begin_document($filename); }
    function pdf_close(MiniPdf $p): void { $p->end_document(); }
    function pdf_findfont(MiniPdf $p, string $name, string $encoding, int $embed = 0): int { return $p->load_font($name, $encoding); }
    function pdf_open_image_file(MiniPdf $p, string $type, string $filename, string $stringparam = '', int $intparam = 0): int
    {
        return $p->load_image($type, $filename);
    }
}
