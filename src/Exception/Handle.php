<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole的简易微服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole\Exception;

use Exception;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\Facade\Config;
use Chency147\CliMessage\Message;
use Chency147\CliMessage\Style;

class Handle
{
    protected $render;

    protected $ignoreReport = [
        '\\Julibo\\Msfoole\\Exception\\HttpException',
    ];

    public function setRender($render)
    {
        $this->render = $render;
    }

    protected function isIgnoreReport(Exception $exception)
    {
        foreach ($this->ignoreReport as $class) {
            if ($exception instanceof $class) {
                return false;
            }
        }
        return false;
    }

    public function report(Exception $exception)
    {
        if (!$this->isIgnoreReport($exception)) {
            $isDebug = Config::get('application.debug');
            if ($isDebug) {
                $data = [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'message' => $this->getMessage($exception),
                    'code' => $this->getCode($exception),
                ];
                $log = "[{$data['code']}]{$data['message']}[{$data['file']}:{$data['line']}]";
            } else {
                $data = [
                    'message' => $this->getMessage($exception),
                    'code' => $this->getCode($exception),
                ];
                $log = "[{$data['code']}]{$data['message']}";
            }
            if (Config::get('log.record_trace')) {
                $log .= "\r\n" . $exception->getTraceAsString();
            }
            Log::record($log, 'error');
        }
    }

    protected function getCode(Exception $exception)
    {
        $code = $exception->getCode();

        if (!$code && $exception instanceof  ErrorException) {
            $code = $exception->getSeverity();
        }
        return $code;
    }

    protected function getMessage(Exception $exception)
    {
        $message = $exception->getMessage();
        if (PHP_SAPI == 'cli') {
            return $message;
        }
        return $message;
    }

    /**
     * 获取出错文件内容
     * 获取错误的前后9行
     * @access protected
     * @param \Exception $exception
     * @return array
     */
    protected function getSourceCode(Exception $exception)
    {
        $line = $exception->getLine();
        $first = ($line - 9 > 0) ? $line - 9 : 1;

        try {
            $contents = file($exception->getFile());
            $source = [
                'first' => $first,
                'source' => array_slice($contents, $first - 1, 19)
            ];
        } catch (Exception $e) {
            $source = [];
        }
        return $source;
    }

    public function render(Exception $exception)
    {
        $data = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'message' => $this->getMessage($exception),
            'code' => $this->getCode($exception),
        ];
        $msg = "[{$data['code']}]{$data['message']}[{$data['file']}:{$data['line']}]";
        echo $msg . PHP_EOL;
        $source = $this->getSourceCode($exception);
        if (!empty($source)) {
            $code = '+----------------------------------------------------------------------' . PHP_EOL;
            foreach ($source['source'] as $v) {
                $code .= '| ' . $v;
            }
            $code .= '+----------------------------------------------------------------------';

            echo $code;
        }
    }

    public function renderForConsole(Exception $exception)
    {
        $style = new Style();
        $message = new Message();
        $data = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'message' => $this->getMessage($exception),
            'code' => $this->getCode($exception),
        ];
        $msg = "[{$data['code']}]{$data['message']}[{$data['file']}:{$data['line']}]";
        $message->setContent($msg);
        $style->setForegroundColor(Style::COLOR_BLUE); // 定义颜色为蓝色
        echo $message->getContentWithStyle($style, PHP_EOL);

        $source = $this->getSourceCode($exception);
        if (!empty($source)) {
            $code = '+----------------------------------------------------------------------' . PHP_EOL;
            foreach ($source['source'] as $v) {
                $code .= '| ' . $v;
            }
            $code .= '+----------------------------------------------------------------------';
            $style->setForegroundColor(Style::COLOR_GREEN); // 定义颜色为绿色
            $message->setContent($code);
            echo $message->getContentWithStyle($style, PHP_EOL);
        }
    }
}