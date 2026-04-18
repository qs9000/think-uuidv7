# think-uuidv7

基于 ThinkPHP 8 的 UUIDv7 分布式 ID 生成器，符合 RFC 9562 标准。

## 特性

- 符合 RFC 9562 UUIDv7 标准
- 时间有序，便于数据库索引和排序
- 支持分片 ID（0-255），多节点分布式场景唯一
- Redis 分布式序列号支持，高并发场景
- 本地序列号支持，低延迟单节点场景
- BINARY(16) 存储，节省 55% 空间
- 毫秒级时间精度 + 40bit 序列号

## 安装

```bash
composer require qs9000/think-uuidv7
```

## 配置

安装后，将 `vendor/qs9000/think-uuidv7/src/config/uuidv7.php` 复制到项目 `config/` 目录：

```php
// config/uuidv7.php
return [
    // Cache store 名称，对应 config/cache.php 中的 stores key
    'driver' => env('UUIDV7_DRIVER', 'file'),

    // 分片 ID (0-255)，每个节点需设置唯一值
    'shard_id' => (int) env('UUIDV7_SHARD_ID', 0),

    // Redis 序列存储的 key 前缀
    'key_prefix' => env('UUIDV7_KEY_PREFIX', 'uuidv7:seq'),
];
```

## 注册服务

在 `config/provider.php` 中注册：

```php
'providers' => [
    // ...
    qs9000\thinkuuidv7\Service::class,
],
```

## 使用

### Facade 方式

```php
use qs9000\thinkuuidv7\UuidV7Facade;

// 生成 UUID 字符串
$uuid = UuidV7Facade::generate();

// 生成 UuidV7 对象
$uuidObj = UuidV7Facade::make();

// 批量生成
$uuids = UuidV7Facade::makeBatch(100);

// 解析 UUID
$parsed = UuidV7Facade::parse($uuid);

// 验证 UUID 格式
$valid = UuidV7Facade::validate($uuid);

// 提取时间戳（毫秒）
$timestamp = UuidV7Facade::timestamp($uuid);

// 转换为 DateTimeImmutable
$datetime = UuidV7Facade::datetime($uuid);

// UUID 字符串转二进制
$binary = UuidV7Facade::toBinary($uuid);

// 二进制转 UuidV7 对象
$uuidV7 = UuidV7Facade::fromBinary($binary, $timestampMs, $shardId);
```

### 服务方式

```php
// 生成 UUID
$uuid = app('uuidv7')->generate();

// 指定驱动
$uuid = app('uuidv7')->generate('redis');

// 批量生成
$uuids = app('uuidv7')->makeBatch(100);

// 解析
$uuidObj = app('uuidv7')->parse($uuid);

// 动态设置分片 ID
$generator = app('uuidv7')->withShardId(42);
$uuid = $generator->generate();

// UUID 转二进制
$binary = app('uuidv7')->toBinary($uuid);

// 二进制转 UuidV7
$uuidV7 = app('uuidv7')->fromBinary($binary);
```

## UUIDv7 格式

```
xxxxxxxx-xxxx-7xxx-yxxx-xxxxxxxxxxxx
|||________| |____| |____| |__________|
  timestamp  ver  variant   random
 (48 bits)   (4)   (2+14)   (62 bits)
```

### 位结构

| 字段 | 位长 | 说明 |
|------|------|------|
| timestamp | 48bit | 毫秒级时间戳（自 Unix 纪元） |
| version | 4bit | 版本号，固定为 7 |
| rand_a | 12bit | shard_id(8bit高) + seq_high(4bit低) |
| sequence | 40bit | 毫秒内序列号（Redis支持40bit，本地12bit） |
| variant | 2bit | 变种位，固定为 10 |
| rand_b | 14bit | seq_low(8bit) + random(6bit) |
| rand_c | 48bit | 随机数 |

**说明**：
- `shard_id` 存储在 rand_a 高 8 位，支持 256 个节点
- `sequence` 编码在 rand_a + rand_b 中，本地同毫秒可生成 4096 个 ID
- 使用 Redis 时，全局序列号支持 2^40 级别

### 时间戳提取

```php
$uuid = '0191a51a-b2c3-7d89-0123-456789abcdef';

// 提取时间戳
$timestampMs = UuidV7Facade::timestamp($uuid);  // 1744968300000

// 转换为 DateTime
$datetime = UuidV7Facade::datetime($uuid);  // DateTimeImmutable
echo $datetime->format('Y-m-d H:i:s.u');  // 2025-04-18 10:05:00.000
```

## 数据库存储

### CHAR(36) 存储（默认）

```php
$uuid = UuidV7Facade::generate();
// 0191a51a-b2c3-7d89-0123-456789abcdef
```

### BINARY(16) 存储（推荐）

节省 55% 存储空间（36 字节 → 16 字节）：

```php
// 方式一：通过 Facade
$binary = UuidV7Facade::toBinary($uuid);
$uuidV7 = UuidV7Facade::fromBinary($binary);

// 方式二：通过 UuidV7 对象
$uuidObj = UuidV7Facade::make();
$binary = $uuidObj->toBinary();

// 方式三：从外部 UUID 创建
$uuidV7 = UuidV7::fromUuid($uuid);
$binary = $uuidV7->toBinary();
```

### MySQL 示例

```sql
-- 创建表
CREATE TABLE orders (
    id BINARY(16) PRIMARY KEY,
    created_at DATETIME
);

-- 插入（使用 UUID 字符串）
INSERT INTO orders (id, created_at) VALUES (UNHEX(REPLACE('0191a51a-b2c3-7d89-0123-456789abcdef', '-', '')), NOW());

-- 插入（使用二进制）
INSERT INTO orders (id, created_at) VALUES (0x0191a51ab2c37d890123456789abcdef, NOW());

-- 查询
SELECT HEX(id) AS uuid FROM orders;
```

## 性能

| 场景 | 吞吐量 |
|------|--------|
| 单节点（本地序列） | ~10,000/秒 |
| 分布式（Redis） | ~50,000/秒/节点 |

### 基准测试

```bash
php think uuidv7 benchmark -c 100000
```

## 驱动说明

### file 驱动（默认）

适用于单服务器场景，使用内存序列号，无需额外依赖。

### redis 驱动

适用于分布式场景，通过 Redis 保证全局唯一序列号。

```php
// 配置 .env
UUIDV7_DRIVER=redis

// 或动态指定
$uuid = app('uuidv7')->generate('redis');
```

## 命令行

```bash
# 生成单个 UUID
php think uuidv7 generate

# 批量生成
php think uuidv7 batch -c 100

# 基准测试
php think uuidv7 benchmark -c 100000

# 解析 UUID
php think uuidv7 parse 0191a51a-b2c3-7d89-0123-456789abcdef
```

## 测试

```bash
./vendor/bin/phpunit
```

## UuidV7 对象方法

```php
$uuidObj = UuidV7Facade::make();

// 获取 UUID 字符串
$uuidObj->getUuid();           // '0191a51a-b2c3-7d89-0123-456789abcdef'

// 获取时间戳（毫秒）
$uuidObj->getTimestampMs();    // 1744968300000

// 获取 DateTimeImmutable
$uuidObj->getDatetime();       // DateTimeImmutable

// 获取分片 ID
$uuidObj->getShardId();        // 42

// 转为二进制
$uuidObj->toBinary();          // "\x01\x91..." (16 字节)

// 比较
$uuidObj->compareTo($other);  // -1, 0, or 1
$uuidObj->isBefore($other);   // true/false
$uuidObj->isAfter($other);    // true/false

// JSON 序列化
json_encode($uuidObj);         // '"0191a51a-b2c3-7d89-0123-456789abcdef"'

// 字符串转换
(string) $uuidObj;             // '0191a51a-b2c3-7d89-0123-456789abcdef'
```

### 静态工厂方法

```php
// 从 UUID 字符串创建（自动提取时间戳和分片ID）
$uuidV7 = UuidV7::fromUuid('0191a51a-b2c3-7d89-0123-456789abcdef');

// 从二进制创建
$uuidV7 = UuidV7::fromBinary($binary);

// 从二进制创建（提供时间戳和分片ID加速解析）
$uuidV7 = UuidV7::fromBinary($binary, $timestampMs, $shardId);

// 获取二进制长度常量
UuidV7::BINARY_LENGTH();  // 16
```

## 依赖

- PHP >= 8.0
- topthink/framework ^8.0
- Redis（可选，分布式场景）

## 许可证

Apache License 2.0
