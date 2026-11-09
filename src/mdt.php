<?php
namespace madtec\tools;

class mdt
{
	private $url;
	private $img_in;
	private $img_in_size;
	private $pic;

	
	public function __construct()
	{

	}
	
	function getgsmnumber($txt)
	{
		$pattern = "#04[0-9]{2}([-./ ]?[0-9]{2}){3}|04[0-9]{2}([-./ ]?[0-9]{3}){2}#";
		
		preg_match($pattern, $txt,$res);
		return $res;
		
	}
	
	public static function datetime($date)
	{
		if (is_numeric($date) && (int) $date == $date)
		{
			return (int) $date;
		}
		$p = explode("/", str_replace("-", "/", trim((string) $date)));
		if (count($p) < 2 || !is_numeric($p[0]) || !is_numeric($p[1]))
		{
			return false;
		}
		$y = (isset($p[2]) && is_numeric($p[2])) ? (int) $p[2] : (int) date('Y');
		return mktime(0, 0, 0, (int) $p[1], (int) $p[0], $y);
	}

	public static function dateuktime($date)
	{
		$p = explode("/", str_replace("-", "/", trim((string) $date)));
		if (count($p) < 2 || !is_numeric($p[0]) || !is_numeric($p[1]))
		{
			return false;
		}
		$y = (isset($p[2]) && is_numeric($p[2])) ? (int) $p[2] : (int) date('Y');
		return mktime(0, 0, 0, (int) $p[0], (int) $p[1], $y);
	}

	public static function dateustime($date)
	{
		$p = explode("/", str_replace("-", "/", trim((string) $date)));
		if (count($p) < 3 || !is_numeric($p[0]) || !is_numeric($p[1]) || !is_numeric($p[2]))
		{
			return false;
		}
		return mktime(0, 0, 0, (int) $p[1], (int) $p[2], (int) $p[0]);
	}


	function datefulltime($date,$tz = 'Europe/Brussels')
	{
		$dateTime = new \DateTime($date,new \DateTimeZone($tz));
		return $dateTime->getTimestamp();
	}
	
	function datetimetime($date)
	{
		if( is_numeric($date) && (int)$date == $date )
		{
			return $date;	
		}		
		$date = trim((string) $date);
		$date = str_replace("-", "/", $date);
		$date = str_replace(" ", "/", $date);
		$date = str_replace(":", "/", $date);
		$date = explode("/", $date);

		if (count($date) < 3 || !is_numeric($date[0]) || !is_numeric($date[1]) || !is_numeric($date[2]))
		{
			return false;
		}
		return mktime((int) ($date[3] ?? 0), (int) ($date[4] ?? 0), 0, (int) $date[1], (int) $date[0], (int) $date[2]);
	}

	function timeday($date)
	{
		$d = date("d",$date);
		$m = date("m",$date);
		$y = date("Y",$date);

		$time = mktime(0,0,0,$m,$d,$y);

		return($time);
	}

	function jourfr($date)
	{
		$jour = array();
		$jour[0]= "Dimanche";
		$jour[1]= "Lundi";
		$jour[2]= "Mardi";
		$jour[3]= "Mercredi";
		$jour[4]= "Jeudi";
		$jour[5]= "Vendredi";
		$jour[6]= "Samedi";

		return($jour[date("w",$date)]);
	}
	
	function moisfrench($mois)
	{
		$m = array('1' => 'Janvier','2' => 'Février','3' => 'Mars','4' => 'Avril','5' => 'Mai','6' => 'Juin','7' => 'Juillet','8' => 'Août','9' => 'Septembre','10' => 'Octobre','11' => 'Novembre','12' => 'Décembre');
		return($m[$mois]);
	}

	function suppr_accents($str, $encoding='utf-8')
	{
		$str = htmlentities($str, ENT_NOQUOTES, $encoding);
		$str = preg_replace('#&([A-za-z])(?:acute|grave|cedil|circ|orn|ring|slash|th|tilde|uml);#', '\1', $str);
		$str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
		$str = preg_replace('#&[^;]+;#', '', $str);
 
		return $str;
	}

	function to_utf8( $string ) {
		// From http://w3.org/International/questions/qa-forms-utf-8.html
		if ( preg_match('%^(?:
		  [\x09\x0A\x0D\x20-\x7E]            # ASCII
		| [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
		| \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
		| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
		| \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
		| \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
		| [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
		| \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
	)*$%xs', $string) ) {
			return $string;
		} else {
			return iconv( 'CP1252', 'UTF-8', $string);
		}
	} 

	function cc($txt,$txt2)
	{
		if($txt)
		{
			return $txt2;
		}
	}

	function mnb($chiffre)
	{
		return number_format((float) $chiffre, 2, ',', '.');
	}
	function mnb2($chiffre)
	{
	return number_format((float) $chiffre, 2, '.', '');
	}

	function valid_email($address)
	{
		return ( ! preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $address)) ? FALSE : TRUE;
	}

	function formquote($val)
	{
		return(htmlspecialchars (stripslashes($val),ENT_QUOTES));
	}
	function mbStringToArray ($string) {
		$array = array();
		$string = (string) $string;
		$strlen = mb_strlen($string);
		while ($strlen) {
			$array[] = mb_substr($string,0,1,"UTF-8");
			$string = mb_substr($string,1,$strlen,"UTF-8");
			$strlen = mb_strlen($string);
		}
		return $array;
	} 
	function qrtoaz($val)
	{
		$az = array();$qw = array();
		$az[] = "0";	$qw[] = "à";
		$az[] = "1";	$qw[] = "&";
		$az[] = "2";	$qw[] = "é";
		$az[] = "3";	$qw[] = '"';
		$az[] = "4";	$qw[] = "'";
		$az[] = "5";	$qw[] = "(";
		$az[] = "6";	$qw[] = "§";
		$az[] = "7";	$qw[] = "è";
		$az[] = "8";	$qw[] = "!";
		$az[] = "9";	$qw[] = "ç";
		$az[] = "A";	$qw[] = "Q";
		$az[] = "C";	$qw[] = "C";
		$az[] = "M";	$qw[] = "?";
		$az[] = "Q";	$qw[] = "A";
		$az[] = "W";	$qw[] = "Z";
		$az[] = "Z";	$qw[] = "W";
		$az[] = "-";	$qw[] = ")";
		$az[] = ".";	$qw[] = ":";
		$az[] = "$";	$qw[] = "4";
		$az[] = "/";	$qw[] = "=";
		$az[] = "+";	$qw[] = "_";
		$az[] = "%";	$qw[] = "5";
		$az[] = "*";	$qw[] = "!";
		$az[] = ";";	$qw[] = "m";

		$code = "";
		$val = $this->mbStringToArray((string) $val);
		
		for ($i = 0; $i < sizeof($val); $i++)
		{
			$found = 0;
			for ($j = 0; $j < sizeof($qw); $j++)
			{
				if($val[$i]==$qw[$j])
				{
				$code.= $az[$j];
				$found = 1;
				break;
				}
			}
			if($found == 0)
			{
				$code .= $val[$i];
			}
		}

		return $code;
	}
		
	function selected($var1, $var2)
	{
		if(($var1==$var2)&&($var2!=''))
		{
			return " selected";
		}
		else
		{
			return "";
		}
	}

	function objectToArray($d)
	{
		if (is_object($d))
		{
			$d = get_object_vars($d);
		}
		if (is_array($d)) {
			return array_map([$this, __FUNCTION__], $d);
		}
		else {
				return $d;
			}
	}

	function pagination($total,$pages,$page,$cases,$callback)
	{

		$html = "&nbsp;";
		if($pages==1)
		{
		return "&nbsp;";
		}
		else
		{
				if($page==1)
					{$html = "<button class='input txtn'><< Préc  </button>&nbsp;";}
				else
					{$html = "<button class='input txtn' onclick='javascript:".$callback."(".($page-1).");'><< Préc  </button>&nbsp;";}
				
				if($cases>($page+9)) $cases = $page+9;
				if($page<6) {$i = 1; $cases = 10;}
				else {$i = $page-5; $cases = $page+4;}
				
				if($cases>$pages) $cases = $pages;
				
				
				
				for ( $i; $i <= $cases; $i++)
				{
					if($page==$i)
						{$html .= "<button class='input txtn' style='background-color: #E8E8E8;width:25px;color:#203548;'>".$i."</button>&nbsp;";}
					else
						{$html .= "<button class='input txtn' style='width:25px;' onclick='javascript:".$callback."(".$i.");'>".$i."</button>&nbsp;";}
				}
				
				if($page==$pages)
					{$html .= "<button class='input txtnn' class='current next' >  Suiv >></button>";}
				else
					{$html .= "<button class='input txtnn' onclick='javascript:".$callback."(".($page+1).");'>  Suiv >></button>";}
			
		}

	return $html;
	}	


		
	
	function pic($pic)
	{
		$data = @file_get_contents((string) $pic);
		$img = ($data === false || $data === '') ? false : @imagecreatefromstring($data);
		if ($img === false)
		{
			throw new \RuntimeException("Image illisible : ".$pic);
		}

		// PNG/GIF en palette -> truecolor pour un redimensionnement propre
		if (!imageistruecolor($img))
		{
			imagepalettetotruecolor($img);
		}

		$this->img_in = $img;
		$this->img_in_size = array(0 => imagesx($img), 1 => imagesy($img));
		$this->pic = null;
		return $this;
	}

	// Image réduite pour tenir dans $l x $h, centrée sur fond blanc (pas d'agrandissement)
	function pic_resize($l, $h)
	{
		$l = (int) $l;
		$h = (int) $h;
		if ($l < 1 || $h < 1 || !$this->img_in)
		{
			throw new \InvalidArgumentException("pic_resize : dimensions invalides ou image non chargée");
		}

		$sw = $this->img_in_size[0];
		$sh = $this->img_in_size[1];

		if ($sw < $l && $sh < $h)
		{
			$largeur = $sw;
			$hauteur = $sh;
		}
		else
		{
			$ratio = max($sw / $l, $sh / $h);
			$largeur = max(1, (int) ceil($sw / $ratio));
			$hauteur = max(1, (int) ceil($sh / $ratio));
		}

		$this->pic = $this->pic_canvas($l, $h);
		imagecopyresampled(
			$this->pic, $this->img_in,
			intdiv($l - $largeur, 2), intdiv($h - $hauteur, 2), 0, 0,
			$largeur, $hauteur, $sw, $sh
		);
		return $this;
	}

	// Image recadrée au centre pour remplir exactement $w x $h
	function pic_crop($w, $h)
	{
		$w = (int) $w;
		$h = (int) $h;
		if ($w < 1 || $h < 1 || !$this->img_in)
		{
			throw new \InvalidArgumentException("pic_crop : dimensions invalides ou image non chargée");
		}

		$sw = $this->img_in_size[0];
		$sh = $this->img_in_size[1];

		$ratio = max($w / $sw, $h / $sh);
		$srcw = (int) round($w / $ratio);
		$srch = (int) round($h / $ratio);
		$srcx = intdiv($sw - $srcw, 2);
		$srcy = intdiv($sh - $srch, 2);

		$this->pic = $this->pic_canvas($w, $h);
		imagecopyresampled($this->pic, $this->img_in, 0, 0, $srcx, $srcy, $w, $h, $srcw, $srch);
		return $this;
	}

	function pic_get($dest = 'raw', $qualite = 100)
	{
		if (!$this->pic)
		{
			throw new \LogicException("pic_get : appeler pic_resize() ou pic_crop() avant");
		}

		if ($dest != 'raw')
		{
			return imagejpeg($this->pic, $dest, (int) $qualite);
		}

		ob_start();
		imagejpeg($this->pic, null, (int) $qualite);
		return ob_get_clean();
	}

	// Fond blanc : les zones transparentes des PNG ne deviennent pas noires en JPEG
	private function pic_canvas($w, $h)
	{
		$img = imagecreatetruecolor($w, $h);
		imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
		return $img;
	}


	function jsonb64req($r)
	{
		return json_decode(base64_decode($r),1);
	}
	
	function getphonenumber($txt)
	{
		$txt = str_replace("+32","0",$txt);
		$pattern = "#04[0-9]{2}([-./ ]?[0-9]{2}){3}|04[0-9]{2}([-./ ]?[0-9]{3}){2}#";
	
		preg_match($pattern, $txt,$num);

		if(sizeof($num)>0)
		{
			return preg_replace('/\D/', '', $num[0]);
		}
		else
		{
			return false;
		}
		
	}

	
	
	function phoneformat($num,$pays)
	{
		$pays = strtoupper($pays);
		if(trim($num)=="" || trim($num)=="0") {
			return false;
		}
		
		$phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
		
		try {
        	    $numproto = $phoneUtil->parse($num, $pays);
        	} catch (\Exception $e) {
			$numproto = false;
		}
	
		if($numproto === false)	{
			return $num;
		}
		
		if($phoneUtil->isValidNumber($numproto)) {
			return $phoneUtil->format($numproto, \libphonenumber\PhoneNumberFormat::E164);
		} else {
			return $num;
		}
	}

	public static function utf8_dec($str, $to = 'Windows-1252')
	{
		$str = (string) $str;
		if ($str === '')
		{
			return '';
		}
		return mb_convert_encoding($str, $to, 'UTF-8');
	}

	public static function utf8_enc($str, $from = 'Windows-1252', $force = false)
	{
		$str = (string) $str;
		if ($str === '' || (!$force && mb_check_encoding($str, 'UTF-8')))
		{
			return $str;
		}
		return mb_convert_encoding($str, 'UTF-8', $from);
	}
	
}
