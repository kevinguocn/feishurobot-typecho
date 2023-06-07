<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 飞书机器人推送
 *
 * @package FeishuRobot
 * @author kevinguo
 * @version 1.0
 * @link https://www.kevinguo.cn/
 */
class FeishuRobot_Plugin implements Typecho_Plugin_Interface
{
    /* 激活插件方法 */
    public static function activate()
    {
        //挂载评论接口
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('FeishuRobot_Plugin', 'send');

        return '插件已激活,请设置机器人相关信息';
    }

    /* 禁用插件方法 */
    public static function deactivate()
    {
        return '飞书机器人插件已被禁用，设置失效';
    }

    /* 插件配置方法 */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $webhook = new Typecho_Widget_Helper_Form_Element_Text('webhook', null, '', '机器人Webhook地址', '飞书机器人Webhook地址');
        $secret = new Typecho_Widget_Helper_Form_Element_Text('secret', null, '', 'Secret密钥', '飞书机器人Webhook地址，如果为空则不使用');
        $form->addInput($webhook);
        $form->addInput($secret);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {

    }

    /* 插件实现方法 */
    public static function render()
    {

    }

    /* 推送通知方法 */
    public static function send($post)
    {
        //获取系统配置
        $options = Helper::options();
        //判断是否配置webhook地址
        if (is_null($options->plugin('FeishuRobot')->webhook)) {
            throw new Typecho_Plugin_Exception(_t('Webhook地址未配置'));
        }
        //判断是否配置secret密钥 如果不配置secret
//        if (is_null($options->plugin('FeishuRobot')->secret)) {
//            throw new Typecho_Plugin_Exception(_t('Secret密钥未配置'));
//        }
        $webhook = $options->plugin('FeishuRobot')->webhook;
        if (is_null($options->plugin('FeishuRobot')->secret)) {
            $secret = null;
        } else {
            $secret = $options->plugin('FeishuRobot')->secret;
        }

        list($msec, $sec) = explode(' ', microtime());
        $timestamp = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $stringToSign = $timestamp . "\n" . $secret;
        $sign = urlencode(base64_encode(hash_hmac('sha256', $stringToSign, $secret, true)));
        $content = [
            "zh_cn" => [
                "title" => "有一条新评论",
                "content" => [
                    [
                        ["tag" => "text",
                            "text"=>"文章:" . $post->title,
                            ]
                    ],
                    [
                        ["tag"=>"text","text"=>"评论内容:" . $post->text]
                    ],
                    [
                        ["tag"=>"a",
                            "text"=>"点击查看",
                            "href"=>$post->permalink,
                        ]

                    ]
                ]
            ]
        ];
        $data = [
            'msg_type' => 'post',
            'content' => [
                "post"=>$content
            ]
        ];
        //计算sign签名校验
        if ($options->plugin('FeishuRobot')->secret) {
            $secret = $options->plugin('FeishuRobot')->secret;
            $data['timestamp'] = self::get_sign($secret)[0];
            $data['sign'] = self::get_sign($secret)[1];
        }


        $response = self::request($webhook, json_encode($data));
        if ($response['code'] !== 0) {
            echo "no";
            //发送失败，记录日志
            $log = @file_get_contents('./error.log');
            file_put_contents('./error.log', '[' . date("Y-m-d H:i:s") . ']' . $response['msg'] . $data);
        }
        if ($response['code'] === 0){
            echo "hello";
        }
    }

    /* Curl请求精简版 */
    private static function request($url, $postData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
        // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
        curl_close($ch);
        return json_decode($data, true);
    }

    private static function get_sign($secret): array
    {
        date_default_timezone_set('PRC');
        $timestamp = time();
        $StringToSign = $timestamp . '\n' . $secret;
        return [$timestamp,base64_encode(hash_hmac('sha256', $StringToSign, $secret, true))];

    }

}
