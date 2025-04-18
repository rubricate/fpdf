<?php

declare(strict_types=1);

$type = 'Core';
$name = 'Helvetica';
$up = -100;
$ut = 50;

$cw = array_merge(
    array_fill_keys(array_map('chr', range(0, 31)), 278),
    [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, 
        '&' => 667, '\'' => 191, '(' => 333, ')' => 333, '*' => 389, '+' => 584, 
        ',' => 278, '-' => 333, '.' => 278, '/' => 278, '0' => 556, '1' => 556, 
        '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556, 
        '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, 
        '>' => 584, '?' => 556, '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722, 
        'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278, 
        'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 
        'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 
        'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278, 
        '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333, 'a' => 556, 
        'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 
        'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 
        'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 
        't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 
        'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
    ],
    array_fill_keys(array_map('chr', range(127, 255)), 350)
);

$enc = 'cp1252';
$uv = [
    0 => [0, 128],
    128 => 8364,
    130 => 8218,
    131 => 402,
    132 => 8222,
    133 => 8230,
    134 => [8224, 2],
    136 => 710,
    137 => 8240,
    138 => 352,
    139 => 8249,
    140 => 338,
    142 => 381,
    145 => [8216, 2],
    147 => [8220, 2],
    149 => 8226,
    150 => [8211, 2],
    152 => 732,
    153 => 8482,
    154 => 353,
    155 => 8250,
    156 => 339,
    158 => 382,
    159 => 376,
    160 => [160, 96],
];

