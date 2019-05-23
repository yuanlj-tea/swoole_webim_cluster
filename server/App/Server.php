<?php

namespace App;

use App\Libs\Common;
use App\Libs\Predis;

/**
 * Created by PhpStorm.
 * User: yuanlj
 * Date: 2017/12/7
 * Time: 21:22
 */
class Server
{
    private $serv = null;

    private static $servPort = 9502;

    private $swoole_set = [
        'worker_num' => 4,
        'task_worker_num' => 4,
        'heartbeat_check_interval' => 30,
        'heartbeat_idle_time' => 60,
    ];

    private $channel_name = 'send_msg_channel';

    public function __construct()
    {
        $this->loadEnv();
        $this->serv = new \swoole_websocket_server('0.0.0.0', self::$servPort);
        $this->serv->set($this->swoole_set);
        $this->serv->on('open', array($this, 'onOpen'));
        $this->serv->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->serv->on('message', array($this, 'onMessage'));
        $this->serv->on('Task', array($this, 'onTask'));
        $this->serv->on('Finish', array($this, 'onFinish'));
        $this->serv->on('close', array($this, 'onClose'));
        $this->sendMsgProcess();
        $this->serv->start();
    }

    public function sendMsgProcess()
    {
        $sendMsgProcess = new \swoole_process(function ($process) {
            try {
                $conf = Config::getInstance()->getConf('config.redis');
                $redis = new Predis($conf);
                $redis->getRedis()->subscribe([$this->channel_name], function ($instance, $channelName, $message) {
                    $pushMsg = json_decode($message, 1);
                    $localIpPort = Room::getIpPort();
                    pp($localIpPort,$pushMsg['disfd']['server']);
                    if ($pushMsg['disfd']['server'] != $localIpPort) {
                        foreach ($this->serv->connections as $fd) {
                            pp("其它服务器发来的消息:本机fd:".$fd);
                            $pushMsg['data']['mine'] = 0;
                            $this->serv->push($fd, json_encode($pushMsg));
                        }
                    }
                    return true;
                });
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        });
        $this->serv->addProcess($sendMsgProcess);
    }

    public function onOpen($serv, $request)
    {
        $data = [
            'task' => 'open',
            'fd' => $request->fd
        ];
        $this->serv->task(json_encode($data));
        echo "open\n";
    }

    public function onWorkerStart($serv, $workerId)
    {
        // echo "worker进程id{$workerId}启动了\n";
        if ($workerId == 0) {
            echo "worker id 0启动，开始清理残余数据\n";
            Room::cleanData();
        }
    }

    public function onMessage($serv, $frame)
    {
        $data = json_decode($frame->data, true);
        switch ($data['type']) {
            case '1':#登录
                $data = [
                    'task' => 'login',
                    'params' => [
                        'name' => $data['name'],
                        'email' => $data['email'],
                    ],
                    'fd' => $frame->fd,
                    'roomid' => $data['roomid']
                ];
                if (!$data['params']['name'] || !$data['params']['email']) {
                    $data['task'] = 'nologin';
                    $this->serv->task(json_encode($data));
                    break;
                }
                $this->serv->task(json_encode($data));
                break;
            case '2':#新消息
                $data = [
                    'task' => 'new',
                    'params' => [
                        'name' => $data['name'],
                        'avatar' => $data['avatar'],
                    ],
                    'c' => $data['c'],
                    'message' => $data['message'],
                    'fd' => $frame->fd,
                    'roomid' => $data['roomid']
                ];
                $this->serv->task(json_encode($data));
                break;
            case '3':#改变房间
                $data = [
                    'task' => 'change',
                    'params' => [
                        'name' => $data['name'],
                        'avatar' => $data['avatar']
                    ],
                    'fd' => $frame->fd,
                    'oldroomid' => $data['oldroomid'],
                    'roomid' => $data['roomid']
                ];
                $this->serv->task(json_encode($data));
                break;
            case 'heartbeat':
                $data = [
                    'code' => 7,
                    'type' => 'heartbeat',
                    'msg' => 'ok'
                ];
                $this->serv->push($frame->fd, json_encode($data));
                return 'Finished';
                break;
            default:
                $this->serv->push($frame->fd, json_encode(array('code' => 0, 'msg' => 'type error')));
                break;
        }
    }

    public function onTask($serv, $task_id, $from_id, $data)
    {
        $pushMsg = ['code' => 0, 'msg' => '', 'data' => []];
        $data = json_decode($data, true);
        switch ($data['task']) {
            case 'open':
                $pushMsg = Room::open();
                $this->serv->push($data['fd'], json_encode($pushMsg));
                return 'Finished';
            case 'login':
                $pushMsg = Room::doLogin($data);
                break;
            case 'new':
                $pushMsg = Room::sendNewMsg($data);
                break;
            case 'logout':
                $pushMsg = Room::doLogout($data);
                break;
            case 'nologin':
                //$pushMsg = Chat::noLogin($data);
                //$this->serv->push($data['fd'], json_encode($pushMsg));
                return 'Finished';
                break;
            case 'change':
                $pushMsg = Room::changeRoom($data);
                break;
        }
        $this->sendMsg($pushMsg, $data['fd']);
        return 'Finished';
    }

    public function onClose($serv, $fd)
    {
        $pushMsg = array('code' => 0, 'msg' => '', 'data' => array());
        #获取用户信息
        $user = Room::getUserInfoByFd($fd);
        if ($user) {
            $data = array(
                'task' => 'logout',
                'params' => array('name' => $user['name']),
                'fd' => $fd
            );
            $this->serv->task(json_encode($data));
        }
        echo "client {$fd} closed\n";
    }

    public function sendMsg($pushMsg, $myfd)
    {
        foreach ($this->serv->connections as $fd) {
            if ($fd == $myfd) {
                $pushMsg['data']['mine'] = 1;
            } else {
                $pushMsg['data']['mine'] = 0;
            }
            $this->serv->push($fd, json_encode($pushMsg));
        }
        $pushMsg['disfd'] = json_decode(Room::getDistributeFd($myfd), true);
        $conf = Config::getInstance()->getConf('config.redis');
        $redis = (new Predis($conf))->getRedis();
        pp("发布消息" . json_encode($pushMsg));
        $redis->publish($this->channel_name, json_encode($pushMsg));
    }

    public function onFinish($serv, $task_id, $data)
    {
        // echo "Task {$task_id} finish\n";
        // echo "Result {$data}\n";
    }

    /**
     * 加载配置文件
     * @throws \Exception
     */
    public function loadEnv()
    {
        $files = Common::getFiles(SERVER_ROOT . '/Config');
        if (is_array($files)) {
            foreach ($files as $file) {
                $fileNameArr = explode('.', $file);
                $fileSuffix = end($fileNameArr);
                if ($fileSuffix == 'php') {
                    Config::getInstance()->loadFile($file);
                }
            }
        } else {
            throw new \Exception("没有要加载的配置文件");
        }
    }

    public static function getServerPort()
    {
        return self::$servPort;
    }
}