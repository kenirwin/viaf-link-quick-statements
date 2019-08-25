<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (! array_key_exists('viaf',$_REQUEST) && (!array_key_exists('q',$_REQUEST))) {
  print '<form>';
  print '<label for="q">Q:</label><input type="text" name="q" /><br />'.PHP_EOL;
  print '<label for="viaf">VIAF:</label><input type="text" name="viaf" id="viaf" /><br />'.PHP_EOL;
  print '<input type="submit" />'.PHP_EOL;
  print '</form>';
  die();
}


header('Content-type: text/plain');
print_r($_REQUEST);
//$viaf = new Viaf2Wiki('51308314', ['use_local'=>false, 'q'=>'Q27517636'] );
$viaf = new Viaf2Wiki($_REQUEST['viaf'], ['use_local'=>false, 'q'=>$_REQUEST['q']] );
print $viaf->q."\t"."P214"."\t"."\"".$viaf->id."\"".PHP_EOL;
foreach ($viaf->pairs as $key => $arr) {
  $val = $viaf->prep($key);
  $viaf->validate($key,$val);
} 
print $viaf->errors;

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

  public function prep($key) {
    // remove spaces
    if (in_array($key,['LC','NUKAT'])) {
      return preg_replace('/ /','',$this->pairs[$key]['val']);
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
      //leave alone
    elseif (in_array($key, ['NTA','NII','SUDOC','BNE','NLI','BIBSYS','DNB','PLWABN'])) {
      return $this->pairs[$key]['val'];
    }
    else { return $this->pairs[$key]['val']; }
  }

  public function  validate($key,$val) {
    if (array_key_exists($key, $this->siteKeys)) {
      $label = $this->siteKeys[$key];
      if (preg_match('/^'.$this->sites->{$label}->regex.'$/', $val)) {
	print $this->q."\t".$this->sites->{$label}->pItem."\t"."\"".$val."\"\t". '/* '.$key.' */'.PHP_EOL;
      }
      else { 
	$this->errors.= '# FAILED format constraint: '.$key.' : '.$val.PHP_EOL;
      }
    }
    else { 
      $this->errors .=  '#SKIPPED no formatting instructions: '.$key.' : '.$val.PHP_EOL;
    }
  }

  private function setSites() {
    $this->sites = json_decode(file_get_contents('auths-formats.json'))->contents;
    $this->siteKeys = [
		       "LC" => "Library of Congress authority ID",
		       "ISNI" => "ISNI",
		       'BNF' => "BnF ID",
		       'NTA' => "NTA ID",
                       'NII' => "CiNii author ID (books)",
                       'NLI' => "NLI ID",
                       'BIBSYS' => "BIBSYS ID",
                       'RERO' => "RERO ID",
		       'NUKAT' => 'NUKAT ID',
		       'SUDOC' => 'SUDOC authorities ID',
		       'NLA' => 'NLA ID',
		       'DNB' => 'GND ID',
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