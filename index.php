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
      var statements = $('#quickies').text();
      if (statements.length > 0) {
	$('#copy-link').toggle();
	$('#copy-link').click(function() {
	    copyToClipboard('#quickies');
	    $(this).addClass('copying');
	    setTimeout(function () {
		$('#copy-link').removeClass('copying');
	      }, 2000);
	  });
      }
    });


</script>


<?
include ('config.php');
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

print '<pre>'.PHP_EOL;
print_r($_REQUEST);
print '</pre>'.PHP_EOL;

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

class Viaf2Wiki {

  public function __construct ($id,$opts) {
    $this->base_url = 'https://viaf.org/viaf/';
    $this->format = '/viaf.json';
    $this->id = $id;
    $this->pairs = array();
    $this->setSites();
    $this->errors = array();
    if (array_key_exists('use_local',$opts) && ($opts['use_local'] == true)) {
      $this->local = true;
    }
    else {$this->local = false; }
    if (array_key_exists('q',$opts)) {
      $this->q = $opts['q'];
    }

    $this->getData(); //creates $this->obj
    $this->findPairs();
    $this->getExistingIds();
  }

  private function getData() {
    if ($this->local === true ) {
      $json =  file_get_contents($this->id.'.json');
    }
    else {
      // create curl resource 
      $ch = curl_init(); 
      
      $url = $this->base_url . $this->id . $this->format;
      // set url 
      curl_setopt($ch, CURLOPT_URL, $url); 
      
      //return the transfer as a string 
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
      
      // $output contains the output string 
      $json = curl_exec($ch); 
      
      // close curl resource to free up system resources 
      curl_close($ch);      
    }
    $this->obj = json_decode($json);
  }
  
  public function findPairs() {
    foreach ($this->obj->sources->source as $src) {
      //      print_r ($src->{'@nsid'});
      list($key,$val) = preg_split('/\|/', $src->{'#text'});
      $this->pairs[$key]['val'] = $val;
      $this->pairs[$key]['nsid'] = $src->{'@nsid'};
    }
  }

  public function getExistingIds() {
    include('SPARQLQueryDispatcher.class.php');
    
    $endpointUrl = 'https://query.wikidata.org/sparql';
    $sparqlQueryString = 'SELECT ?predicate ?object WHERE { wd:'. $this->q.' ?predicate ?object }';
    
    $queryDispatcher = new SPARQLQueryDispatcher($endpointUrl);
    $queryResult = $queryDispatcher->query($sparqlQueryString);
    
    //var_dump ($queryResult);
    $props = array();
    
    $arr = $queryResult['results']['bindings'];
    
    foreach ($arr as $key=>$item) {
      $prop = $item['predicate']['value'];
      if (preg_match('/\/(P\d+)$/', $prop, $m)) {
    array_push($props,$m[1]);
      }
    }
    $this->ids = $props;
  }
  
  public function prep($key) {
    // remove spaces
    if (in_array($key,['LC','NUKAT'])) {
      return preg_replace('/ /','',$this->pairs[$key]['val']);
    }

    // just grab the numbers
    elseif (in_array($key, ['NLR','B2Q','SRP'])) {
      if (preg_match('/(\d+)/', $this->pairs[$key]['val'],$m)) {
	return $m[1];
      }
    }
    
    //delete leading zeros 
    elseif (in_array($key, ['NLA'])) {
      if (preg_match('/^0+(\d+)/', $this->pairs[$key]['val'], $m)) {
	return $m[1];
      }
    }

    elseif ($key == 'ISNI') {
      if (preg_match('/(\d\d\d\d)(\d\d\d\d)(\d\d\d\d)(\d\d\d.)/',$this->pairs[$key]['val'],$m)) {
	return $m[1].' '.$m[2].' '.$m[3].' '.$m[4];
      }
    }
    elseif ($key == 'BNF') {
      if (preg_match('/cb(\d{8}.)/',$this->pairs[$key]['nsid'], $m)) {
	return $m[1];
      }
    }
    elseif ($key == 'LNB') {
      if (preg_match('/(\d{9})/', $this->pairs[$key]['val'], $m)) {
	return $m[1];
      }
    }
      //leave alone
    elseif (in_array($key, ['NTA','NII','SUDOC','BNE','NLI','BIBSYS','DNB','PLWABN', 'DBC', 'NKC', 'BNC','SELIBR'])) {
      return $this->pairs[$key]['val'];
    }
    else { return $this->pairs[$key]['val']; }
  }

  public function  validate($key,$val) {
      if (array_key_exists($key, $this->siteKeys)) {
	$label = $this->siteKeys[$key];


	if (in_array($this->sites->{$label}->pItem, $this->ids)) {
	  array_push($this->errors, ['key' => $key, 'val' => $val, 'type' => 'SKIPPED', 'reason' => 'already in Wikidata']);
	  return false;
	}

	if (preg_match('/^'.$this->sites->{$label}->regex.'$/', $val)) {
	  print $this->q."\t".$this->sites->{$label}->pItem."\t"."\"".$val."\"\t". '/* '.$key.' */'.PHP_EOL;

	}
	else { 
	  array_push($this->errors, ['key' => $key, 'val' => $val, 'type' => 'FAILED', 'reason' => 'format constraint']);
	}
      }
      else { 
	array_push($this->errors, ['key' => $key, 'val' => $val, 'type' => 'SKIPPED', 'reason' => 'no formatting instructions']);
      }
    }
  
  
  private function setSites() {
    $this->sites = json_decode(file_get_contents('auths-formats.json'))->contents;
    $this->siteKeys = [
                       'BIBSYS' => 'BIBSYS ID',
		       'B2Q' => 'BanQ author ID',
		       'BNE' => 'BNE ID',
		       'BNF' => 'BnF ID',
		       'BNC' => 'CANTIC ID',
		       'DBC' => 'DBC author ID',
		       'DNB' => 'GND ID',
		       'ISNI' => 'ISNI',
		       'LC' => 'Library of Congress authority ID',
		       'LNB' => 'LNB ID',
                       'NII' => 'CiNii author ID (books)',
		       'NKC' => 'NKCR AUT ID',
		       'NLA' => 'NLA ID',
                       'NLI' => 'NLI ID',
		       'NLR' => 'NLR ID',
		       'NTA' => 'NTA ID',
		       'NUKAT' => 'NUKAT ID',
                       'RERO' => 'RERO ID',
		       'SELIBR' => 'SELIBR ID',
		       'SRP' => 'Syriac Biographical Dictionary ID',
		       'SUDOC' => 'SUDOC authorities ID',
		       // 'W2Z' => 'NOT USED FOR Norwegian filmography ID',
		       ];
  }
  
}

?>