<?php

namespace Rubricate\Fpdf;

use InvalidArgumentException;

class FileFpdf extends AbstractFileFpdf
{
    public function __construct(string $orientation = 'P', string $unit = 'mm', string $size = 'A4')
    {
        $this->iconv = function_exists('iconv');
        $this->fontpath = defined('FPDF_FONTPATH')? FPDF_FONTPATH :
            dirname(dirname(dirname(__FILE__))) . '/font/';

        $this->coreFonts = ['courier', 'helvetica', 'times', 'symbol', 'zapfdingbats'];

        $this->k = match ($unit) {
            'pt' => 1,
            'mm' => 72 / 25.4,
            'cm' => 72 / 2.54,
            'in' => 72,
            default => throw new InvalidArgumentException('Incorrect unit: ' . $unit),
        };

        $this->StdPageSizes = [
            'a3' => [841.89, 1190.55],
            'a4' => [595.28, 841.89],
            'a5' => [420.94, 595.28],
            'letter' => [612, 792],
            'legal' => [612, 1008],
        ];

        $size = $this->getpagesize($size);
        $this->defPageSize = $size;
        $this->curPageSize = $size;

        $orientation = strtolower($orientation);

        if ($orientation === 'p' || $orientation === 'portrait') {

            $this->defOrientation = 'P';
            $this->w = $size[0];
            $this->h = $size[1];

        } elseif ($orientation === 'l' || $orientation === 'landscape') {

            $this->defOrientation = 'L';
            $this->w = $size[1];
            $this->h = $size[0];

        } else {

            throw new InvalidArgumentException('Incorrect orientation: ' . $orientation);
        }

        $this->curOrientation = $this->defOrientation;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;

        $this->curRotation = 0;

        $margin = 28.35 / $this->k;
        $this->setMargins($margin, $margin);
        $this->cMargin = $margin/10;
        $this->lineWidth = .567/$this->k;
        $this->setAutoPageBreak(true, 2 * $margin);
        $this->setDisplayMode('default');
        $this->setCompression(true);
        $this->metadata = ['Producer' => 'FPDF ' . self::VERSION];

    }

    public function setMargins(float $left, float $top, ?float $right = null): void
    {
        $this->lMargin = $left;
        $this->tMargin = $top;
        $this->rMargin = $right ?? $left;
    }

    public function setLeftMargin(float $margin): void
    {
        $this->lMargin = $margin;

        if($this->page > 0 && $this->x < $margin){
            $this->x = $margin;
        }
    }

    public function setTopMargin(float $margin): void
    {
        $this->tMargin = $margin;
    }

    public function setRightMargin(float $margin): void
    {
        $this->rMargin = $margin;
    }

    public function setAutoPageBreak(string $auto, int $margin = 0): void
    {
        $this->autoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->pageBreakTrigger = ($this->h - $margin);
    }

    public function setDisplayMode(string|int $zoom, string $layout = 'default'): void
    {
        $validZoomModes = ['fullpage', 'fullwidth', 'real', 'default'];
        $validLayoutModes = ['single', 'continuous', 'two', 'default'];

        if (is_int($zoom) || in_array($zoom, $validZoomModes, true)) {
            $this->zoomMode = $zoom;
        } else {
            throw new InvalidArgumentException($this->fpdfError . $this->fpdfError . "Incorrect zoom display mode: {$zoom}");
        }

        if (in_array($layout, $validLayoutModes, true)) {
            $this->layoutMode = $layout;
        } else {
            throw new InvalidArgumentException($this->fpdfError . "Incorrect layout display mode: {$layout}");
        }
    }

    public function setCompression(bool $compress): void
    {
        $this->compress = $compress;
    }

    private function utf8Encode(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }

    public function setTitle(string $title, bool $isUTF8 = false): void
    {
        $this->metadata['Title'] = $isUTF8 ? $title : $this->utf8Encode($title);
    }

    public function setAuthor(string $author, bool $isUTF8 = false): void
    {
        $this->metadata['Author'] = $isUTF8 ? $author : $this->utf8Encode($author);
    }

    public function setSubject(string $subject, bool $isUTF8 = false): void
    {
        $this->metadata['Subject'] = $isUTF8 ? $subject : $this->utf8Encode($subject);
    }

    public function setKeywords(string $keywords, bool $isUTF8 = false): void
    {
        $this->metadata['Keywords'] = $isUTF8 ? $keywords : $this->utf8Encode($keywords);
    }

    public function setCreator(string $creator, bool $isUTF8 = false): void
    {
        $this->metadata['Creator'] = $isUTF8 ? $creator : $this->utf8Encode($creator);
    }

    public function aliasNbPages(string $alias = '{nb}'): void
    {
        $this->aliasNbPages = $alias;
    }

    public function close()
    {
        if ($this->state === 3) {
            return;
        }
        if ($this->page === 0) {
            $this->addPage();
        }
        $this->inFooter = true;
        $this->footer();
        $this->inFooter = false;
        $this->endpage();
        $this->enddoc();
    }

    public function addPage(string $orientation = '', array|string $size='', int $rotation = 0): void
    {
        if($this->state==3){
            throw new InvalidArgumentException($this->fpdfError . 'The document is closed');
        }

        $family = $this->fontFamily;
        $style = $this->fontStyle.($this->underline ? 'U' : '');
        $fontsize = $this->fontSizePt;
        $lw = $this->lineWidth;
        $dc = $this->drawColor;
        $fc = $this->fillColor;
        $tc = $this->textColor;
        $cf = $this->colorFlag;

        if($this->page > 0){

            $this->inFooter = true;
            $this->footer();
            $this->inFooter = false;
            $this->endpage();
        }

        $this->beginpage($orientation,$size,$rotation);
        $this->out('2 J');
        $this->lineWidth = $lw;
        $this->out(sprintf('%.2F w',$lw*$this->k));

        if($family){
            $this->setFont($family,$style,$fontsize);
        }

        $this->drawColor = $dc;

        if($dc != '0 G'){
            $this->out($dc);
        }

        $this->fillColor = $fc;

        if($fc != '0 g'){
            $this->out($fc);
        }

        $this->textColor = $tc;
        $this->colorFlag = $cf;

        $this->inHeader = true;
        $this->header();
        $this->inHeader = false;

        if($this->lineWidth!=$lw) {

            $this->lineWidth = $lw;
            $this->out(sprintf('%.2F w',$lw*$this->k));
        }

        if($family){
            $this->setFont($family,$style,$fontsize);
        }

        if($this->drawColor!=$dc){
            $this->drawColor = $dc;
            $this->out($dc);
        }
        if($this->fillColor!=$fc){
            $this->fillColor = $fc;
            $this->out($fc);
        }
        $this->textColor = $tc;
        $this->colorFlag = $cf;
    }

    public function header()
    {
        // To be implemented in your own inherited class
    }

    public function footer()
    {
        // To be implemented in your own inherited class
    }

    public function pageNo()
    {
        // Get current page number
        return $this->page;
    }

    public function setDrawColor(int $r, ?int $g = null, ?int $b = null): void
    {
        $this->drawColor = ($g === null || $b === null)
            ? sprintf('%.3F G', $r / 255)
            : sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);

        if($this->page>0){
            $this->out($this->drawColor);
        }
    }

    public function setFillColor(int $r, ?int $g = null, ?int $b = null): void
    {
        $this->fillColor = ($g === null || $b === null)
            ? sprintf('%.3F g', $r / 255)
            : sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);

        $this->colorFlag = ($this->fillColor != $this->textColor);

        if($this->page>0){
            $this->out($this->fillColor);
        }
    }

    public function setTextColor(int $r, ?int $g = null, ?int $b = null): void
    {
        $this->textColor = ($g === null || $b === null)
            ? sprintf('%.3F g', $r / 255)
            : sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);

        $this->colorFlag = ($this->fillColor != $this->textColor);
    }

    public function getStringWidth(string $s)
    {
        // Get width of a string in the current font
        $cw = $this->currentFont['cw'];
        $w = 0;
        $l = strlen($s);

        for($i = 0; $i < $l; $i++){
            $w += $cw[$s[$i]];
        }

        return $w * $this->fontSize/1000;
    }

    public function setLineWidth(int $width): void
    {
        $this->lineWidth = $width;

        if($this->page > 0){
            $this->out(sprintf('%.2F w', $width * $this->k));
        }
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->out(sprintf(
            '%.2F %.2F m %.2F %.2F l S',
            $x1 * $this->k, ($this->h - $y1) * $this->k,
            $x2 * $this->k, ($this->h - $y2) * $this->k
        ));
    }

    public function rect(float $x, float $y, float $w, float $h, string $style = ''): void
    {
        $op = match ($style) {
        'F' => 'f',
            'FD', 'DF' => 'B',
            default => 'S',
    };

        $this->out(sprintf(
            '%.2F %.2F %.2F %.2F re %s',
            $x * $this->k, ($this->h - $y) * $this->k,
            $w * $this->k, -$h * $this->k, $op
        ));
    }

    public function addFont(string $family, string $style='', string $file='', string $dir=''): void
    {
        $family = strtolower($family);
        $file = $file ?: str_replace(' ', '', $family) . strtolower($style) . '.php';
        $style = strtoupper($style === 'IB' ? 'BI' : $style);
        $fontkey = $family . $style;

        if (isset($this->fonts[$fontkey])) {
            return;
        }

        if (str_contains($file, '/') || str_contains($file, '\\')) {
            throw new InvalidArgumentException($this->fpdfError . 'Incorrect font definition file name: ' . $file);
        }

        if($dir==''){
            $dir = $this->fontpath;
        }

        if(substr($dir,-1)!='/' && substr($dir,-1)!='\\'){
            $dir .= '/';
        }

        $info = $this->loadfont($dir . $file);
        $info['i'] = count($this->fonts) + 1;

        if(!empty($info['file'])){

            $fileLength1 = ['length1'=>$info['size1'], 'length2'=>$info['size2']];
            $fileLength2 = ['length1'=>$info['originalsize']];

            $info['file'] = $dir . $info['file'];
            $this->fontFiles[$info['file']] = $fileLength1;

            if($info['type']=='TrueType'){
                $this->fontFiles[$info['file']] = $fileLength2;
            }
        }

        $this->fonts[$fontkey] = $info;
    }

    public function setFont(string $family, string $style='', int $size = 0): void
    {
        $family = ($family == '')? $this->fontFamily: strtolower($family);

        $style = strtoupper($style);
        $this->underline = false;

        if(strpos($style,'U')!==false){
            $this->underline = true;
            $style = str_replace('U', '', $style);
        }

        if($style=='IB'){
            $style = 'BI';
        }

        if($size == 0){
            $size = $this->fontSizePt;
        }

        if(
            $this->fontFamily == $family && 
            $this->fontStyle == $style && 
            $this->fontSizePt == $size
        ){
            return;
        }

        $fontkey = $family . $style;

        if(!isset($this->fonts[$fontkey]))
        {

            if($family=='arial'){
                $family = 'helvetica';
            }

            if(!in_array($family, $this->coreFonts)){
                throw new InvalidArgumentException(
                    $this->fpdfError . 'Undefined font: '.$family.' '.$style
                );
            }

            if($family=='symbol' || $family=='zapfdingbats'){
                $style = '';
            }

            $fontkey = $family . $style;

            if(!isset($this->fonts[$fontkey])){
                $this->addFont($family,$style);
            }

        }

        $this->fontFamily = $family;
        $this->fontStyle = $style;
        $this->fontSizePt = $size;
        $this->fontSize = $size/$this->k;
        $this->currentFont = $this->fonts[$fontkey];

        if($this->page>0){

            $this->out(
                sprintf('BT /F%d %.2F Tf ET', 
                $this->currentFont['i'], 
                $this->fontSizePt
                ));
        }
    }

    public function setFontSize(float $size): void
    {
        $this->fontSizePt = $size;
        $this->fontSize = $size/$this->k;

        if($this->page>0 && isset($this->currentFont)){
            $this->out(sprintf(
                'BT /F%d %.2F Tf ET', 
                $this->currentFont['i'],
                $this->fontSizePt
            ));
        }
    }

    public function addLink(): int
    {
        $n = count($this->links) + 1;
        $this->links[$n] = [0, 0];
        return $n;
    }

    public function setLink(string $link, float $y = 0, int $page = -1): void
    {
        if ($y === -1) {
            $y = $this->y;
        }
        if ($page === -1) {
            $page = $this->page;
        }

        $this->links[$link] = [$page, $y];
    }

    public function link(float $x, float $y, float $w, float $h, int|string $link): void
    {
        // Put a link on the page
        $this->PageLinks[$this->page][] = [
            $x * $this->k, 
            $this->hPt - $y * $this->k, 
            $w * $this->k, 
            $h * $this->k, 
            $link
        ];
    }

    public function text(float $x, float $y, string $txt): void
    {
        if (!isset($this->currentFont)) {
            throw new InvalidArgumentException($this->fpdfError . 'No font has been set');
        }

        $s = sprintf(
            'BT %.2F %.2F Td (%s) Tj ET',
            $x * $this->k,
            ($this->h - $y) * $this->k,
            $this->escape($txt)
        );

        if ($this->underline && $txt !== '') {
            $s .= ' ' . $this->dounderLine($x, $y, $txt);
        }

        if ($this->colorFlag) {
            $s = 'q ' . $this->textColor . ' ' . $s . ' Q';
        }

        $this->out($s);
    }


    public function acceptPageBreak(): bool
    {
        return $this->autoPageBreak;
    }

    public function cell(

        float $w, float $h = 0, 
        string $txt = '', string|int $border = 0, 
        int $ln = 0, string $align = '', 
        bool $fill = false, int|string $link = ''

    ): void {
        $k = $this->k;

        if (
            $this->y + $h > $this->pageBreakTrigger && 
            !$this->inHeader && !$this->inFooter && 
            $this->acceptPageBreak()
        ) {
            // Automatic page break
            $x = $this->x;
            $ws = $this->ws;

            if ($ws > 0) {
                $this->ws = 0;
                $this->out('0 Tw');
            }

            $this->addPage($this->curOrientation, $this->curPageSize, $this->curRotation);
            $this->x = $x;

            if ($ws > 0) {
                $this->ws = $ws;
                $this->out(sprintf('%.3F Tw', $ws * $k));
            }
        }

        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        $s = '';

        if ($fill || $border == 1) {
            $op = $fill ? ($border == 1 ? 'B' : 'f') : 'S';
            $s = sprintf(
                '%.2F %.2F %.2F %.2F re %s ',
                $this->x * $k,
                ($this->h - $this->y) * $k,
                $w * $k,
                -$h * $k,
                $op
            );
        }

        if (is_string($border)) {
            $x = $this->x;
            $y = $this->y;

            if (strpos($border, 'L') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $k, 
                    ($this->h - $y) * $k, 
                    $x * $k, 
                    ($this->h - ($y + $h)) * $k
                );
            }

            if (strpos($border, 'T') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $k, 
                    ($this->h - $y) * $k, 
                    ($x + $w) * $k, 
                    ($this->h - $y) * $k
                );
            }

            if (strpos($border, 'R') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    ($x + $w) * $k, 
                    ($this->h - $y) * $k, 
                    ($x + $w) * $k, 
                    ($this->h - ($y + $h)) * $k
                );
            }
            if (strpos($border, 'B') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $k, 
                    ($this->h - ($y + $h)) * $k, 
                    ($x + $w) * $k, 
                    ($this->h - ($y + $h)) * $k
                );
            }
        }

        if ($txt !== '') {
            if (!isset($this->currentFont)) {
                throw new InvalidArgumentException($this->fpdfError . 'No font has been set');
            }

            $dx = match ($align) {
            'R' => $w - $this->cMargin - $this->getStringWidth($txt),
                'C' => ($w - $this->getStringWidth($txt)) / 2,
                default => $this->cMargin
            };

            if ($this->colorFlag) {
                $s .= 'q ' . $this->textColor . ' ';
            }

            $s .= sprintf(
                'BT %.2F %.2F Td (%s) Tj ET',
                ($this->x + $dx) * $k,
                ($this->h - ($this->y + 0.5 * $h + 0.3 * $this->fontSize)) * $k,
                $this->escape($txt)
            );

            if ($this->underline) {
                $s .= ' ' . $this->dounderLine(
                    $this->x + $dx, $this->y + 0.5 * $h + 0.3 * $this->fontSize, $txt
                );
            }

            if ($this->colorFlag) {
                $s .= ' Q';
            }

            if ($link) {
                $this->link(
                    $this->x + $dx, 
                    $this->y + 0.5 * $h - 0.5 * $this->fontSize, 
                    $this->getStringWidth($txt), 
                    $this->fontSize, 
                    $link
                );
            }
        }

        if ($s) {
            $this->out($s);
        }

        $this->lasth = $h;

        if ($ln > 0) {
            // Go to next line
            $this->y += $h;

            if ($ln == 1) {
                $this->x = $this->lMargin;
            }

        } else {
            $this->x += $w;
        }
    }

    public function MultiCell(

        float $w, float $h, string $txt, 
        string|int $border = 0, 
        string $align = 'J', bool $fill = false

    ): void {

        $b = '';
        $b2 = '';
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;

        if (!isset($this->currentFont)) {
            throw new InvalidArgumentException($this->fpdfError . 'No font has been set');
        }

        $cw = $this->currentFont['cw'];

        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
        $s = str_replace("\r", '', (string) $txt);
        $nb = strlen($s);

        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }

        if ($border) {

            if ($border == 1) {

                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            }

            if (strpos($border, 'L') !== false) {
                $b2 .= 'L';
            }

            if (strpos($border, 'R') !== false) {
                $b2 .= 'R';
            }

            $b = (strpos($border, 'T') !== false)? $b2 . 'T':  $b2;

        }

        while ($i < $nb) {

            $c = $s[$i];

            if ($c == "\n") {

                if ($this->ws > 0) {
                    $this->ws = 0;
                    $this->out('0 Tw');
                }

                $this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);

                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;

                if ($border && $nl == 2) {
                    $b = $b2;
                }

                continue;
            }

            if ($c == ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }

            if(isset($cw[$c])){
                $l += $cw[$c];
            }

            if ($l <= $wmax) {
                $i++;
                continue;
            }

            // Automatic line break
            if ($sep == -1) {
                if ($i == $j) {
                    $i++;
                }

                if ($this->ws > 0) {
                    $this->ws = 0;
                    $this->out('0 Tw');
                }

                $this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
            } else {

                if ($align == 'J') {
                    $this->ws = ($ns > 1) ? ($wmax - $ls) / 1000 * $this->fontSize / ($ns - 1) : 0;
                    $this->out(sprintf('%.3F Tw', $this->ws * $this->k));
                }

                $this->cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
                $i = $sep + 1;
            }

            $sep = -1;
            $j = $i;
            $l = 0;
            $ns = 0;
            $nl++;

            if ($border && $nl == 2) {
                $b = $b2;
            }
        }

        // Last chunk
        if ($this->ws > 0) {
            $this->ws = 0;
            $this->out('0 Tw');
        }

        if ($border && strpos($border, 'B') !== false) {
            $b .= 'B';
        }

        $this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    public function Write(float $h, string $txt, int|string $link = ''): void
    {
        if (!isset($this->currentFont)) {
            throw new InvalidArgumentException($this->fpdfError . 'No font has been set');
        }

        $cw = $this->currentFont['cw'];
        $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
        $s = str_replace("\r", '', (string) $txt);
        $nb = strlen($s);

        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;

        while($i < $nb) {

            $c = $s[$i];

            if($c == "\n"){

                $this->cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', false, $link);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;

                if($nl == 1){

                    $this->x = $this->lMargin;
                    $w = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
                }
                $nl++;
                continue;
            }
            if($c ==' '){
                $sep = $i;
            }

            if (isset($cw[$c])) {
                $l += $cw[$c];
            }

            if($l > $wmax){

                if($sep == -1){

                    if($this->x > $this->lMargin){

                        $this->x = $this->lMargin;
                        $this->y += $h;
                        $w = $this->w - $this->rMargin - $this->x;
                        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
                        $i++;
                        $nl++;
                        continue;
                    }

                    if($i == $j){
                        $i++;
                    }

                    $this->cell($w, $h, substr($s, $j, $i - $j), 0,2, '', false, $link);

                }else {

                    $this->cell($w, $h, substr($s, $j, $sep - $j),0,2, '', false, $link);
                    $i = $sep+1;
                }

                $sep = -1;
                $j = $i;
                $l = 0;

                if($nl == 1){

                    $this->x = $this->lMargin;
                    $w = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w -2 * $this->cMargin) * 1000 / $this->fontSize;
                }

                $nl++;

            } else{
                $i++;
            }
        }

        if ($i !== $j) {
            $this->cell($l / 1000 * $this->fontSize, $h, substr($s, $j), 0, 0, '', false, $link);
        }
    }

    public function ln($h=null): void
    {
        $this->x = $this->lMargin;
        $this->y += $h;

        if($h === null){
            $this->y += $this->lasth;
        }
    }

    public function image(

        string $file,
        ?float $x = null,
        ?float $y = null, 
        float $w = 0,
        float $h = 0,
        string $type = '', 
        int|string $link = ''

    ): void {

        if ($file === '') {
            throw new InvalidArgumentException($this->fpdfError . 'Image file name is empty');
        }

        if (!isset($this->images[$file])) {
            if ($type === '') {
                $pos = strrpos($file, '.');
                if (!$pos) {
                    throw new InvalidArgumentException($this->fpdfError . 'Image file has no extension and no type was specified: ' . $file);
                }
                $type = substr($file, $pos + 1);
            }

            $type = strtolower($type);
            if ($type === 'jpeg') {
                $type = 'jpg';
            }

            $mtd = 'parse' . $type;
            if (!method_exists($this, $mtd)) {
                throw new InvalidArgumentException($this->fpdfError . 'Unsupported image type: ' . $type);
            }

            $info = $this->$mtd($file);
            $info['i'] = count($this->images) + 1;
            $this->images[$file] = $info;
        }

        $info = $this->images[$file];

        // Automatic width and height calculation if needed
        if ($w == 0 && $h == 0) {
            $w = -96;
            $h = -96;
        }

        if ($w < 0) {
            $w = -$info['w'] * 72 / $w / $this->k;
        }

        if ($h < 0) {
            $h = -$info['h'] * 72 / $h / $this->k;
        }

        if ($w == 0) {
            $w = $h * $info['w'] / $info['h'];
        }

        if ($h == 0) {
            $h = $w * $info['h'] / $info['w'];
        }

        // Flowing mode
        if ($y === null) {
            if (
                $this->y + $h > $this->pageBreakTrigger && 
                !$this->inHeader && 
                !$this->inFooter && 
                $this->acceptPageBreak()

            ) {
                $x2 = $this->x;
                $this->addPage($this->curOrientation, $this->curPageSize, $this->curRotation);
                $this->x = $x2;
            }

            $y = $this->y;
            $this->y += $h;
        }

        if ($x === null) {
            $x = $this->x;
        }

        $this->out(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', 
            $w * $this->k, $h * $this->k, 
            $x * $this->k, 
            ($this->h - ($y + $h)) * $this->k, $info['i'])
        );

        if ($link) {
            $this->link($x, $y, $w, $h, $link);
        }
    }

    public function getPageWidth()
    {
        return $this->w;
    }

    public function getPageHeight()
    {
        return $this->h;
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function setX(float $x): void
    {
        $this->x = $this->w + $x;

        if($x>=0){
            $this->x = $x;
        }
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function setY(float $y, bool $resetX = true): void
    {
        $this->y = $this->h+$y;

        if($y>=0){
            $this->y = $y;
        }

        if($resetX){
            $this->x = $this->lMargin;
        }
    }

    public function setXY($x, $y): void
    {
        $this->setX($x);
        $this->setY($y,false);
    }



    public function output(

        string $dest = '', 
        string $name = '', 
        bool $isUTF8 = false

    ): string {

        $this->close();

        if (strlen($name) == 1 && strlen($dest) != 1) {
            [$dest, $name] = [$name, $dest];
        }

        if ($dest === '') {
            $dest = 'I';
        }

        if ($name === '') {
            $name = 'doc.pdf';
        }

        switch (strtoupper($dest)) {
        case 'I':
        case 'D':
            $this->checkoutput();
            header('Content-Type: application/pdf');
            $disposition = ($dest === 'I') ? 'inline' : 'attachment';
            header('Content-Disposition: ' . $disposition . '; ' . $this->httpencode('filename', $name, $isUTF8));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $this->buffer;
            return '';

        case 'F':
            if (!file_put_contents($name, $this->buffer)) {
                throw new InvalidArgumentException($this->fpdfError . 'Unable to create output file: ' . $name);
            }
            return '';

        case 'S':
            return $this->buffer;
        }

        throw new InvalidArgumentException($this->fpdfError . 'Incorrect output destination: ' . $dest);
    }


}

