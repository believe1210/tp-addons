<?php

namespace think\addons;

use think\facade\Config;
use think\exception\HttpException;
use think\facade\Hook;
use think\facede\Loader;
use think\Request;
use think\App;
use think\Container;

/**
 * 插件执行默认控制器
 * @package think\addons
 */
class Route
{
    protected $request;

    public function __construct(App $app, Request $request, $controller = '')
    {
        $this->app = $app;
        $this->request = $request;

        // 处理路由参数
        $route = [
            $this->request->param('addon'),
            $this->request->param('controller'),
            $this->request->param('action'),
        ];
        // 是否自动转换控制器和操作名
        $convert = Config::get('app.url_convert');
        // 格式化路由的插件位置
        $this->action = $convert ? strtolower(array_pop($route)) : array_pop($route);
        $this->controller = $convert ? strtolower(array_pop($route)) : array_pop($route);
        $this->addon = $convert ? strtolower(array_pop($route)) : array_pop($route);
        // 生成view_path
        $view_path = ADDON_PATH . $this->addon . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;
        // 重置配置

        config('template.view_path', $view_path);
        $this->request->setController($this->controller);
        $this->request->setAction($this->action);
        $class = $app->parseClass($this->addon, 'controller', $this->controller);
    }
    /**
     * 插件执行
     */
    public function execute($addon = null, $controller = null, $action = null)
    {

        $request = Request();
        // 是否自动转换控制器和操作名
        $convert = Config::get('url_convert');
        $filter = $convert ? 'strtolower' : 'trim';

        $addon = $addon ? trim(call_user_func($filter, $addon)) : '';
        $controller = $controller ? trim(call_user_func($filter, $controller)) : 'index';
        $action = $action ? trim(call_user_func($filter, $action)) : 'index';

        Hook::listen('addon_begin', $request);
        if (!empty($addon) && !empty($controller) && !empty($action)) {
            $info = get_addon_info($addon);
            if (!$info) {
                throw new HttpException(404, __('addon %s not found', $addon));
            }
            if (!$info['state']) {
                throw new HttpException(500, __('addon %s is disabled', $addon));
            }
            $dispatch = $request->dispatch();
//            halt($dispatch);
//            if (isset($dispatch['var']) && $dispatch['var']) {
//                //$request->route($dispatch['var']);
//            }

            // 设置当前请求的控制器、操作
            $request->setController($controller);
            $request->setAction($action);
             // 监听addon_module_init
            Hook::listen('addon_module_init', $request);
            // 兼容旧版本行为,即将移除,不建议使用
            Hook::listen('addons_init', $request);

            $class = get_addon_class($addon, 'controller', $controller);
            if (!$class) {
                throw new HttpException(404, __('addon controller %s not found', Loader::parseName($controller, 1)));
            }

            return $this->app->invokeMethod([$class, $this->action]);

        } else {
            abort(500, lang('addon can not be empty'));
        }
    }

}
