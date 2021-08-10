<?php

namespace Alone\LaravelHuaweiPush;

use Alone\LaravelHuaweiPush\Exceptions\CouldNotSendNotification;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades;
use Throwable;

class HuaweiPushChannel
{
	const MAX_TOKEN_PER_REQUEST = 500;
	
	/**
	 * @var Dispatcher
	 */
	protected $events;
	
	/**
	 * @var array
	 */
    protected $config;

    public function __construct(Dispatcher $dispatcher)
    {
	    $this->events = $dispatcher;
	    
    	$cfg = 'huawei_push';
        is_string($cfg) && $cfg = config("services.$cfg") ?: [];
        $this->config = (array)$cfg;
    }
	
	/**
	 * 华为推送
	 *
	 * @param mixed $notifiable
	 * @param Notification $notification
	 * @return mixed
	 * @throws CouldNotSendNotification
	 */
    public function send($notifiable, Notification $notification)
    {
	    $token = $notifiable->routeNotificationFor('huaweiPush', $notification);
	
	    $token = $token && !is_array($token) ? [$token] : $token;
	    if (empty($token)) return [];
	    
        $config = $this->getConfig();
	    $app_id = data_get($config,'appid');
	    if (!$app_id)
	    {
		    Facades\Log::warning("huawei push error: none config \t", compact('config', 'token'));
		    return false;
	    }
	    
	    $app_secret = data_get($config,'secret');
        if (!$app_secret)
        {
            Facades\Log::warning("huawei push error: none config \t", compact('config', 'token'));
            return false;
        }
	
	    $app = new HuaweiPushApplication($app_id, $app_secret);
        
        /** @var $notification Notification|HuaweiNotification */
        $message = $notification->toHuaweiPush($notifiable, $config);
	
	    if (! $message instanceof HuaweiMessage) {
		    throw CouldNotSendNotification::invalidMessage();
	    }
        
        $message->token($token);
        
	    $response = $app->push_send_msg($mdt = $message->getFields());
        $eno = data_get($response, 'code');
        if ($eno != '80000000')
        {
            $rts = [];
            if ($eno == '80300007')
            {
                $rts = $token;
                Facades\Log::notice("huawei push with illegal_token \t", compact('eno','response', 'mdt'));
            }
            elseif ($eno == '80100000')
            {
                $edt = data_get($response,'msg');
                $edt = is_array($edt) ? $edt : (json_decode($edt,true) ?: []);
                $rts = data_get($edt,'illegal_tokens') ?: [];
                Facades\Log::notice("huawei push success with illegal_tokens \t", compact('eno','response', 'mdt'));
            }
            else
            {
                Facades\Log::warning("huawei push error \t", compact('eno','response', 'mdt'));
            }
            
            if ($rts && method_exists($notifiable,'invalidNotificationRouters'))
            {
                $notifiable->invalidNotificationRouters($this, $rts, 'token');
            }
        }
        else
        {
            Facades\Log::debug("huawei push success \t",compact('eno','response','mdt'));
        }
        return $response;
    }

    public function getConfig($pkg = null, $dvc = null)
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
        return Arr::except($cfg, ['android', 'ios', 'bundles']);
    }
    
	/**
	 * Dispatch failed event.
	 *
	 * @param mixed $notifiable
	 * @param Notification $notification
	 * @param Throwable $exception
	 * @return array|null
	 */
	protected function failedNotification($notifiable, Notification $notification, Throwable $exception)
	{
		return $this->events->dispatch(new NotificationFailed(
			$notifiable,
			$notification,
			self::class,
			[
				'message' => $exception->getMessage(),
				'exception' => $exception,
			]
		));
	}

}
