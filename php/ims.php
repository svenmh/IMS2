<?php namespace IMS;
require_once('pubmed.php');

global $errors_reported;
$errors_reported=FALSE;
global $errors;
$errors=[];
function divert_errors(){
  register_shutdown_function(function(){
      global $errors_reported;
      if(!$errors_reported){
	print json_encode(['messages'=>[error_get_last()]]);
      }
    });
  set_error_handler(function($errno,$errstr,$filename,$linenum,$vars){
      global $errors;
      // hopefully this is the same as error_get_last() produces.
      $errors[]=
	[
	 'type'=>$errno,
	 'message'=>htmlentities($errstr),
	 'file'=>$filename,
	 'line'=>$linenum
	 ];
      return FALSE;
    },E_ALL);
  error_reporting(0);
  trigger_error("Messaging On");
}


class config
{
  private $path;
  function __construct($json){
    $this->path=realpath(dirname(__FILE__).'/'.$json);
    $this->config=json_decode(file_get_contents($this->path));
  }

  public $pdos=[];
  function pdo($db){
    if(!isset($this->pdos[$db])){
      $c=$this->config->dbs->$db;
      $this->pdos[$db]=new \PDO('mysql:host=localhost;dbname='.$c->db,
			      $c->user,$c->passwd);
    }
    return $this->pdos[$db];
  }

  function html_head(){
    $out=[];
    foreach($this->config->css as $css){
      $out[]=sprintf('<link href="%s" rel="stylesheet">',$css);
    }
    foreach($this->config->js as $js){
      $out[]=sprintf('<script src="%s"></script>',$js);
    }
    return join("\n",$out);
  }

  function now(){
    return date("c");
  }
  function pubmed_update($cur){
    if(NULL==$cur){
      return TRUE;
    }
    return date($cur) <= date($this->config->pubmed_update);
  }
}




class _Table
{
  const LIMIT=5;
  const DB='ims';

  public function __construct($cfg,$qs){
    $this->cfg=$cfg;
    $this->qs=$qs;
  }
  public function limit(){
    return isset($this->cfg->config->limit) ?
      $this->cfg->config->limit             :
      self::LIMIT                           ;
  }

  public function pdo(){
    $c=get_called_class();
    return $this->cfg->pdo($c::DB);
  }

  public function update($pk,$to){
    $c=get_called_class();
    $dbh=$this->pdo();
    $sql='UPDATE ' . $c::TABLE . ' SET ';
    $first=TRUE;
    foreach($to as $col=>$data){
      if($first){
	$first=FALSE;
      }else{
	$sql.=',';
      }
      $sql.=$col."=".$dbh->quote($data);
    }
    $sql.=' WHERE '.$c::PRIMARY_KEY.'='.$pk;

    $s=$dbh->prepare($sql);
    $s->execute();
  }

  public function _where(){
    $c=get_called_class();
    $dbh=$this->pdo();
    $where=[];
    foreach($this->qs as $k=>$v){
      switch($k){
      case '_':
	break;
      case 'q':
	$where[]=$c::SEARCH_COLUMN.' LIKE '.$dbh->quote($v.'%');
	break;
      case 'status':
	$where[]=$c::STATUS_COLUMN."=".$dbh->quote($v);
	break;
      case $c::PRIMARY_KEY:
      case 'publication_id':
      case 'interaction_id':
	$where[]=$k.'='.(int)$v;
      break;
      default:
	$where[]=$k."='".$dbh->quote($v)."'";
      }
    }
    return $where;
  }
  

  public function query(){
    $c=get_called_class();
    $dbh=$this->pdo();
    $sql='SELECT * FROM ' . $c::TABLE;
    $where=$this->_where();

    if(!isset($this->qs['status'])){
      $this->qs['status']=$c::DEFAULT_STATUS;
    }

    if($where){
      // We know there is a status item in $where so lets add the WHERE
      $sql.=' WHERE '.implode(' AND ',$where);
    }
    
    $limit=isset($this->qs['limit']) ?
      (int)$this->qs['limit']        :
      $this->limit()                 ;
    $sql.=' LIMIT '.$limit;

    $this->statement=$dbh->prepare($sql);
    $out=$this->statement->execute();
    if(!$out){
      trigger_error($sql . ' failed.');
    }
    return $out;
  }

  public function fetch(){
    return $this->statement->fetch(\PDO::FETCH_ASSOC);
  }

  public function message($row,$msg){
    $c=get_called_class();
    return sprintf('Where %s=%d %s',$c::PRIMARY_KEY,$row[$c::PRIMARY_KEY],
		   $msg);
  }
}

class Interactions extends _Table
{
  const TABLE='interactions';
  const PRIMARY_KEY='interaction_id';
  const STATUS_COLUMN='interaction_status';
  const DEFAULT_STATUS='normal';
  //const SEARCH_COLUMN;
}


class Interaction_sources extends _Table
{
  const TABLE='interaction_sources';
  const PRIMARY_KEY='interaction_source_id';
  const STATUS_COLUMN='interaction_source_status';
  const DEFAULT_STATUS='active';
}


// Information about the table
class Publications extends _Table
{
  const TABLE='publications';
  const PRIMARY_KEY='publication_id';
  const STATUS_COLUMN='publication_status';
  const DEFAULT_STATUS='active';
  const SEARCH_COLUMN='publication_pubmed_id';

  public function fetch(){
    $out=$this->statement->fetch(\PDO::FETCH_ASSOC);
    if(!$out){
      return $out;
    }
    if($this->cfg->pubmed_update($out['publication_lastupdated'])){
      $pm=new \PubMedID($out[self::SEARCH_COLUMN]);
      $date=$pm->date();
      if(!$date){
	trigger_error($this->message($out,"unknown date format"));
      }
      $this->update
	($out[self::PRIMARY_KEY],
	 [
	 'publication_article_title'=>$pm->article_title(),
	 'publication_abstract'=>$pm->abs(),
	 'publication_author_short'=>$pm->author_short(),
	 'publication_author_full'=>$pm->author_full(),
	 'publication_volume'=>$pm->volume(),
	 'publication_issue'=>$pm->issue(),
	 'publication_date'=>$date,
	 'publication_journal'=>$pm->journal(),
	 'publication_pagination'=>$pm->pagination(),
	 'publication_affiliation'=>$pm->affiliation(),
	 'publication_meshterms'=>$pm->meshterms(),
	 'publication_lastupdated'=>$this->cfg->now()
	 ]);
      $updated=new Publications
	($this->cfg,
	 [
	  'publication_id'=>$out['publication_id'],
	  ]);
      $updated->query();
      return $updated->statement->fetch(\PDO::FETCH_ASSOC);
    }else if(0==(int)$out['publication_date']){
      trigger_error
	($this->message
	 ($out,'publication_date='.$out['publication_date']),
	 E_USER_WARNING);
    }
    return $out;
  }
}

function table_factory($cfg,$qs)
/* Will return an object for a given table. Or Null if not a valid
   table. */
{
  $table=$qs['table'];
  unset($qs['table']);

  // We don't need breaks because we are always returning.
  switch($table){
  case 'publications':
    return new Publications($cfg,$qs);
  case 'interactions':
    return new Interactions($cfg,$qs);
  case 'interaction_sources':
    return new Interaction_sources($cfg,$qs);
  }
  return NULL;
}


function _messages2json(){
  global $errors,$errors_reported;
  $errors_reported=TRUE;
  return json_encode($errors);
}
function messages2json(){
  print '['.json_encode($out).']';
}
/* Print PDOstatement as JSON. */
function pdo2json($r){
  global $errors;
  $first=TRUE;

  print '{"results":[';
  while($v=$r->fetch()){
    if($first){
      $first=FALSE;
    }else{
      print ',';
    }
    print json_encode($v);
  }


  print '],"messages":' . _messages2json() . '}';
}