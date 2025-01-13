<?php
// Configurations
$accessToken = 'VOTRE_TOKEN_D_ACCES'; // Remplacez par votre token d'accès
$userId = 'VOTRE_USER_ID'; // ID utilisateur Instagram
$endpoint = "https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink&access_token=$accessToken";

// Récupération des données depuis l'API Instagram
$response = file_get_contents($endpoint);
$data = json_decode($response, true);

// Vérifiez si les données ont été récupérées avec succès
if (isset($data['data'])) {
    echo json_encode($data['data']); // Envoie les données JSON pour le widget
} else {
    echo json_encode(["error" => "Impossible de récupérer les données Instagram."]);
}
?>
