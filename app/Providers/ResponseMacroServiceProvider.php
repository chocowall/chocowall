<?php

namespace App\Providers;

use Illuminate\Support\Facades\Response;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Arr;

class ResponseMacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //

        Response::macro('atom', function ($xml, $status = 200, array $header = [])
        {
            if (is_null($xml))
                $xml = new DOMDocument('1.0', 'utf-8');
            if (empty($header) || !Arr::has($header, 'Content-Type'))
                $header['Content-Type'] = 'application/atom+xml;type=feed;charset=utf-8';
            return Response::make($xml->saveXML(), $status, $header);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
