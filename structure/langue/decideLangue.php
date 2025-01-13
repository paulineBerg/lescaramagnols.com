	<?php
	
	
  	 
 	    if ($_GET['lang']=='fr') {           // si la langue est 'fr' (français) on inclut le fichier frLangue.php
  	    include("frLangue.php");
  	 	} 
 	 
  	 	else if ($_GET['lang']=='en') {      // si la langue est 'en' (anglais) on inclut le fichier ukLangue.php
   	 	include('ukLangue.php');
  	 	}
	 
	 	 else if ($_GET['lang']=='de') {      // si la langue est 'de' (allemand) on inclut le fichier deLangue.php
   	    include('deLangue.php');
  	 	}
	 
	 	 else if ($_GET['lang']=='nl') {      // si la langue est 'nl' (neerlandais) on inclut le fichier nlLangue.php
   	 	include('nlLangue.php');
  	 	}
 
   	    else {                       // si aucune langue n'est déclarée on inclut le fichier frLangue par défaut
   	    include("frLangue.php");
   	    }
   	 
   	?> 
	 
