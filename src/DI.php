<?php
namespace BMND;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

class DI
{
    private static ?DI $instance = null;
    private array $bindings = [];
    private array $instances = [];
    private array $resolved = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Создать объект класса с автоподстановкой зависимостей
     */
    public static function make(string $className, array $arguments = []): object
    {
        return self::getInstance()->resolve($className, $arguments);
    }

    /**
     * Вызвать метод/функцию с автоподстановкой зависимостей
     */
    public static function call(callable $callable, array $arguments = []): mixed
    {
        return self::getInstance()->resolveCallable($callable, $arguments);
    }

    /**
     * Зарегистрировать синглтон
     */
    public static function singleton(string $abstract, mixed $concrete = null): void
    {
        $container = self::getInstance();
        $container->bi($abstract, $concrete ?? $abstract, true);
    }

    /**
     * Зарегистрировать привязку
     */
    public static function bind(string $abstract, mixed $concrete, bool $shared = false): void
    {
        $container = self::getInstance();
        $container->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    /**
     * Получить зарегистрированный экземпляр
     */
    public static function get(string $abstract): mixed
    {
        return self::getInstance()->instances[$abstract] ?? null;
    }

    /**
     * Проверить, есть ли привязка
     */
    public static function has(string $abstract): bool
    {
        return isset(self::getInstance()->bindings[$abstract]);
    }

    /**
     * Сбросить все привязки
     */
    public static function flush(): void
    {
        $container = self::getInstance();
        $container->bindings = [];
        $container->instances = [];
        $container->resolved = [];
    }

    private function resolve(string $className, array $arguments = []): object
    {
        // Кэширование разрешенных зависимостей
        $cacheKey = $className . md5(serialize($arguments));
        if (isset($this->resolved[$cacheKey])) {
            return $this->resolved[$cacheKey];
        }

        // Если есть зарегистрированная привязка, используем её
        if (isset($this->bindings[$className])) {
            $binding = $this->bindings[$className];
            
            // Если это синглтон и уже создан, возвращаем его
            if ($binding['shared'] && isset($this->instances[$className])) {
                return $this->instances[$className];
            }
            
            // Разрешаем concrete
            $concrete = $binding['concrete'];
            
            if ($concrete instanceof Closure || is_callable($concrete)) {
                $object = $concrete($this);
            } elseif (is_string($concrete) && class_exists($concrete)) {
                $object = $this->buildClass($concrete, $arguments);
            } else {
                $object = $concrete;
            }
            
            // Сохраняем синглтон
            if ($binding['shared']) {
                $this->instances[$className] = $object;
            }
            
            $this->resolved[$cacheKey] = $object;
            return $object;
        }
        
        // Если нет привязки, просто создаем класс
        $object = $this->buildClass($className, $arguments);
        $this->resolved[$cacheKey] = $object;
        return $object;
    }

    private function buildClass(string $className, array $arguments = []): object
    {
        try {
            $reflector = new ReflectionClass($className);
            
            // Если класс не может быть создан (интерфейс, абстрактный класс)
            if (!$reflector->isInstantiable()) {
                throw new DIException("Class {$className} is not instantiable");
            }
            
            // Получаем конструктор
            $constructor = $reflector->getConstructor();
            
            // Если конструктора нет, просто создаем объект
            if ($constructor === null) {
                return new $className();
            }
            
            // Получаем параметры конструктора
            $parameters = $constructor->getParameters();
            $dependencies = $this->resolveParameters($parameters, $arguments);
            
            // Создаем объект с зависимостями
            return $reflector->newInstanceArgs($dependencies);
            
        } catch (ReflectionException $e) {
            throw new DIException("Class {$className} does not exist", 0, $e);
        }
    }

    private function resolveCallable(callable $callable, array $arguments = []): mixed
    {
        $cacheKey = md5(serialize($callable)) . md5(serialize($arguments));
        if (isset($this->resolved[$cacheKey])) {
            return $this->resolved[$cacheKey];
        }

        try {
            if (is_array($callable)) {
                // Метод класса
                $reflection = new ReflectionMethod($callable[0], $callable[1]);
            } elseif (is_object($callable) && !$callable instanceof Closure) {
                // Объект с методом __invoke
                $reflection = new ReflectionMethod($callable, '__invoke');
            } else {
                // Функция или Closure
                $reflection = new ReflectionFunction($callable);
            }
            
            $parameters = $reflection->getParameters();
            $dependencies = $this->resolveParameters($parameters, $arguments);
            
            $result = call_user_func_array($callable, $dependencies);
            $this->resolved[$cacheKey] = $result;
            return $result;
            
        } catch (ReflectionException $e) {
            throw new DIException("Failed to resolve callable", 0, $e);
        }
    }

    private function resolveParameters(array $parameters, array $arguments = []): array
    {
        $dependencies = [];
        $providedArgs = $arguments;
        
        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            
            // Если аргумент передан явно, используем его
            if (isset($providedArgs[$paramName])) {
                $dependencies[] = $providedArgs[$paramName];
                continue;
            }
            
            // Если аргументы переданы по порядку
            if (!empty($providedArgs) && isset($providedArgs[0])) {
                $dependencies[] = array_shift($providedArgs);
                continue;
            }
            
            // Пытаемся разрешить тип параметра
            $type = $parameter->getType();
            
            if ($type && !$type->isBuiltin() && class_exists($type->getName())) {
                // Это класс - создаем его рекурсивно
                $dependencies[] = $this->resolve($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                // Используем значение по умолчанию
                $dependencies[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                // Разрешаем null
                $dependencies[] = null;
            } else {
                // Не можем разрешить параметр
                throw new DIException(
                    "Cannot resolve parameter \${$paramName} of type {$type?->getName()}"
                );
            }
        }
        
        return $dependencies;
    }

    private function bi(string $abstract, mixed $concrete, bool $shared): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }
}

class DIException extends \Exception {}
