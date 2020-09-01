<?php

class Viaf2Wiki {

  public function __construct ($id,$opts) {
    $this->base_url = 'https://viaf.org/viaf/';
    $this->format = '/viaf.json';
    $this->id = $id;
    $this->pairs = array();
    $this->setSites();
    $this->itemLabels = array();
    $this->quickstatements = '';
    $this->success = array();
    $this->failures = array();
    $this->warnings = array();
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

  public function getItemLabel () {
    if (array_key_exists('en', $this->itemLabels)) {
      return $this->itemLabels['en'];
    }
    else return $this->itemLabels[array_keys($this->itemLabels)[0]];
  }

  public function findPairs() {
    foreach ($this->obj->sources->source as $src) {
      //      print_r ($src->{'@nsid'});
      if (isset($src->{'#text'})) {
	  list($key,$val) = preg_split('/\|/', $src->{'#text'});
	  $this->pairs[$key]['val'] = $val;
	  $this->pairs[$key]['nsid'] = $src->{'@nsid'};
	}
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
      if ($item['predicate']['value'] == 'http://www.w3.org/2000/01/rdf-schema#label') {
	$lang = $item['object']['xml:lang'];
	$this->itemLabels[$lang] = $item['object']['value'];
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
    elseif (in_array($key, ['NLR','B2Q','SRP','PLWABN'])) {
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
    elseif ($key == 'RERO') {
      if (preg_match('/^A/',$this->pairs[$key]['val'])) {
	return '02-'.$this->pairs[$key]['val'];
      }
      else { 
	return '01-'.$this->pairs[$key]['val'];
      }
    }
    elseif ($key == 'VcBA ID') {
      if (preg_match('/(\d+)\_(\d+)/', $this->pairs[$key]['val'], $m)) {
	return ($m[1].'/'.$m[2]);
      }
    }
      //leave alone
    elseif (in_array($key, ['ERRR','GRATEVE','NTA','NII','SUDOC','BNE','NLI','BIBSYS','DNB','PLWABN', 'DBC', 'NKC', 'PTBNP','BNC','SELIBR'])) {
      return $this->pairs[$key]['val'];
    }
    else { return $this->pairs[$key]['val']; }
  }

  public function  validate($key,$val) {
      if (array_key_exists($key, $this->siteKeys)) {
	$label = $this->siteKeys[$key];


	if (in_array($this->sites->{$label}->pItem, $this->ids)) {
	  array_push($this->success, ['key' => $key, 'val' => $val, 'type' => 'SKIPPED', 'reason' => 'already in Wikidata']);
	  return false;
	}

	if (preg_match('/^'.$this->sites->{$label}->regex.'$/', $val)) {
	  //	  print_r($this->sites->{$label}
	  $this->quickstatements .= $this->q."\t".$this->sites->{$label}->pItem."\t"."\"".$val."\"\t". '/* '.$key.' = '.$label .' */'.PHP_EOL;

	}
	else { 
	  array_push($this->failures, ['key' => $key, 'val' => $val, 'type' => 'FAILED', 'reason' => 'format constraint']);
	}
      }
      else { 
	array_push($this->warnings, ['key' => $key, 'val' => $val, 'type' => 'SKIPPED', 'reason' => 'no formatting instructions']);
      }
    }
  
  
  private function setSites() {
    $this->sites = json_decode(file_get_contents('auths-formats.json'))->contents;
    $this->siteKeys = [
		       'BAV' => 'VcBA ID',
                       'BIBSYS' => 'NORAF ID',
		       'B2Q' => 'BanQ author ID',
		       'BNE' => 'Biblioteca Nacional de España ID',
		       'BNF' => 'Bibliothèque nationale de France ID',
		       'BNC' => 'CANTIC ID',
		       'DBC' => 'DBC author ID',
		       'DNB' => 'GND ID',
		       'ERRR' => 'ELNET ID',
		       'GRATEVE' => 'National Library of Greece ID',
		       'ISNI' => 'ISNI',
		       'LC' => 'Library of Congress authority ID',
		       'LNB' => 'LNB ID',
                       'NII' => 'CiNii author ID (books)',
		       'NKC' => 'NKCR AUT ID',
		       'NLA' => 'Libraries Australia ID',
                       'NLI' => 'NLI ID',
		       'NLR' => 'NLR ID',
		       'NTA' => 'Nationale Thesaurus voor Auteurs ID',
		       'NUKAT' => 'NUKAT ID',
		       'PLWABN' => 'PLWABN ID',
		       'PTBNP' => 'Portuguese National Library ID',
                       'RERO' => 'RERO ID',
		       'SELIBR' => 'SELIBR ID',
		       'SRP' => 'Syriac Biographical Dictionary ID',
		       'SUDOC' => 'IdRef ID'
		       //		       'SUDOC' => 'SUDOC authorities ID',
		       // 'W2Z' => 'NOT USED FOR Norwegian filmography ID',
		       ];
  }
  
}

