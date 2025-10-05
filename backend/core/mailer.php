<?php
function send_notification_email($to, $subject, $message) {
    $headers = "From: no-reply@lescaramagnols.com\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $message, $headers);
}
