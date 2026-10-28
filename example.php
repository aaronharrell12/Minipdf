<?php
declare(strict_types=1);
require __DIR__ . '/MiniPdf.php';

$p = new MiniPdf();
$p->begin_document('', '');
$p->set_info('Title', 'MiniPdf test — José Müller');
$p->set_info('Author', 'District 186');

$p->begin_page_ext(0, 0, 'width=letter.width height=letter.height');
$bold = $p->load_font('Helvetica-Bold', 'winansi');
$body = $p->load_font('Times-Roman', 'winansi');

$p->setfont($bold, 18);
$p->show_xy('Student Report', 72, 720);

$p->setfont($body, 11);
$p->set_text_pos(72, 696);
$p->show('Name: José Müller   ');
$p->show('Grade: 9');
$p->continue_text('Second line via continue_text');

$text = str_repeat('The quick brown fox jumps over the lazy dog. ', 12) . "\nNew paragraph after a line break.";
$y = 560;
foreach (['left', 'right', 'center', 'justify', 'fulljustify'] as $mode) {
    $left = $p->show_boxed($text, 72, $y, 220, 90, $mode, '');
    $p->show_xy("$mode: $left chars did not fit", 310, $y + 80);
    $y -= 110;
}
$p->show_boxed('Centered on a point', 306, 40, 0, 0, 'center', '');

foreach (array_slice($argv, 1) as $i => $file) {   // php example.php logo.png photo.jpg
    $img = $p->load_image('auto', $file, '');
    $p->fit_image($img, 400, 600 - $i * 120, 'boxsize={140 100} fitmethod=meet position=center');
    $p->close_image($img);
}
$p->end_page_ext('');

$p->begin_page_ext(612, 792, '');
$p->setfont($body, 12);
$p->show_xy('Page two', 72, 720);
$p->end_page_ext('');

$p->end_document('');
file_put_contents(__DIR__ . '/example.pdf', $p->get_buffer());
echo "wrote example.pdf\n";
