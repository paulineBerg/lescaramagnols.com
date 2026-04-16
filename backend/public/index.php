<?php
// public/index.php

// Front-controller modernisé : tente FastRoute puis délègue au routeur public du site
require_once __DIR__ . '/../core/bootstrap.php';

use Caramagnols\Http\FrontController;
use Caramagnols\Http\Request;

$request = Request::fromGlobals();
$response = FrontController::boot()->handle($request);

$response->send();
