<?php

namespace Rubricate\Fpdf\Trait;

trait PropertyTraitFpdf
{
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
}

