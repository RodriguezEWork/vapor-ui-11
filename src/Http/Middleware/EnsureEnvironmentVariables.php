<?php

namespace rodriguezework\VaporUi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use RuntimeException;

class EnsureEnvironmentVariables
{
    /**
     * The list of needed configs.
     *
     * @var array
     */
    protected $configs = [
        'project',
        'environment',
        'region',
        'key',
        'secret',
    ];

    /**
     * Ensures environment variables are properly configured.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if(env('APP_ENV') == 'local') {
            return $next($request);
        }

        $message = 'Unable to detect [vapor-ui.%s]. Please deploy your project, and visit this URI on a Vapor powered environment.';
        $config = config('vapor-ui');

        collect($this->configs)->each(function ($name) use ($message, $config) {
            if (empty(Arr::get($config, $name))) {
                throw new RuntimeException(sprintf($message, $name));
            }
        });

        return $next($request);
    }
}
