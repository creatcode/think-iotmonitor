<?php

namespace CreatCode\ThinkIotMonitor\Http;

use CreatCode\ThinkIotMonitor\Monitor\OverviewReader;

class MonitorController
{
    /** 输出监控页面。 */
    public function index()
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.html';
        $html = (string) file_get_contents($file);
        $html = str_replace('/app/iotmonitor/index/overview', '/iotmonitor.php/overview', $html);
        $html = str_replace('/app/iotmonitor/', '/iotmonitor-assets/', $html);

        return $this->response($html, 'text/html; charset=utf-8');
    }

    /** 输出监控概览数据。 */
    public function overview($request = null)
    {
        $minutes = is_object($request) && method_exists($request, 'get') ? (int) $request->get('minutes', 60) : 60;
        if (!in_array($minutes, array(5, 60, 360, 1440), true)) {
            $minutes = 60;
        }

        $data = array('code' => 200, 'msg' => 'ok', 'data' => (new OverviewReader())->build($minutes));
        return function_exists('json')
            ? json($data)
            : $this->response(json_encode($data), 'application/json; charset=utf-8');
    }

    /** 创建框架响应；无框架响应类时直接返回内容。 */
    private function response(string $content, string $contentType)
    {
        if (class_exists('think\\Response')) {
            return \think\Response::create($content, 'html', 200, array(
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=3600',
            ));
        }

        return $content;
    }
}
