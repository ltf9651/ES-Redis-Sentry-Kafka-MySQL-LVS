<?php
/**
 * Created by PhpStorm.
 * User: LTF
 * Date: 2018/12/17
 * Time: 23:06
 */

namespace redismailer\mailerquene;

use Yii;

class Message extends \yii\swiftmailer\Mailer
{
    public function quene()
    {
        //\Yii::$app->mailer->compose()->setFrom()->setTo();
        $redis = \Yii::$app->redis;
        if (empty($redis)) {
            throw new \yii\base\InvalidConfigException('redis not found in config');
        }

        $mailer = \Yii::$app->mailer;
        if (empty($mailer) || !$redis->select($mailer->db)) {
            throw new \yii\base\InvalidConfigException('db not defined');
        }

        //收集邮件信息
        $message = [];
        $message['from'] = array_keys($this->from);
        $message['to'] = array_keys($this->getTo());
        $message['cc'] = array_keys($this->getCc());
        $message['bcc'] = array_keys($this->getBcc());
        $message['reply_to'] = array_keys($this->getReplyTo());
        $message['charset'] = array_keys($this->getCharset());
        $message['subject'] = array_keys($this->getSubject());

        //获取邮件内容
        $parts = $this->getSwiftMessage()->children();
        if (!is_array($parts) || !sizeof($parts)) {
            $parts = [];
        }
        foreach ($parts as $part) {
            if (!$part instanceof \Swift_Mime_Attachment) {
                switch ($part->getContentType()) {
                    case 'text/html':
                        $message['html_body'] = $part->getBody();
                        break;
                    case 'text/plain':
                        $message['text_body'] = $part->getBody();
                        break;
                }
                if (!$message['charset']) {
                    $message['charset'] = $part->getCharset();
                }
            }
        }

        // 存储邮件队列
        return $redis->rpush($mailer->key, json_encode($message));
    }
}