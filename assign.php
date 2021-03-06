<?php

$rev_per_presen = 2; // 一発表あたりの審査員数
$presen_per_rev = 6; // 審査員あたりの最大割当数
$max_slots = 2; // 審査員あたりの担当スロット数
$rev_per_slot = 10; // 一審査員、一スロットあたりの最大審査数


function mb_SplFileObject($filename)
{
	$data = file_get_contents($filename);
	$enc = mb_detect_encoding($data);
	$data_utf8 = mb_convert_encoding($data, 'UTF-8', $enc);
	$filename2 = dirname($filename) . '/' . basename($filename, '.csv') . '.utf8.csv';
	file_put_contents($filename2, $data_utf8);
	echo 'Opening ' . $filename . "\n";
	echo ' encoding: ' . $enc . "\n";
	return new SplFileObject($filename2);
}

$presentations_csv = mb_SplFileObject("presentations.csv"); 
$presentations_csv->setFlags(SplFileObject::READ_CSV); 

$reviewers_csv = mb_SplFileObject("reviewers.csv"); 
$reviewers_csv->setFlags(SplFileObject::READ_CSV); 

$sessions_csv = mb_SplFileObject("sessions.csv"); 
$sessions_csv->setFlags(SplFileObject::READ_CSV); 

echo "\n";

function rmws($in)
{
	return str_replace(array(' ', "\t", '　'), '', $in);
}
function rmpr($in)
{
	return str_replace(array('【', '】'), '', $in);
}

$session_from_name = array();
$cat_from_name = array();
$cat_from_id = array();
$slot_list = array();

class presentation
{
	public $name = '';
	public $number = 0;
	public $id = '';
	public $slot = '';
	public $os_cat = 0;
	public $os_num = 0;
	public $young_award = false;
	public $general_award = false;
	public $title = '';
	public $authors = array();
	public $reviewers = array();
	public $reviewers_assigned = array();

	public function csv($reviewers)
	{
		$ret = '';
		$ret .= $this->number . ',';
		$ret .= $this->id . ',';
		$ret .= $this->os_cat . '_' . $this->os_num . ',';
		$ret .= $this->name . ',';
		$ret .= $this->young_award . ',' . $this->general_award . ',';
		$ret .= $this->title . ',';

		foreach($this->reviewers_assigned as $reviewer_id => $status)
		{
			$ret .= $reviewers[$reviewer_id]->name . ',' . $reviewers[$reviewer_id]->affiliation . ',';
			$ret .= $reviewers[$reviewer_id]->addr . ',';
			//$ret .= $reviewer_id . ',';
		}
		return $ret;
	}

	public function __construct($raw)
	{
		$this->number = intval($raw[0]);
		$this->id = $raw[1];
		$this->slot = preg_replace('/-.*$/', '', $this->id);
		if($raw[8] !== '')
		{
			$this->title = $raw[8];
		}
		else
		{
			$this->title = $raw[10];
		}

		if($raw[17] !== '')
		{
			$this->name = rmws($raw[17] . $raw[19]);
		}
		else if($raw[21] !== '')
		{
			$this->name = $raw[22] . " " . $raw[21];
		}
		else
		{
			die('Empty name field. ' . $raw[0] . "\n");
		}

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
			if($raw[28 + 11 * $i] !== '')
			{
				$this->authors[rmws($raw[28 + 11 * $i] . $raw[30 + 11 * $i])] = true;
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
	public $slots = array();
	public $slots_ap = array();
	public $valid = false;
	public $cnt_assignable = 0;
	public $cnt_assigned = 0;

	public function csv($presentations, $num, $max_assign)
	{
		global $slot_list;

		$ret = '';
		$ret .= $this->name . ',' . $this->affiliation . ',';
		$ret .= $this->addr . ',';
		$num_assign = 0;

		$slots = array();
		foreach($slot_list as $id => $status)
		{
			$slots[$id] = '';
		}
		foreach($presentations as $presentation)
		{
			if(isset($presentation->reviewers_assigned[$num]))
			{
				if($slots[$presentation->slot] !== '') 
					$slots[$presentation->slot] .= ',';
				$slots[$presentation->slot] .= $presentation->id;
				//$ret .= $presentation->id . ',';
				if(isset($this->unavailable[$presentation->slot]))
				{
					echo 'Error: ' . $this->name . "\t" . ' is assigned to unavailable slot ' . $presentation->slot . ".\n";
				}
				$num_assign ++;
			}
		}
		foreach($slots as $id => $presen)
		{
			$state = '';
			if(isset($this->unavailable[$id]))
				$state = 'X';
			$ret .= $state . ',"' . $slots[$id] . '",';
		}
		if(count($this->slots) > 2)
		{
			echo 'Warn: ' . str_pad($this->name, 16) . ' is assigned to ' . count($this->slots) . " slots. \t";
			foreach($this->slots as $slot => $status)
			{
				echo $slot . ' ';
			}
			if(count($this->slots_ap) > 2) echo " \t (" . count($this->slots_ap) . " AM/PM zones.)";

			echo "\n";
		}

		$max_review = 0;
		foreach($this->slots as $slot => $status)
		{
			if($status > $max_review)
				$status = $max_review;
		}
		if($max_review > 3)
		{
			echo 'Warn: ' . str_pad($this->name, 16) . ' has ' . $max_review . " in one slot.\n";
		}

		if($num_assign > $max_assign)
		{
			echo 'Error: ' . $this->name . ' assigned too much ' . $num_assign . ".\n";
		}
		
		return $ret;
	}
	public function __construct($raw)
	{
		global $session_from_name, $cat_from_name;

		if($raw[3] == '否')
		{
			$this->valid = false;
			return;
		}
		else if($raw[3] !== '諾') die('Invalid answer. ' . $raw[3] . "\n");
		$this->valid = true;

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
				$this->os_cats[$cat_from_name[$cat]->os_cat] = true;
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
			$this->unavailable[preg_replace('/（[^,]*）/', '', $unavail)] = true;
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
		$presen = new presentation($line);
		$presentations[] = $presen;
		$slot_list[$presen->slot] = true;
	}
}
//var_dump($presentations);
ksort($slot_list);

$sessions = array();
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
			$cat_from_name[$session->session_name_en] = $session;
			$cat_from_id[$session->os_cat] = $session;
		}
		else
		{
			$session_from_name[$session->session_name] = $session;
			$session_from_name[$session->session_name_en] = $session;
		}
	}
}
$sessions_bak = $sessions;
//var_dump($sessions);
//var_dump($cat_from_name);
//var_dump($session_from_name);


$reviewers = array();
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
		if($line[0][0] == '#') continue;
		$ret = new reviewer($line);
		$registered = false;
		foreach($reviewers as $key => $reviewer)
		{
			if($reviewer->name === $ret->name)
			{
				echo "Double registration:\n";
				echo ' ' . $reviewer->name . ' (' . $reviewer->affiliation . ")\n";
				echo ' ' . $ret->name . ' (' . $ret->affiliation . ")\n";
				$registered = $key;
				break;
			}
		}
		if($registered !== false)
		{
			// use newer registration
			if($ret->valid === true) $reviewers[$registered] = $ret;
			else unset($reviewers[$registered]);
		}
		else if($ret->valid === true)
		{
			$reviewers[] = $ret;
		}
	}
}
//var_dump($reviewers);

echo "\n";
echo "All data loaded.\n";
echo ' ' . count($reviewers) . " reviewers\n";
echo ' ' . count($presentations) . " presentations\n";
echo "\n";

echo 'Categories: ' . count($cat_from_id) . "\n";
$pinch_hitter_cat_req = count($cat_from_id) - 9;

$unavailable_slots = array();
$cat_request = array();
$pinch_hitters = array();
foreach($reviewers as $key => &$reviewer)
{
	$unavailable_cnt = count($reviewer->unavailable);
	$cat_cnt = count($reviewer->os_cats);
	if($unavailable_cnt == 0 &&
		(/*$cat_cnt == 0 || */$cat_cnt >= $pinch_hitter_cat_req))
	{
		$pinch_hitters[$key] = true;
	}

	if(!isset($unavailable_slots[$unavailable_cnt]))
		$unavailable_slots[$unavailable_cnt] = 0;
	if(!isset($cat_request[$cat_cnt]))
		$cat_request[$cat_cnt] = 0;
	$unavailable_slots[$unavailable_cnt] ++;
	$cat_request[$cat_cnt] ++;

	foreach($presentations as &$presentation)
	{
		if(isset($reviewer->unavailable[$presentation->slot]))
		{
			// unavailable
			continue;
		}
		if(isset($presentation->authors[$reviewer->name]))
		{
			// author == reviewer
			continue;
		}
		if(count($reviewer->os_cats) == 0 ||
			isset($reviewer->os_cats[$presentation->os_cat]))
		{
			// preffered
			$presentation->reviewers[$key] = true;
			$reviewer->cnt_assignable ++;
		}
	}
	unset($presentation);
}
unset($reviewer);
ksort($cat_request);
ksort($unavailable_slots);

echo "\n";
echo "Number of unavailable slot histogram:\n";
foreach($unavailable_slots as $key => $number)
{
	echo $key . ': ' . $number . "\n";
}
echo "\n";
echo "Number of preferred categoris histogram:\n";
foreach($cat_request as $key => $number)
{
	echo $key . ': ' . $number . "\n";
}
echo "\n";
echo "Pinch hitters.\n";
foreach($pinch_hitters as $key => $status)
{
	echo ' [' . $key . '] ' . $reviewers[$key]->name . ' ' . $reviewers[$key]->affiliation;
	$num_req = count($reviewers[$key]->os_cats);
	echo ' (' . $num_req . " categories)\n";

	$reviewers[$key]->unavailable = $slot_list;

	foreach($presentations as &$presentation)
	{
		if(isset($presentation->reviewers[$key]))
			unset($presentation->reviewers[$key]);
	}
	unset($presentation);
	$reviewers[$key]->cnt_assignable = 0;
}
echo "\n";

$debug = false;


$cnt = 0;
$not_desired = false;

$presen_per_rev_now = $presen_per_rev - 1;
while(true)
{
	$resort = true;
	while($resort)
	{
		usort($presentations, function($a, $b) {
			//global $reviewers;
			//$a_poss = $b_poss = 0;
			//foreach($a->reviewers as $reviewer_id => $status) $a_poss += $reviewers[$reviewer_id]->cnt_assignable;
			//foreach($b->reviewers as $reviewer_id => $status) $b_poss += $reviewers[$reviewer_id]->cnt_assignable;
			//return $a_poss - $b_poss;
			return count($a->reviewers) - count($b->reviewers);
		});

		$resort = false;
		foreach($presentations as &$presentation)
		{
			if(count($presentation->reviewers_assigned) >= $rev_per_presen) continue;
			if($debug) if(++$cnt > 10) die();

			$assignable = array();
			foreach($presentation->reviewers as $reviewer_id => $status)
			{
				if(!$status) die("Invalid reviewer status.\n");
				if($reviewers[$reviewer_id]->cnt_assigned > $presen_per_rev)
				{
					die('[' . $reviewer_id . "] assigned too much.\n");
				}
				if($reviewers[$reviewer_id]->cnt_assigned >= $presen_per_rev_now) continue;
				$slot_already_assigned = 0;
				if(isset($reviewers[$reviewer_id]->slots[$presentation->slot]))
					$slot_already_assigned = 4;//$reviewers[$reviewer_id]->slots[$presentation->slot];
				$assignable[$reviewer_id] = $reviewers[$reviewer_id]->cnt_assignable
					- $slot_already_assigned;
			}
			if(count($assignable) == 0) continue;
			asort($assignable);

			if($debug) echo $presentation->name . ' ' . count($presentation->reviewers) . "\n";
			foreach($assignable as $reviewer_id => $cnt_assignable)
			{
				if(count($presentation->reviewers_assigned) < $rev_per_presen)
				{
					// avoid more than 3 slots
					if(count($reviewers[$reviewer_id]->slots) >= $max_slots &&
						!isset($reviewers[$reviewer_id]->slots[$presentation->slot])) continue;
					// avoid more than 3 presentations in one slot
					if(isset($reviewers[$reviewer_id]->slots[$presentation->slot]))
						if($reviewers[$reviewer_id]->slots[$presentation->slot] >= $rev_per_slot) continue;

					$presentation->reviewers_assigned[$reviewer_id] = true;
					$reviewers[$reviewer_id]->cnt_assigned ++;

					if(!isset($reviewers[$reviewer_id]->slots[$presentation->slot]))
						$reviewers[$reviewer_id]->slots[$presentation->slot] = 0;
					$reviewers[$reviewer_id]->slots[$presentation->slot] ++;
					$reviewers[$reviewer_id]->slots_ap[substr($presentation->slot, 0, 2)] = true;

					if($debug) echo '*' . count($presentation->reviewers_assigned);
					if($reviewers[$reviewer_id]->cnt_assigned >= $presen_per_rev)
					{
						foreach($presentations as &$presentation2)
						{
							if(isset($presentation2->reviewers[$reviewer_id]))
							{
								unset($presentation2->reviewers[$reviewer_id]);
							}
						}
						unset($presentation2);
						$resort = true;
					}
					if($debug || $not_desired)
					{
						echo ' [' . $reviewer_id . '] ' . $reviewers[$reviewer_id]->name;
						if(!$not_desired) echo ' (' . $cnt_assignable . ')';
						echo "\n";
					}
				}
				else if($debug)
				{
					echo ' ';
					echo ' [' . $reviewer_id . '] ' . $reviewers[$reviewer_id]->name . ' (' . $cnt_assignable . ")\n";
				}
			}
			if($resort) break;
		}
		unset($presentation);
	}

	if($presen_per_rev_now == $presen_per_rev)
	{
		// relax category select if presentations without reviewer exist

		$cnt_not_assigned_presentation = 0;
		foreach($presentations as $presentation)
		{
			if(count($presentation->reviewers_assigned) < $rev_per_presen)
			{
				$cnt_not_assigned_presentation ++;
			}
		}
		if($cnt_not_assigned_presentation == 0) break;
		
		echo $cnt_not_assigned_presentation . " presentations don't have enough reviewers.\n";
		echo "Relax category preference.\n\n";
		echo "Following assignments ignore preferred category:\n";

		foreach($reviewers as $key => &$reviewer)
		{
			foreach($presentations as &$presentation)
			{
				if(count($presentation->reviewers_assigned) >= $rev_per_presen) continue;
				if(isset($reviewer->unavailable[$presentation->slot]))
				{
					// unavailable
					continue;
				}
				if(isset($presentation->authors[$reviewer->name]))
				{
					// author == reviewer
					continue;
				}
				if(!isset($presentation->reviewers[$key]) &&
					!isset($presentation->reviewers_assigned[$key]))
				{
					$presentation->reviewers[$key] = true;
					$reviewer->cnt_assignable ++;
				}
			}
			unset($presentation);
		}
		unset($reviewer);
		$not_desired = true;
	}
	// try again
	$presen_per_rev_now = $presen_per_rev;
}
$cnt_not_assigned_presentation = 0;
foreach($presentations as $presentation)
{
	if(count($presentation->reviewers_assigned) < $rev_per_presen)
	{
		$cnt_not_assigned_presentation ++;
		//echo 'No more available reviewers for ' . $presentation->name . '(' . count($presentation->reviewers) . " assigned)\n";
	}
}
$cnt_not_assigned_reviewer = 0;
$assign_num = array();
$assign_slots = array();
for($i = 0; $i < $presen_per_rev + 1; $i ++) $assign_num[$i] = 0;
for($i = 0; $i < $max_slots + 1; $i ++) $assign_slots[$i] = 0;
foreach($reviewers as $reviewer)
{
	$cnt = $reviewer->cnt_assigned;
	if($cnt < $presen_per_rev)
	{
		$cnt_not_assigned_reviewer ++;
	}
	$assign_num[$cnt] ++;
	$assign_slots[count($reviewer->slots)] ++;
}
echo "\n";
echo $cnt_not_assigned_presentation . " presentations don't have enough reviewers.\n";
echo $cnt_not_assigned_reviewer . " reviewers don't have full assignments.\n";

echo "\n";
echo "Number of assigned reviews histogram:\n";
for($i = 0; $i < $presen_per_rev + 1; $i ++)
{
	echo $i . ' presentations: ' . $assign_num[$i] . "\n";
}
echo "\n";
echo "Number of assigned slots histogram:\n";
for($i = 0; $i < $max_slots + 1; $i ++)
{
	echo $i . ' slots: ' . $assign_slots[$i] . "\n";
}

echo "\n";
echo "Reviewers without assignment:\n";
foreach($reviewers as $reviewer)
{
	if($reviewer->cnt_assigned === 0)
	{
		echo " " . $reviewer->name . ' ' . $reviewer->comment . " " . $reviewer->note . "\n";
	}
}

echo "\n";
echo "Generating assignments.csv and reviewer_status.csv.\n";
echo "\n";

foreach($pinch_hitters as $key => $status)
{
	$reviewers[$key]->unavailable = array();
}

usort($presentations, function($a, $b) {
	//global $reviewers;
	//$a_poss = $b_poss = 0;
	//foreach($a->reviewers as $reviewer_id => $status) $a_poss += $reviewers[$reviewer_id]->cnt_assignable;
	//foreach($b->reviewers as $reviewer_id => $status) $b_poss += $reviewers[$reviewer_id]->cnt_assignable;
	//return $a_poss - $b_poss;
	return $a->id > $b->id;
});

$sessions = $sessions_bak;

function convert_csv($filename, $filename2)
{
	$data = file_get_contents($filename);
	$enc = mb_detect_encoding($data);
	$data_utf8 = mb_convert_encoding($data, 'SJIS', $enc);
	file_put_contents($filename2, $data_utf8);
}

$csv = '';
foreach($presentations as $presentation)
{
	$csv .= $presentation->csv($reviewers) . "\n";
}
file_put_contents('assignments.utf8.csv', $csv);
convert_csv('assignments.utf8.csv', 'assignments.csv');

$csv = '';
foreach($reviewers as $key => $reviewer)
{
	$csv .= $reviewer->csv($presentations, $key, $presen_per_rev) . "\n";
}
file_put_contents('reviewer_status.utf8.csv', $csv);
convert_csv('reviewer_status.utf8.csv', 'reviewer_status.csv');


?>
