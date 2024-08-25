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
	
	function datetime($date)
	{
		if( is_numeric($date) && (int)$date == $date )
		{
			return $date;	
		}
		
		
		$date = str_replace("-", "/", $date);
		$date = explode("/", $date);
		if(!$date[2])
		{$date[2]=date("Y",time());}

		$time = mktime(0,0,0,$date[1],$date[0],$date[2]);
		return $time;
	}


	function dateuktime($date)
	{
		$date = str_replace("-", "/", $date);	
		$date = explode("/", $date);
		if(!$date[2])
		{$date[2]=date("Y",time());}

		$time = mktime(0,0,0,$date[0],$date[1],$date[2]);
		return $time;
		
	}
	
	function dateustime($date)
	{
		$date = str_replace("-", "/", $date);	
		$date = explode("/", $date);

		$time = mktime(0,0,0,$date[1],$date[2],$date[0]);
		return $time;
		
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
		
		$date = str_replace("-", "/", $date);
		$date = str_replace(" ", "/", $date);
		$date = str_replace(":", "/", $date);
		$date = explode("/", $date);

		$time = mktime($date[3],$date[4],0,$date[1],$date[0],$date[2]);
		return $time;
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
		return number_format($chiffre, 2, ',', '.');
	}
	function mnb2($chiffre)
	{
	return number_format($chiffre, 2, '.', '');
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
		$val = mbStringToArray($val);
		
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
			return array_map(__FUNCTION__, $d);
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
		
		$type_img = exif_imagetype($pic);
		if($type_img==2)
		{
			$this->img_in = imagecreatefromjpeg($pic);
		}
		elseif($type_img==3)
		{
			$this->img_in = imagecreatefrompng($pic);
		}

		$this->img_in_size = array('0' => imagesx($this->img_in), '1' => imagesy($this->img_in)); // getimagesize($pic);
		return $this;
	}
		
	function pic_resize($l,$h)
	{
		
		$size_im = $this->img_in_size;
		
		$pour = $size_im[0]/$l;
		if(($size_im[1]/$h)>$pour)
		{
			$pour = $size_im[1]/$h;
		}

		if(($h>$size_im[1])&&($l>$size_im[0]))
		{
			$largeur=$size_im[0];
			$hauteur=$size_im[1];
		}
		else
		{
			$largeur=ceil($size_im[0]/$pour);
			$hauteur=ceil($size_im[1]/$pour);
		}

		$img_out = imagecreatetruecolor($largeur, $hauteur);
		$w = imagecolorallocate($img_out, 255, 255, 255);
		imagefilledrectangle($img_out, 0, 0, imagesx($img_out), imagesy($img_out), $w);
		
		imagecopyresampled($img_out, $this->img_in, 0, 0, 0, 0, imagesx($img_out), imagesy($img_out), imagesx($this->img_in), imagesy($this->img_in));

		
		$this->pic = imagecreatetruecolor($l, $h);
		$w = imagecolorallocate($this->pic, 255, 255, 255);
		imagefill($this->pic, 0, 0, $w);

		imagecopymerge($this->pic,$img_out,($l-$largeur)/2,($h-$hauteur)/2,0,0,$largeur,$hauteur,100);
		return $this;
	}
	
	
	function pic_crop($w,$h)
	{
		$x = 0;
		$y = 0;
		
		/*if($this->img_in_size[0] >= $w && $this->img_in_size[1] >= $h)
		{
			$wc = $w / $this->img_in_size[0];
			$hc = $h / $this->img_in_size[1];
			$corec = min($wc, $hc);
			
			
			
			$x = ($this->img_in_size[0] - $w) / 2 ;
			$y = ($this->img_in_size[1] - $h) / 2 ;
			$this->pic = imagecrop($this->img_in, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
		}
		*///else if($this->img_in_size[0] < $w || $this->img_in_size[1] < $h)
		{
			$wc = $w / $this->img_in_size[0];
			$hc = $h / $this->img_in_size[1];
			
			$corec = max($wc, $hc);
			
			$wc = ($this->img_in_size[0]*$corec);
			$hc = ($this->img_in_size[1]*$corec);
			//print $hc;
			
			$img_out = imagecreatetruecolor($wc, $hc);
			imagecopyresampled($img_out, $this->img_in, 0, 0, 0, 0, $wc, $hc, $this->img_in_size[0], $this->img_in_size[1]);
			
			$this->pic = $img_out;
			
			//$this->img_in_size = array('0' => imagesx($img_out), '1' => imagesy($img_out));
			
			$x = ($wc - $w) / 2 ;
			$y = ($hc - $h) / 2 ;
			$this->pic = imagecrop($img_out, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
		}
		
		
		return $this;
	}
	
	function pic_get($dest = 'raw')
	{
		if($dest != 'raw')
		{
			Imagejpeg ($this->pic,$dest,100);
		}
		else
		{
			ob_start();
			Imagejpeg ($this->pic,null,100);
			return ob_get_clean();
		}
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
}


?>
