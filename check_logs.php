<?php
$lines = file('storage/logs/laravel.log');
$exceptions = [];
foreach ($lines as $line) {
    if (str_contains(strtolower($line), 'fcm delivery failure') || str_contains(strtolower($line), 'exception')) {
        $exceptions[] = $line;
    }
}
echo implode("\n", array_slice($exceptions, -10));
