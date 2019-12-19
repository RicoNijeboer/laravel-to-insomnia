<?php


namespace Rico\Insomnia\Commands;

use Carbon\Carbon;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Rico\Insomnia\Entities\Insomnia;
use Rico\Insomnia\Entities\InsomniaEntity;
use Rico\Insomnia\Entities\InsomniaEnvironment;
use Rico\Insomnia\Entities\InsomniaRequest;

/**
 * Class RoutesToInsomnia
 *
 * @package Rico\Insomnia\Commands
 */
class RoutesToInsomnia extends Command
{

    /**
     * @var string
     */
    protected $signature = 'make:insomnia {name} {--description} {--filter}';

    /**
     * @param \Illuminate\Routing\Router       $router
     * @param \Rico\Insomnia\Entities\Insomnia $insomnia
     *
     * @throws \Exception
     */
    public function handle(Router $router, Insomnia $insomnia): void
    {
        $filter = $this->option('filter') ?? '';
        $workspace = $insomnia->workspace($this->argument('name'), $this->option('description') ?? '');

        /** @var InsomniaEnvironment $environment */
        $environment = $workspace->createEnvironment('Base Environment');
        $environment->setData('baseUrl', url(''));

        /** @var Route $route */
        foreach ($router->getRoutes() as $route)
        {
            $name = $this->getRouteName($route);
            $middlewares = $this->getRouteMiddleware($route);

            if ( ! Str::contains($middlewares, 'api'))
            {
                continue;
            }

            if ( ! empty($filter) && ! Str::contains($name, $filter))
            {
                continue;
            }

            $this->createRouteRequests($route, $workspace, $name);
        }

        $fileName = $this->ask('File name (extension is already included)?', Carbon::now()
                                                                                   ->format('d-m-Y H:i:s')) . '.json';
        $path = storage_path('insomnia' . DIRECTORY_SEPARATOR . $fileName);

        $this->createGitIgnoreFile();

        file_put_contents($path, json_encode($insomnia));
    }

    /**
     * @param \Illuminate\Routing\Route $route
     *
     * @return string
     */
    public function getRouteDescription(Route $route): string
    {
        return <<<END
Action: {$this->getRouteAction($route)}
Middleware(s): {$this->getRouteMiddleware($route)}
END;
    }

    /**
     * @param \Illuminate\Routing\Route $route
     *
     * @return string
     */
    public function getRouteName(Route $route): string
    {
        return $route->uri();
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
     * @param \Illuminate\Routing\Route              $route
     * @param \Rico\Insomnia\Entities\InsomniaEntity $workspace
     * @param string                                 $name
     *
     * @throws \Exception
     */
    public function createRouteRequests(Route $route, InsomniaEntity $workspace, string $name): void
    {
        foreach ($route->methods() as $method)
        {
            if (strtoupper($method) === 'HEAD')
            {
                continue;
            }

            $workspace->request(
                $name,
                $this->getRouteDescription($route),
                $this->getRouteDomain($route) . '/' . $route->uri(),
                strtoupper($method)
            );
        }
    }

    /**
     * @param \Illuminate\Routing\Route $route
     *
     * @return string|null
     */
    public function getRouteDomain(Route $route): string
    {
        if (Str::contains(config('app.url'), 'https'))
        {
            return ! empty($route->getDomain()) ? 'https://' . $route->getDomain() : config('app.url');
        }

        return $route->getDomain() ?? url('');
    }

    /**
     * @param \Illuminate\Routing\Route $route
     *
     * @return string
     */
    private function getRouteAction(Route $route): string
    {
        return ltrim($route->getActionName(), '\\');
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
}