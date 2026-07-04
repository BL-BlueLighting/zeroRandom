<?php
/**
 * OIManka - Adapter Manager
 *
 * Registry and factory for platform adapters.
 * Handles loading, caching, and switching between adapters.
 */

require_once __DIR__ . '/AdapterInterface.php';
require_once __DIR__ . '/HustojAdapter.php';

class AdapterManager {

    /** @var array<string, AdapterInterface> */
    private static array $adapters = [];

    /** @var string[] Registered adapter class names */
    private static array $registry = [
        'hustoj' => HustojAdapter::class,
    ];

    /**
     * Get an adapter instance by name.
     */
    public static function get(string $name): ?AdapterInterface {
        if (isset(self::$adapters[$name])) {
            return self::$adapters[$name];
        }

        if (!isset(self::$registry[$name])) {
            return null;
        }

        $class = self::$registry[$name];
        try {
            $adapter = new $class();
            self::$adapters[$name] = $adapter;
            return $adapter;
        } catch (Throwable $e) {
            error_log("Failed to instantiate adapter '$name': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the default adapter.
     */
    public static function getDefault(): ?AdapterInterface {
        return self::get(DEFAULT_ADAPTER);
    }

    /**
     * Get all registered adapter instances.
     *
     * @return array<string, AdapterInterface>
     */
    public static function all(): array {
        foreach (self::$registry as $name => $class) {
            if (!isset(self::$adapters[$name])) {
                self::get($name);
            }
        }
        return self::$adapters;
    }

    /**
     * Get list of registered adapter names and metadata.
     */
    public static function list(): array {
        $list = [];
        foreach (self::$registry as $name => $class) {
            $adapter = self::get($name);
            $list[] = [
                'name' => $name,
                'display_name' => $adapter ? $adapter->getDisplayName() : $name,
                'description' => $adapter ? $adapter->getDescription() : '',
                'connected' => $adapter ? $adapter->testConnection() : false,
                'is_default' => $name === DEFAULT_ADAPTER,
            ];
        }
        return $list;
    }

    /**
     * Register a new adapter at runtime.
     */
    public static function register(string $name, string $class): void {
        if (!is_subclass_of($class, AdapterInterface::class)) {
            throw new InvalidArgumentException("$class must implement AdapterInterface");
        }
        self::$registry[$name] = $class;
    }

    /**
     * Sync stocks from all adapters.
     */
    public static function syncAll(): array {
        $results = [];
        foreach (self::all() as $name => $adapter) {
            $results[$name] = $adapter->syncStocks();
        }
        return $results;
    }
}
