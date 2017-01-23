<?php
/**
 * The purpose of this class is to act as a Dependency Injection service provider thus fulfilling the Inversion of Control aspect of Dependency Injection.
 */
namespace Maleficarum\Ioc;

class Container
{
    /**
     * Internal storage for the default builder definition file
     *
     * @var string|null
     */
    private static $defaultBuilders = null;

    /**
     * Internal storage for namespaces
     *
     * @var array
     */
    private static $namespaces = [];

    /**
     * Internal storage for object initializer closures.
     *
     * @var array
     */
    private static $initializers = [];

    /**
     * Internal storage for available dependencies.
     *
     * @var array
     */
    private static $dependencies = [];

    /**
     * Internal storage for a list of ioc definitions that we either loaded or checked for existence.
     *
     * @var array
     */
    private static $loadedDefinitions = [];

    /**
     * Register a new initializer closure.
     *
     * @param string $name
     * @param \Closure $closure
     * 
     * @return void
     * @throws \RuntimeException
     */
    public static function register(string $name, \Closure $closure) {
        if (self::isRegistered($name)) {
            throw new \RuntimeException(sprintf('Another closure with given name is already registered. \%s::register()', static::class));
        }

        self::$initializers[$name] = $closure;
    }

    /**
     * Fetch a new instance of the specified class.
     *
     * @param string $name
     * @param array $opts
     *
     * @return object
     */
    public static function get(string $name, array $opts = []) {
        // fetch decremental builder names
        $name = self::reduce($name);
        $prefix = $name[count($name) - 1];

        // lazy-load IOC definitions for specified namespace (only once)
        self::includeFile($prefix);

        // attempt to execute builders
        foreach ($name as $builder) {
            if (self::isRegistered($builder)) {
                $init = self::$initializers[$builder];

                return $init(self::$dependencies, array_key_exists('__class', $opts) ? $opts : array_merge($opts, ['__class' => $name[0]]));
            }
        }

        // reaching this point means that no valid builder was found - execute generic ones
        if (empty($opts)) {
            return new $name[0]();
        }

        $reflection = new \ReflectionClass($name[0]);

        return $reflection->newInstanceArgs($opts);
    }

    /**
     * Check if an object of the specified name can be provided by this container.
     *
     * @param string $name
     *
     * @return bool
     */
    public static function isRegistered(string $name) : bool {
        return array_key_exists($name, self::$initializers);
    }

    /**
     * Register a new dependency to use inside initializer closures.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return void
     * @throws \RuntimeException
     */
    public static function registerDependency(string $name, $value) {
        if (array_key_exists($name, self::$dependencies)) {
            throw new \RuntimeException(sprintf('Dependency with given name is already registered. \%s::registerDependency()', static::class));
        }

        self::$dependencies[$name] = $value;
    }

    /**
     * Add single namespace with path.
     *
     * @param string $ns
     * @param string $path
     *
     * @return void
     * @throws \RuntimeException
     */
    public static function addNamespace(string $ns, string $path) {
        if (array_key_exists($ns, self::$namespaces)) {
            throw new \RuntimeException(sprintf('Namespace with given name already exist. \%s::addNamespace()', static::class));
        }

        self::$namespaces[$ns] = $path;
    }

    /**
     * Set the path to a file with default builder definitions.
     *
     * @param string $path
     *
     * @return void
     * @throws \RuntimeException
     */
    public static function setDefaultBuilders(string $path) {
        if (!is_null(self::$defaultBuilders)) {
            throw new \RuntimeException(sprintf('Default builders already set. \%s::setDefaultBuilders()', static::class));
        }

        self::$defaultBuilders = $path;
    }

    /**
     * Reduce specified name to a list of decremental namespaces.
     *
     * @param string $name
     *
     * @return array
     */
    private static function reduce(string $name) : array {
        $delimiter = '\\';
        $name = explode($delimiter, $name);
        $index = count($name);

        // create handler results
        $result = [];
        while ($index-- > 0) {
            $result[] = implode($delimiter, array_slice($name, 0, $index + 1));
        }

        return $result;
    }

    /**
     * Includes file with given prefix or load default builder definition file
     *
     * @param string $prefix
     * 
     * @return void
     */
    private static function includeFile(string $prefix) {
        if (!in_array($prefix, self::$loadedDefinitions, true) && isset(self::$namespaces[$prefix])) {
            require_once self::$namespaces[$prefix] . DIRECTORY_SEPARATOR . $prefix . '.php';
            self::$loadedDefinitions[] = $prefix;
        } elseif (is_string(self::$defaultBuilders) && !in_array('*', self::$loadedDefinitions, true)) {
            require_once self::$defaultBuilders;
            self::$loadedDefinitions[] = '*';
        }
    }
}
