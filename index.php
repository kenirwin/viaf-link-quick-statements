<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
//$viaf = new Viaf2Wiki('51308314', ['use_local'=>false, 'q'=>'Q27517636'] );
$viaf = new Viaf2Wiki($_REQUEST['viaf'], ['use_local'=>false, 'q'=>$_REQUEST['q']] );
if (in_array('P214',$viaf->ids)) {
  $viaf->errors.= '# SKIPPED: already in Wikidata: VIAF : '.$viaf->id.PHP_EOL;
}
else { 
  print $viaf->q."\t"."P214"."\t"."\"".$viaf->id."\"\t/* VIAF*/".PHP_EOL;
}
foreach ($viaf->pairs as $key => $arr) {
  $val = $viaf->prep($key);
  $viaf->validate($key,$val);
} 
print $viaf->errors;
print '</pre>'.PHP_EOL;

class Viaf2Wiki {

  public function __construct ($id,$opts) {
    $this->errors = '';
    $this->base_url = 'https://viaf.org/viaf/';
    $this->format = '/viaf.json';
    $this->id = $id;
    $this->pairs = array();
    $this->setSites();
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
    elseif (in_array($key, ['NTA','NII','SUDOC','BNE','NLI','BIBSYS','DNB','PLWABN', 'DBC', 'NKC', 'BNC'])) {
      return $this->pairs[$key]['val'];
    }
    else { return $this->pairs[$key]['val']; }
  }

  public function  validate($key,$val) {
      if (array_key_exists($key, $this->siteKeys)) {
	$label = $this->siteKeys[$key];


	if (in_array($this->sites->{$label}->pItem, $this->ids)) {
	  $this->errors.= '# SKIPPED: already in Wikidata: '.$key.' : '.$val.PHP_EOL;
	  return false;
	}

	if (preg_match('/^'.$this->sites->{$label}->regex.'$/', $val)) {
	  print $this->q."\t".$this->sites->{$label}->pItem."\t"."\"".$val."\"\t". '/* '.$key.' */'.PHP_EOL;

	}
	else { 
	  $this->errors.= '# FAILED format constraint: '.$key.' : '.$val.PHP_EOL;
	}
      }
      else { 
	$this->errors .=  '# SKIPPED: no formatting instructions: '.$key.' : '.$val.PHP_EOL;
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
		       'SRP' => 'Syriac Biographical Dictionary ID',
		       'SUDOC' => 'SUDOC authorities ID',
		       ];
    
    /*
    $this->sites = array (
			  'BNF' => new stdClass(),
			  'NTA' => new stdClass(),
			  'NII' => new stdClass(),
			  'SUDOC' => new stdClass(),
			  'BNE' => new stdClass(),
			  'NLI' => new stdClass(),
			  'NUKAT' => new stdClass(),
			  'BIBSYS' => new stdClass(),
			  'RERO' => new stdClass(),
			  'DNB' => new stdClass(),
			  'PLWABN' => new stdClass(),
			  );

    $this->sites['LC']->regex = '((n|nb|nr|no|ns|sh|gf)([4-9][0-9]|00|20[0-1][0-9])[0-9]{6})';
    $this->sites['LC']->property = 'P244';

    $this->sites['ISNI']->regex = '(0000 000[0-4] [0-9]{4} [0-9]{3}[0-9X]|)';
    $this->sites['ISNI']->property = 'P213';

    $this->sites['BNF']->regex = '(\d{8}[0-9bcdfghjkmnpqrstvwxz]|)';
    $this->sites['BNF']->property = 'P268';

    $this->sites['NTA']->regex = '\d{8}(\d|X)';
    $this->sites['NTA']->property = 'P1006';

    $this->sites['NII']->regex = 'DA\d{7}[\dX]';
    $this->sites['NII']->property = 'P271'; 

    $this->sites['SUDOC']->regex = '(\d{8}[\dX]|)';
    $this->sites['SUDOC']->property = 'P269';

    $this->sites['BNE']->regex = '(XX|FF|a)\d{4,7}|(bima|bimo|bica|bis[eo]|bivi|Mise|Mimo|Mima)\d{10}|';
    $this->sites['BNE']->property = 'P950';

    $this->sites['NLI']->regex = '\d{9}';
    $this->sites['NLI']->property = 'P949';

    $this->sites['NUKAT']->regex = 'n(9[3-9]|0[0-2]|200[2-9]|201\d)\d{6}';
    $this->sites['NUKAT']->property = 'P1207';

    $this->sites['BIBSYS']->regex = '[1-9](\d{0,8}|\d{12})';
    $this->sites['BIBSYS']->property = 'P1015';

    $this->sites['RERO']->regex = '0[1-2]-[A-Z|0-9]{1,10}';
    $this->sites['RERO']->property = 'P3065';

    $this->sites['DNB']->regex = '|(1[01]?\d{7}[0-9X]|[47]\d{6}-\d|[1-9]\d{0,7}-[0-9X]|3\d{7}[0-9X])';
    $this->sites['DNB']->property = 'P227';

    $this->sites['PLWABN']->regex = 'A[0-9]{7}[0-9X]';
    $this->sites['PLWABN']->property = 'P1695';
   */
  }
 
  }

?>