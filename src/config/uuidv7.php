<?php

/**
 * UUIDv7 Configuration
 *
 * driver: 对应 cache.php 中的 stores key (如 'file', 'redis')
 * key_prefix: Redis 序列存储的 key 前缀
 */

return [
    // Cache store name, 对应 config/cache.php 中的 stores key
    'driver' => env('UUIDV7_DRIVER', 'file'),

    // Shard ID (0-255), 每个服务器应设置唯一值
    'shard_id' => (int) env('UUIDV7_SHARD_ID', 0),

    // 序列存储的 key 前缀
    'key_prefix' => env('UUIDV7_KEY_PREFIX', 'uuidv7:seq'),
];
