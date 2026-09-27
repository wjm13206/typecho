<?php

namespace Typecho;

/**
 * cookie支持
 *
 * @author qining
 * @category typecho
 * @package Cookie
 */
class Cookie
{
    /**
     * 前缀
     *
     * @var string
     * @access private
     */
    private static string $prefix = '';

    /**
     * 路径
     *
     * @var string
     * @access private
     */
    private static string $path = '/';

    /**
     * @var string
     * @access private
     */
    private static string $domain = '';

    /**
     * @var bool
     * @access private
     */
    private static bool $secure = false;

    /**
     * @var bool
     * @access private
     */
    private static bool $httponly = false;

    /**
     * @var string
     * @access private
     */
    private static string $samesite = 'Lax';

    /**
     * 获取前缀
     *
     * @access public
     * @return string
     */
    public static function getPrefix(): string
    {
        return self::$prefix;
    }

    /**
     * 设置前缀
     *
     * @param string $url
     *
     * @access public
     * @return void
     */
    public static function setPrefix(string $url)
    {
        self::$prefix = md5($url);
        $parsed = parse_url($url);

        self::$domain = $parsed['host'];
        /** 在路径后面强制加上斜杠 */
        self::$path = empty($parsed['path']) ? '/' : Common::url(null, $parsed['path']);
    }

    /**
     * 获取目录
     *
     * @access public
     * @return string
     */
    public static function getPath(): string
    {
        return self::$path;
    }

    /**
     * @access public
     * @return string
     */
    public static function getDomain(): string
    {
        return self::$domain;
    }

    /**
     * @access public
     * @return bool
     */
    public static function getSecure(): bool
    {
        return self::$secure ?: false;
    }

    /**
     * @access public
     * @return bool
     */
    public static function getHttponly(): bool
    {
        return self::$httponly;
    }

    /**
     * @access public
     * @return string
     */
    public static function getSamesite(): string
    {
        return self::$samesite;
    }

    /**
     * 设置额外的选项
     *
     * 只覆盖传入的键, 未传入的保持原值
     *
     * @param array $options
     * @return void
     */
    public static function setOptions(array $options)
    {
        if (array_key_exists('domain', $options)) {
            self::$domain = $options['domain'] ?: self::$domain;
        }

        if (array_key_exists('secure', $options)) {
            self::$secure = !!$options['secure'];
        }

        if (array_key_exists('httponly', $options)) {
            self::$httponly = !!$options['httponly'];
        }

        if (array_key_exists('samesite', $options)) {
            self::$samesite = self::normalizeSamesite($options['samesite']);
        }
    }

    /**
     * 归一化 samesite 取值, 非法值回退为 Lax
     *
     * @param mixed $samesite
     * @return string
     */
    private static function normalizeSamesite($samesite): string
    {
        $samesite = ucfirst(strtolower((string) $samesite));

        return in_array($samesite, ['Lax', 'Strict', 'None'], true) ? $samesite : 'Lax';
    }

    /**
     * 获取指定的COOKIE值
     *
     * @param string $key 指定的参数
     * @param string|null $default 默认的参数
     * @return mixed
     */
    public static function get(string $key, ?string $default = null)
    {
        $key = self::$prefix . $key;
        $value = $_COOKIE[$key] ?? $default;
        return is_array($value) ? $default : $value;
    }

    /**
     * 设置指定的COOKIE值
     *
     * @param string $key 指定的参数
     * @param mixed $value 设置的值
     * @param integer $expire 过期时间,默认为0,表示随会话时间结束
     * @param boolean|null $httponly 是否仅可通过 HTTP 协议访问,默认为空表示使用全局设置
     * @param string|null $samesite 防跨站请求伪造策略,默认为空表示使用全局设置
     */
    public static function set(string $key, $value, int $expire = 0, ?bool $httponly = null, ?string $samesite = null)
    {
        $key = self::$prefix . $key;
        $_COOKIE[$key] = $value;
        Response::getInstance()->setCookie(
            $key,
            $value,
            $expire,
            self::$path,
            self::$domain,
            self::$secure,
            $httponly ?? self::$httponly,
            $samesite ?? self::$samesite
        );
    }

    /**
     * 删除指定的COOKIE值
     *
     * @param string $key 指定的参数
     * @param boolean|null $httponly 是否仅可通过 HTTP 协议访问,默认为空表示使用全局设置
     * @param string|null $samesite 防跨站请求伪造策略,默认为空表示使用全局设置
     */
    public static function delete(string $key, ?bool $httponly = null, ?string $samesite = null)
    {
        $key = self::$prefix . $key;
        if (!isset($_COOKIE[$key])) {
            return;
        }

        Response::getInstance()->setCookie(
            $key,
            '',
            -1,
            self::$path,
            self::$domain,
            self::$secure,
            $httponly ?? self::$httponly,
            $samesite ?? self::$samesite
        );
        unset($_COOKIE[$key]);
    }
}
