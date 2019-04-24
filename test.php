<?php

  require_once(__DIR__ . '/AnsiToHtml.php');

  $str = file_get_contents('./sample.ans');
  $a2h = new AnsiToHtml($str, 160);
  $a2h->parse();
  $html = $a2h->getHtml();
  file_put_contents('./sample.html', $html);
  
?>
