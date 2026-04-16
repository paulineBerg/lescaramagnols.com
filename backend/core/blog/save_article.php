<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogApiController;
use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Http\Request;

require_once dirname(__DIR__) . '/bootstrap.php';

$controller = new BlogApiController(new BlogSaveService(blog_repository(), app_event_logger()), app_event_logger());
$controller->saveArticle(Request::fromGlobals())->send();
