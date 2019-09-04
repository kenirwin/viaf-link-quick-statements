<html>
<head>
<title>VIAF links to QuickStatements</title>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<style>
#copy-link { display:none; border: 2px solid darkgreen; color: darkgreen; width: 8em; padding: 0.5em;}
.copying { background-color: yellow; }
</style>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  <script>
  function copyToClipboard(element) {
  var $temp = $("<textarea>");
  $("body").append($temp);
  $temp.val($(element).text()).select();
  document.execCommand("copy");
  $temp.remove();
}

  $(document).ready(function() {
      var statements = $('#quickies pre').text();
      if (statements.length > 0) {
	$('#copy-link').toggle();
	$('#copy-link').click(function() {
	    copyToClipboard('#quickies');
	    $(this).addClass('copying');
	    setTimeout(function () {
		$('#copy-link').removeClass('copying');
	      }, 500);
	  });
      }
    });


</script>


<?
include ('config.php');
require ('Viaf2Wiki.class.php');
try {
  $db = new PDO(DSN, USER, PASS);
} catch (PDOException $e) {
  print_r($e->getMessage);
  }

print '<h1>VIAF links to QuickStatements</h1>';
print '<p><a href="https://tools.wmflabs.org/quickstatements/#/batch" target="qs">QuickStatements</a>'.PHP_EOL;
  print '<form>';
  print '<label for="q">Q:</label><input type="text" name="q" /><br />'.PHP_EOL;
  print '<label for="viaf">VIAF:</label><input type="text" name="viaf" id="viaf" /><br />'.PHP_EOL;
  print '<input type="submit" />'.PHP_EOL;
  print '</form>';
if (! array_key_exists('viaf',$_REQUEST) && (!array_key_exists('q',$_REQUEST))) {
  die();
}

/*
print '<pre>'.PHP_EOL;
print_r($_REQUEST);
print '</pre>'.PHP_EOL;
*/

$viaf = new Viaf2Wiki($_REQUEST['viaf'], ['use_local'=>false, 'q'=>$_REQUEST['q']] );

print '<div id="copy-link">Copy Statements</div>'.PHP_EOL;
print '<div id="quickies">'.PHP_EOL;
print '<pre>'.PHP_EOL;
if (in_array('P214',$viaf->ids)) {
  array_push($viaf->errors, ['type'=>'SKIPPED', 'reason'=>'already in Wikidata', 'key'=>'VIAF', 'val' =>$viaf->id]);
}
else { 
  print $viaf->q."\t"."P214"."\t"."\"".$viaf->id."\"\t/* VIAF*/".PHP_EOL;
}
foreach ($viaf->pairs as $key => $arr) {
  if ($key != 'WKP') { //skip wikidata reference 
    $val = $viaf->prep($key);
    $viaf->validate($key,$val);
  }
} 
print $viaf->quickstatements;
print '</pre>';
print '</div>';

print '<pre>'.PHP_EOL;

try {
  foreach($viaf->errors as $err) {
    if (! $err['reason'] == 'already in Wikidata') {
      $stmt = $db->prepare('INSERT INTO error_log(error_type,error_message,q,viaf_id,code,value) values (?,?,?,?,?,?)');
      $stmt->execute([$err['type'], $err['reason'], $viaf->q, $viaf->id, $err['key'], $err['val']]);
    }
    print '# '.$err['type'].': '.$err['reason']. ', ' .$viaf->q .', '. $err['key'] .', '. $err['val'].PHP_EOL;
  }

} catch (PDOException $e) {
  print ($e->getMessage());
  }

print '</pre>'.PHP_EOL;

?>