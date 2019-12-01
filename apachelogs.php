#!/usr/bin/php
<?php
include_once(__DIR__."/vendor/geoiploc.php");

class Log
{
	private static $total = 0;

	private $id;
	private $string;
	private $ip;
	private $identity;
	private $user;
	private $time;
	private $request;
	private $status;
	private $bytes;
	private $referrer;
	private $user_agent;
	private $country;
	private $country_code;

	public static function getFields ()
	{
		$a = [];
		foreach (get_class_vars(get_class()) as $p => $v) {
			if ($p!=="id" && $p!=="string" && $p!=="total")
				$a[] = $p;
		}

		return $a;
	}

	public function __construct ()
	{
		Log::$total++;
		$this->id = Log::$total;
	}

	public function getField ($field)
	{
		if (isset($this->$field)) {
			return $this->$field;
		} else {
			return null;
		}
	}

	public static function getTotal()
	{
		return Log::$total;
	}

	public function getId ()
	{
		return $this->id;
	}

	public function getString ()
	{
		return $this->string;
	}

	public function getIp ()
	{
		return $this->ip;
	}

	public function getIpValue ()
	{
		$ip = explode(".",$this->ip);
		$v = 0;
		foreach ($ip as $c) {
			$v *= 256;
			$v += $c;
		}

		return $v;
	}

	public function getIdentity ()
	{
		return $this->identity;
	}

	public function getUser ()
	{
		return $this->user;
	}

	public function getTime ()
	{
		return $this->time;
	}

	public function getTimeString ()
	{
		return $this->time->format("d/m/Y H:i:s O");
	}

	public function getRequest ()
	{
		return $this->request;
	}

	public function getStatus ()
	{
		return $this->status;
	}

	public function getBytes ()
	{
		return $this->bytes;
	}

	public function getReferrer ()
	{
		return $this->referrer;
	}

	public function getUserAgent ()
	{
		return $this->user_agent;
	}

	public function getCountry ()
	{
		return $this->country;
	}

	public function getCountryCode()
	{
		return $this->country_code;
	}

	public function show ()
	{
		echo "\n\e[4mRegistro #\e[1m".$this->getId()."\e[0m:\n";
		echo "\n\t\e[1mPaís\e[0m: ".$this->getCountry();
		echo "\n\t\e[1mIP\e[0m: ".$this->getIp();
		echo "\n\t\e[1mIdentidad\e[0m: ".$this->getIdentity();
		echo "\n\t\e[1mUsuario\e[0m: ".$this->getUser();
		echo "\n\t\e[1mHora\e[0m: ".$this->getTimeString();
		echo "\n\t\e[1mOrigen\e[0m: ".$this->getReferrer();
		echo "\n\t\e[1mAgente\e[0m: ".$this->getUserAgent();
		echo "\n\t\e[1mPetición\e[0m: ".$this->getRequest();
		echo "\n\t\e[1mRespuesta\e[0m: ".$this->getStatus();
		echo "\n\t\e[1mBytes transmitidos\e[0m: ".$this->getBytes();
		echo "\n\n";
	}

	public function setFromCombined ($combined)
	{
		if (is_string($combined)) {
			$this->string = mb_substr($combined,0,-1);
			$this->ip = $this->next_field($combined);
			$this->identity = $this->next_field($combined);
			$this->user = $this->next_field($combined," [");
			$this->time = date_create_from_format("d/M/Y:H:i:s O",$this->next_field($combined,"] \""));
			$this->request = $this->next_field($combined,"\" ");
			$this->status = $this->next_field($combined);
			$this->bytes = $this->next_field($combined," \"");
			$this->referrer = $this->next_field($combined,"\" \"");
			$this->user_agent = $this->next_field($combined,"\"");
			if (function_exists("getCountryFromIp")) {
				$this->country = getCountryFromIP($this->ip,"name");
				$this->country_code = getCountryFromIP($this->ip,"code");
			}
		}

		return $this;
	}

	private function next_field (&$str, $end=" ")
	{
		$e = mb_strpos($str,$end);
		$f = mb_substr($str,0,$e);
		$str = mb_substr($str,$e+mb_strlen($end));
		return $f;
	}
}

class LogFile
{

	private $filename;
	private $handler;
	private $gziped;

	public function __construct($filename)
	{
		$this->filename = $filename;
		$this->gziped = strpos($filename,".gz") !== false;
	}

	public function getFilename()
	{
		return $this->filename;
	}

	public function getHandler()
	{
		if (!$this->handler) {
			if ($this->gziped) {
				$this->handler = gzopen($this->filename,"r");
			} else {
				$this->handler = fopen($this->filename,"r");
			}
		}
		return $this->handler;
	}

	public function close()
	{
		if ($this->handler) {
			if ($this->gziped) {
				return gzclose($this->handler);
			} else {
				return fclose($this->handler);
			}
		} else {
			return false;
		}
	}

}

function find_log_files ($path)
{
	$files = [];

	$c = 0;
	$f = $path;
	while (is_file($f) && is_readable($f)) {
		$files[] = new LogFile($f);
		$c++;
		$f = "$path.$c";
	}
	$f .= ".gz";
	while (is_file($f) && is_readable($f)) {
		$files[] = new LogFile($f);
		$c++;
		$f = "$path.$c.gz";
	}

	if ($c===0) {
		echo "No se ha encontrado el archivo '$path' o no es legible.\nSaliendo...\n";
		exit;
	} else {
		echo "\e[1mSe leerán los siguientes archivos:\e[0m\n";
		foreach ($files as $f) {
			echo "\t- ".$f->getFilename()."\n";
		}
	}

	return $files;
}

function read_logs ($files = [])
{
	$r = [];
	$c = 0;
	for ($i=count($files)-1; $i>=0; $i--) {
		$fc = $c;
		$h = $files[$i]->getHandler();
		while ($l = fgets($h)) {
			$r[$c] = new Log();
			$r[$c]->setFromCombined($l);
			$c++;
		}
		$files[$i]->close();
		if ($fc<$c) {
			echo "Leídos \e[1m".($c-$fc)."\e[0m registros de \e[91m'".$files[$i]->getFilename()."'\e[0m ";
			echo "entre \e[93m".$r[$fc]->getTimeString()."\e[0m ";
			echo "y \e[92m".$r[$c-1]->getTimeString()."\e[0m.\n";
		} else {
			echo "El archivo \e[91m'".$files[$i]->getFilename()."'\e[0m está vacío.\n";
		}
	}
	return $r;
}

function get_sorter ($property, $desc)
{
	$c = explode("_",strtolower($property));
	for ($i=0; $i<count($c); $i++) {
		$c[$i] = ucfirst($c[$i]);
	}
	$p = "get".implode($c);
	if ($p==="getIp") $p .= "Value";

	if ($desc) {
		return function ($a, $b) use ($p) {
			if ($a->$p()<$b->$p()) return 1;
			if ($a->$p()>$b->$p()) return -1;
			else return 0;
		};
	} else {
		return function ($a, $b) use ($p) {
			if ($a->$p()<$b->$p()) return -1;
			if ($a->$p()>$b->$p()) return 1;
			else return 0;
		};
	}
}

function filter_by ($field, $value, $logs, $not=false) {
	$filtered = [];
	foreach ($logs as $log) {
		if ($not
			xor ($log->getField($field)===$value
			|| strpos(mb_strtolower($log->getField($field)),mb_strtolower($value)))!==false) {
			$filtered[] = $log;
		}

	}
	return $filtered;
}

function group_by ($fields, $logs) {
	$values = [];
	foreach ($logs as $log) {
		$add = true;
		foreach ($values as $i => $value) {
			$is_equals = true;
			foreach ($fields as $field) {
				$is_equals = $is_equals && $log->getField($field)===$value[$field];
			}
			if ($is_equals) {
				$add = false;
				$values[$i]["count"]++;
				break;
			}
		}
		if ($add) {
			$idx = count($values);
			$values[] = [];
			foreach ($fields as $field) {
				$values[$idx][$field] = $log->getField($field);
			}
			$values[$idx]["count"] = 1;
		}
	}
	return $values;
}

function show_groups($groups, &$off, $fields) {

	$cols = exec("tput cols");
	$rows = exec("tput lines");

	$total = count($groups);
	$max_count = $groups[$total-1]["count"];
	$lcount = floor(log10($max_count)+1);
	$lrow = $cols-$lcount-2;
	$f = "\e[1m%'. ".$lcount."u:\e[0m %s\n";

	if (!is_numeric($off) || $off>$total-$rows+2) $off = $total-$rows+2;
	if ($off<0) $off = 0;

	echo "\e[4mAgrupación\e[0m por \e[4m".implode("\e[0m, \e[4m",$fields)."\e[0m. Resultados ".($off+1)." a ".($off+$rows-2)." de ".$total."\n";
	for ($i=$off; $i<$off+$rows-2; $i++) {
		if ($i<$total) {
			$text = "";
			foreach ($fields as $field) {
				if (mb_strlen($text)>0) {
					$text .= " ";
				}
				$text .= "[".$groups[$i][$field]."]";
			}
			printf($f,$groups[$i]["count"],mb_substr($text,0,$lrow));
		} else {
			echo "\n";
		}
	}

}

function show_logs ($logs=[], &$off)
{
	$cols = exec("tput cols");
	$rows = exec("tput lines");

	$c = count($logs);
	$lc = floor(log10(Log::getTotal())+1);
	$ls = $cols-$lc-2;
	$f = "\e[1m%'. ".$lc."u:\e[0m %s\n";

	if (!is_numeric($off) || $off>$c-$rows+2) $off = $c-$rows+2;
	if ($off<0) $off = 0;

	echo "\e[4mRegistros ".($off+1)." a ".($off+$rows-2)." de ".$c.":\e[0m\n";
	for ($i=$off; $i<$off+$rows-2; $i++) {
		if ($i<$c) {
			printf($f,$logs[$i]->getId(),mb_substr($logs[$i]->getCountryCode() . " " . $logs[$i]->getString(),0,$ls));
		} else {
			echo "\n";
		}
	}
}

function help ()
{
	echo "\t\e[1mhelp\e[0m:               Ayuda (este texto).\n";
	echo "\t\e[1mback\e[0m:               Retrocede en el historial.\n";
	echo "\t\e[1mforward\e[0m:            Avanza en el historial.\n";
	echo "\t\e[1mup\e[0m [LINES]:         Sube LINES líneas o una página.\n";
	echo "\t\e[1mdown\e[0m [LINES]:       Baja LINES líneas o una página.\n";
	echo "\t\e[1mredraw\e[0m:             Redibuja la selección.\n";
	echo "\t\e[1mall\e[0m:                Muestra todos los registros.\n";
	echo "\t\e[1morder by\e[0m FIELD:     Ordena la selección por el campo FIELD.\n";
	echo "\t\e[1mgroup by\e[0m FIELD:     Agrupa la selección por el campo FIELD.\n";
	echo "\t\e[1mfilter by\e[0m FIELD:    Filtra la selección por el campo FIELD.\n";
	echo "\t\e[1mshow\e[0m INDEX:         Muestra detalle del registro con índice INDEX.\n";
	echo "\t\e[1mexit\e[0m:               Sale del programa.\n";
	echo "\t\e[1mquit\e[0m:               Sale del programa.\n";
}


/*
 * Programa principal.
 *
 * @param $filename
 */

echo "\n\t\e[1;44;93mNAVEGADOR DE REGISTROS DE APACHE\e[0m\n\n";

if (count($argv)<2) {
	echo "Usage: $argv[0] <log file>\n";
	exit;
} else {
	$files = find_log_files($argv[1]);
}

echo "\n";

$logs = read_logs($files);

echo "\n";

$history = [];
$offset = [];
$position = -1;
$grouped = false;
$group_fields = [];
$groups = [];
$group_offset = 0;

echo "Introduce una orden (`\e[1mhelp\e[0m` para mostrar la ayuda):\n";

do {
	$draw = false;
	$command = trim(readline(">> "));
	readline_add_history($command);
	$order = preg_split('/\s+/',$command);
	switch ($order[0]) {
		case "help":
			help();
		break;
		case "back":
			$position--;
			$draw = true;
			$grouped = false;
			$group_fields = [];
			$group_offset = 0;
			if ($position<0) {
				echo "No se puede retroceder más en el historial.\n";
				$position++;
				$draw = false;
			}
		break;
		case "forward":
			$position++;
			$draw = true;
			$grouped = false;
			$group_fields = [];
			$group_offset = 0;
			if ($position>=count($history)) {
				echo "No se puede avanzar más en el historial.\n";
				$position--;
				$draw = false;
			}
		break;
		case "up":
			if (isset($order[1]) && is_numeric($order[1])) {
				if ($grouped) {
					$group_offset -= intval($order[1]);
				} else {
					$offset[$position] -= intval($order[1]);
				}
			} else {
				if ($grouped) {
					$group_offset -= intval(exec("tput lines")) - 2;
				} else {
					$offset[$position] -= intval(exec("tput lines")) - 2;
				}
			}
			$draw = true;
		break;
		case "down":
			if (isset($order[1]) && is_numeric($order[1])) {
				if ($grouped) {
					$group_offset += intval($order[1]);
				} else {
					$offset[$position] += intval($order[1]);
				}
			} else {
				if ($grouped) {
					$group_offset += intval(exec("tput lines")) - 2;
				} else {
					$offset[$position] += intval(exec("tput lines")) - 2;
				}
			}
			$draw = true;
		break;
		case "redraw":
			if ($position<0 || $position>=count($history)) {
				echo "No has hecho ninguna búsqueda.\n";
			} else {
				$draw = true;
				$grouped = false;
				$group_fields = [];
			}
		break;
		case "all":
			for ($i=count($history)-1; $i>$position; $i--) {
				unset($history[$i]);
				unset($offset[$i]);
			}
			$position++;
			$history[] = $logs;
			$offset[] = count($logs);
			$draw = true;
			$grouped = false;
			$group_fields = [];
		break;
		case "show":
			if ($position<0 || $position>=count($history)) {
				echo "No has hecho ninguna búsqueda.\n";
				break;
			}
			if (isset($order[1]) && is_numeric($order[1]) && $order[1]>0) {
				$id = intval($order[1]);
				foreach($history[$position] as $log)
				{
					if($log->getId()===$id)
					{
						$log->show();
					}
				}
			} else {
				echo "Debes especificar un índice que exista.\n";
			}
		break;
		case "order":
			if (isset($order[1]) && $order[1]==="by") {
				if (isset($order[2]) && in_array($order[2],Log::getFields())) {
					if ($position>=0&&isset($history[$position])) {
						usort($history[$position],get_sorter($order[2],isset($order[3])&&$order[3]==="desc"));
						$draw = true;
						$grouped = false;
						$group_fields = [];
					} else {
						echo "Debe hacer una selección para poder ordenarla.\n";
					}
				} else {
					echo "Debe especificar un campo válido por el que ordenar:\n";
					echo "Los campos disponibles son: \e[1m".implode("\e[0m, \e[1m",Log::getFields())."\e[0m.\n";
				}
			} else {
				echo "Orden incomprensible. Introduce `\e[1mhelp\e[0m` para mostrar la lista de órdenes.\n";
			}
		break;
		case "group":
			if (isset($order[1]) && $order[1]==="by") {
				if (isset($order[2])) {
					$group_fields = explode(",",$order[2]);
					$valid_fields = true;
					foreach ($group_fields as $field) {
						if (!in_array($field,Log::getFields())) {
							$valid_fields = false;
							break;
						}
					}
					if ($valid_fields) {
						$groups = group_by($group_fields, $history[$position]);
						usort($groups, function ($a, $b) { return $a["count"] - $b["count"]; });
						$group_offset = count($groups);
						$grouped = true;
						$draw = true;
					} else {
						$group_fields = [];
						echo "Debe especificar uno o más campos válidos separados por ',' por los que agrupar.\n";
						echo "Los campos disponibles son: \e[1m".implode("\e[0m, \e[1m",Log::getFields())."\e[0m.\n";
					}
				} else {
					echo "Orden incomprensible. Introduce `\e[1mhelp\e[0m` para mostrar la lista de órdenes.\n";
				}
			} else {
				echo "Orden incomprensible. Introduce `\e[1mhelp\e[0m` para mostrar la lista de órdenes.\n";
			}
		break;
		case "filter":
			if (isset($order[1]) && $order[1]==="by") {
				if (isset($order[2])) {
					if ($order[2]==="not") {
						$not = true;
						$k = 3;
					} else {
						$not = false;
						$k = 2;
					}
					if (in_array($order[$k],Log::getFields())) {
						if (isset($order[$k+1])) {
							$search = [];
							for ($i=$k+1; $i<count($order); $i++) {
								$search[] = $order[$i];
							}
							for ($i=count($history)-1; $i>$position; $i--) {
								unset($history[$i]);
								unset($offset[$i]);
							}
							$position++;
							$history[$position] = filter_by($order[$k],implode(" ",$search),$history[$position-1],$not);
							$offset[$position] = count($history[$position]);
							$draw = true;
							$grouped = false;
							$group_fields = [];
						} else {
							echo "Debe especificar el criterio de búsqueda.\n";
						}
					} else {
						echo "Debe especificar un campo válido por el que filtrar:\n";
						$log_fields = Log::getFields();
						$log_fields[] = "not \e[0mFIELD\e[1m";
						echo "Los campos disponibles son: \e[1m".implode("\e[0m, \e[1m",$log_fields)."\e[0m.\n";
					}
				} else {
					echo "Debe especificar un campo válido por el que ordenar:\n";
					$log_fields = Log::getFields();
					$log_fields[] = "not \e[0mFIELD\e[1m";
					echo "Los campos disponibles son: \e[1m".implode("\e[0m, \e[1m",$log_fields)."\e[0m.\n";
				}

			} else {
				echo "Orden incomprensible. Introduce `\e[1mhelp\e[0m` para mostrar la lista de órdenes.\n";
			}
		break;
		case "exit":
		case "quit":
		break;
		default:
			echo "Orden no reconocida. Introduce `\e[1mhelp\e[0m` para mostrar la lista de órdenes.\n";
	}
	if ($draw) {
		if ($grouped) {
			show_groups($groups,$group_offset,$group_fields);
		} else {
			show_logs($history[$position],$offset[$position]);
		}
	}
} while ($order[0]!=="exit" && $order[0]!=="quit");
?>