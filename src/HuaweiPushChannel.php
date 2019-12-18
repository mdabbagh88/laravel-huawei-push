<?php

namespace Alone\LaravelHuaweiPush;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades;

class HuaweiPushChannel
{

    protected $config;

    public function __construct($cfg = [])
    {
        is_string($cfg) && $cfg = config("services.$cfg") ?: [];
        $this->config = (array)$cfg;
    }

    /**
     * 发送小米推送
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return mixed
     */
    public function send($notifiable,Notification $notification)
    {
        $pkg = null;
        if(is_object($notifiable) && method_exists($notifiable,'routeNotificationFor'))
        {
            if(!$sto = $notifiable->routeNotificationFor('huaweiPush'))
            {
                return false;
            }
            if(method_exists($notifiable,'getAppPackage'))
            {
                $pkg = $notifiable->getAppPackage();
            }
            else
            {
                $pkg = data_get($notifiable,'app_package');
            }
        }
        else
        {
            $sto = $notifiable;
        }
        $cfg = $this->getConfig($this->config,$pkg);
        /** @var $notification Notification|HuaweiNotification */
        $msg = $notification->toHuaweiPush($notifiable,$cfg);
        $app = new Huawei\Application(
            data_get($cfg,'appid'),
            data_get($cfg,'secret'),
            Huawei\Constants::HW_TOKEN_SERVER,
            Huawei\Constants::HW_PUSH_SERVER
        );

        $msg->token((array)$sto); // 推送目标
        $msg->buildFields();
        $ret = $app->push_send_msg($mdt = $msg->getFields());
        $eno = data_get($ret,'code');
        if($eno != '80000000')
        {
            Facades\Log::warning("huawei push error \t",compact('eno','ret','sto','mdt'));
        }
        else
        {
            Facades\Log::debug("huawei push success \t",compact('mdt','ret','sto'));
        }
        return $ret;
    }

    public function getConfig($pkg = null,$dvc = null)
    {
        $cfg = $this->config ?: [];
        if(!empty($dvc) && isset($cfg[$dvc]))
        {
            $cfg = ($cfg[$dvc] ?: []) + $cfg;
        }
        if(!empty($pkg))
        {
            // 多包名不同配置
            if(isset($cfg['bundles'][$pkg]))
            {
                $cfg = ($cfg['bundles'][$pkg] ?: []) + $cfg;
            }
            elseif(isset($this->config['bundles'][$pkg]))
            {
                $cfg = ($this->config['bundles'][$pkg] ?: []) + $cfg;
            }
        }
        return Arr::except($cfg,['android','ios','bundles']);
    }

}