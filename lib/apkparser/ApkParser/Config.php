<?php
namespace ApkParser;

/**
 * This file is part of the Apk Parser package.
 *
 * (c) Tufan Baris Yildirim <tufanbarisyildirim@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Config
{
    private $config;

    public function __construct(array $config = null)
    {
        if ($config == null)
            $config = [];

        $this->config = array_merge(array(
            'tmp_path' => sys_get_temp_dir(),
            'jar_path' => __DIR__ . '/Dex/dedexer.jar'
        ), $config);
    }

    public function get($key)
    {
        return $this->config[$key];
    }
}
