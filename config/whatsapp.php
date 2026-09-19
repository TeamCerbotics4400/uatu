<?php

return [
    'token' => env('WHATSAPP_TOKEN_API'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v22.0'),
    'graph_url' => fn() => "https://graph.facebook.com/" . env('WHATSAPP_GRAPH_VERSION') . "/" . env('WHATSAPP_PHONE_NUMBER_ID') . "/messages",
];