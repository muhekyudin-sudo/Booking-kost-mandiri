<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];
    private array $prefix = [''];
    private array $mw = [[]];

    public function group(string $prefix, array $middleware, callable $fn): void
    {
        $this->prefix[] = end($this->prefix) . $prefix;
        $this->mw[]     = array_merge(end($this->mw), $middleware);
        $fn($this);
        array_pop($this->prefix);
        array_pop($this->mw);
    }

    public function get(string $path, mixed $handler, array $mw = []): void  { $this->add('GET', $path, $handler, $mw); }
    public function post(string $path, mixed $handler, array $mw = []): void { $this->add('POST', $path, $handler, $mw); }

    private function add(string $method, string $path, mixed $handler, array $mw): void
    {
        $full  = rtrim(end($this->prefix) . $path, '/') ?: '/';
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#i', '(?P<$1>[^/]+)', $full) . '$#';
        $this->routes[] = [
            'method' => $method, 'regex' => $regex, 'handler' => $handler,
            'mw'     => array_merge(end($this->mw), $mw),
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path   = rtrim(rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/'), '/') ?: '/';
        $method = strtoupper($method) === 'HEAD' ? 'GET' : strtoupper($method);
        $pathExists = false;

        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $path, $m)) continue;
            $pathExists = true;
            if ($r['method'] !== $method) continue;

            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            if ($method === 'POST') csrf_verify();            // sebelum controller
            foreach ($r['mw'] as $spec) run_middleware($spec, $params);

            $h = $r['handler'];
            if (is_array($h)) { [$class, $fn] = $h; (new $class())->$fn($params); }
            else { $h($params); }
            return;
        }
        abort($pathExists ? 405 : 404);
    }
}
