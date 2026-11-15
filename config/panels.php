<?php

/*
 * Records panel templates (/recpanel, /admin recpanel), merged into config('panels.records') by mergeConfigFrom (shallow array_merge):
 * a template name the app defines under 'records' in config/panels.php replaces that whole template. Positions are ManiaLink units.
 * Click targets: PB /topsums, Local /recs, Dedi /dedirecs, TMX /tmxrecs.
 */

// Records panel templates. rows: which of PB, Local, Dedi, TMX are drawn, top to bottom.
// label/value: x (one number, or one per row), ys (one per row), size [w, h], align attributes,
// 'format' (a <format> line before the labels), 'style' (a ManiaLink text style), 'texts' (label
// captions), 'prefix' (colour code before the value, per row), 'scale' (per row).
$recRows = ['pb', 'local', 'dedi', 'tmx'];
$recRight = fn (array $over = []) => ['rows' => $over['rows'] ?? $recRows] + array_replace_recursive([
    'frame' => '53.5 -32.7 0', 'bg' => null, 'rows' => $recRows,
    'label' => ['x' => 4.5, 'ys' => [-0.8, -2.6, -4.4, -6.2], 'size' => [4.5, 2], 'align' => 'halign="right" valign="center"', 'format' => 'textsize="1" textcolor="dddf"', 'texts' => ['PB:', 'Local:', 'Dedi:', 'TMX:']],
    'value' => ['x' => 5.0, 'ys' => [-0.8, -2.6, -4.4, -6.2], 'size' => [6.5, 2], 'align' => 'halign="left" valign="center"', 'format' => 'textsize="1" textcolor="ffff"'],
], $over);
// Left-hand templates: a NavButton background, the labels in one text style, the values in another.
$recLeft = fn (string $valueStyle) => [
    'frame' => '-64.8 23.3 0', 'bg' => [14, 11.5], 'rows' => $recRows,
    'label' => ['x' => 6.0, 'ys' => [-1.0, -3.5, -6.0, -8.5], 'size' => [4.5, 2], 'align' => 'halign="right"', 'style' => 'TextCardSmallScores2Rank', 'texts' => ['PB:', 'Local:', 'Dedi:', 'TMX:']],
    'value' => ['x' => 6.5, 'ys' => [-1.0, -3.5, -6.0, -8.5], 'size' => [6.5, 2], 'align' => 'halign="left"', 'style' => $valueStyle],
];
$recSize = fn (int $n, array $over) => ['rows' => $n === 4 ? $recRows : ['pb', 'local', 'tmx']] + $over + ['frame' => '-64.8 23.3 0'];

return [
    'RightBottom'       => $recRight(),
    'RightBottomRM'     => $recRight([
        'label' => ['format' => 'textsize="1" textcolor="dddf"', 'texts' => ['$da2PB:', '$fffLocal:', '$dddDedi:', '$bbbTMX:']],
        'value' => ['x' => [4.75, 5.0, 5.0, 5.0], 'ys' => [-0.75, -2.7, -4.5, -6.3], 'format' => null, 'style' => 'TextRaceChrono', 'align' => 'halign="left" valign="center"',
                    'prefix' => ['$da2', '$fff', '$ddd', '$bbb'], 'scale' => [1, 0.8, 0.8, 0.8]],
    ]),
    'RightBottomNoDedi' => $recRight(['rows' => ['pb', 'local', 'tmx'], 'label' => ['ys' => [-0.8, -2.6, -4.4], 'texts' => ['PB:', 'Local:', 'TMX:']], 'value' => ['ys' => [-0.8, -2.6, -4.4]]]),
    'LeftBlackBold'     => $recLeft('TextSubTitle2'),
    'LeftBlueBold'      => $recLeft('TextButtonSmall'),
    'LeftBlueLight'     => $recLeft('TextRaceValueSmall'),
    'LeftBlue'          => $recLeft('TextButtonMedium'),
    'LeftGrayBold'      => $recLeft('TextTitle3'),
    'LeftGray'          => $recLeft('TextStaticSmall'),
    'LeftItalic'        => $recLeft('TextTips'),
    'LeftOrange'        => $recLeft('TextRaceChat'),
    'LeftWhite'         => $recLeft('TextCardSmallScores2'),
    'LeftSize1'         => $recSize(4, [
        'bg' => [10.5, 9],
        'label' => ['x' => 4.5, 'ys' => [-1.5, -3.5, -5.5, -7.5], 'size' => [3.5, 2], 'align' => 'halign="right" valign="center"', 'format' => 'textsize="1" textcolor="dddf"', 'texts' => ['PB:', 'Local:', 'Dedi:', 'TMX:']],
        'value' => ['x' => 5.0, 'ys' => [-1.5, -3.5, -5.5, -7.5], 'size' => [4.9, 2], 'align' => 'halign="left" valign="center"', 'format' => 'textsize="1" textcolor="ffff"'],
    ]),
    'LeftSize1NoDedi'   => $recSize(3, [
        'bg' => [10.5, 7.1],
        'label' => ['x' => 4.5, 'ys' => [-1.5, -3.5, -5.5], 'size' => [3.5, 2], 'align' => 'halign="right" valign="center"', 'format' => 'textsize="1" textcolor="dddf"', 'texts' => ['PB:', 'Local:', 'TMX:']],
        'value' => ['x' => 5.0, 'ys' => [-1.5, -3.5, -5.5], 'size' => [4.9, 2], 'align' => 'halign="left" valign="center"', 'format' => 'textsize="1" textcolor="ffff"'],
    ]),
    'LeftSize2'         => $recSize(4, [
        'bg' => [14, 11.5],
        'label' => ['x' => 6.0, 'ys' => [-1.9, -4.4, -6.9, -9.4], 'size' => [4.5, 2], 'align' => 'halign="right" valign="center"', 'format' => 'textsize="2" textcolor="dddf"', 'texts' => ['PB:', 'Local:', 'Dedi:', 'TMX:']],
        'value' => ['x' => 6.5, 'ys' => [-1.9, -4.4, -6.9, -9.4], 'size' => [6.5, 2], 'align' => 'halign="left" valign="center"', 'format' => 'textsize="2" textcolor="ffff"'],
    ]),
    'LeftSize2NoDedi'   => $recSize(3, [
        'bg' => [14, 9.0],
        'label' => ['x' => 6.0, 'ys' => [-1.9, -4.4, -6.9], 'size' => [4.5, 2], 'align' => 'halign="right" valign="center"', 'format' => 'textsize="2" textcolor="dddf"', 'texts' => ['PB:', 'Local:', 'TMX:']],
        'value' => ['x' => 6.5, 'ys' => [-1.9, -4.4, -6.9], 'size' => [6.5, 2], 'align' => 'halign="left" valign="center"', 'format' => 'textsize="2" textcolor="ffff"'],
    ]),
];
