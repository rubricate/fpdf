<?php

namespace Rubricate\Fpdf;

use InvalidArgumentException;
use Rubricate\Fpdf\Trait\PropertyTraitFpdf;

abstract class AbstractFileFpdf
{
    use PropertyTraitFpdf;

    abstract public function header();
    abstract public function footer();

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

