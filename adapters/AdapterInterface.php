<?php
/**
 * OIManka - Platform Adapter Interface
 *
 * Defines the contract that all platform adapters must implement.
 * Each adapter connects to a different backend platform (HustOJ, SCPPER, etc.)
 * and maps its data into OIManka's stock/token model.
 */

interface AdapterInterface {

    /**
     * Get the internal name of this adapter.
     * Example: 'hustoj', 'scpper', 'codeforces'
     */
    public function getName(): string;

    /**
     * Get the human-readable display name.
     * Example: 'HustOJ', 'SCPPER-CN'
     */
    public function getDisplayName(): string;

    /**
     * Get a longer description of what this adapter connects to.
     */
    public function getDescription(): string;

    /**
     * Fetch all available stocks/items from the platform.
     *
     * @param array $options Filtering options (category, limit, offset, etc.)
     * @return array Array of stock data arrays with keys:
     *   - symbol: string (unique identifier for the stock, e.g., "P1001")
     *   - name: string (display name, e.g., "A+B Problem")
     *   - adapter_key: string (platform's native ID/key for this item)
     *   - category: string (group/category name)
     *   - ac_count: int (number of accepted/successful attempts)
     *   - submit_count: int (total number of attempts)
     *   - metadata: array (platform-specific extra data)
     */
    public function fetchStocks(array $options = []): array;

    /**
     * Calculate the stock price from platform metrics.
     *
     * Takes the raw data for a stock and returns a computed price.
     * Different platforms may use different formulas.
     *
     * @param array $stockData Raw data for one stock (from fetchStocks)
     * @return float The calculated price
     */
    public function calculatePrice(array $stockData): float;

    /**
     * Calculate token earnings from platform activity.
     *
     * Given a user's platform data, compute how many tokens they've earned.
     * For HustOJ: total AC count (each AC = some tokens).
     *
     * @param array $platformUserData Platform-specific user data
     * @return float Total tokens the user has earned on the platform
     */
    public function calculateTokens(array $platformUserData): float;

    /**
     * Fetch a user's platform data by their platform user ID.
     *
     * @param string $platformUserId The user's ID on the platform
     * @return array|null User data array or null if not found:
     *   - platform_user_id: string
     *   - username: string
     *   - total_ac: int (or equivalent metric)
     *   - total_submit: int (or equivalent metric)
     *   - metadata: array (additional data)
     */
    public function fetchUserData(string $platformUserId): ?array;

    /**
     * Resolve a user by identifier (username or ID).
     *
     * @param string $identifier Username or user ID
     * @return array|null Resolved user data or null
     */
    public function resolveUser(string $identifier): ?array;

    /**
     * Sync all stocks from the platform into the local database.
     *
     * This should fetch all stocks, calculate their prices,
     * and update/create records in the local SQLite database.
     *
     * @return array Sync result with keys:
     *   - items_synced: int
     *   - items_created: int
     *   - items_updated: int
     *   - errors: array (optional)
     */
    public function syncStocks(): array;

    /**
     * Sync a specific user's data from the platform.
     *
     * @param string $localUsername The local username to sync
     * @return bool Success
     */
    public function syncUser(string $localUsername): bool;

    /**
     * Test if the connection to the platform is working.
     *
     * @return bool True if connection is successful
     */
    public function testConnection(): bool;

    /**
     * Get configuration fields required by this adapter.
     *
     * Returns an array of field definitions for the admin settings page.
     * Each field: ['key' => string, 'label' => string, 'type' => 'text'|'number'|'password', 'required' => bool]
     *
     * @return array
     */
    public function getConfigFields(): array;
}
