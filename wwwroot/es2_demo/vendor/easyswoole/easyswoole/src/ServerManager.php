<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/28
 * Time: 下午5:36
 */

namespace EasySwoole\EasySwoole;


use EasySwoole\Component\Singleton;
use EasySwoole\EasySwoole\Swoole\EventRegister;

class ServerManager
{
    use Singleton;

    private $swooleServer;
    private $mainServerEventRegister;

    private $subServer = [];
    private $subServerRegister = [];

    const TYPE_SERVER = 'SERVER';
    const TYPE_WEB_SERVER = 'WEB_SERVER';
    const TYPE_WEB_SOCKET_SERVER = 'WEB_SOCKET_SERVER';


    function __construct()
    {
        $this->mainServerEventRegister = new EventRegister();
    }
    /**
     * @param string $serverName
     * @return null|\swoole_server|\swoole_server_port
     */
    function getSwooleServer(string $serverName = null)
    {
        if($serverName === null){
            return $this->swooleServer;
        }else{
            if(isset($this->subServer[$serverName])){
                return $this->subServer[$serverName];
            }
            return null;
        }
    }

    function createSwooleServer($port,$type = self::TYPE_SERVER,$address = '0.0.0.0',array $setting = [],...$args):bool
    {
        switch ($type){
            case self::TYPE_SERVER:{
                $this->swooleServer = new \swoole_server($address,$port,...$args);
                break;
            }
            case self::TYPE_WEB_SERVER:{
                $this->swooleServer = new \swoole_http_server($address,$port,...$args);
                break;
            }
            case self::TYPE_WEB_SOCKET_SERVER:{
                $this->swooleServer = new \swoole_websocket_server($address,$port,...$args);
                break;
            }
            default:{
                Trigger::getInstance()->error('"unknown server type :{$type}"');
                return false;
            }
        }
        if($this->swooleServer){
            $this->swooleServer->set($setting);
        }
        return true;
    }


    public function addServer(string $serverName,int $port,int $type = SWOOLE_TCP,string $host = '0.0.0.0',array $setting = [
        "open_eof_check"=>false,
    ]):EventRegister
    {
        $eventRegister = new EventRegister();
        $this->subServerRegister[$serverName] = [
            'port'=>$port,
            'host'=>$host,
            'type'=>$type,
            'setting'=>$setting,
            'eventRegister'=>$eventRegister
        ];
        return $eventRegister;
    }

    function getMainEventRegister():EventRegister
    {
        return $this->mainServerEventRegister;
    }

    function start()
    {
        $events = $this->getMainEventRegister()->all();
        foreach ($events as $event => $callback){
            $this->getSwooleServer()->on($event, function (...$args) use ($callback) {
                foreach ($callback as $item) {
                    call_user_func($item,...$args);
                }
            });
        }
        $this->attachListener();
        $this->getSwooleServer()->start();
    }

    private function attachListener():void
    {
        foreach ($this->subServerRegister as $serverName => $server){
            $subPort = $this->getSwooleServer()->addlistener($server['host'],$server['port'],$server['type']);
            if($subPort){
                $this->subServer[$serverName] = $subPort;
                if(is_array($server['setting'])){
                    $subPort->set($server['setting']);
                }
                $events = $server['eventRegister']->all();
                foreach ($events as $event => $callback){
                    $subPort->on($event, function (...$args) use ($callback) {
                        foreach ($callback as $item) {
                            call_user_func($item,...$args);
                        }
                    });
                }
            }else{
                Trigger::getInstance()->throwable(new \Exception("addListener with server name:{$serverName} at host:{$server['host']} port:{$server['port']} fail"));
            }
        }
    }

    function getSubServerRegister():array
    {
        return $this->subServerRegister;
    }
}