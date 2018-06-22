<?php

use Illuminate\Http\Request;

$api = app('Dingo\Api\Routing\Router');


$api->version('v1', function($api) {
    $api->get('auto', function() {
        return response('this is version v1');
    });
});