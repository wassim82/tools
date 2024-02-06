<?php

namespace madtec\tools;

class pics
{
	private $url;
	private $img_in;
	private $img_in_size;
	private $pic;

	
	public function __construct()
    {

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
		elseif($type_img==18)
		{
			$img_in = imagecreatefromwebp($pic);
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
	
	function pic_reduce($l,$h)
	{
		$size_im = $this->img_in_size;
		$r = $size_im[0] / $size_im[1];
		
		if($l/$h > $r){
			$new_width = $h*$r;
			$new_height = $h;
		} else {
			$new_height = $l/$r;
			$new_width = $l;
		}

		$img_out = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($img_out, $this->img_in, 0, 0, 0, 0, $new_width, $new_height, $size_im[0], $size_im[1]);
		
		$this->pic = $img_out;
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
	

}
	
?>
