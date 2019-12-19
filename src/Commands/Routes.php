<?php

namespace Rico\Insomnia\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionParameter;
use Rico\Insomnia\Entities\Insomnia;
use Rico\Insomnia\Entities\InsomniaEntity;
use Rico\Insomnia\Entities\InsomniaEnvironment;
use Rico\Insomnia\Entities\InsomniaWorkspace;
use Faker\Generator as Faker;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class Routes
 *
 * @package Rico\Insomnia\Commands
 */
class Routes extends Command
{

    /**
     * @var string
     */
    protected $signature = 'route:map {name} {--url=} {--description=}';

    /**
     * @var \Faker\Generator
     */
    private $faker;

    /**
     * Routes constructor.
     *
     * @param \Faker\Generator $faker
     */
    public function __construct(Faker $faker)
    {
        parent::__construct();
        $this->faker = $faker;
    }

    /**
     * @param \Illuminate\Routing\Router       $router
     * @param \Rico\Insomnia\Entities\Insomnia $insomnia
     *
     * @throws \Exception
     */
    public function handle(Router $router, Insomnia $insomnia)
    {
        $name = $this->argument('name');
        $routes = $this->getRoutes($router);
        $map = $this->getMappedRoutes($routes);

        $workspace = $insomnia->workspace($name, $this->option('description') ?? '');

        /** @var InsomniaEnvironment $environment */
        $environment = $workspace->createEnvironment('Base Environment');
        $environment->setData('baseUrl', $this->getDomain());

        $this->createInsomnia($workspace, $map);

        $path = storage_path(implode(DIRECTORY_SEPARATOR, [
            'insomnia',
            Str::kebab($name) . '-export.json'
        ]));
        $this->createGitIgnoreFile();

        file_put_contents($path, json_encode($insomnia));
    }

    /**
     * @param InsomniaEntity|InsomniaWorkspace $folder
     * @param mixed[]                          $map
     *
     * @throws \Exception
     */
    private function createInsomnia(&$folder, array $map)
    {
        /**
         * @var string        $key
         * @var Route|mixed[] $value
         */
        foreach ($map as $key => $value)
        {
            if ( ! ($value instanceof Route))
            {
                $subFolder = $folder->folder(ucfirst($key));

                $this->createInsomnia($subFolder, $value);
                continue;
            }

            $url = '{{ baseUrl }}/' . $value->uri;
            $action = $this->getRouteAction($value);
            $middleware = $this->getRouteMiddleware($value);
            $description = "Action: {$action}\nMiddleware: {$middleware}";

            foreach ($value->methods as $method)
            {
                if (strtoupper($method) === 'HEAD')
                {
                    continue;
                }

                $function = last(explode('@', $action));

                $request = $folder->request(ucfirst(Str::camel($function)), $description, $url, strtoupper($method));

                $this->setRequestBody($request, $value);
            }
        }
    }

    /**
     * @param string $uri
     *
     * @return array|string
     */
    private function getPath(string $uri)
    {
        return str_replace('/', '.', $uri);
    }

    /**
     * @param array $routes
     *
     * @return array
     */
    private function getMappedRoutes(array $routes): array
    {
        $map = [];

        /** @var Route $route */
        foreach ($routes as $route)
        {
            $uri = $route->uri;

            if (Str::startsWith($uri, 'api/'))
            {
                $uri = substr($uri, 4);
            }

            $path = $this->getPath($uri);

            $routes = Arr::get($map, $path, []);

            $routes[] = $route;

            Arr::set($map, $path, $routes);
        }

        return $map;
    }

    /**
     * @param \Illuminate\Routing\Router $router
     *
     * @return array
     */
    private function getRoutes(Router $router): array
    {
        return array_filter($router->getRoutes()->getIterator()->getArrayCopy(), function (Route $route) {
            return in_array('api', $route->gatherMiddleware());
        });
    }

    /**
     * Get before filters.
     *
     * @param \Illuminate\Routing\Route $route
     *
     * @return string
     */
    private function getRouteMiddleware(Route $route): string
    {
        return collect($route->gatherMiddleware())->map(function ($middleware) {
            return $middleware instanceof Closure ? 'Closure' : $middleware;
        })->implode(', ');
    }

    /**
     * @return void
     */
    public function createGitIgnoreFile(): void
    {
        if ( ! file_exists(storage_path('insomnia')))
        {
            mkdir(storage_path('insomnia'), 0777, true);
        }

        $gitignore = <<<GITIGNORE
*
!.gitignore
GITIGNORE;
        file_put_contents(storage_path('insomnia' . DIRECTORY_SEPARATOR . '.gitignore'), $gitignore);
    }

    /**
     * @param \Illuminate\Routing\Route $route
     * @param bool                      $short
     *
     * @return string
     */
    private function getRouteAction(Route $route, bool $short = true): string
    {
        $action = ltrim($route->getActionName(), '\\');

        return $short ? last(explode('\\', $action)) : $action;
    }

    /**
     * @return array|bool|string|null
     */
    private function getDomain()
    {
        $url = $this->option('url') ?? url('');

        if ( ! Str::startsWith($url, 'https://'))
        {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * @param \Rico\Insomnia\Entities\InsomniaEntity $insomniaRequest
     * @param \Illuminate\Routing\Route              $route
     *
     * @throws \ReflectionException
     */
    private function setRequestBody(InsomniaEntity &$insomniaRequest, Route $route)
    {
        $reflectionMethod = $this->getReflectionMethod($route);

        $parameters = array_map(function (ReflectionParameter $parameter) {
            $class = $parameter->getClass()->name;

            return new $class;
        }, $reflectionMethod->getParameters());

        $requests = array_filter($parameters, function ($instance) {
            return ($instance instanceof FormRequest);
        });

        if (count($requests) === 0)
        {
            return;
        }

        foreach ($requests as $request)
        {
            $rules = $request->rules();

            /**
             * @var string  $key
             * @var mixed[] $keyedRules
             */
            foreach ($rules as $key => $keyedRules)
            {
                if (is_string($keyedRules))
                {
                    $keyedRules = explode('|', $keyedRules);
                }

                if (in_array('nullable', $keyedRules))
                {
                    continue;
                }

                if (in_array('boolean', $keyedRules))
                {
                    $insomniaRequest->setBody($key, ($this->faker->boolean));
                    continue;
                }

                if (Str::contains($key, 'password'))
                {
                    $password = Str::random(16);
                    $insomniaRequest->setBody($key, $password);

                    if (in_array('confirmed', $keyedRules))
                    {
                        $insomniaRequest->setBody("{$key}_confirmation", $password);
                    }

                    continue;
                }

                $insomniaRequest->setBody($key, ($this->faker->$key ?? $this->faker->word));
            }
        }
    }

    /**
     * @param \Illuminate\Routing\Route $route
     *
     * @return \ReflectionMethod
     *
     * @throws \ReflectionException
     */
    private function getReflectionMethod(Route $route)
    {
        [$controller, $function] = explode('@', $this->getRouteAction($route, false));

        $controller = new ReflectionClass($controller);

        return $controller->getMethod($function);
    }
}