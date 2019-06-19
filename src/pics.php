<?php

namespace madtec\tools;

class pics
{
	private $url;
	private $pic;
	
	public function __construct()
    {

    }
	
	
	function pic_resize($url,$l,$h)
	{
		$pic = $url;
		$size_im = getimagesize($pic);
		
		$type_img = exif_imagetype($pic);


		if($type_img==2)
		{
			$img_in = imagecreatefromjpeg($pic);
		}
		elseif($type_img==3)
		{
			$img_in = imagecreatefrompng($pic);

		}

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
		
		imagecopyresampled($img_out, $img_in, 0, 0, 0, 0, imagesx($img_out), imagesy($img_out), imagesx($img_in), imagesy($img_in));

		
		$this->pic = imagecreatetruecolor($l, $h);
		$w = imagecolorallocate($this->pic, 255, 255, 255);
		imagefill($this->pic, 0, 0, $w);

		imagecopymerge($this->pic,$img_out,($l-$largeur)/2,($h-$hauteur)/2,0,0,$largeur,$hauteur,100);
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
