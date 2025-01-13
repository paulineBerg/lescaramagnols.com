

<?php
 if(isset($_POST['submit']) && $_POST['submit'] == 'Envoyer'){
  if(isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response']))
  {
        $secret = '6LeoR3gaAAAAAIc-mGn2lhBw4-rrg7PDjsibDqYT';
        $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['g-recaptcha-response']);
        $responseData = json_decode($verifyResponse);
        if($responseData->success)
        { ?>
        
   
        
<?php   // REPONSE GOOGLE - PAGE OK  

$subject = $_POST["subject"];

$TO = "accueil@lescaramagnols.com";
$h  = "From: " . $TO;
$message = "";

while (list($key, $val) = each($_POST )) {
  $message .= "$key : $val\n";
}
mail($TO, $subject, $message, $h); 


Header("Location: https://lescaramagnols.com/reserver/reservation-ok.php");

?>


 
    <?php }
    else
    
			// REPONSE GOOGLE NON ROBOT - PAGE ERREUR

    Header("Location: https://lescaramagnols.com/structure/erreur/erreur.php");{?>
    
    
   <?php }
   }else   // PAS DE CLIC DANS LE CAPTCHA - RETOUR FORMULAIRE
   {?>
   


<b style="color: red;"><b>Merci de cliquer dans le Captcha :)</b>

   <?php

time_nanosleep ( 5,0);

?>

<body onLoad="window.setTimeout('history.go(-1)', 3000)">
</body>
   <?php }
 }
?>




