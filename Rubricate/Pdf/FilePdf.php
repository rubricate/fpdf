<?php

namespace Rubricate\Pdf;

use InvalidArgumentException;

class FilePdf
{
    const VERSION = '1.86';
    protected int $page = 0;               // current page number
    protected int $state = 0;              // current page number
    protected int $n = 2;                  // current object number
    protected array $offsets = [];         // array of object offsets
    protected string $buffer = '';         // buffer holding in-memory PDF
    protected array $pages = [];           // array containing pages
    protected array $PageInfo = [];        // page-related data
    protected array $fonts = [];           // array of used fonts
    protected array $FontFiles = [];       // array of font files
    protected array $encodings = [];       // array of encodings
    protected array $cmaps = [];           // array of ToUnicode CMaps
    protected array $images = [];          // array of used images
    protected array $links = [];           // array of internal links
    protected bool $InHeader = false;      // flag set when processing header
    protected bool $InFooter = false;      // flag set when processing footer
    protected float $lasth = 0;            // height of last printed cell
    protected string $FontFamily = '';     // current font family
    protected string $FontStyle = '';      // current font style
    protected bool $underline = false;     // underlining flag
    protected array $CurrentFont = [];     // current font info
    protected float $FontSizePt = 12.0;    // current font size in points
    protected float $FontSize = 0.0;       // current font size in user unit
    protected string $DrawColor = '0 G';   // commands for drawing color
    protected string $FillColor = '0 g';   // commands for filling color
    protected string $TextColor = '0 g';   // commands for text color
    protected bool $ColorFlag = false;     // indicates whether fill and text colors are different
    protected bool $WithAlpha = false;     // indicates whether alpha channel is used
    protected float $ws = 0.0;             // word spacing
    protected bool $AutoPageBreak = true;  // automatic page breaking
    protected float $PageBreakTrigger = 0.0; // threshold used to trigger page breaks
    protected string $AliasNbPages = '';   // alias for total number of pages
    protected string $ZoomMode = '';       // zoom display mode
    protected string $LayoutMode = '';     // layout display mode
    protected array $metadata = [];        // document properties
    protected string $CreationDate = '';   // document creation date
    protected string $PDFVersion = '1.3';  // PDF version number

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
    protected string $CurOrientation = '';
    protected array $CurPageSize = [];
    protected int $CurRotation = 0; // Page rotation
    protected bool $iconv;
    protected string $fontpath = '';
    protected array $CoreFonts = [];
    protected float $LineWidth = 2.0;
    protected string $DefOrientation = 'P';
    protected array $DefPageSize = [];
    private $fpdfError = "Error FPDF: " ;

    public function __construct(string $orientation = 'P', string $unit = 'mm', string $size = 'A4')
    {
        $this->iconv = function_exists('iconv');
        $this->fontpath = defined('FPDF_FONTPATH')? FPDF_FONTPATH :
            dirname(dirname(dirname(__FILE__))) . '/font/';

        $this->CoreFonts = ['courier', 'helvetica', 'times', 'symbol', 'zapfdingbats'];

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

        $size = $this->_getpagesize($size);
        $this->DefPageSize = $size;
        $this->CurPageSize = $size;

        $orientation = strtolower($orientation);

        if ($orientation === 'p' || $orientation === 'portrait') {

            $this->DefOrientation = 'P';
            $this->w = $size[0];
            $this->h = $size[1];

        } elseif ($orientation === 'l' || $orientation === 'landscape') {

            $this->DefOrientation = 'L';
            $this->w = $size[1];
            $this->h = $size[0];

        } else {

            throw new InvalidArgumentException('Incorrect orientation: ' . $orientation);
        }

        $this->CurOrientation = $this->DefOrientation;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;

        $this->CurRotation = 0;

        $margin = 28.35 / $this->k;
        $this->SetMargins($margin, $margin);
        $this->cMargin = $margin/10;
        $this->LineWidth = .567/$this->k;
        $this->SetAutoPageBreak(true, 2 * $margin);
        $this->SetDisplayMode('default');
        $this->SetCompression(true);
        $this->metadata = ['Producer' => 'FPDF ' . self::VERSION];

    }

    public function SetMargins(float $left, float $top, ?float $right = null): void
    {
        $this->lMargin = $left;
        $this->tMargin = $top;
        $this->rMargin = $right ?? $left;
    }

    public function SetLeftMargin(float $margin): void
    {
        $this->lMargin = $margin;

        if($this->page > 0 && $this->x < $margin){
            $this->x = $margin;
        }
    }

    public function SetTopMargin(float $margin): void
    {
        $this->tMargin = $margin;
    }

    public function SetRightMargin(float $margin): void
    {
        $this->rMargin = $margin;
    }

    public function SetAutoPageBreak(string $auto, int $margin = 0): void
    {
        $this->AutoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->PageBreakTrigger = $this->h-$margin;
    }

    public function SetDisplayMode(string|int $zoom, string $layout = 'default'): void
    {
        $validZoomModes = ['fullpage', 'fullwidth', 'real', 'default'];
        $validLayoutModes = ['single', 'continuous', 'two', 'default'];

        if (is_int($zoom) || in_array($zoom, $validZoomModes, true)) {
            $this->ZoomMode = $zoom;
        } else {
            throw new InvalidArgumentException($this->fpdfError . $this->fpdfError . "Incorrect zoom display mode: {$zoom}");
        }

        if (in_array($layout, $validLayoutModes, true)) {
            $this->LayoutMode = $layout;
        } else {
            throw new InvalidArgumentException($this->fpdfError . "Incorrect layout display mode: {$layout}");
        }
    }

    public function SetCompression(bool $compress): void
    {
        $this->compress = $compress;
    }

    private function utf8Encode(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }

    public function SetTitle(string $title, bool $isUTF8 = false): void
    {
        $this->metadata['Title'] = $isUTF8 ? $title : $this->utf8Encode($title);
    }

    public function SetAuthor(string $author, bool $isUTF8 = false): void
    {
        $this->metadata['Author'] = $isUTF8 ? $author : $this->utf8Encode($author);
    }

    public function SetSubject(string $subject, bool $isUTF8 = false): void
    {
        $this->metadata['Subject'] = $isUTF8 ? $subject : $this->utf8Encode($subject);
    }

    public function SetKeywords(string $keywords, bool $isUTF8 = false): void
    {
        $this->metadata['Keywords'] = $isUTF8 ? $keywords : $this->utf8Encode($keywords);
    }

    public function SetCreator(string $creator, bool $isUTF8 = false): void
    {
        $this->metadata['Creator'] = $isUTF8 ? $creator : $this->utf8Encode($creator);
    }

    public function AliasNbPages(string $alias = '{nb}'): void
    {
        $this->AliasNbPages = $alias;
    }

    public function Close()
    {
        if ($this->state === 3) {
            return;
        }
        if ($this->page === 0) {
            $this->AddPage();
        }
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
        $this->_endpage();
        $this->_enddoc();
    }

    public function AddPage(string $orientation = '', array|string $size='', int $rotation = 0): void
    {
        if($this->state==3){
            throw new InvalidArgumentException($this->fpdfError . 'The document is closed');
        }

        $family = $this->FontFamily;
        $style = $this->FontStyle.($this->underline ? 'U' : '');
        $fontsize = $this->FontSizePt;
        $lw = $this->LineWidth;
        $dc = $this->DrawColor;
        $fc = $this->FillColor;
        $tc = $this->TextColor;
        $cf = $this->ColorFlag;

        if($this->page > 0){

            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            $this->_endpage();
        }

        $this->_beginpage($orientation,$size,$rotation);
        $this->_out('2 J');
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w',$lw*$this->k));

        if($family){
            $this->SetFont($family,$style,$fontsize);
        }

        $this->DrawColor = $dc;

        if($dc != '0 G'){
            $this->_out($dc);
        }

        $this->FillColor = $fc;

        if($fc != '0 g'){
            $this->_out($fc);
        }

        $this->TextColor = $tc;
        $this->ColorFlag = $cf;

        $this->InHeader = true;
        $this->Header();
        $this->InHeader = false;

        if($this->LineWidth!=$lw) {

            $this->LineWidth = $lw;
            $this->_out(sprintf('%.2F w',$lw*$this->k));
        }

        if($family){
            $this->SetFont($family,$style,$fontsize);
        }

        if($this->DrawColor!=$dc){
            $this->DrawColor = $dc;
            $this->_out($dc);
        }
        if($this->FillColor!=$fc){
            $this->FillColor = $fc;
            $this->_out($fc);
        }
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
    }

    public function Header()
    {
        // To be implemented in your own inherited class
    }

    public function Footer()
    {
        // To be implemented in your own inherited class
    }

    public function PageNo()
    {
        // Get current page number
        return $this->page;
    }

    public function SetDrawColor(int $r, ?int $g = null, ?int $b = null): void
    {
        $this->DrawColor = ($g === null || $b === null)
            ? sprintf('%.3F G', $r / 255)
            : sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);

        if($this->page>0){
            $this->_out($this->DrawColor);
        }
    }

    public function SetFillColor(int $r, ?int $g = null, ?int $b = null): void
    {
        $this->FillColor = ($g === null || $b === null)
            ? sprintf('%.3F g', $r / 255)
            : sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);

        $this->ColorFlag = ($this->FillColor != $this->TextColor);

        if($this->page>0){
            $this->_out($this->FillColor);
        }
    }

    public function SetTextColor(int $r, ?int $g = null, ?int $b = null): void
    {
        $this->TextColor = ($g === null || $b === null)
            ? sprintf('%.3F g', $r / 255)
            : sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);

        $this->ColorFlag = ($this->FillColor != $this->TextColor);
    }

    public function GetStringWidth(string $s)
    {
        // Get width of a string in the current font
        $cw = $this->CurrentFont['cw'];
        $w = 0;
        $l = strlen($s);

        for($i = 0; $i < $l; $i++){
            $w += $cw[$s[$i]];
        }

        return $w * $this->FontSize/1000;
    }

    public function SetLineWidth(int $width): void
    {
        $this->LineWidth = $width;

        if($this->page > 0){
            $this->_out(sprintf('%.2F w', $width * $this->k));
        }
    }

    public function Line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->_out(sprintf(
            '%.2F %.2F m %.2F %.2F l S',
            $x1 * $this->k, ($this->h - $y1) * $this->k,
            $x2 * $this->k, ($this->h - $y2) * $this->k
        ));
    }

    public function Rect(float $x, float $y, float $w, float $h, string $style = ''): void
    {
        $op = match ($style) {
        'F' => 'f',
            'FD', 'DF' => 'B',
            default => 'S',
    };

        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F re %s',
            $x * $this->k, ($this->h - $y) * $this->k,
            $w * $this->k, -$h * $this->k, $op
        ));
    }

    public function AddFont(string $family, string $style='', string $file='', string $dir=''): void
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

        $info = $this->_loadfont($dir . $file);
        $info['i'] = count($this->fonts) + 1;

        if(!empty($info['file'])){

            $fileLength1 = ['length1'=>$info['size1'], 'length2'=>$info['size2']];
            $fileLength2 = ['length1'=>$info['originalsize']];

            $info['file'] = $dir . $info['file'];
            $this->FontFiles[$info['file']] = $fileLength1;

            if($info['type']=='TrueType'){
                $this->FontFiles[$info['file']] = $fileLength2;
            }
        }

        $this->fonts[$fontkey] = $info;
    }

    public function SetFont(string $family, string $style='', int $size = 0): void
    {
        $family = ($family == '')? $this->FontFamily: strtolower($family);

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
            $size = $this->FontSizePt;
        }

        if(
            $this->FontFamily == $family && 
            $this->FontStyle == $style && 
            $this->FontSizePt == $size
        ){
            return;
        }

        $fontkey = $family . $style;

        if(!isset($this->fonts[$fontkey]))
        {

            if($family=='arial'){
                $family = 'helvetica';
            }

            if(!in_array($family, $this->CoreFonts)){
                throw new InvalidArgumentException(
                    $this->fpdfError . 'Undefined font: '.$family.' '.$style
                );
            }

            if($family=='symbol' || $family=='zapfdingbats'){
                $style = '';
            }

            $fontkey = $family . $style;

            if(!isset($this->fonts[$fontkey])){
                $this->AddFont($family,$style);
            }

        }

        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size/$this->k;
        $this->CurrentFont = $this->fonts[$fontkey];

        if($this->page>0){

            $this->_out(
                sprintf('BT /F%d %.2F Tf ET', 
                $this->CurrentFont['i'], 
                $this->FontSizePt
                ));
        }
    }

    public function SetFontSize(float $size): void
    {
        $this->FontSizePt = $size;
        $this->FontSize = $size/$this->k;

        if($this->page>0 && isset($this->CurrentFont)){
            $this->_out(sprintf(
                'BT /F%d %.2F Tf ET', 
                $this->CurrentFont['i'],
                $this->FontSizePt
            ));
        }
    }

    public function AddLink(): int
    {
        $n = count($this->links) + 1;
        $this->links[$n] = [0, 0];
        return $n;
    }

    public function SetLink(string $link, float $y = 0, int $page = -1): void
    {
        if ($y === -1) {
            $y = $this->y;
        }
        if ($page === -1) {
            $page = $this->page;
        }

        $this->links[$link] = [$page, $y];
    }

    public function Link(float $x, float $y, float $w, float $h, int|string $link): void
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

    public function Text(float $x, float $y, string $txt): void
    {
        if (!isset($this->CurrentFont)) {
            throw new InvalidArgumentException($this->fpdfError . 'No font has been set');
        }

        $s = sprintf(
            'BT %.2F %.2F Td (%s) Tj ET',
            $x * $this->k,
            ($this->h - $y) * $this->k,
            $this->_escape($txt)
        );

        if ($this->underline && $txt !== '') {
            $s .= ' ' . $this->_dounderline($x, $y, $txt);
        }

        if ($this->ColorFlag) {
            $s = 'q ' . $this->TextColor . ' ' . $s . ' Q';
        }

        $this->_out($s);
    }


    public function AcceptPageBreak(): bool
    {
        return $this->AutoPageBreak;
    }

    public function Cell(

        float $w, float $h = 0, 
        string $txt = '', string|int $border = 0, 
        int $ln = 0, string $align = '', 
        bool $fill = false, int|string $link = ''

    ): void {
        $k = $this->k;

        if (
            $this->y + $h > $this->PageBreakTrigger && 
            !$this->InHeader && !$this->InFooter && 
            $this->AcceptPageBreak()
        ) {
            // Automatic page break
            $x = $this->x;
            $ws = $this->ws;

            if ($ws > 0) {
                $this->ws = 0;
                $this->_out('0 Tw');
            }

            $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
            $this->x = $x;

            if ($ws > 0) {
                $this->ws = $ws;
                $this->_out(sprintf('%.3F Tw', $ws * $k));
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
            if (!isset($this->CurrentFont)) {
                throw new InvalidArgumentException($this->fpdfError . 'No font has been set');
            }

            $dx = match ($align) {
            'R' => $w - $this->cMargin - $this->GetStringWidth($txt),
                'C' => ($w - $this->GetStringWidth($txt)) / 2,
                default => $this->cMargin
            };

            if ($this->ColorFlag) {
                $s .= 'q ' . $this->TextColor . ' ';
            }

            $s .= sprintf(
                'BT %.2F %.2F Td (%s) Tj ET',
                ($this->x + $dx) * $k,
                ($this->h - ($this->y + 0.5 * $h + 0.3 * $this->FontSize)) * $k,
                $this->_escape($txt)
            );

            if ($this->underline) {
                $s .= ' ' . $this->_dounderline(
                    $this->x + $dx, $this->y + 0.5 * $h + 0.3 * $this->FontSize, $txt
                );
            }

            if ($this->ColorFlag) {
                $s .= ' Q';
            }

            if ($link) {
                $this->Link(
                    $this->x + $dx, 
                    $this->y + 0.5 * $h - 0.5 * $this->FontSize, 
                    $this->GetStringWidth($txt), 
                    $this->FontSize, 
                    $link
                );
            }
        }

        if ($s) {
            $this->_out($s);
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

        if (!isset($this->CurrentFont)) {
            throw new InvalidArgumentException($this->fpdfError . 'No font has been set');
        }

        $cw = $this->CurrentFont['cw'];

        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
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
                    $this->_out('0 Tw');
                }

                $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);

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
                    $this->_out('0 Tw');
                }

                $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
            } else {

                if ($align == 'J') {
                    $this->ws = ($ns > 1) ? ($wmax - $ls) / 1000 * $this->FontSize / ($ns - 1) : 0;
                    $this->_out(sprintf('%.3F Tw', $this->ws * $this->k));
                }

                $this->Cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
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
            $this->_out('0 Tw');
        }

        if ($border && strpos($border, 'B') !== false) {
            $b .= 'B';
        }

        $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    public function Write(float $h, string $txt, int|string $link = ''): void
    {
        if (!isset($this->CurrentFont)) {
            throw new InvalidArgumentException($this->fpdfError . 'No font has been set');
        }

        $cw = $this->CurrentFont['cw'];
        $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
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

                $this->Cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', false, $link);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;

                if($nl == 1){

                    $this->x = $this->lMargin;
                    $w = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
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
                        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
                        $i++;
                        $nl++;
                        continue;
                    }

                    if($i == $j){
                        $i++;
                    }

                    $this->Cell($w, $h, substr($s, $j, $i - $j), 0,2, '', false, $link);

                }else {

                    $this->Cell($w, $h, substr($s, $j, $sep - $j),0,2, '', false, $link);
                    $i = $sep+1;
                }

                $sep = -1;
                $j = $i;
                $l = 0;

                if($nl == 1){

                    $this->x = $this->lMargin;
                    $w = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w -2 * $this->cMargin) * 1000 / $this->FontSize;
                }

                $nl++;

            } else{
                $i++;
            }
        }

        if ($i !== $j) {
            $this->Cell($l / 1000 * $this->FontSize, $h, substr($s, $j), 0, 0, '', false, $link);
        }
    }

    public function Ln($h=null): void
    {
        $this->x = $this->lMargin;
        $this->y += $h;

        if($h === null){
            $this->y += $this->lasth;
        }
    }

    public function Image(

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

            $mtd = '_parse' . $type;
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
                $this->y + $h > $this->PageBreakTrigger && 
                !$this->InHeader && 
                !$this->InFooter && 
                $this->AcceptPageBreak()

            ) {
                $x2 = $this->x;
                $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
                $this->x = $x2;
            }

            $y = $this->y;
            $this->y += $h;
        }

        if ($x === null) {
            $x = $this->x;
        }

        $this->_out(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', 
            $w * $this->k, $h * $this->k, 
            $x * $this->k, 
            ($this->h - ($y + $h)) * $this->k, $info['i'])
        );

        if ($link) {
            $this->Link($x, $y, $w, $h, $link);
        }
    }

    public function GetPageWidth()
    {
        return $this->w;
    }

    public function GetPageHeight()
    {
        return $this->h;
    }

    public function GetX(): float
    {
        return $this->x;
    }

    public function SetX(float $x): void
    {
        $this->x = $this->w + $x;

        if($x>=0){
            $this->x = $x;
        }
    }

    public function GetY(): float
    {
        return $this->y;
    }

    public function SetY(float $y, bool $resetX = true): void
    {
        $this->y = $this->h+$y;

        if($y>=0){
            $this->y = $y;
        }

        if($resetX){
            $this->x = $this->lMargin;
        }
    }

    public function SetXY($x, $y): void
    {
        $this->SetX($x);
        $this->SetY($y,false);
    }



    public function Output(

        string $dest = '', 
        string $name = '', 
        bool $isUTF8 = false

    ): string {

        $this->Close();

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
            $this->_checkoutput();
            header('Content-Type: application/pdf');
            $disposition = ($dest === 'I') ? 'inline' : 'attachment';
            header('Content-Disposition: ' . $disposition . '; ' . $this->_httpencode('filename', $name, $isUTF8));
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


    protected function _checkoutput()
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

    protected function _getpagesize(string|array $size): string|array
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


    protected function _beginpage(string $orientation, array|string $size, int $rotation): void
    {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->PageLinks[$this->page] = [];
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->FontFamily = '';

        // Check page size and orientation
        $orientation = $orientation === '' ? $this->DefOrientation : strtoupper($orientation[0]);
        $size = $size === '' ? $this->DefPageSize : $this->_getpagesize($size);

        if (
            $orientation !== $this->CurOrientation || 
            $size[0] !== $this->CurPageSize[0] || 
            $size[1] !== $this->CurPageSize[1])
        {
            $this->w = $size[1];
            $this->h = $size[0];

            if ($orientation === 'P') {
                $this->w = $size[0];
                $this->h = $size[1];
            }

            $this->wPt = $this->w * $this->k;
            $this->hPt = $this->h * $this->k;
            $this->PageBreakTrigger = $this->h - $this->bMargin;
            $this->CurOrientation = $orientation;
            $this->CurPageSize = $size;
        }

        if (
            $orientation !== $this->DefOrientation || 
            $size[0] !== $this->DefPageSize[0] || 
            $size[1] !== $this->DefPageSize[1]) 
        {
            $this->PageInfo[$this->page]['size'] = [$this->wPt, $this->hPt];
        }

        if ($rotation !== 0) {

            if ($rotation % 90 !== 0) {
                throw new InvalidArgumentException(
                    $this->fpdfError . 'Incorrect rotation value: ' . $rotation
                );
            }

            $this->PageInfo[$this->page]['rotation'] = $rotation;
        }

        $this->CurRotation = $rotation;
    }

    protected function _endpage()
    {
        $this->state = 1;
    }

    protected function _loadfont(string $path): array
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

    protected function _isascii(string $s): bool
    {
        $nb = strlen($s);
        for ($i = 0; $i < $nb; $i++) {
            if (ord($s[$i]) > 127) {
                return false;
            }
        }

        return true;
    }

    protected function _httpencode(string $param, string $value, bool $isUTF8): string
    {
        if ($this->_isascii($value)) {
            return $param . '="' . $value . '"';
        }

        if (!$isUTF8) {
            $value = $this->_UTF8encode($value);
        }

        return $param . "*=UTF-8''" . rawurlencode($value);
    }

    protected function _UTF8encode(string $s): string
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

    protected function _UTF8toUTF16(string $s): string
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

    protected function _escape(string $s): string
    {
        if (str_contains($s, '(') || str_contains($s, ')') || str_contains($s, '\\') || str_contains($s, "\r")) {
            return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $s);
        }
        return $s;
    }

    protected function _textstring($s)
    {
        if(!$this->_isascii($s))
            $s = $this->_UTF8toUTF16($s);
        return '('.$this->_escape($s).')';
    }

    protected function _dounderline($x, $y, $txt)
    {
        $up = $this->CurrentFont['up'];
        $ut = $this->CurrentFont['ut'];
        $w = $this->GetStringWidth($txt)+$this->ws*substr_count($txt,' ');
        return sprintf(
            '%.2F %.2F %.2F %.2F re f',
            $x * $this->k, ($this->h - ($y-$up/1000*$this->FontSize)) * $this->k,
            $w * $this->k, -$ut/1000 * $this->FontSizePt
        );
    }

    protected function _parsejpg($file)
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

    protected function _parsepng($file): array
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

        $info = $this->_parsepngstream($f,$file);
        fclose($f);

        return $info;
    }

    protected function _parsepngstream($f, string $file): array
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
            $this->WithAlpha = true;

            if($this->PDFVersion < '1.4'){
                $this->PDFVersion = '1.4';
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

        $info = $this->_parsepngstream($f, $file);
        fclose($f);

        return $info;
    }

    protected function _out(string $s): void
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

    protected function _put($s): void
    {
        $this->buffer .= $s."\n";
    }

    protected function _getoffset(): int
    {
        return strlen($this->buffer);
    }

    protected function _newobj(?int $n = null): void
    {
        if ($n === null) {
            $n = ++$this->n;
        }

        $this->offsets[$n] = $this->_getoffset();
        $this->_put($n . ' 0 obj');
    }

    protected function _putstream(string $data): void
    {
        $this->_put('stream');
        $this->_put($data);
        $this->_put('endstream');
    }

    protected function _putstreamobject(string $data): void
    {
        $entries = '';

        if ($this->compress) {
            $entries = '/Filter /FlateDecode ';
            $data = gzcompress($data);
        } 

        $entries .= '/Length ' . strlen($data);

        $this->_newobj();
        $this->_put('<<' . $entries . '>>');
        $this->_putstream($data);
        $this->_put('endobj');
    }

    protected function _putlinks(int $n): void
    {
        foreach ($this->PageLinks[$n] as $pl) {

            $this->_newobj();
            $rect = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
            $s = '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';

            if (is_string($pl[4])) {

                $s .= '/A <</S /URI /URI ' . $this->_textstring($pl[4]) . '>>>>';

            } else {

                $l = $this->links[$pl[4]];
                $h = $this->PageInfo[$l[0]]['size'][1] ?? 
                    (($this->DefOrientation === 'P') ? 
                    $this->DefPageSize[1] * $this->k : 
                    $this->DefPageSize[0] * $this->k);

                $s .= sprintf(
                    '/Dest [%d 0 R /XYZ 0 %.2F null]>>', 
                    $this->PageInfo[$l[0]]['n'], 
                    $h - $l[1] * $this->k
                );
            }

            $this->_put($s);
            $this->_put('endobj');
        }
    }

    protected function _putpage(int $n): void
    {
        $this->_newobj();
        $this->_put('<</Type /Page');
        $this->_put('/Parent 1 0 R');

        if (isset($this->PageInfo[$n]['size'])) {
            $this->_put(sprintf(
                '/MediaBox [0 0 %.2F %.2F]',
                $this->PageInfo[$n]['size'][0],
                $this->PageInfo[$n]['size'][1]
            ));
        }

        if (isset($this->PageInfo[$n]['rotation'])) {
            $this->_put('/Rotate ' . $this->PageInfo[$n]['rotation']);
        }

        $this->_put('/Resources 2 0 R');

        if (!empty($this->PageLinks[$n])) {

            $s = '/Annots [';

            foreach ($this->PageLinks[$n] as $pl) {
                $s .= $pl[5] . ' 0 R ';
            }

            $s .= ']';
            $this->_put($s);
        }

        if ($this->WithAlpha) {
            $this->_put('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
        }

        $this->_put('/Contents ' . ($this->n + 1) . ' 0 R>>');
        $this->_put('endobj');

        if (!empty($this->AliasNbPages)) {
            $this->pages[$n] = str_replace(
                $this->AliasNbPages, 
                (string) $this->page, $this->pages[$n]
            );
        }

        $this->_putstreamobject($this->pages[$n]);

        $this->_putlinks($n);
    }

    protected function _putpages(): void
    {
        $nb = $this->page;
        $n = $this->n;
        $w = $this->DefPageSize[1];
        $h = $this->DefPageSize[0];

        for($i=1;$i<=$nb;$i++){

            $this->PageInfo[$i]['n'] = ++$n;
            $n++;

            foreach($this->PageLinks[$i] as &$pl){
                $pl[5] = ++$n;
            }

            unset($pl);
        }

        for($i=1;$i<=$nb;$i++){
            $this->_putpage($i);
        }

        $this->_newobj(1);
        $this->_put('<</Type /Pages');
        $kids = '/Kids [';

        for($i=1;$i<=$nb;$i++){
            $kids .= $this->PageInfo[$i]['n'].' 0 R ';
        }

        $kids .= ']';
        $this->_put($kids);
        $this->_put('/Count '.$nb);

        if($this->DefOrientation=='P'){
            $w = $this->DefPageSize[0];
            $h = $this->DefPageSize[1];
        }

        $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]', $w*$this->k,$h*$this->k));
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _putfonts(): void
    {
        if (!empty($this->fontfiles) && is_array($this->fontfiles)) {

            foreach ($this->fontfiles as $file => $info) {
                // Font file embedding
                $this->_newobj();
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

                $this->_put('<</Length ' . strlen($font));

                if ($compressed) {
                    $this->_put('/Filter /FlateDecode');
                }

                $this->_put('/Length1 ' . $info['length1']);

                if (isset($info['length2'])) {
                    $this->_put('/Length2 ' . $info['length2'] . ' /Length3 0');
                }

                $this->_put('>>');
                $this->_putstream($font);
                $this->_put('endobj');
            }
        }

        foreach ($this->fonts as $k => $font) {
            // Encoding
            if (!empty($font['diff']) && !isset($this->encodings[$font['enc']])) {
                $this->_newobj();
                $this->_put(''
                    . '<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' 
                    . $font['diff'] . ']>>');

                $this->_put('endobj');
                $this->encodings[$font['enc']] = $this->n;
            }

            // ToUnicode CMap
            $cmapKey = $font['enc'] ?? $font['name'];

            if (!empty($font['uv']) && !isset($this->cmaps[$cmapKey])) {
                $cmap = $this->_tounicodecmap($font['uv']);
                $this->_putstreamobject($cmap);
                $this->cmaps[$cmapKey] = $this->n;
            }

            // Font object
            $this->fonts[$k]['n'] = $this->n + 1;
            $type = $font['type'];
            $name = !empty($font['subsetted']) ? 'AAAAAA+' . $font['name'] : $font['name'];

            if ($type === 'Core') {
                // Core font
                $this->_newobj();
                $this->_put('<</Type /Font');
                $this->_put('/BaseFont /' . $name);
                $this->_put('/Subtype /Type1');

                if ($name !== 'Symbol' && $name !== 'ZapfDingbats') {
                    $this->_put('/Encoding /WinAnsiEncoding');
                }

                if (!empty($font['uv'])) {
                    $this->_put('/ToUnicode ' . $this->cmaps[$cmapKey] . ' 0 R');
                }

                $this->_put('>>');
                $this->_put('endobj');

            } elseif ($type === 'Type1' || $type === 'TrueType') {
                // Additional Type1 or TrueType/OpenType font
                $this->_newobj();
                $this->_put('<</Type /Font');
                $this->_put('/BaseFont /' . $name);
                $this->_put('/Subtype /' . $type);
                $this->_put('/FirstChar 32 /LastChar 255');
                $this->_put('/Widths ' . ($this->n + 1) . ' 0 R');
                $this->_put('/FontDescriptor ' . ($this->n + 2) . ' 0 R');
                $this->_put('/Encoding ' . ($this->encodings[$font['enc']] ?? '/WinAnsiEncoding') . ' 0 R');

                if (!empty($font['uv'])) {
                    $this->_put('/ToUnicode ' . $this->cmaps[$cmapKey] . ' 0 R');
                }

                $this->_put('>>');
                $this->_put('endobj');

                // Widths
                $this->_newobj();
                $this->_put('[' . implode(' ', array_map(fn($i) => $font['cw'][chr($i)], range(32, 255))) . ']');
                $this->_put('endobj');

                // Descriptor
                $this->_newobj();
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

                $this->_put($s . '>>');
                $this->_put('endobj');

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

    protected function _putimages(): void
    {
        foreach(array_keys($this->images) as $file){

            $this->_putimage($this->images[$file]);
            unset($this->images[$file]['data']);
            unset($this->images[$file]['smask']);
        }
    }

    protected function _putimage(array &$info): void
    {
        $this->_newobj();
        $info['n'] = $this->n;

        // Definindo tipo e propriedades da imagem
        $this->_put('<</Type /XObject');
        $this->_put('/Subtype /Image');
        $this->_put('/Width ' . $info['w']);
        $this->_put('/Height ' . $info['h']);

        // Definindo a cor
        if ($info['cs'] === 'Indexed') {

            $this->_put(''
                . '/ColorSpace [/Indexed /DeviceRGB ' 
                . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->n + 1) . ' 0 R]'
            );

        } else {

            $this->_put('/ColorSpace /' . $info['cs']);

            if ($info['cs'] === 'DeviceCMYK') {
                $this->_put('/Decode [1 0 1 0 1 0 1 0]');
            }
        }

        // Definindo BitsPerComponent
        $this->_put('/BitsPerComponent ' . $info['bpc']);

        // Definindo o filtro
        if (isset($info['f'])) {
            $this->_put('/Filter /' . $info['f']);
        }

        // Definindo parâmetros de decodificação
        if (isset($info['dp'])) {
            $this->_put('/DecodeParms <<' . $info['dp'] . '>>');
        }

        // Definindo a máscara de transparência
        if (isset($info['trns']) && is_array($info['trns'])) {
            $trns = '';
            foreach ($info['trns'] as $value) {
                $trns .= "$value $value ";
            }
            $this->_put('/Mask [' . $trns . ']');
        }

        // Definindo o Soft Mask (máscara suave)
        if (isset($info['smask'])) {
            $this->_put('/SMask ' . ($this->n + 1) . ' 0 R');
        }

        // Comprimento dos dados da imagem
        $this->_put('/Length ' . strlen($info['data']) . '>>');
        $this->_putstream($info['data']);
        $this->_put('endobj');

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
            $this->_putstreamobject($info['pal']);
        }
    }

    protected function _putxobjectdict(): void
    {
        foreach($this->images as $image){
            $this->_put('/I'.$image['i'].' '.$image['n'].' 0 R');
        }
    }

    protected function _putresourcedict(): void
    {
        $this->_put('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_put('/Font <<');

        foreach($this->fonts as $font){
            $this->_put('/F'.$font['i'].' '.$font['n'].' 0 R');
        }

        $this->_put('>>');
        $this->_put('/XObject <<');
        $this->_putxobjectdict();
        $this->_put('>>');
    }

    protected function _putresources(): void
    {
        $this->_putfonts();
        $this->_putimages();
        // Resource dictionary
        $this->_newobj(2);
        $this->_put('<<');
        $this->_putresourcedict();
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _putinfo(): void
    {
        $date = @date('YmdHisO',$this->CreationDate);
        $this->metadata['CreationDate'] = 'D:'.substr($date,0,-2)."'".substr($date,-2)."'";

        foreach($this->metadata as $key=>$value){
            $this->_put('/'.$key.' '.$this->_textstring($value));
        }
    }

    protected function _putcatalog(): void
    {
        $n = $this->PageInfo[1]['n'];
        $this->_put('/Type /Catalog');
        $this->_put('/Pages 1 0 R');

        // Tratando o OpenAction baseado no ZoomMode
        switch ($this->ZoomMode) {
        case 'fullpage':
            $this->_put('/OpenAction [' . $n . ' 0 R /Fit]');
            break;
        case 'fullwidth':
            $this->_put('/OpenAction [' . $n . ' 0 R /FitH null]');
            break;
        case 'real':
            $this->_put('/OpenAction [' . $n . ' 0 R /XYZ null null 1]');
            break;
        default:
            if (is_numeric($this->ZoomMode)) {
                $this->_put(''
                    . '/OpenAction [' . $n . ' 0 R /XYZ null null ' 
                    . sprintf('%.2F', $this->ZoomMode / 100) . ']'
                );
            }

            break;
        }

        // Tratando o LayoutMode
        switch ($this->LayoutMode) {
        case 'single':
            $this->_put('/PageLayout /SinglePage');
            break;
        case 'continuous':
            $this->_put('/PageLayout /OneColumn');
            break;
        case 'two':
            $this->_put('/PageLayout /TwoColumnLeft');
            break;
        }
    }

    protected function _putheader(): void
    {
        $this->_put('%PDF-'.$this->PDFVersion);
    }

    protected function _puttrailer(): void
    {
        $this->_put('/Size '.($this->n+1));
        $this->_put('/Root '.$this->n.' 0 R');
        $this->_put('/Info '.($this->n-1).' 0 R');
    }

    protected function _enddoc(): void
    {
        $this->CreationDate = time();
        $this->_putheader();
        $this->_putpages();
        $this->_putresources();
        // Info
        $this->_newobj();
        $this->_put('<<');
        $this->_putinfo();
        $this->_put('>>');
        $this->_put('endobj');
        // Catalog
        $this->_newobj();
        $this->_put('<<');
        $this->_putcatalog();
        $this->_put('>>');
        $this->_put('endobj');
        // Cross-ref
        $offset = $this->_getoffset();
        $this->_put('xref');
        $this->_put('0 '.($this->n+1));
        $this->_put('0000000000 65535 f ');

        for($i=1;$i<=$this->n;$i++){
            $this->_put(sprintf('%010d 00000 n ',$this->offsets[$i]));
        }

        // Trailer
        $this->_put('trailer');
        $this->_put('<<');
        $this->_puttrailer();
        $this->_put('>>');
        $this->_put('startxref');
        $this->_put($offset);
        $this->_put('%%EOF');
        $this->state = 3;
    }
}

