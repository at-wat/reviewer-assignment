<?php

$presentations_csv = new SplFileObject("presentations.csv"); 
$presentations_csv->setFlags(SplFileObject::READ_CSV); 

$reviewers_csv = new SplFileObject("reviewers.csv"); 
$reviewers_csv->setFlags(SplFileObject::READ_CSV); 

$sessions_csv = new SplFileObject("sessions.csv"); 
$sessions_csv->setFlags(SplFileObject::READ_CSV); 

function rmws($in)
{
	return str_replace(array(' ', "\t", '　'), '', $in);
}
function rmpr($in)
{
	return str_replace(array('【', '】'), '', $in);
}

$session_from_name = array();
$session_from_name_en = array();
$cat_from_name = array();
$cat_from_name_en = array();

class presentation
{
	public $name = '';
	public $number = 0;
	public $id = '';
	public $os_cat = 0;
	public $os_num = 0;
	public $young_award = false;
	public $general_award = false;
	public $title = '';
	public $authors = array();

	public function __construct($raw)
	{
		$this->number = intval($raw[0]);
		$this->id = $raw[1];
		$this->name = rmws($raw[17] . $raw[19]);
		$this->title = $raw[8];

		$os_id = explode('_', $raw[2]);
		if(count($os_id) != 2) die("Invalid os_id. " . $raw[2] . "\n");
		$this->os_cat = intval($os_id[0]);
		$this->os_num = intval($os_id[1]);
		
		if($raw[6] == '希望しない' || $raw[6] == 'NO') $this->young_award = false;
		else if($raw[6] == '希望する' || $raw[6] == 'YES') $this->young_award = true;
		else die('Invalid young_award value. ' . $raw[6] . "\n");

		if($raw[7] == '希望しない' || $raw[7] == 'NO') $this->general_award = false;
		else if($raw[7] == '希望する' || $raw[7] == 'YES') $this->general_award = true;
		else die('Invalid general_award value. ' . $raw[7] . "\n");

		for($i = 0; $i < 15; $i ++)
		{
			if($raw[28 + 11 * $i] != '')
			{
				$this->authors[] = rmws($raw[28 + 11 * $i] . $raw[30 + 11 * $i]);
			}
		}
	}
};

class reviewer
{
	public $name = '';
	public $affiliation = '';
	public $comment = '';
	public $note = '';
	public $addr = '';
	public $os_cats = array();
	public $unavailable = array();

	public function __construct($raw)
	{
		global $session_from_name, $session_from_name_en, $cat_from_name, $cat_from_name_en;

		if($raw[3] == '否') return null;
		else if($raw[3] !== '諾') die('Invalid answer. ' . $raw[3] . "\n");

		$this->name = rmws($raw[1]);
		$this->affiliation = $raw[2];
		$this->comment = $raw[4];
		$this->note = $raw[7];
		$this->addr = $raw[8];

		$os_cats_raw = explode(',', rmws($raw[5]));
		foreach($os_cats_raw as $cat)
		{
			if($cat === '') continue;
			if(isset($cat_from_name[$cat]))
			{
				$this->os_cats[] = $cat_from_name[$cat];
			}
			else if(isset($cat_from_name_en[$cat]))
			{
				$this->os_cats[] = $cat_from_name_en[$cat];
			}
			else
			{
				die('Unknown category name. ' . $cat . "\n");
			}
		}

		$unavailable_raw = explode(',', rmws($raw[6]));
		foreach($unavailable_raw as $unavail)
		{
			if($unavail === '') continue;
			$this->unavailable[] = preg_replace('/（[^,]*）/', '', $unavail);
		}
	}
};

class session
{
	public $os_cat = 0;
	public $os_num = 0;
	public $session_name = '';
	public $session_name_en = '';

	public function __construct($raw)
	{
		$this->os_cat = intval($raw[0]);
		$this->os_num = intval($raw[1]);
		$this->session_name = rmws(rmpr($raw[2]));
		$this->session_name_en = rmws(rmpr($raw[3]));
	}
};

$presentations = array();
$header = true;
foreach($presentations_csv as $line)
{
	if($header)
	{
		$header = false;
		continue;
	}
	if(!is_null($line[0]))
	{
		$presentations[] = new presentation($line);
	}
}
//var_dump($presentations);

$header = true;
foreach($sessions_csv as $line)
{
	if($header)
	{
		$header = false;
		continue;
	}
	if(!is_null($line[0]))
	{
		$session = new session($line);
		$sessions[] = $session;
		if($session->os_num === 0)
		{
			$cat_from_name[$session->session_name] = $session;
		}
		else
		{
			$cat_from_name_en[$session->session_name_en] = $session;
		}
	}
}
//var_dump($sessions);
//var_dump($cat_from_name);
//var_dump($cat_from_name_en);

$header = true;
foreach($reviewers_csv as $line)
{
	if($header)
	{
		$header = false;
		continue;
	}
	if(!is_null($line[0]))
	{
		$ret = new reviewer($line);
		if(!is_null($ret)) $reviewers[] = $ret;
	}
}
//var_dump($reviewers);

echo "All data loaded.\n"

?>
