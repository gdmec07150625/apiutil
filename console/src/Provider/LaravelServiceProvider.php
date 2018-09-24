<?php
/**
 * Created by PhpStorm.
 * User: ling
 * Date: 2018/9/24
 * Time: 14:16
 */

namespace Apiutil\Console\Provider;


use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;

class LaravelServiceProvider extends ServiceProvider {

    /**
     * 命令注册
     */
    public function register()
    {
        $this->registerDocsCommand();
    }

    /**
     * Register the documentation command.
     *
     * @return void
     */
    protected function registerDocsCommand()
    {
        $this->commands([\Apiutil\Console\Command\Docs::class]);
    }

}