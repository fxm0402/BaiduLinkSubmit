<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Typecho 后台发布或更新文章时，文章链接将会自动推送到百度收录平台。
 * @package BaiduLinkSubmit
 * @author 湘铭呀！
 * @version 1.0.0
 * @link https://xiangming.site/
 */


class BaiduLinkSubmit_Plugin implements Typecho_Plugin_Interface
{
    const LOG_FILE = __DIR__ . '/log.txt';

    /* 激活插件方法 */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array(__CLASS__, 'render');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array(__CLASS__, 'render');
        return _t('请设置 <b>站点域名</b> 和 <b>密钥</b>');
    }

    /* 禁用插件方法 */
    public static function deactivate() {}

    /* 插件配置方法 */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        preg_match("/^(http(s)?:\/\/)?([^\/]+)/i", Helper::options()->siteUrl, $matches);
        $domain = $matches[3] ? $matches[3] : '';
        $site = new Typecho_Widget_Helper_Form_Element_Text('site', NULL, $domain, _t('站点域名'), _t('站长工具中添加的域名'));
        $form->addInput($site->addRule('required', _t('请填写站点域名')));

        $token = new Typecho_Widget_Helper_Form_Element_Text('token', NULL, '', _t('准入密钥'), _t('更新密钥后，请同步修改此处密钥，否则身份校验不通过将导致数据发送失败。'));
        $form->addInput($token->addRule('required', _t('请填写准入密钥')));

        // 显示日志
        $logs = self::getLogs();
        $logDisplay = new Typecho_Widget_Helper_Form_Element_Textarea('logDisplay', NULL, $logs, _t('推送日志'), _t('最近20条百度推送日志'));
        $logDisplay->input->setAttribute('style', 'width: 100%; height: 400px; display: none;');
        $form->addInput($logDisplay->addRule('required', _t('推送日志显示')));

        // 添加日志展示样式
        echo '<style>
            .log-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .log-table th, .log-table td {
                border: 1px solid #ddd;
                padding: 8px;
            }
            .log-table th {
                padding-top: 12px;
                padding-bottom: 12px;
                text-align: left;
                background-color: #f2f2f2;
            }
            .log-status-success {
                color: green;
            }
            .log-status-failure {
                color: red;
            }
        </style>';
        echo self::displayLogs();
    }

    /* 个人用户的配置方法 */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /* 插件实现方法 */
    public static function render($contents, $widget)
    {
        $options = Helper::options();
        $site = $options->plugin('BaiduLinkSubmit')->site;
        $token = $options->plugin('BaiduLinkSubmit')->token;

        $urls = array($widget->permalink);
        $api = sprintf('http://data.zz.baidu.com/urls?site=%s&token=%s', $site, $token);

        $client = Typecho_Http_Client::get();
        $time = date('Y-m-d H:i:s');
        if ($client) {
            $client->setData(implode(PHP_EOL, $urls))
                   ->setHeader('Content-Type', 'text/plain')
                   ->setTimeout(30)
                   ->send($api);

            $response = $client->getResponseBody();
            $responseData = json_decode($response, true);
            $result = '';
            
            if ($client->getResponseStatus() == 200 && isset($responseData['success'])) {
                $result = "成功";
            } else {
                $result = "失败";
                if (isset($responseData['error'])) {
                    $result .= " - " . $responseData['message'];
                }
            }

            $log_entry = "$time [$result] $urls[0]\n";
            file_put_contents(self::LOG_FILE, $log_entry, FILE_APPEND);
        }
        return false;
    }

    /* 获取日志 */
    private static function getLogs()
    {
        if (file_exists(self::LOG_FILE)) {
            $logs = array_reverse(array_slice(file(self::LOG_FILE), -20));
            $logText = '<table class="log-table"><tr><th>状态</th><th>链接</th><th>时间</th></tr>';
            foreach ($logs as $log) {
                preg_match('/\[(成功|失败.*?)\]/', $log, $matches);
                $statusClass = (isset($matches[1]) && strpos($matches[1], '成功') !== false) ? 'log-status-success' : 'log-status-failure';
                $logText .= "<tr><td class='{$statusClass}'>" . htmlspecialchars($matches[1]) . "</td><td>" . htmlspecialchars($log) . "</td><td>" . htmlspecialchars($log) . "</td></tr>";
            }
            $logText .= '</table>';
            return $logText;
        }
        return '暂无日志记录。';
    }

    /* 显示日志 */
    private static function displayLogs()
    {
        $logs = self::getLogs();
        echo $logs;
    }
}
?>
