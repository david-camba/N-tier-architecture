<?php

/**
 * @method mixed get(string $key)
 */
interface ContextService {}


class ContextService_Base extends Service implements ContextService
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function getConfig($key, $default = null)
    {
        return $this->app->getConfig($key, $default);
    }
    
    public function get($key, $default = null)
    {
        return $this->app->getContext($key, $default);
    }
    
}