<?php

/**
 * CodeMommy Cache
 * @author  Candison November <www.kandisheng.com>
 */

namespace Model;

use CodeMommy\WebPHP\Model;
use CodeMommy\WebPHP\Cache;

/**
 * Class AllowDomain
 * @package Model
 */
class AllowDomain extends Model
{
    const CACHE_TIMEOUT = 60;

    /**
     * @var string
     */
    protected $table = 'allow_domain';

    /**
     * Is Allow
     *
     * @param string $domain
     *
     * @return bool $result
     */
    public static function isAllow($domain)
    {
        $domain = strtolower($domain);
        $cacheKey = sprintf('DomainCount:%s', $domain);
        if (Cache::isExist($cacheKey)) {
            return Cache::get($cacheKey, null);
        }
        $count = self::where('domain', '=', $domain)
            ->count();
        $result = $count > 0 ? true : false;
        Cache::set($cacheKey, $result, self::CACHE_TIMEOUT);
        return $result;
    }
}