<?php
/**
 * Created by PhpStorm.
 * User: LTF
 * Date: 2018/12/17
 * Time: 23:31
 */

namespace redismailer\mailerquene;

use Yii;

class MailerQuene extends \yii\swiftmailer\Mailer
{
    public $messageClass = 'redismailer\mailerquene\Message';
    public $key = 'mails';
    public $db = '1';

    public function process()
    {
        $redis = Yii::$app->redis;
        if (empty($redis)) {
            throw new \yii\base\InvalidConfigException('redis not found in config');
        }
        if ($redis->select($mailer->db) && $messages = $redis->lrange($this->key, 0, -1)) {
            $messageObj = new Message();
            foreach ($messages as $message) {
                $message = json_encode($message, true);
                if (empty($message) || !$this->setMessage($messageObj, $message)) {
                    throw new \ServerErrorHttpException('message error');
                }
                if ($messageObj->send()) {
                    //发送成功进行删除
                    /*lrem :
                    count > 0 : 从表头开始向表尾搜索，移除与 VALUE 相等的元素，数量为 COUNT 。
                    count < 0 : 从表尾开始向表头搜索，移除与 VALUE 相等的元素，数量为 COUNT 的绝对值。
                    count = 0 : 移除表中所有与 VALUE 相等的值。*/
                    $redis->lrem($this->key, -1, json_encode($message));
                }
            }
        }

        return true;
    }

    public function setMessage($messageObj, $message)
    {
        if (empty($messageObj)) {
            return false;
        }
        if (!empty($message['from']) && !empty($message['to'])) {
            $messageObj->setFrom($message['from']);
            $messageObj->setTo($message['to']);
            if (!empty($message['cc'])) {
                $messageObj->setCc($message['cc']);

            }
            return $messageObj;
        }
        return false;
    }
}