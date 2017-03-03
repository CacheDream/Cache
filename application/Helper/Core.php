<?php

/**
 * CodeMommy Cache
 * @author Candison <www.kandisheng.com>
 */

namespace Helper;

use MatthiasMullie\Minify;

class Core
{
    const DEFAULT_CONTENT      = '';
    const DEFAULT_CONTENT_TYPE = '';

    private $outputContentType = null;
    private $outputContent     = null;
    private $parameter         = null;
    private $config            = null;

    public function __construct()
    {
        $this->outputContentType = self::DEFAULT_CONTENT_TYPE;
        $this->outputContent = self::DEFAULT_CONTENT;
        $this->parameter = array();
        $this->config = array();
        return;
    }

    private function getParameter()
    {
        $this->parameter['version'] = isset($_GET['v']) ? $_GET['v'] : '';
        $this->parameter['files'] = isset($_GET['f']) ? $_GET['f'] : '';
        return;
    }

    private function getFile($file)
    {
        $return = array();
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $file);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_NOBODY, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Not verified SSL certificate
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // Not verified SSL certificate
        $result = curl_exec($curl);
        if (curl_errno($curl)) {
            return null;
        }
        $information = curl_getinfo($curl);
        $headerSize = isset($information['header_size']) ? $information['header_size'] : 0;
        $return['information'] = $information;
        $return['header'] = substr($result, 0, $headerSize);
        $return['content'] = substr($result, $headerSize);
        curl_close($curl);
        return $return;
    }

    private function handleContent($url, $content, $contentType, $version)
    {
        $contentType = strtolower($contentType);
        // Compress Javascript
        if (strpos($contentType, 'javascript') !== false) {
            $mini = new Minify\JS();
            $mini->add($content);
            $content = $mini->minify();
        }
        // Compress CSS
        if (strpos($contentType, 'css') !== false) {
            $mini = new Minify\CSS();
            $mini->add($content);
            $content = $mini->minify();
        }
        // Replace path in CSS
        if (strpos($contentType, 'css') !== false) {
            $preg = '/url\((.*)\)/sU';
            preg_match_all($preg, $content, $result);
            $fileList = $result[1];
            foreach ($fileList as $file) {
                $informationURL = parse_url($file);
                if (isset($informationURL['scheme'])) {
                    $fileNew = $file;
                } else if (substr($file, 0, 1) == '/') {
                    $information = parse_url($url);
                    $fileNew = sprintf('%s://%s%s', $information['scheme'], $information['host'], $file);
                } else {
                    $urlNew = substr($url, 0, strripos($url, '/'));
                    $fileNew = sprintf('%s/%s', $urlNew, $file);
                }
                if (empty($version)) {
                    $fileNew = sprintf('url(/?f=%s)', $fileNew);
                } else {
                    $fileNew = sprintf('url(/?v=%s&f=%s)', $version, $fileNew);
                }
                if(substr(strtolower($file), 0, 5) != 'data:'){
                    $content = str_replace(sprintf('url(%s)', $file), $fileNew, $content);
                }
            }
        }
        return $content;
    }

    private function output()
    {
        // Get Parameter
        $this->getParameter();
        /**
         * @todo Get from Cache
         */
        $this->parameter['files'] = explode(';', $this->parameter['files']);
        $this->outputContent = '';
        foreach ($this->parameter['files'] as $file) {
            // 过滤空文件
            if (empty($file)) {
                continue;
            }
            // 添加版本
            if (!empty($this->parameter['version'])) {
                $file .= sprintf('?v=%s', $this->parameter['version']);
            }
            /**
             * @todo Get from Cache
             */
            // 获取内容
            $result = $this->getFile($file);
            if (empty($result)) {
                $this->outputContentType = self::DEFAULT_CONTENT_TYPE;
                $this->outputContent = self::DEFAULT_CONTENT;
                return;
            }
            // 获取头
            $this->outputContentType = isset($result['information']['content_type']) ? $result['information']['content_type'] : self::DEFAULT_CONTENT_TYPE;
            if (!empty($this->outputContentType) && strpos($this->outputContentType, ';') === false) {
                $this->outputContentType .= '; charset=';
            }
            // 处理特殊文件
            $result['content'] = $this->handleContent($file, $result['content'], $this->outputContentType, $this->parameter['version']);
            /**
             * @todo Write Cache
             */
            // 合并内容
            $this->outputContent .= $result['content'];
        }
        /**
         * @todo Write Cache
         */
        return;
    }

    public function show()
    {
        $this->output();
        header(sprintf('Content-type: %s', $this->outputContentType));
        echo($this->outputContent);
        return;
    }
}