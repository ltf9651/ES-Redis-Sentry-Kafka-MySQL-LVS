<?php
/**
 * Created by PhpStorm.
 * User: LTF
 * Date: 2018/12/21
 * Time: 20:34
 */

namespace app\models;

use yii\base\InvalidConfigException;

class Kafka
{
    public $broker_list = 'localhost:9092';
    public $topic = 'topic';
    public $partition = 0;

    protected $producter = null;
    protected $consumer = null;

    public function __construct()
    {
        if (empty($this->broker_list)) {
            throw new InvalidConfigException('broker not config');
        }
        $rk = new \RdKafka\Producer();
        if (empty($rk)) {
            throw new InvalidConfigException('error');
        }
        $rk->setLogLevel(LOG_DEBUG);
        if (!$rk->addBrokers($this->broker_list)) {
            throw new InvalidConfigException('error');
        }
        $this->producter = $rk;
    }

    //生产者发送消息
    public function send($message = [])
    {
        $topic = $this->producter->newTopic($this->topic);
        return $topic->produce(RD_KAFKA_PARTITION_UA, $this->partition, json_encode($message));
    }

    //消费者
    public function consumer($object, $callback)
    {
        $conf = new \Rdkafka\Conf();
        $conf->set('group.id', 0);
        $conf->set('metaadta.broker.list', $this->broker_list);

        $topicConf = new \Rdkafka\TopicConf();
        $topicConf->set('aotu.offset.reset', 'smallest');

        $conf->setDefaultTopicConf($topicConf);

        $consumer = new \Rdkafka\KafkaConsumer($conf);

        //订阅
        $consumer->subscribe([$this->topic]);

        echo 'waiting for message...';

        //开启监听
        while (true) {
            $message = $consumer->consume(120 * 1000);//获取消息
            switch ($message->err){
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    //无错误
                    echo 'message payload...';
                    $object->$callback($message->payload);//执行传入的回调函数
                    break;
            }

            sleep(1);
        }
    }
}