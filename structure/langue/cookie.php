<?php
function set_cookie($lang)
	{
//d�finition de la dur�e du cookie (1 an)
	$expire = 365*24*3600;
//enregistrement du cookie au nom de CHOIXlang + d�tection si erreur
	if (setcookie("CHOIXlang", $lang, time() + $expire) != TRUE)
		{
    	        echo 'La d�tection de la langue n a pas fonctionn�<br />';
		}
	else
		{
		setcookie("CHOIXlang", $lang, time() + $expire);
		echo 'Le cookie a march�<br />';		
		}
	}
?>
