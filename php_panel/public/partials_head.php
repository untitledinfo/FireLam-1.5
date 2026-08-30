<?php
/**
 * Include after $pageTitle and $user (nullable) are set.
 * Prints <head> + opening of app shell + sidebar. Call fl_foot() (partials_foot.php) to close.
 */
$siteName = fl_setting('site_name', 'FireLam');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= fl_h($pageTitle ?? $siteName) ?> · <?= fl_h($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
