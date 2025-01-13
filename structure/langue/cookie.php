<?php
function set_cookie($lang)
	{
//définition de la durée du cookie (1 an)
	$expire = 365*24*3600;
//enregistrement du cookie au nom de CHOIXlang + détection si erreur
	if (setcookie("CHOIXlang", $lang, time() + $expire) != TRUE)
		{
    	        echo 'La détection de la langue n a pas fonctionné<br />';
		}
	else
		{
		setcookie("CHOIXlang", $lang, time() + $expire);
		echo 'Le cookie a marché<br />';		
		}
	}
?>
