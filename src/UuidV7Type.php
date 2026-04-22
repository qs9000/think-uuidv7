<?php

declare(strict_types=1);

namespace qs9000\thinkuuidv7;

use think\model\contract\Typeable;
use think\model\contract\Modelable as Model;
use qs9000\thinkuuidv7\UuidV7Facade as Uuidv7;

/**
 * UUID v7 类型转换器
 * 
 * 用于 ThinkPHP ORM 的类型转换，支持 UUID v7 与二进制之间的转换：
 * - 数据库存储：16字节二进制格式
 * - PHP 处理：UUID 字符串格式 (如 "0190abcd-1234-7000-8000-000000000000")
 * - 在模型中定义字段类型为 uuidv7(protected $type = ['id' => UuidV7Type::class])
 */
class UuidV7Type implements Typeable
{
    /**
     * @var mixed 存储转换后的值（二进制格式）
     */
    protected mixed $data;

    /**
     * 从原始值创建类型实例
     *
     * @param mixed $value 原始值（字符串或二进制）
     * @param Model $model 当前模型实例
     * @return static
     */
    public static function from($value, Model $model): static
    {
        $static = new static();
        $isUuid = Uuidv7::validate($value);
        $static->setData($value, $isUuid);
        return $static;
    }

    /**
     * 设置数据值
     *
     * 根据输入类型进行相应转换：
     * - UUID 字符串 → 二进制（写入数据库前）
     * - 二进制数据 → 保持不变（从数据库读取后）
     *
     * @param mixed $value 原始值
     * @param bool $isUuid 是否为有效的 UUID 字符串
     * @throws \InvalidArgumentException 当值无效时抛出
     */
    protected function setData(mixed $value, bool $isUuid): void
    {
        try {
            if ($isUuid) {
                // 输入是 UUID 字符串，转换为二进制用于数据库存储
                $this->data = Uuidv7::toBinary($value);
            } else {
                // 输入是二进制数据转换为uuid
                $this->data = $value;
            }
        } catch (\Throwable $e) {
            return;
        }
    }

    /**
     * 获取转换后的值
     *
     * @return mixed 二进制格式的 UUID 数据
     */
    public function value(): mixed
    {
        return $this->data;
    }
}
