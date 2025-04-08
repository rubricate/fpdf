<?php

namespace Rubricate\Fpdf;

use InvalidArgumentException;

class FileFpdf
{
    const VERSION = '1.86';
    protected int $page = 0;               // current page number
    protected int $state = 0;              // current page number
    protected int $n = 2;                  // current object number
    protected array $offsets = [];         // array of object offsets
    protected string $buffer = '';         // buffer holding in-memory PDF
    protected array $pages = [];           // array containing pages
    protected array $pageInfo = [];        // page-related data
    protected array $fonts = [];           // array of used fonts
    protected array $fontFiles = [];       // array of font files
    protected array $encodings = [];       // array of encodings
    protected array $cmaps = [];           // array of ToUnicode CMaps
    protected array $images = [];          // array of used images
    protected array $links = [];           // array of internal links
    protected bool $inHeader = false;      // flag set when processing header
    protected bool $inFooter = false;      // flag set when processing footer
    protected float $lasth = 0;            // height of last printed cell
    protected string $fontFamily = '';     // current font family
    protected string $fontStyle = '';      // current font style
    protected bool $underline = false;     // underlining flag
    protected array $currentFont = [];     // current font info
    protected float $fontSizePt = 12.0;    // current font size in points
    protected float $fontSize = 0.0;       // current font size in user unit
    protected string $drawColor = '0 G';   // commands for drawing color
    protected string $fillColor = '0 g';   // commands for filling color
    protected string $textColor = '0 g';   // commands for text color
    protected bool $colorFlag = false;     // indicates whether fill and text colors are different
    protected bool $withAlpha = false;     // indicates whether alpha channel is used
    protected float $ws = 0.0;             // word spacing
    protected bool $autoPageBreak = true;  // automatic page breaking
    protected float $pageBreakTrigger = 0.0; // threshold used to trigger page breaks
    protected string $aliasNbPages = '';   // alias for total number of pages
    protected string $zoomMode = '';       // zoom display mode
    protected string $layoutMode = '';     // layout display mode
    protected array $metadata = [];        // document properties
    protected string $creationDate = '';   // document creation date
    protected string $pdfVersion = '1.3';  // PDF version number

    protected float $lMargin = 2;
    protected float $tMargin = 2;
    protected float $rMargin = 2;
    protected float $bMargin = 2;
    protected array $PageLinks = [];
    protected float $x = 0;
    protected float $y = 0;
    protected float $w = 0;
    protected float $h = 0;
    protected float $k = 0;
    protected string $curOrientation = '';
    protected array $curPageSize = [];
    protected int $curRotation = 0; // Page rotation
    protected bool $iconv;
    protected string $fontpath = '';
    protected array $coreFonts = [];
    protected float $lineWidth = 2.0;
    protected string $defOrientation = 'P';
    protected array $defPageSize = [];
    private $fpdfError = "Error FPDF: " ;

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


    protected function checkoutput()
    {
        if(PHP_SAPI!='cli') {

            if(headers_sent($file,$line)){
                throw new InvalidArgumentException($this->fpdfError . "Some data has already been output, can't send PDF file (output started at $file:$line)");
            }
        }

        if(ob_get_length()){

            if(!preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())){
                throw new InvalidArgumentException($this->fpdfError . "Some data has already been output, can't send PDF file");
            }

            ob_clean();
        }
    }

    protected function getpagesize(string|array $size): string|array
    {
        if(is_string($size)) {

            $size = strtolower($size);
            $size = (is_string($size))? $size: 'a4';

            if(!isset($this->StdPageSizes[$size])){
                throw new InvalidArgumentException($this->fpdfError . 'Unknown page size: '. $size);
            }

            $a = $this->StdPageSizes[$size];

            return [$a[0]/$this->k, $a[1]/$this->k];

        } else {

            if($size[0] > $size[1]){
                return [$size[1], $size[0]];
            }

            return $size;
        }
    }


    protected function beginpage(string $orientation, array|string $size, int $rotation): void
    {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->PageLinks[$this->page] = [];
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->fontFamily = '';

        // Check page size and orientation
        $orientation = $orientation === '' ? $this->defOrientation : strtoupper($orientation[0]);
        $size = $size === '' ? $this->defPageSize : $this->getpagesize($size);

        if (
            $orientation !== $this->curOrientation || 
            $size[0] !== $this->curPageSize[0] || 
            $size[1] !== $this->curPageSize[1])
        {
            $this->w = $size[1];
            $this->h = $size[0];

            if ($orientation === 'P') {
                $this->w = $size[0];
                $this->h = $size[1];
            }

            $this->wPt = $this->w * $this->k;
            $this->hPt = $this->h * $this->k;
            $this->pageBreakTrigger = $this->h - $this->bMargin;
            $this->curOrientation = $orientation;
            $this->curPageSize = $size;
        }

        if (
            $orientation !== $this->defOrientation || 
            $size[0] !== $this->defPageSize[0] || 
            $size[1] !== $this->defPageSize[1]) 
        {
            $this->pageInfo[$this->page]['size'] = [$this->wPt, $this->hPt];
        }

        if ($rotation !== 0) {

            if ($rotation % 90 !== 0) {
                throw new InvalidArgumentException(
                    $this->fpdfError . 'Incorrect rotation value: ' . $rotation
                );
            }

            $this->pageInfo[$this->page]['rotation'] = $rotation;
        }

        $this->curRotation = $rotation;
    }

    protected function endpage()
    {
        $this->state = 1;
    }

    protected function loadfont(string $path): array
    {
        include $path;

        if (!isset($name)) {
            throw new InvalidArgumentException(
                $this->fpdfError . 'Could not include font definition file: ' . $path
            );
        }

        $enc = isset($enc) ? strtolower($enc) : null;
        $subsetted = $subsetted ?? false;

        return get_defined_vars();
    }

    protected function isascii(string $s): bool
    {
        $nb = strlen($s);
        for ($i = 0; $i < $nb; $i++) {
            if (ord($s[$i]) > 127) {
                return false;
            }
        }

        return true;
    }

    protected function httpencode(string $param, string $value, bool $isUTF8): string
    {
        if ($this->isascii($value)) {
            return $param . '="' . $value . '"';
        }

        if (!$isUTF8) {
            $value = $this->utf8convert($value);
        }

        return $param . "*=UTF-8''" . rawurlencode($value);
    }

    protected function utf8convert(string $s): string
    {
        if ($this->iconv) {
            return iconv('ISO-8859-1', 'UTF-8', $s);
        }

        $res = '';
        $nb = strlen($s);
        for ($i = 0; $i < $nb; $i++) {

            $c = $s[$i];
            $v = ord($c);

            if ($v >= 128) {

                $res .= chr(0xC0 | ($v >> 6));
                $res .= chr(0x80 | ($v & 0x3F));

            } else {
                $res .= $c;
            }
        }
        return $res;
    }

    protected function utf8toUtf16(string $s): string
    {
        $res = "\xFE\xFF";
        if ($this->iconv) {
            return $res . iconv('UTF-8', 'UTF-16BE', $s);
        }

        $nb = strlen($s);
        $i = 0;

        while ($i < $nb) {

            $c1 = ord($s[$i++]);

            if ($c1 >= 224) {

                $c2 = ord($s[$i++]);
                $c3 = ord($s[$i++]);
                $res .= chr((($c1 & 0x0F) << 4) + (($c2 & 0x3C) >> 2));
                $res .= chr((($c2 & 0x03) << 6) + ($c3 & 0x3F));

            } elseif ($c1 >= 192) {

                $c2 = ord($s[$i++]);
                $res .= chr(($c1 & 0x1C) >> 2);
                $res .= chr((($c1 & 0x03) << 6) + ($c2 & 0x3F));

            } else {

                $res .= "\0" . chr($c1);
            }
        }

        return $res;
    }

    protected function escape(string $s): string
    {
        if (str_contains($s, '(') || str_contains($s, ')') || str_contains($s, '\\') || str_contains($s, "\r")) {
            return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $s);
        }
        return $s;
    }

    protected function textString($s)
    {
        if(!$this->isascii($s))
            $s = $this->utf8toUtf16($s);
        return '('.$this->escape($s).')';
    }

    protected function dounderLine($x, $y, $txt)
    {
        $up = $this->currentFont['up'];
        $ut = $this->currentFont['ut'];
        $w = $this->getStringWidth($txt)+$this->ws*substr_count($txt,' ');
        return sprintf(
            '%.2F %.2F %.2F %.2F re f',
            $x * $this->k, ($this->h - ($y-$up/1000*$this->fontSize)) * $this->k,
            $w * $this->k, -$ut/1000 * $this->fontSizePt
        );
    }

    protected function parsejpg($file)
    {
        // Extract info from a JPEG file
        $a = getimagesize($file);

        if(!$a){
            throw new InvalidArgumentException($this->fpdfError . 'Missing or incorrect image file: ' . $file);
        }

        if($a[2]!=2){
            throw new InvalidArgumentException($this->fpdfError . 'Not a JPEG file: ' . $file);
        }

        if(!isset($a['channels']) || $a['channels'] == 3){

            $colspace = 'DeviceRGB';

        } elseif($a['channels']==4){

            $colspace = 'DeviceCMYK';

        }else{

            $colspace = 'DeviceGray';
        }

        $bpc = isset($a['bits']) ? $a['bits'] : 8;
        $data = file_get_contents($file);

        return [
            'w' => $a[0],
            'h' => $a[1],
            'cs'=>$colspace,
            'bpc'=>$bpc,
            'f'=>'DCTDecode',
            'data'=>$data
        ];

    }

    protected function parsepng($file): array
    {
        if (!file_exists($file)) {
            throw new InvalidArgumentException(
                $this->fpdfError . sprintf('Image "%s" not found', $file)
            );
        }

        $f = fopen($file,'rb');

        if(!$f){
            throw new InvalidArgumentException($this->fpdfError . 'Can\'t open image file: '.$file);
        }

        $info = $this->parsepngstream($f,$file);
        fclose($f);

        return $info;
    }

    protected function parsepngstream($f, string $file): array
    {
        // Check signature
        if ($this->_readstream($f, 8) !== chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) {
            throw new InvalidArgumentException($this->fpdfError . 'Not a PNG file: ' . $file);
        }

        // Read header chunk
        $this->_readstream($f, 4);
        if ($this->_readstream($f, 4) !== 'IHDR') {
            throw new InvalidArgumentException($this->fpdfError . 'Incorrect PNG file: ' . $file);
        }

        $w = $this->_readint($f);
        $h = $this->_readint($f);
        $bpc = ord($this->_readstream($f, 1));

        if ($bpc > 8) {
            throw new InvalidArgumentException($this->fpdfError . '16-bit depth not supported: ' . $file);
        }

        $ct = ord($this->_readstream($f, 1));
        $colspace = match ($ct) {
        0, 4 => 'DeviceGray',
            2, 6 => 'DeviceRGB',
            3 => 'Indexed',
default => throw new InvalidArgumentException($this->fpdfError . 'Unknown color type: ' . $file),
        };

        if (ord($this->_readstream($f, 1)) !== 0) {
            throw new InvalidArgumentException($this->fpdfError . 'Unknown compression method: ' . $file);
        }
        if (ord($this->_readstream($f, 1)) !== 0) {
            throw new InvalidArgumentException($this->fpdfError . 'Unknown filter method: ' . $file);
        }
        if (ord($this->_readstream($f, 1)) !== 0) {
            throw new InvalidArgumentException($this->fpdfError . 'Interlacing not supported: ' . $file);
        }

        $this->_readstream($f, 4);
        $dp = "/Predictor 15 /Colors " . ($colspace === 'DeviceRGB' ? 3 : 1) . " /BitsPerComponent $bpc /Columns $w";

        $pal = '';
        $trns = [];
        $data = '';

        do{
            $n = $this->_readint($f);
            $type = $this->_readstream($f,4);

            if($type == 'PLTE') {

                $pal = $this->_readstream($f,$n);
                $this->_readstream($f,4);

            } elseif($type == 'tRNS') {

                $t = $this->_readstream($f,$n);

                if($ct==0){
                    $trns = [ord(substr($t,1,1))];

                }elseif($ct == 2){
                    $trns = [ord(substr($t,1,1)), ord(substr($t,3,1)), ord(substr($t,5,1))];

                } else {

                    $pos = strpos($t,chr(0));

                    if($pos !== false){
                        $trns = [$pos];
                    }
                }

                $this->_readstream($f,4);

            } elseif($type == 'IDAT'){

                $data .= $this->_readstream($f,$n);
                $this->_readstream($f,4);

            } elseif($type == 'IEND'){
                break;

            } else{

                $this->_readstream($f,$n+4);
            }

        } while($n);


        if($colspace == 'Indexed' && empty($pal)){
            throw new InvalidArgumentException(
                $this->fpdfError . 'Missing palette in '.$file
            );
        }

        $info = [
            'w' => $w,
            'h' => $h,
            'cs' => $colspace,
            'bpc' => $bpc,
            'f' => 'FlateDecode',
            'dp' => $dp,
            'pal' => $pal,
            'trns' => $trns
        ];

        if($ct >= 4){

            if(!function_exists('gzuncompress')){
                throw new InvalidArgumentException(
                    $this->fpdfError . 'Zlib not available, can\'t handle alpha channel: '.$file
                );
            }

            $data = gzuncompress($data);
            $color = '';
            $alpha = '';

            if($ct == 4){

                $len = 2*$w;
                for($i=0;$i<$h;$i++){

                    $pos = (1+$len)*$i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data,$pos+1,$len);
                    $color .= preg_replace('/(.)./s','$1',$line);
                    $alpha .= preg_replace('/.(.)/s','$1',$line);
                }

            } else {

                $len = 4 * $w;
                for($i=0; $i<$h; $i++){

                    $pos = (1+$len)*$i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data,$pos+1,$len);
                    $color .= preg_replace('/(.{3})./s','$1',$line);
                    $alpha .= preg_replace('/.{3}(.)/s','$1',$line);
                }
            }

            unset($data);
            $data = gzcompress($color);
            $info['smask'] = gzcompress($alpha);
            $this->withAlpha = true;

            if($this->pdfVersion < '1.4'){
                $this->pdfVersion = '1.4';
            }
        }

        $info['data'] = $data;
        return $info;
    }

    protected function _readstream($f, int $n): string
    {
        if (!is_resource($f)) {
            throw new InvalidArgumentException($this->fpdfError . 'Invalid stream resource');
        }

        $res = '';
        while ($n > 0 && !feof($f)) {
            $s = fread($f, $n);
            if ($s === false) {
                throw new InvalidArgumentException($this->fpdfError . 'Error while reading stream');
            }
            $n -= strlen($s);
            $res .= $s;
        }

        if ($n > 0) {
            throw new InvalidArgumentException($this->fpdfError . 'Unexpected end of stream');
        }

        return $res;
    }

    protected function _readint($f): int
    {
        if (!is_resource($f)) {
            throw new InvalidArgumentException($this->fpdfError . 'Invalid stream resource');
        }

        $a = unpack('Ni', $this->_readstream($f, 4));
        return $a['i'];
    }

    protected function _parsegif(string $file): array
    {
        if (!function_exists('imagepng')) {
            throw new InvalidArgumentException(
                $this->fpdfError . 'GD extension is required for GIF support'
            );
        }

        if (!function_exists('imagecreatefromgif')) {
            throw new InvalidArgumentException($this->fpdfError . 'GD has no GIF read support');
        }

        $im = imagecreatefromgif($file);
        if (!$im) {
            throw new InvalidArgumentException(
                $this->fpdfError . 'Missing or incorrect image file: ' . $file
            );
        }

        imageinterlace($im, 0);

        ob_start();
        imagepng($im);
        $data = ob_get_clean();
        imagedestroy($im);

        $f = fopen('php://temp', 'rb+');

        if (!$f) {
            throw new InvalidArgumentException(
                $this->fpdfError . 'Unable to create memory stream'
            );
        }

        fwrite($f, $data);
        rewind($f);

        $info = $this->parsepngstream($f, $file);
        fclose($f);

        return $info;
    }

    protected function out(string $s): void
    {
        // Adiciona uma linha à página atual
        switch ($this->state) {
        case 2:
            $this->pages[$this->page] .= $s . "\n";
            break;
        case 0:
            throw new InvalidArgumentException($this->fpdfError . 'No page has been added yet');
        case 1:
            throw new InvalidArgumentException($this->fpdfError . 'Invalid call');
        case 3:
            throw new InvalidArgumentException($this->fpdfError . 'The document is closed');
        }
    }

    protected function put($s): void
    {
        $this->buffer .= $s."\n";
    }

    protected function getoffset(): int
    {
        return strlen($this->buffer);
    }

    protected function newobj(?int $n = null): void
    {
        if ($n === null) {
            $n = ++$this->n;
        }

        $this->offsets[$n] = $this->getoffset();
        $this->put($n . ' 0 obj');
    }

    protected function putstream(string $data): void
    {
        $this->put('stream');
        $this->put($data);
        $this->put('endstream');
    }

    protected function putstreamobject(string $data): void
    {
        $entries = '';

        if ($this->compress) {
            $entries = '/Filter /FlateDecode ';
            $data = gzcompress($data);
        } 

        $entries .= '/Length ' . strlen($data);

        $this->newobj();
        $this->put('<<' . $entries . '>>');
        $this->putstream($data);
        $this->put('endobj');
    }

    protected function _putlinks(int $n): void
    {
        foreach ($this->PageLinks[$n] as $pl) {

            $this->newobj();
            $rect = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
            $s = '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';

            if (is_string($pl[4])) {

                $s .= '/A <</S /URI /URI ' . $this->textString($pl[4]) . '>>>>';

            } else {

                $l = $this->links[$pl[4]];
                $h = $this->pageInfo[$l[0]]['size'][1] ?? 
                    (($this->defOrientation === 'P') ? 
                    $this->defPageSize[1] * $this->k : 
                    $this->defPageSize[0] * $this->k);

                $s .= sprintf(
                    '/Dest [%d 0 R /XYZ 0 %.2F null]>>', 
                    $this->pageInfo[$l[0]]['n'], 
                    $h - $l[1] * $this->k
                );
            }

            $this->put($s);
            $this->put('endobj');
        }
    }

    protected function putpage(int $n): void
    {
        $this->newobj();
        $this->put('<</Type /Page');
        $this->put('/Parent 1 0 R');

        if (isset($this->pageInfo[$n]['size'])) {
            $this->put(sprintf(
                '/MediaBox [0 0 %.2F %.2F]',
                $this->pageInfo[$n]['size'][0],
                $this->pageInfo[$n]['size'][1]
            ));
        }

        if (isset($this->pageInfo[$n]['rotation'])) {
            $this->put('/Rotate ' . $this->pageInfo[$n]['rotation']);
        }

        $this->put('/Resources 2 0 R');

        if (!empty($this->PageLinks[$n])) {

            $s = '/Annots [';

            foreach ($this->PageLinks[$n] as $pl) {
                $s .= $pl[5] . ' 0 R ';
            }

            $s .= ']';
            $this->put($s);
        }

        if ($this->withAlpha) {
            $this->put('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
        }

        $this->put('/Contents ' . ($this->n + 1) . ' 0 R>>');
        $this->put('endobj');

        if (!empty($this->aliasNbPages)) {
            $this->pages[$n] = str_replace(
                $this->aliasNbPages, 
                (string) $this->page, $this->pages[$n]
            );
        }

        $this->putstreamobject($this->pages[$n]);

        $this->_putlinks($n);
    }

    protected function putpages(): void
    {
        $nb = $this->page;
        $n = $this->n;
        $w = $this->defPageSize[1];
        $h = $this->defPageSize[0];

        for($i=1;$i<=$nb;$i++){

            $this->pageInfo[$i]['n'] = ++$n;
            $n++;

            foreach($this->PageLinks[$i] as &$pl){
                $pl[5] = ++$n;
            }

            unset($pl);
        }

        for($i=1;$i<=$nb;$i++){
            $this->putpage($i);
        }

        $this->newobj(1);
        $this->put('<</Type /Pages');
        $kids = '/Kids [';

        for($i=1;$i<=$nb;$i++){
            $kids .= $this->pageInfo[$i]['n'].' 0 R ';
        }

        $kids .= ']';
        $this->put($kids);
        $this->put('/Count '.$nb);

        if($this->defOrientation=='P'){
            $w = $this->defPageSize[0];
            $h = $this->defPageSize[1];
        }

        $this->put(sprintf('/MediaBox [0 0 %.2F %.2F]', $w*$this->k,$h*$this->k));
        $this->put('>>');
        $this->put('endobj');
    }

    protected function putfonts(): void
    {
        if (!empty($this->fontfiles) && is_array($this->fontfiles)) {

            foreach ($this->fontfiles as $file => $info) {
                // Font file embedding
                $this->newobj();
                $this->fontfiles[$file]['n'] = $this->n;

                $font = @file_get_contents($file);

                if ($font === false) {
                    throw new InvalidArgumentException(
                        $this->fpdfError . ' Font file not found or cannot be read: ' . $file
                    );
                }

                $compressed = str_ends_with($file, '.z');

                if (!$compressed && isset($info['length2'])) {
                    $font = ''
                        . substr($font, 6, $info['length1']) 
                        . substr($font, 6 + $info['length1'] + 6, $info['length2']
                        );
                }

                $this->put('<</Length ' . strlen($font));

                if ($compressed) {
                    $this->put('/Filter /FlateDecode');
                }

                $this->put('/Length1 ' . $info['length1']);

                if (isset($info['length2'])) {
                    $this->put('/Length2 ' . $info['length2'] . ' /Length3 0');
                }

                $this->put('>>');
                $this->putstream($font);
                $this->put('endobj');
            }
        }

        foreach ($this->fonts as $k => $font) {
            // Encoding
            if (!empty($font['diff']) && !isset($this->encodings[$font['enc']])) {
                $this->newobj();
                $this->put(''
                    . '<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' 
                    . $font['diff'] . ']>>');

                $this->put('endobj');
                $this->encodings[$font['enc']] = $this->n;
            }

            // ToUnicode CMap
            $cmapKey = $font['enc'] ?? $font['name'];

            if (!empty($font['uv']) && !isset($this->cmaps[$cmapKey])) {
                $cmap = $this->_tounicodecmap($font['uv']);
                $this->putstreamobject($cmap);
                $this->cmaps[$cmapKey] = $this->n;
            }

            // Font object
            $this->fonts[$k]['n'] = $this->n + 1;
            $type = $font['type'];
            $name = !empty($font['subsetted']) ? 'AAAAAA+' . $font['name'] : $font['name'];

            if ($type === 'Core') {
                // Core font
                $this->newobj();
                $this->put('<</Type /Font');
                $this->put('/BaseFont /' . $name);
                $this->put('/Subtype /Type1');

                if ($name !== 'Symbol' && $name !== 'ZapfDingbats') {
                    $this->put('/Encoding /WinAnsiEncoding');
                }

                if (!empty($font['uv'])) {
                    $this->put('/ToUnicode ' . $this->cmaps[$cmapKey] . ' 0 R');
                }

                $this->put('>>');
                $this->put('endobj');

            } elseif ($type === 'Type1' || $type === 'TrueType') {
                // Additional Type1 or TrueType/OpenType font
                $this->newobj();
                $this->put('<</Type /Font');
                $this->put('/BaseFont /' . $name);
                $this->put('/Subtype /' . $type);
                $this->put('/FirstChar 32 /LastChar 255');
                $this->put('/Widths ' . ($this->n + 1) . ' 0 R');
                $this->put('/FontDescriptor ' . ($this->n + 2) . ' 0 R');
                $this->put('/Encoding ' . ($this->encodings[$font['enc']] ?? '/WinAnsiEncoding') . ' 0 R');

                if (!empty($font['uv'])) {
                    $this->put('/ToUnicode ' . $this->cmaps[$cmapKey] . ' 0 R');
                }

                $this->put('>>');
                $this->put('endobj');

                // Widths
                $this->newobj();
                $this->put('[' . implode(' ', array_map(fn($i) => $font['cw'][chr($i)], range(32, 255))) . ']');
                $this->put('endobj');

                // Descriptor
                $this->newobj();
                $s = '<</Type /FontDescriptor /FontName /' . $name;

                foreach ($font['desc'] as $key => $value) {
                    $s .= ' /' . $key . ' ' . $value;
                }

                if (!empty($font['file'])) {
                    $s .= ''
                        . ' /FontFile' . ($type === 'Type1' ? '' : '2') 
                        . ' ' . $this->fontfiles[$font['file']]['n'] . ' 0 R'
                        . '';
                }

                $this->put($s . '>>');
                $this->put('endobj');

            } else {

                // Allow for additional types
                $mtd = '_put' . strtolower($type);

                if (!method_exists($this, $mtd)) {

                    throw new InvalidArgumentException(
                        $this->fpdfError . ' Unsupported font type: ' . $type
                    );
                }

                $this->$mtd($font);
            }
        }
    }

    protected function _tounicodecmap(array $uv): string
    {
        $ranges = '';
        $nbr = 0;
        $chars = '';
        $nbc = 0;

        foreach ($uv as $c => $v) {
            if (is_array($v)) {

                $ranges .= sprintf("<%02X> <%02X> <%04X>\n", $c, $c + $v[1] - 1, $v[0]);
                $nbr++;

            } else {

                $chars .= sprintf("<%02X> <%04X>\n", $c, $v);
                $nbc++;
            }
        }

        $s = <<<EOD
        /CIDInit /ProcSet findresource begin
        12 dict begin
        begincmap
        /CIDSystemInfo
        <</Registry (Adobe)
        /Ordering (UCS)
        /Supplement 0
        >> def
        /CMapName /Adobe-Identity-UCS def
        /CMapType 2 def
        1 begincodespacerange
        <00> <FF>
        endcodespacerange
EOD;

        if ($nbr > 0) {
            $s .= "\n$nbr beginbfrange\n$ranges\nendbfrange";
        }

        if ($nbc > 0) {
            $s .= "\n$nbc beginbfchar\n$chars\nendbfchar";
        }

        $s .= <<<EOD

        endcmap
        CMapName currentdict /CMap defineresource pop
        end
        end
EOD;

        return $s;
    }

    protected function putimages(): void
    {
        foreach(array_keys($this->images) as $file){

            $this->_putimage($this->images[$file]);
            unset($this->images[$file]['data']);
            unset($this->images[$file]['smask']);
        }
    }

    protected function _putimage(array &$info): void
    {
        $this->newobj();
        $info['n'] = $this->n;

        // Definindo tipo e propriedades da imagem
        $this->put('<</Type /XObject');
        $this->put('/Subtype /Image');
        $this->put('/Width ' . $info['w']);
        $this->put('/Height ' . $info['h']);

        // Definindo a cor
        if ($info['cs'] === 'Indexed') {

            $this->put(''
                . '/ColorSpace [/Indexed /DeviceRGB ' 
                . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->n + 1) . ' 0 R]'
            );

        } else {

            $this->put('/ColorSpace /' . $info['cs']);

            if ($info['cs'] === 'DeviceCMYK') {
                $this->put('/Decode [1 0 1 0 1 0 1 0]');
            }
        }

        // Definindo BitsPerComponent
        $this->put('/BitsPerComponent ' . $info['bpc']);

        // Definindo o filtro
        if (isset($info['f'])) {
            $this->put('/Filter /' . $info['f']);
        }

        // Definindo parâmetros de decodificação
        if (isset($info['dp'])) {
            $this->put('/DecodeParms <<' . $info['dp'] . '>>');
        }

        // Definindo a máscara de transparência
        if (isset($info['trns']) && is_array($info['trns'])) {
            $trns = '';
            foreach ($info['trns'] as $value) {
                $trns .= "$value $value ";
            }
            $this->put('/Mask [' . $trns . ']');
        }

        // Definindo o Soft Mask (máscara suave)
        if (isset($info['smask'])) {
            $this->put('/SMask ' . ($this->n + 1) . ' 0 R');
        }

        // Comprimento dos dados da imagem
        $this->put('/Length ' . strlen($info['data']) . '>>');
        $this->putstream($info['data']);
        $this->put('endobj');

        // Soft mask
        if (isset($info['smask'])) {
            $dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns ' . $info['w'];
            $smask = [
                'w' => $info['w'],
                'h' => $info['h'],
                'cs' => 'DeviceGray',
                'bpc' => 8,
                'f' => $info['f'],
                'dp' => $dp,
                'data' => $info['smask']
            ];

            $this->_putimage($smask);
        }

        // Palette
        if ($info['cs'] === 'Indexed') {
            $this->putstreamobject($info['pal']);
        }
    }

    protected function putxobjectdict(): void
    {
        foreach($this->images as $image){
            $this->put('/I'.$image['i'].' '.$image['n'].' 0 R');
        }
    }

    protected function putresourcedict(): void
    {
        $this->put('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->put('/Font <<');

        foreach($this->fonts as $font){
            $this->put('/F'.$font['i'].' '.$font['n'].' 0 R');
        }

        $this->put('>>');
        $this->put('/XObject <<');
        $this->putxobjectdict();
        $this->put('>>');
    }

    protected function putresources(): void
    {
        $this->putfonts();
        $this->putimages();
        // Resource dictionary
        $this->newobj(2);
        $this->put('<<');
        $this->putresourcedict();
        $this->put('>>');
        $this->put('endobj');
    }

    protected function putinfo(): void
    {
        $date = @date('YmdHisO',$this->creationDate);
        $this->metadata['CreationDate'] = 'D:'.substr($date,0,-2)."'".substr($date,-2)."'";

        foreach($this->metadata as $key=>$value){
            $this->put('/'.$key.' '.$this->textString($value));
        }
    }

    protected function putcatalog(): void
    {
        $n = $this->pageInfo[1]['n'];
        $this->put('/Type /Catalog');
        $this->put('/Pages 1 0 R');

        // Tratando o OpenAction baseado no ZoomMode
        switch ($this->zoomMode) {
        case 'fullpage':
            $this->put('/OpenAction [' . $n . ' 0 R /Fit]');
            break;
        case 'fullwidth':
            $this->put('/OpenAction [' . $n . ' 0 R /FitH null]');
            break;
        case 'real':
            $this->put('/OpenAction [' . $n . ' 0 R /XYZ null null 1]');
            break;
        default:
            if (is_numeric($this->zoomMode)) {
                $this->put(''
                    . '/OpenAction [' . $n . ' 0 R /XYZ null null ' 
                    . sprintf('%.2F', $this->zoomMode / 100) . ']'
                );
            }

            break;
        }

        // Tratando o LayoutMode
        switch ($this->layoutMode) {
        case 'single':
            $this->put('/PageLayout /SinglePage');
            break;
        case 'continuous':
            $this->put('/PageLayout /OneColumn');
            break;
        case 'two':
            $this->put('/PageLayout /TwoColumnLeft');
            break;
        }
    }

    protected function putheader(): void
    {
        $this->put('%PDF-'.$this->pdfVersion);
    }

    protected function puttrailer(): void
    {
        $this->put('/Size '.($this->n+1));
        $this->put('/Root '.$this->n.' 0 R');
        $this->put('/Info '.($this->n-1).' 0 R');
    }

    protected function enddoc(): void
    {
        $this->creationDate = time();
        $this->putheader();
        $this->putpages();
        $this->putresources();
        // Info
        $this->newobj();
        $this->put('<<');
        $this->putinfo();
        $this->put('>>');
        $this->put('endobj');
        // Catalog
        $this->newobj();
        $this->put('<<');
        $this->putcatalog();
        $this->put('>>');
        $this->put('endobj');
        // Cross-ref
        $offset = $this->getoffset();
        $this->put('xref');
        $this->put('0 '.($this->n+1));
        $this->put('0000000000 65535 f ');

        for($i=1;$i<=$this->n;$i++){
            $this->put(sprintf('%010d 00000 n ',$this->offsets[$i]));
        }

        // Trailer
        $this->put('trailer');
        $this->put('<<');
        $this->puttrailer();
        $this->put('>>');
        $this->put('startxref');
        $this->put($offset);
        $this->put('%%EOF');
        $this->state = 3;
    }
}

