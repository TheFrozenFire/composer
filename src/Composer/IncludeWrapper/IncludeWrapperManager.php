<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace Composer\IncludeWrapper;

/**
 * @author Justin Martin <justin@thefrozenfire.com>
 */
class IncludeWrapperManager
{
    protected static $protocol = 'composer';
    
    protected static $wrappers = array();
    
    protected $fp;
    
    public static get_protocol()
    {
        return static::$protocol;
    }
    
    public static set_protocol($protocol)
    {
        static::$protocol = $protocol;
    }
    
    public static get_wrappers()
    {
        return static::$wrappers;
    }
    
    public static add_wrapper($wrapper)
    {
        static::$wrappers[] = $wrapper;
    }

    public static function applies_to_file($file)
    {
        // The file already has a scheme, so we can't wrap it
        if (parse_url($file, PHP_URL_SCHEME) !== NULL) {
            return false;
        }
        
        // TODO: Whitelist/blacklist semantics
        
        return true;
    }
    
    public static function apply_to_file($file)
    {
        $protocol = static::get_protocol();
        
        return "{$protocol}://{$file}";
    }
    
    public static function register()
    {
        return stream_wrapper_register(static::get_protocol(), 'Composer\IncludeWrapper\IncludeWrapperManager');
    }
    
    public static function unregister()
    {
        return stream_wrapper_unregister(static::get_protocol());
    }
    
    protected static function wrapper_call($method, $callback)
    {
        foreach (static::get_wrappers() as $wrapper) {
            if (method_exists($wrapper, $method)) {
                $callback(array($wrapper, $method));
            }
        }
    }
    
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $scheme = parse_url($path, PHP_URL_SCHEME);
        if (!is_null($scheme)) {
            $opened_path = substr($path, "{$scheme}://");
        }
        
        static::wrapper_call('open', function($method) use (&$opened_path, &$mode, &$options)
        {
            $method($opened_path, $mode, $options);
        }
        
        $fp = fopen($opened_path, $mode);
        
        if(is_resource($fp)) {
            $this->fp = $fp;
            return true;
        } else {
            return false;
        }
    }
    
    public function stream_read($count)
    {
        $startingPosition = ftell($this->fp);
        
        $result = fread($this->fp, $count);
        
        static::wrapper_call('read', function($method) use (&$result, $startingPosition, $count)
        {
            $method($result, $startingPosition, $count);
        }
        
        return $result;
    }
    
    public function stream_close()
    {
        return fclose($this->fp);
    }
    
    public function stream_eof()
    {
        return feof($this->fp);
    }
    
    public function stream_stat()
    {
        return fstat($this->fp);
    }
}
