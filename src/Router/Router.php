<?php

namespace BMND\Router;

use ReflectionClass;
use BMND\DI;
use ReflectionMethod;
use BMND\Http\MiddlewareHandler;
use BMND\Http\ResponseInterface;
use BMND\Http\MiddlewareInterface;
use BMND\Http\Request;
use BMND\Http\Response;
use BMND\Http\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

class Router implements RequestHandlerInterface
{
	private array $routes = [];
	private array $namedRoutes = [];
	private array $middlewares = [];
	private string $controllersPath;
	private string $controllersNamespace;
	private ?string $cachePath = null;
	private array $errors = [];
	private bool $cacheEnabled = false;
	private static ?self $instance = null;
	private ServerRequestInterface $request;
	private array $groupStack = [];

	public static function setup(string $controllersPath, string $controllersNamespace = '', ?string $cachePath = null): self
	{
		if (!self::$instance) {
			self::$instance = new self($controllersPath, $controllersNamespace, $cachePath);
		}
		return self::$instance;
	}

	public function __construct(string $controllersPath, string $controllersNamespace = '', ?string $cachePath = null)
	{
		$this->controllersPath = rtrim($controllersPath, '/');
		$this->controllersNamespace = rtrim($controllersNamespace, '\\');
		$this->request = Request::createFromGlobals();

		if ($cachePath !== null) {
			$this->cachePath = rtrim($cachePath, '/');
			$this->cacheEnabled = true;

			if (!is_dir($this->cachePath)) {
				mkdir($this->cachePath, 0755, true);
			}
		}
	}

	public function run(): void
	{
		try {
			$this->loadRoutes();
			$response = $this->handle($this->request);
			$response->send();
		} catch (\Throwable $e) {
			$response = $this->handleError(0, $e);
			$response->send();
		}
	}

	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$this->request = $request;

		// Применяем middleware
		if (!empty($this->middlewares)) {
			$handler = new MiddlewareHandler($this->middlewares, $this);
			return $handler->handle($request);
		}

		return $this->dispatch($request);
	}

	public function withMiddleware(MiddlewareInterface $middleware): self
	{
		$new = clone $this;
		$new->middlewares[] = $middleware;
		return $new;
	}

	public static function getNamedRoute(string $name): ?NamedRoute
	{
		$router = self::$instance;
		if (isset($router->namedRoutes[$name])) {
			$routeData = $router->namedRoutes[$name];
			return new NamedRoute(
				$name,
				$routeData['path'],
				$routeData['class'],
				$routeData['method'],
				$routeData['handler']
			);
		}
		return null;
	}

	public function generateUrl(string $name, array $params = []): ?string
	{
		$route = $this->getNamedRoute($name);
		if (!$route) {
			return null;
		}

		$path = $route->path;
		foreach ($params as $key => $value) {
			$path = str_replace('{' . $key . '}', $value, $path);
		}
		return $path;
	}

	private function loadRoutes(): void
	{
		if ($this->cacheEnabled && $this->loadFromCache()) {
			return;
		}

		$this->loadFromControllers();

		if ($this->cacheEnabled) {
			$this->saveToCache();
		}
	}

	private function loadFromCache(): bool
	{
		$cacheFile = $this->getCacheFile();

		if (!file_exists($cacheFile)) {
			return false;
		}

		$cacheData = unserialize(file_get_contents($cacheFile));

		if (!$this->isCacheValid($cacheData)) {
			return false;
		}

		$this->routes = $cacheData['routes'];
		$this->namedRoutes = $cacheData['named_routes'];
		$this->errors = $cacheData['errors'];
		return true;
	}

	private function isCacheValid(array $cacheData): bool
	{
		if (!isset($cacheData['timestamp'], $cacheData['controllers_hash'], $cacheData['routes'], $cacheData['named_routes'])) {
			return false;
		}

		if (time() - $cacheData['timestamp'] > 3600) {
			return false;
		}

		$currentHash = $this->getControllersHash();
		return $currentHash === $cacheData['controllers_hash'];
	}

	private function getControllersHash(): string
	{
		$files = $this->scanControllers();
		$hashData = [];

		foreach ($files as $file) {
			$hashData[$file] = filemtime($file);
		}

		return md5(serialize($hashData));
	}

	private function getCacheFile(): string
	{
		return $this->cachePath . '/routes.cache';
	}

	private function saveToCache(): void
	{
		$cacheData = [
			'timestamp' => time(),
			'controllers_hash' => $this->getControllersHash(),
			'routes' => $this->routes,
			'named_routes' => $this->namedRoutes,
			'errors' => $this->errors,
		];

		file_put_contents($this->getCacheFile(), serialize($cacheData));
	}

	private function loadFromControllers(): void
	{
		if (!is_dir($this->controllersPath)) {
			throw new \RuntimeException("Controllers directory not found: {$this->controllersPath}");
		}

		$files = $this->scanControllers();

		foreach ($files as $file) {
			$this->processController($file);
		}
	}

	private function scanControllers(): array
	{
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->controllersPath)
		);

		$files = [];
		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getExtension() === 'php') {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	private function processController(string $file): void
	{
		$className = $this->getClassNameFromFile($file);
		if (!$className) {
			return;
		}

		require_once $file;

		$fullClassName = $this->controllersNamespace
			? $this->controllersNamespace . '\\' . $className
			: $className;

		if (!class_exists($fullClassName)) {
			return;
		}

		$reflection = new ReflectionClass($fullClassName);

		// Получаем атрибуты роутов класса (для групп)
		$classAttributes = $reflection->getAttributes(Route::class);
		$classPathPrefix = '';
		$classNamePrefix = '';
		$classMiddlewares = [];

		foreach ($classAttributes as $attribute) {
			$route = $attribute->newInstance();
			$classPathPrefix = rtrim($route->path, '/');

			if ($route->name) {
				$classNamePrefix = rtrim($route->name, '.') . '.';
			}

			if (!empty($route->middlewares)) {
				$classMiddlewares = is_array($route->middlewares) ? $route->middlewares : [$route->middlewares];
			}
		}

		foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			$attributes = $method->getAttributes(Route::class);
			foreach ($attributes as $attribute) {
				/** @var Route $route */
				$route = $attribute->newInstance();

				// Формируем полный путь
				$methodPath = $route->path;
				$fullPath = $classPathPrefix . $methodPath;
				if ($fullPath === '') {
					$fullPath = '/';
				}

				// Формируем полное имя
				$methodName = $route->name;
				$fullName = $classNamePrefix . $methodName;

				// Обрабатываем методы (может быть строкой или массивом)
				$methods = is_array($route->method) ? $route->method : [$route->method];

				// Middleware для метода
				$methodMiddlewares = [];
				if (!empty($route->middlewares)) {
					$methodMiddlewares = is_array($route->middlewares) ? $route->middlewares : [$route->middlewares];
				}

				foreach ($methods as $httpMethod) {
					$this->addRoute(
						$httpMethod,
						$fullPath,
						[$fullClassName, $method->getName()],
						$fullName,
						array_merge($classMiddlewares, $methodMiddlewares)
					);
				}
			}

			$attributes = $method->getAttributes(Error::class);
			foreach ($attributes as $attribute) {
				/** @var Error $error */
				$error = $attribute->newInstance();

				$this->errors[$error->code] = [$fullClassName, $method->getName()];
			}
		}
	}

	private function getClassNameFromFile(string $file): ?string
	{
		$content = file_get_contents($file);
		$tokens = token_get_all($content);
		$namespace = '';
		$className = '';

		for ($i = 0; $i < count($tokens); $i++) {
			if ($tokens[$i][0] === T_NAMESPACE) {
				for ($j = $i + 1; $j < count($tokens); $j++) {
					if ($tokens[$j][0] === T_STRING) {
						$namespace .= '\\' . $tokens[$j][1];
					} elseif ($tokens[$j] === '{' || $tokens[$j] === ';') {
						break;
					}
				}
			}

			if ($tokens[$i][0] === T_CLASS) {
				for ($j = $i + 1; $j < count($tokens); $j++) {
					if ($tokens[$j] === '{') {
						$className = $tokens[$i + 2][1];
						break 2;
					}
				}
			}
		}

		if ($namespace && $className) {
			return trim($namespace, '\\') . '\\' . $className;
		}

		return $className ?: null;
	}

	private function addRoute(
		string $method,
		string $path,
		array $handler,
		?string $name = null,
		array $middlewares = []
	): void {
		$pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
		$pattern = "#^" . $pattern . "$#";

		$routeData = [
			'method' => strtoupper($method),
			'path' => $path,
			'pattern' => $pattern,
			'handler' => $handler,
			'name' => $name,
			'middlewares' => $middlewares
		];

		$this->routes[] = $routeData;

		if ($name) {
			$this->namedRoutes[$name] = [
				'path' => $path,
				'class' => $handler[0],
				'method' => $handler[1],
				'handler' => $handler,
				'middlewares' => $middlewares
			];
		}
	}

	private function dispatch(ServerRequestInterface $request): ResponseInterface
	{
		$params = [];
		$requestUri = parse_url($request->getUri(), PHP_URL_PATH);
		$requestMethod = $request->getMethod();

		foreach ($this->routes as $route) {
			if ($route['method'] !== $requestMethod) {
				continue;
			}

			if (preg_match($route['pattern'], $requestUri, $matches)) {
				$params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

				// Добавляем параметры в request
				foreach ($params as $key => $value) {
					$request = $request->withAttribute($key, $value);
				}

				[$class, $method] = $route['handler'];

				// Обрабатываем middleware маршрута
				if (!empty($route['middlewares'])) {
					$handler = new RouteHandler([$class, $method], $params);
					$middlewareHandler = new MiddlewareHandler($route['middlewares'], $handler);
					return $middlewareHandler->handle($request);
				}

				// Без middleware
				$controller = DI::make($class);
				$result = DI::call([$controller, $method], array_merge(['request' => $request], $params));

				return $this->ensureResponse($result);
			}
		}

		return $this->handleError(404);
	}

	private function handleError(int $code, ?\Throwable $exception = null): ResponseInterface
	{
		$httpCode = $code;
		if ($code === 0) {
			$httpCode = 500;
			if (isset($this->errors[get_class($exception)])) {
				$code = get_class($exception);
			} else { $code = 500; }
		}


		if (isset($this->errors[$code])) {
			[$class, $method] = $this->errors[$code];

			if (method_exists($class, $method)) {
				$reflection = new ReflectionMethod($class, $method);

				if ($reflection->isStatic()) {
					$result = DI::call([$class, $method], [$exception]);
				} else {
					$controller = DI::make($class);
					$result = DI::call([$controller, $method], [$exception]);
				}

				return $this->ensureResponse($result, $httpCode);
			}
		}

		// Дефолтные ошибки
		$messages = [
			404 => '404 Not Found',
			500 => '500 Internal Server Error',
			403 => '403 Forbidden',
		];

		return new Response($messages[$httpCode] ?? 'Error', $httpCode);
	}

	private function ensureResponse($result, int $defaultCode = 200): ResponseInterface
	{
		if ($result instanceof ResponseInterface) {
			return $result;
		}

		if (is_array($result) || is_object($result)) {
			$response = new Response('', $defaultCode, ['Content-Type' => 'application/json']);
			return $response->withJson($result, JSON_PRETTY_PRINT);
		}

		return new Response((string)$result, $defaultCode);
	}

	public function clearCache(): void
	{
		if ($this->cacheEnabled && file_exists($this->getCacheFile())) {
			unlink($this->getCacheFile());
		}
	}

	public function setCacheEnabled(bool $enabled): void
	{
		$this->cacheEnabled = $enabled && $this->cachePath !== null;
	}

	public function getRequest(): ServerRequestInterface
	{
		return $this->request;
	}
}
