<?php

namespace App\Providers;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Illuminate\Auth\Events\Login' => [
            'App\Listeners\LoginListener'
        ],
        'Illuminate\Auth\Events\Logout' => [
            'App\Listeners\LogoutListener'
        ],
        'App\Events\SeatExchangeApplied' =>[
            'App\Listeners\HandleSeatExchangeApplied'
        ],
        'App\Events\SeatExchanged'=>[
            'App\Listeners\HandleSeatExchanged'
        ],
        'App\Events\DelegationCreated'=>[
            'App\Listeners\HandleDelegationCreated'
        ],
        'App\Events\DelegationUpdated'=>[
            'App\Listeners\HandleDelegationUpdated'
        ]
    ];

    /**
     * Register any other events for your application.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher $events
     * @return void
     */
    public function boot(DispatcherContract $events)
    {
        parent::boot($events);

        //
    }
}
