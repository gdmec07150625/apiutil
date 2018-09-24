<?php

namespace Apiutil\Console\Command;

use Apiutil\Blueprint\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Illuminate\Support\Arr;
use Dingo\Api\Routing\Router;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class Docs extends Command
{
    /**
     * Router instance.
     *
     * @var \Dingo\Api\Routing\Router
     */
    protected $router;

    /**
     * The blueprint instance.
     *
     * @var \Dingo\Blueprint\Blueprint
     */
    protected $blueprint;

    /**
     * Blueprint instance.
     *
     * @var \Dingo\Blueprint\Blueprint
     */
    protected $docs;

    /**
     * Default documentation name.
     *
     * @var string
     */
    protected $name;

    /**
     * Default documentation version.
     *
     * @var string
     */
    protected $version;

    protected $outputDir;

    //文档类型
    const CUSTOMER = 1;
    const RIDER = 2;
    const BUSINESS = 3;
    const POS = 4;
    const COMMON = 5;

    //mapping
    const MAPPING = [
        self::CUSTOMER => 'customer',
        self::RIDER => 'rider',
        self::BUSINESS => 'business',
        self::POS => 'pos',
        self::COMMON => 'common'
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'util:docs {--name= : Name of the generated documentation}
                                     {--use-version= : Version of the documentation to be generated}
                                     {--type= : 1-customer 2-rider 3-business 4-POS 5-common}
                                     {--output-dir= : Output the generated documentation to a dir}
                                     {--include-path= : Path where included documentation files are located}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API documentation from annotated controllers';

    /**
     * Create a new docs command instance.
     *
     * @param \Dingo\Api\Routing\Router  $router
     * @param \Dingo\Blueprint\Blueprint $blueprint
     * @param \Dingo\Blueprint\Writer    $writer
     * @param string                     $name
     * @param string                     $version
     *
     * @return void
     */
    public function __construct(Router $router, Blueprint $blueprint, $name='docs', $version='v1')
    {
        parent::__construct();

        $this->router = $router;
        $this->blueprint = $blueprint;
        $this->name = $name;
        $this->version = $version;
        $this->outputDir = base_path('docs');

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //清空当前目录的所有文档
        $this->delDir($this->getOutputDir());
        $writer = new Filesystem();
        if (!$writer->isDirectory($this->getOutputDir())){
            $writer->makeDirectory($this->getOutputDir());
        }
        //生成README文档
        $contents = $this->createReadme($this->getType());
        $file = $this->getOutputDir().'README.md';
        $writer->put($file, $contents);

        $this->blueprint->generate($this->getControllers(self::MAPPING[$this->getType()]), $this->getType(), $this->getVersion(), $this->getIncludePath(),$this->getOutputDir());

        return $this->info('Documentation was generated successfully.');
    }


    /**
     * Get the documentation name.
     *
     * @return string
     */
    protected function getDocName()
    {
        $name = $this->option('name') ?: $this->name;

        if (! $name) {
            $this->comment('A name for the documentation was not supplied. Use the --name option or set a default in the configuration.');

            exit;
        }

        return $name;
    }

    /**
     * 输出文档类型
     * 类型说明：1-用户端 2-骑手端 3-商家端 4-POS端 5-公共接口
     */
    protected function getType () {
        $arr = [self::CUSTOMER, self::RIDER, self::BUSINESS, self::POS, self::COMMON];
        $type = $this->option('type') ?: 0;
        if (!in_array($type, $arr)) {
            $this->comment('Type does not exist');
            exit;
        }
        return $type;
    }

    /**
     * 文档输出文件夹
     */
    protected function getOutputDir () {
        $dir = $this->option('output-dir') ?: $this->outputDir;
        return $dir.DIRECTORY_SEPARATOR;
    }

    /**
     * Get the include path for documentation files.
     *
     * @return string
     */
    protected function getIncludePath()
    {
        return base_path($this->option('include-path'));
    }

    /**
     * Get the documentation version.
     *
     * @return string
     */
    protected function getVersion()
    {
        $version = $this->option('use-version') ?: $this->version;

        if (! $version) {
            $this->comment('A version for the documentation was not supplied. Use the --use-version option or set a default in the configuration.');

            exit;
        }

        return $version;
    }

    /**
     * Get all the controller instances.
     *
     * @return array
     */
    protected function getControllers($type)
    {
        $controllers = new Collection;

        foreach ($this->router->getRoutes() as $collections) {
            foreach ($collections as $route) {
                if ($controller = $route->getControllerInstance()) {
                    $this->addControllerIfNotExists($controllers, $controller, $type);
                }
            }
        }

        return $controllers;
    }

    /**
     * Add a controller to the collection if it does not exist. If the
     * controller implements an interface suffixed with "Docs" it
     * will be used instead of the controller.
     *
     * @param \Illuminate\Support\Collection $controllers
     * @param object                         $controller
     *
     * @return void
     */
    protected function addControllerIfNotExists(Collection $controllers, $controller, $type)
    {
        $class = get_class($controller);

        if ($controllers->has($class)) {
            return;
        }

        $reflection = new ReflectionClass($controller);

        $interface = Arr::first($reflection->getInterfaces(), function ($key, $value) {
            return ends_with($key, 'Docs');
        });

        if ($interface) {
            $controller = $interface;
        }
        $arr = explode('\\', $class);
        if (strtolower($arr[count($arr)-2] === $type)) {
            $controllers->put($class, $controller);
        }
    }

    /**
     * 生成readme文档
     */
    protected function createReadme ($type) {
        $contents = '';
        switch ($type){
            case self::CUSTOMER:
                $contents .= '# 用户端';
                break;
            case self::RIDER:
                $contents .= '# 骑手端';
                break;
            case self::BUSINESS:
                $contents .= '# 商家端（商家APP/商家PC）';
                break;
            case self::POS:
                $contents .= '# POS/PAD端';
                break;
            case self::COMMON:
                $contents .= '# 公共接口';
                break;
        }
        return $contents;
    }

    /**
     * 清空文件夹内容
     * @param $dir
     */
    protected function delDir($dir){
        if(is_dir($dir)){
            $p = scandir($dir);
            foreach($p as $val){
                if($val !="." && $val !=".."){
                    if(is_dir($dir.$val)){
                        $this->delDir($dir.$val.DIRECTORY_SEPARATOR);
                        //@rmdir($dir.$val.DIRECTORY_SEPARATOR);
                    }else{
                        unlink($dir.$val);
                    }
                }
            }
        }
    }
}
