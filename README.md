# think-uuidv7

基于 ThinkPHP 8 的 UUIDv7 生成器，专为分布式系统设计。

## 特性

- **RFC 9562 兼容** - 严格遵循 UUIDv7 标准
- **自动安装** - Composer 安装后自动配置，无需手动复制文件
- **二进制存储** - 16 字节二进制格式，节省 55% 存储空间
- **分片支持** - 内置 shard_id (0-255)，支持 256 个节点
- **分布式序列号** - Redis Lua 脚本保证原子性
- **批量生成** - 支持批量生成单调递增 UUID
- **ORM 集成** - ThinkPHP 模型类型转换器
- **时间可排序** - 同一节点内完全可排序

## 安装

```bash
composer require qs9000/think-uuidv7
```

**安装后自动完成：**
- 配置文件复制到 `config/uuidv7.php`
- 服务自动注册

## 快速开始

### 1. 注册服务（可选）

如果自动注册失败，在 `application/provider.php` 中手动注册：

```php
<?php
return [
    'think\\Service' => qs9000\\thinkuuidv7\\Service::class,
];
```

### 2. 配置

配置文件已自动生成在 `config/uuidv7.php`：

```php
<?php
return [
    // 缓存驱动 (对应 config/cache.php 中的 stores)
    'driver' => env('UUIDV7_DRIVER', 'file'),

    // 分片 ID (0-255)，每个服务器设置唯一值
    'shard_id' => (int) env('UUIDV7_SHARD_ID', 0),

    // Redis 序列号 key 前缀
    'key_prefix' => env('UUIDV7_KEY_PREFIX', 'uuidv7:seq'),
];
```

### 3. 生成 UUID

```php
use qs9000\thinkuuidv7\UuidV7Facade as Uuidv7;

// 生成单个 UUID
$uuid = Uuidv7::generate();
// "0191a51a-b2c3-7d89-0123-456789abcdef"

// 获取 UuidV7 对象
$uuidObj = Uuidv7::make();
echo $uuidObj->getUuid();        // UUID 字符串
echo $uuidObj->getTimestampMs();  // 时间戳 (毫秒)
echo $uuidObj->getShardId();      // 分片 ID
echo $uuidObj->getDatetime()->format('Y-m-d H:i:s.u'); // DateTime 对象

// 批量生成
$uuids = Uuidv7::makeBatch(100);
```

### 4. 解析 UUID

```php
// 验证 UUID
if (Uuidv7::validate($uuid)) {
    // ...
}

// 提取时间戳
$timestamp = Uuidv7::timestamp($uuid);  // 毫秒时间戳
$datetime = Uuidv7::datetime($uuid);     // DateTimeImmutable 对象

// 解析完整信息
$uuidObj = Uuidv7::parse($uuid);
```

### 5. 二进制格式

数据库存储使用 `BINARY(16)` 而非 `CHAR(36)`：

```php
// UUID 字符串转二进制（写入数据库）
$binary = Uuidv7::toBinary($uuid);
// "\x01\x91\xa5\x1a\xb2\xc3..."

// 二进制转 UuidV7 对象（从数据库读取）
$uuidObj = Uuidv7::fromBinary($binary);
```

## ThinkPHP 模型集成

### 类型转换器

在模型中定义 `uuidv7` 类型字段：

```php
<?php
use qs9000\thinkuuidv7\UuidV7Type;

class User extends Model
{
    protected $type = [
        'id' => UuidV7Type::class,
    ];
}
```

**工作原理：**
- 写入时：UUID 字符串 → 二进制
- 读取时：二进制 → UUID 字符串

### 数据库迁移

```php
use think\migration\Migrator;

class CreateUsersTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('users');
        $table->addColumn('id', 'binary', ['limit' => 16])  // 16 字节
              ->addColumn('name', 'string')
              ->create();
    }
}
```

## UUID 结构

```
xxxxxxxx-xxxx-7xxx-yxxx-xxxxxxxxxxxx
|||________| |____| |____| |__________|
  timestamp  ver  variant   random
 (48 bits)   (4)   (2+14)   (62 bits)
```

| 字段 | 位长 | 说明 |
|------|------|------|
| timestamp | 48bit | 毫秒级时间戳（自 Unix 纪元） |
| version | 4bit | 版本号，固定为 7 |
| rand_a | 12bit | shard_id(8bit高) + seq_high(4bit低) |
| sequence | 40bit | 毫秒内序列号（Redis支持40bit，本地12bit） |
| variant | 2bit | 变种位，固定为 10 |
| rand_b | 14bit | seq_low(8bit) + random(6bit) |
| rand_c | 48bit | 随机数 |

**说明：**
- `shard_id` 存储在 rand_a 高 8 位，支持 256 个节点
- `sequence` 编码在 rand_a + rand_b 中，本地同毫秒可生成 4096 个 ID
- 使用 Redis 时，全局序列号支持 2^40 级别

## 时间戳提取

```php
// 从 UUID 提取时间戳
$uuid = '0191a51a-b2c3-7d89-0123-456789abcdef';
$timestamp = Uuidv7::timestamp($uuid);  // 1776503423651
```

## 分布式配置

### 多节点部署

每个服务器设置唯一的 `shard_id`：

```php
// .env
UUIDV7_SHARD_ID=0
```

### Redis 配置

```php
// config/cache.php
'stores' => [
    'redis' => [
        'type' => 'redis',
        'host' => '127.0.0.1',
        'port' => 6379,
        // ...
    ],
],
```

```php
// .env
UUIDV7_DRIVER=redis
```

## 性能特性

| 场景 | 单次生成 | 批量生成 (100) |
|------|---------|----------------|
| 本地模式 | ~0.02ms | ~0.5ms |
| Redis 模式 | ~0.1ms | ~1ms |

- **随机字节缓存** - 批量生成时复用随机字节，减少系统调用
- **本地序列号** - 同一毫秒内无需 Redis 往返
- **Redis Lua 脚本** - 原子操作，避免竞态条件

## 容量分析

| 存储方式 | 序列号容量 | 时间范围 |
|----------|-----------|----------|
| 本地模式 | 4,096/毫秒 | 无限（按 shard_id 分片） |
| Redis 模式 | 1万亿/毫秒 | 约 35,000 年 |

## CLI 命令

```bash
# 生成单个 UUID
php think uuidv7

# 生成批量 UUID
php think uuidv7 -n 10

# 生成指定 shard_id 的 UUID
php think uuidv7 -s 5
```

## 测试

```bash
./vendor/bin/phpunit
```

## 依赖

- PHP >= 8.0
- topthink/framework ^8.0
- phpunit/phpunit ^10.0 (dev)

## License

Apache-2.0
