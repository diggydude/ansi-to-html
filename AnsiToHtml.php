<?php

  require_once(__DIR__ . '/isInRange.php');

  class AnsiToHtml
  {

    const ESCAPE_CHARACTER      = "\x1B";
    const STATE_INDETERMINATE   = 0;
    const STATE_PARSING_COMMAND = 1;
    const STATE_PARSING_GLYPHS  = 2;

    protected static

      $cssAttrs = array(
                    0 => 'font-weight: normal;',
                    1 => 'font-weight: bold;',
                   30 => ' color: #000000;',
                   31 => ' color: #ff0000;',
                   32 => ' color: #00ff00;',
                   33 => ' color: #ffff00;',
                   34 => ' color: #0000ff;',
                   35 => ' color: #ff00ff;',
                   36 => ' color: #00ffff;',
                   37 => ' color: #ffffff;',
                   39 => ' color: #555555;',
                   40 => ' background-color: #000000;',
                   41 => ' background-color: #ff0000;',
                   42 => ' background-color: #00ff00;',
                   43 => ' background-color: #ffff00;',
                   44 => ' background-color: #0000ff;',
                   45 => ' background-color: #ff00ff;',
                   46 => ' background-color: #00ffff;',
                   47 => ' background-color: #ffffff;',
                   49 => ' background-color: #000000;'
                 );

    protected

      $inputStr,
      $state,
      $tagIsOpen,
      $currWeight,
      $currFgColor,
      $currBgColor,
      $glyphCount,
      $buffer,
      $outputStr;

    public function __construct($inputStr, $columns = 80)
    {
      $this->inputStr    = $inputStr;
      $this->columns     = $columns;
      $this->state       = self::STATE_INDETERMINATE;
      $this->tagIsOpen   = false;
      $this->currWeight  = 0;
      $this->currFgColor = 39;
      $this->currBgColor = 49;
      $this->glyphCount  = 0;
      $this->buffer      = "";
      $this->outputStr   = "";
    } // __construct

    public function parse()
    {
      while (strlen($this->inputStr) > 0) {
        $char = $this->eatChar();
        if (strlen($this->buffer) == 0) {
          if ($char == self::ESCAPE_CHARACTER) {
            $this->state  = self::STATE_PARSING_COMMAND;
            $this->buffer = $char;
            continue;
          }
          $this->state  = self::STATE_PARSING_GLYPHS;
          $this->buffer = $char;
          $this->glyphCount++;
          if ($this->glyphCount == $this->columns) {
            $this->outputStr .= "<br />";
            $this->glyphCount = 0;
          }
          continue;
        }
        if ($this->state == self::STATE_PARSING_COMMAND) {
          $this->buffer .= $char;
          if (!$this->cmdCompleted()) {
            continue;
          }
          if ($this->isAttrCommand()) {
            if ($this->tagIsOpen) {
              $this->outputStr .= $this->buildCloseTag();
              $this->tagIsOpen  = false;
            }
            $this->parseAttrs();
            $this->outputStr .= $this->buildOpenTag();
            $this->buffer     = "";
            $this->tagIsOpen  = true;
            $this->state      = self::STATE_INDETERMINATE;
            continue;
          }
          if ($this->isSpaceSeqCommand()) {
            $numSpaces        = $this->parseSpaceCount();
            $this->outputStr .= $this->buildSpaceSeq($numSpaces);
            $this->buffer     = "";
            $this->state      = self::STATE_INDETERMINATE;
            continue;
          }
        }
        if ($this->state == self::STATE_PARSING_GLYPHS) {
          if ($char == self::ESCAPE_CHARACTER) {
            $entities = self::stringToHtmlEntities($this->buffer);
            for ($i = 0; $i < count($entities); $i++) {
              $this->outputStr .= $entities[$i];
              if ($entities[$i] == "<br />") {
                $this->glyphCount = 0;
              }
              else {
                $this->glyphCount++;
                if ($this->glyphCount == $this->columns) {
                  $this->outputStr .= "<br />";
                  $this->glyphCount = 0;
                }
              }
            }
            $this->buffer = $char;
            $this->state  = self::STATE_PARSING_COMMAND;
            continue;
          }
          $this->buffer .= $char;
          $this->glyphCount++;
          if ($this->glyphCount == $this->columns) {
            $this->outputStr .= "<br />";
            $this->glyphCount = 0;
          }
          continue;
        }
      }
      $this->outputStr = "<div style=\"font-family: courier new;\">0123456789012345678901234567890123456789012345678901234567890123456789</div>"
                       . "<div style=\"font-family: courier new; background-color: #000000;\">" . $this->outputStr . "</div>";
    } // parse

    protected function eatChar()
    {
      $char = substr($this->inputStr, 0, 1);
      $this->inputStr = substr($this->inputStr, 1);
      return $char;
    } // eatChar

    protected function cmdCompleted()
    {
      $char   = substr($this->buffer, strlen($this->buffer) - 1, 1);
      $result = (($char == "m") || ($char == "C"));
      return $result;
    } // cmdCompleted

    protected function isAttrCommand()
    {
      $pattern = "/^\e\[[\d|;]+m$/";
      $result  = preg_match($pattern, $this->buffer, $matches);
      return $result;
    } // isAttrCommand

    protected function isSpaceSeqCommand()
    {
      $pattern = "/^\e\[\d+C$/";
      $result  = preg_match($pattern, $this->buffer, $matches);
      return $result;
    } // isSpaceSeqCommand

    protected function parseAttrs()
    {
      $pattern = "/^\e\[([\d|;]+)m$/";
      preg_match($pattern, $this->buffer, $matches);
      $attrs = explode(";", $matches[1]);
      foreach ($attrs as $attr) {
        $attr = intval($attr);
        if (isInRange($attr, 0, 1)) {
          if ($attr == 0) {
            $this->currWeight  = $attr;
            $this->currFgColor = 39;
            $this->currBgColor = 49;
            continue;
          }
          $this->currWeight = $attr;
          continue;
        }
        if (isInRange($attr, 30, 37)) {
          $this->currFgColor = $attr;
          continue;
        }
        if (isInRange($attr, 40, 47)) {
          $this->currBgColor = $attr;
          continue;
        }
      }
    } // parseAttrs

    protected function parseSpaceCount()
    {
      $pattern = "/^\e\[(\d+)C$/";
      preg_match($pattern, $this->buffer, $matches);
      $count = intval($matches[1]);
      return $count;
    } // parseSpaceCount

    protected function buildOpenTag()
    {
      $weight  = self::$cssAttrs[$this->currWeight];
      $fgColor = self::$cssAttrs[$this->currFgColor];
      $bgColor = self::$cssAttrs[$this->currBgColor];
      return "<span style=\"" . $weight . $fgColor . $bgColor . "\">";
    } // buildOpenTag

    protected function buildCloseTag()
    {
      return "</span>";
    } // buildCloseTag

    protected function buildSpaceSeq($numSpaces)
    {
      $seq = "";
      for ($i = 0; $i < $numSpaces; $i++) {
        $seq .= "&nbsp;";
        $this->glyphCount++;
        if ($this->glyphCount == $this->columns) {
          $seq .= "<br />";
          $this->glyphCount = 0;
        }
      }
      return $seq;
    } // buildSpaceSeq

    public function getHtml()
    {
      return $this->outputStr;
    } // getHtml

    public static function stringToHtmlEntities($str)
    {
      $lookup = function($n)
      {
        $map = array(
          128 => '&#0199;',
          129 => '&#0252;',
          130 => '&#0233;',
          131 => '&#0226;',
          132 => '&#0228;',
          133 => '&#0224;',
          134 => '&#0229;',
          135 => '&#0231;',
          136 => '&#0234;',
          137 => '&#0235;',
          138 => '&#0232;',
          139 => '&#0239;',
          140 => '&#0238;',
          141 => '&#0236;',
          142 => '&#0196;',
          143 => '&#0197;',
          144 => '&#0201;',
          145 => '&#0230;',
          146 => '&#0198;',
          147 => '&#0244;',
          148 => '&#0246;',
          149 => '&#0242;',
          150 => '&#0251;',
          151 => '&#0249;',
          152 => '&#0255;',
          153 => '&#0214;',
          154 => '&#0220;',
          155 => '&#0162;',
          156 => '&#0163;',
          157 => '&#0165;',
          158 => '&#8359;',
          159 => '&#0402;',
          160 => '&#0225;',
          161 => '&#0237;',
          162 => '&#0243;',
          163 => '&#0250;',
          164 => '&#0241;',
          165 => '&#0209;',
          166 => '&#0170;',
          167 => '&#0186;',
          168 => '&#0191;',
          169 => '&#8976;',
          170 => '&#0172;',
          171 => '&#0189;',
          172 => '&#0188;',
          173 => '&#0161;',
          174 => '&#0171;',
          175 => '&#0187;',
          176 => '&#9617;',
          177 => '&#9618;',
          178 => '&#9619;',
          179 => '&#9474;',
          180 => '&#9508;',
          181 => '&#9569;',
          182 => '&#9570;',
          183 => '&#9558;',
          184 => '&#9557;',
          185 => '&#9571;',
          186 => '&#9553;',
          187 => '&#9559;',
          188 => '&#9565;',
          189 => '&#9564;',
          190 => '&#9563;',
          191 => '&#9488;',
          192 => '&#9492;',
          193 => '&#9524;',
          194 => '&#9516;',
          195 => '&#9500;',
          196 => '&#9472;',
          197 => '&#9532;',
          198 => '&#9566;',
          199 => '&#9567;',
          200 => '&#9562;',
          201 => '&#9556;',
          202 => '&#9577;',
          203 => '&#9574;',
          204 => '&#9568;',
          205 => '&#9552;',
          206 => '&#9580;',
          207 => '&#9575;',
          208 => '&#9576;',
          209 => '&#9572;',
          210 => '&#9573;',
          211 => '&#9561;',
          212 => '&#9560;',
          213 => '&#9554;',
          214 => '&#9555;',
          215 => '&#9579;',
          216 => '&#9578;',
          217 => '&#9496;',
          218 => '&#9484;',
          219 => '&#9608;',
          220 => '&#9604;',
          221 => '&#9612;',
          222 => '&#9616;',
          223 => '&#9600;',
          224 => '&#0945;',
          225 => '&#0223;',
          226 => '&#0915;',
          227 => '&#0960;',
          228 => '&#0931;',
          229 => '&#0963;',
          230 => '&#0181;',
          231 => '&#0964;',
          232 => '&#0934;',
          233 => '&#0920;',
          234 => '&#0937;',
          235 => '&#0948;',
          236 => '&#8734;',
          237 => '&#0966;',
          238 => '&#0949;',
          239 => '&#8745;',
          240 => '&#8801;',
          241 => '&#0177;',
          242 => '&#8805;',
          243 => '&#8804;',
          244 => '&#8992;',
          245 => '&#8993;',
          246 => '&#0247;',
          247 => '&#8776;',
          248 => '&#0176;',
          249 => '&#8729;',
          250 => '&#0183;',
          251 => '&#8730;',
          252 => '&#8319;',
          253 => '&#0178;',
          254 => '&#9632;',
          255 => '&#0160;'
        );
        if ($n == 13) {
          return "<br />";
        }
        if (isInRange($n, 0, 31)) { // || ($n == 127)) {
          return "";
        }
        if ($n == 32) {
          return "&nbsp;";
        }
        return ($n < 128) ? chr($n) : $map[$n];
      };
    
      $str = mb_convert_encoding($str, 'UTF-32', 'Windows-1252');
      $t = unpack("N*", $str);
      $t = array_map($lookup, $t);
      return array_values(array_filter($t));
    } // stringToHtmlEntities

  } // AnsiToHtml

  $str = file_get_contents('./sample.ans');
  ob_start();
  $a2h = new AnsiToHtml($str, 161);
  $a2h->parse();
  $dump = ob_get_clean();
  file_put_contents('./dump.txt', $dump);
  $html = $a2h->getHtml();
  file_put_contents('./sample.html', $html);

?>
