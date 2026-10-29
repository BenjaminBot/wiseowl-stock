<?php

return [
    'enabled' => env('BOOK_LOOKUP_ENABLED', true),
    'user_agent' => env('BOOK_LOOKUP_USER_AGENT', 'WiseOwlStock/0.1'),
    'google_api_key' => env('GOOGLE_BOOKS_API_KEY'),
    'brave_api_key' => env('BRAVE_SEARCH_API_KEY'),
    'timeout_seconds' => 4,
    'alternative_connect_timeout_seconds' => 5,
    'alternative_timeout_seconds' => 10,
];
