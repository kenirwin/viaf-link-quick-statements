<html>
<head>
<title>VIAF links to QuickStatements</title>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('bootstrap.html');
?>

<style>
#was-copy-link { display:none; border: 2px solid darkgreen; color: darkgreen; width: 8em; padding: 0.5em; text-align: center; font-weight: bold; font-family: Calibri, Helvetica, sans-serif;}
.copying { background-color: yellow; }
.alert { margin-bottom: .5rem;     padding: .25rem 1rem; }
#copy-link { margin-bottom: 1rem; }
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
      var statements = $('#quickies').text();
      if (statements.length > 0) {
	//	$('#copy-link').toggle();
	$('#copy-link').click(function() {
	    copyToClipboard('#quickies');
	    $(this).removeClass('btn-outline-success');
	    $(this).addClass('btn-warning');
	    $(this).text('Copied Statements');
	  });
      }
    });


</script>
</head>
<body class="container">

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
elseif ($_REQUEST['viaf'] == '') {
  print('<div class="alert alert-danger">No VIAF ID detected in record</div>');
  die('</body></html>');
}
/*
print '<pre>'.PHP_EOL;
print_r($_REQUEST);
print '</pre>'.PHP_EOL;
*/

$viaf = new Viaf2Wiki($_REQUEST['viaf'], ['use_local'=>false, 'q'=>$_REQUEST['q']] );

print '<h2>'.$viaf->getItemLabel().' : '. $viaf->q . '</h2>'.PHP_EOL;
print '<div id="copy-link" class="btn btn-outline-success">Copy Statements</div>'.PHP_EOL;
print '<div id="quickies">'.PHP_EOL;
print '<pre>';
if (in_array('P214',$viaf->ids)) {
  array_push($viaf->success, ['type'=>'SKIPPED', 'reason'=>'already in Wikidata', 'key'=>'VIAF', 'val' =>$viaf->id]);
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

try {
  $msg_classes = array("failures"=>"danger","warnings"=>"warning","success"=>"success"); 
  foreach ($msg_classes as $cat => $bsClass) {
    foreach($viaf->$cat as $err) {
      print '<div class="alert alert-'.$bsClass.'">'.$err['type'].': '.$err['reason']. ', ' .$viaf->q .', '. $err['key'] .', '. $err['val'].'</div>'.PHP_EOL;
      if ($cat != 'success') { 
	$stmt = $db->prepare('INSERT INTO error_log(error_type,error_message,q,viaf_id,code,value) values (?,?,?,?,?,?)');
	$stmt->execute([$err['type'], $err['reason'], $viaf->q, $viaf->id, $err['key'], $err['val']]);
      }
    }


  }




} catch (PDOException $e) {
  print ($e->getMessage());
  }

?>
</body>
</html>