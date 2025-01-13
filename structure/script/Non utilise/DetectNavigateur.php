    
	<?php 
	
	
	function detect_browser(){$a=$_SERVER['HTTP_USER_AGENT'];$a=str_replace('+',' ',$a);$z=strrev(trim($a));if($z[0]==')') return 'Navigateur non précisé'
	;$b='[\s\/]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})';if (preg_match('#firefox'.$b.'#i',$a,$c))return('Firefox version '.$c[1])
	;if (preg_match('#msie'.$b.'#i',$a,$c))return('Microsoft Internet Explorer version '.$c[1]);if (preg_match('#chrome'.$b.'#i',$a,$c))return('Google Chrome version '
	.$c[1]);if (preg_match('#icab'.$b.'#i',$a,$c))return('iCab (Crystal Atari Browser) version '.$c[1])
	;if (preg_match('#microsoft\sPocket\sinternet\sexplorer'.$b.'#i',$a,$c))return('Microsoft Pocket Internet Explorer version '.$c[1])
	;if (preg_match('#mspie'.$b.'#i',$a,$c))return('Microsoft Pocket Internet Explorer version '.$c[1])
	;if (preg_match('#konqueror'.$b.'#i',$a,$c))return('Konqueror version '.$c[1]);if (preg_match('#lunascape'.$b.'#i',$a,$c))return('Lunascape version '.$c[1])
	;if (preg_match('#lynx'.$b.'#i',$a,$c))return('Lynx version '.$c[1]);if (preg_match('#minimo'.$b.'#i',$a,$c))return('Minimo version '.$c[1])
	;if (preg_match('#netscape[0-9]?'.$b.'#i',$a,$c))return('Netscape version '.$c[1]);if (preg_match('#^nokia([^\/]+)\/([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c))return('Nokia '.trim($c[1]).' version '.$c[2]);if (preg_match('#offbyone;#i',$a))return('OffByOne');if (preg_match('#omniweb'.$b.'#i',$a,$c))return('Omniweb version '.$c[1]);if (preg_match('#opera'.$b.'#i',$a,$c))return('Opera version '.$c[1]);if (preg_match('#safari'.$b.'#i',$a,$c))return('Safari version '.$c[1]);if (preg_match('#seamonkey'.$b.'#i',$a,$c))return('SeaMonkey version '.$c[1]);if (preg_match('#w3m'.$b.'#i',$a,$c))return('W3m version '.$c[1]);if (preg_match('#ia_archiver#i',$a))return('Alexa Bot');if (preg_match('#ask\sjeeves#i',$a))return('Ask Jeeves Bot');if (preg_match('#curl'.$b.'#i',$a,$c))return('Curl version '.$c[1]);if (preg_match('#exabot'.$b.'#i',$a,$c))return('Exaled bot version '.$c[1]);if (preg_match('#ng'.$b.'#i',$a,$c))return('Exaled bot version '.$c[1]);if (preg_match('#exabot-thumbnails#i',$a))return('Exaled bot');if (preg_match('#gamespyhttp'.$b.'#i',$a,$c))return('GameSpy Industries bot version '.$c[1]);if (preg_match('#gigabot'.$b.'#i',$a,$c))return('Gigablast bot version '.$c[1])
	;if (preg_match('#googlebot'.$b.'#i',$a,$c))return('Google bot version '.$c[1]);if (preg_match('#googlebot-image'.$b.'#i',$a,$c))return('Google bot (image) version '.$c[1])
	;if (preg_match('#grub-client[\s\/\-]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c))return('LookSmart Grub bot version '.$c[1])
	;if (preg_match('#yahoo! slurp#i',$a))return('Yahoo! Search) bot');if (preg_match('#slurp#i',$a))return('Inktomi Slurp bot')
	;if (preg_match('#msnbot'.$b.'#i',$a,$c))return('Microsoft MSN Search bot version '.$c[1])
	;if (preg_match('#scooter[\s\/\-]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c))return('AltaVista Scooter bot version '.$c[1])
	;if (preg_match('#wget'.$b.'#i',$a,$c))return('Wget bot version '.$c[1]);if (preg_match('#w3c_validator'.$b.'#i',$a,$c))return('W3C validator bot version '.$c[1])
	;if (preg_match('#firebird'.$b.'#i',$a,$c))return('Mozilla Firebird version '.$c[1]);if (preg_match('#Iceweasel'.$b.'#i',$a,$c))return('Iceweasel version '.$c[1])
	;if (preg_match('#galeon'.$b.'#i',$a,$c))return('Galeon version '.$c[1]);if (preg_match('#dejavu\.org#i',$a))return('dejavu.org')
	;if (preg_match('#applewebkit'.$b.'#i',$a,$c))return('Safari version '.$c[1]);if (preg_match('#screenbrowser'.$b.'#i',$a,$c))return('Internet ScreenBrowser version '.$c[1])
	;if (preg_match('#depspid\.net#i',$a))return('DepSpid bot');if (preg_match('#scej#i',$a))return('SCEJ');if (preg_match('#playstation\sportable#i',$a))return('NetFront')
	;if (preg_match('#netfront#i',$a))return('NetFront');if (preg_match('#semc#i',$a))return('SEMC');if (preg_match('#lizard#i',$a))return('Lizard')
	;if (preg_match('#scoutjet\.com#i',$a))return('Scoutjet.com bot');if (preg_match('#proximic\.com#i',$a))return('Proximic.com bot')
	;if (preg_match('#vwp-online\.de#i',$a))return('Vwp-online.de bot');if (preg_match('#icvn\.de#i',$a))return('Icvn.de bot')
	;if (preg_match('#vbseo\.com#i',$a))return('Vbseo.com bot');if (preg_match('#spydom\.de#i',$a))return('SpyDom.de bot');if (preg_match('#uc\s?browser#i',$a))return('UC Browser')
	;if (preg_match('#gecko#i',$a))return('Navigateur inconnu basé sur Gecko');if (preg_match('#netscape#i',$a))return('Netscape')
	;if (preg_match('#funnelback#i',$a))return('Funnelback bot');if (preg_match('#linkman#i',$a))return('Linkman bot')
	;if (preg_match('#teleca#i',$a))return('Teleca');return 'Navigateur non identifié';}
	

   	 ?> 