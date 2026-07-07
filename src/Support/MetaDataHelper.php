<?php
/**
 * MetaDataHelper – Flexible JSON metadata management for BSO Survival entities
 *
 * Provides safe get/set/merge operations on entity meta_data fields.
 * All entity types (Event, Team, Part, Assignment) use this helper for custom data storage.
 *
 * Usage:
 *   $event = Event::find(1);
 *   MetaDataHelper::set($event, 'sponsor_name', 'Acme Corp');
 *   $sponsor = MetaDataHelper::get($event, 'sponsor_name');
 *
 * @package BSO\Survival\Support
 * @since 2.0.0
 */

namespace BSO\Survival\Support;

use InvalidArgumentException;

class MetaDataHelper {
    /**
     * Get meta value from entity
     *
     * Safely retrieves a value from the entity's meta_data JSON field.
     * Returns default value if key not found or meta_data is invalid.
     *
     * @param object $entity The entity object (Event, Team, Part, Assignment)
     * @param string $key The meta key to retrieve
     * @param mixed $default Default value if key not found
     *
     * @return mixed The meta value, or $default if not found
     *
     * @throws InvalidArgumentException If entity or key is invalid
     */
    public static function get($entity, $key, $default = null) {
        self::validate_entity($entity);
        self::validate_key($key);

        if (!isset($entity->meta_data)) {
            return $default;
        }

        try {
            $data = json_decode($entity->meta_data, true);
            if (!is_array($data)) {
                return $default;
            }
            return array_key_exists($key, $data) ? $data[$key] : $default;
        } catch (\Throwable $e) {
            do_action('bso_survival_metadata_error', 'get', $entity, $key, $e);
            return $default;
        }
    }

    /**
     * Set meta value on entity
     *
     * Safely sets a single meta value in the entity's meta_data JSON field.
     * Preserves existing keys, only updates the specified key.
     *
     * @param object $entity The entity object (passed by reference)
     * @param string $key The meta key to set
     * @param mixed $value The value to set (must be JSON-serializable)
     *
     * @return object Returns the modified entity for chaining
     *
     * @throws InvalidArgumentException If entity, key, or value is invalid
     */
    public static function set(&$entity, $key, $value) {
        self::validate_entity($entity);
        self::validate_key($key);
        self::validate_value($value);

        try {
            $data = self::decode_meta_data($entity->meta_data ?? '{}');
            $data[$key] = $value;
            $entity->meta_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $entity;
        } catch (\Throwable $e) {
            do_action('bso_survival_metadata_error', 'set', $entity, $key, $e);
            throw new InvalidArgumentException("Failed to set meta key '{$key}': " . $e->getMessage());
        }
    }

    /**
     * Merge multiple meta values into entity
     *
     * Safely merges an array of key-value pairs into the entity's meta_data.
     * Existing keys are overwritten, new keys are added.
     *
     * @param object $entity The entity object (passed by reference)
     * @param array $updates Key-value pairs to merge
     *
     * @return object Returns the modified entity for chaining
     *
     * @throws InvalidArgumentException If updates is not an array or contains invalid data
     */
    public static function merge(&$entity, array $updates) {
        self::validate_entity($entity);

        if (empty($updates)) {
            return $entity;
        }

        try {
            $data = self::decode_meta_data($entity->meta_data ?? '{}');
            
            // Validate all values before merging
            foreach ($updates as $key => $value) {
                self::validate_key($key);
                self::validate_value($value);
            }
            
            $data = array_merge($data, $updates);
            $entity->meta_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $entity;
        } catch (\Throwable $e) {
            do_action('bso_survival_metadata_error', 'merge', $entity, null, $e);
            throw new InvalidArgumentException("Failed to merge meta data: " . $e->getMessage());
        }
    }

    /**
     * Delete meta key from entity
     *
     * Safely removes a single key from the entity's meta_data JSON field.
     * If key doesn't exist, silently returns the entity unchanged.
     *
     * @param object $entity The entity object (passed by reference)
     * @param string $key The meta key to delete
     *
     * @return object Returns the modified entity for chaining
     *
     * @throws InvalidArgumentException If entity or key is invalid
     */
    public static function delete(&$entity, $key) {
        self::validate_entity($entity);
        self::validate_key($key);

        try {
            $data = self::decode_meta_data($entity->meta_data ?? '{}');
            unset($data[$key]);
            $entity->meta_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $entity;
        } catch (\Throwable $e) {
            do_action('bso_survival_metadata_error', 'delete', $entity, $key, $e);
            throw new InvalidArgumentException("Failed to delete meta key '{$key}': " . $e->getMessage());
        }
    }

    /**
     * Clear all meta data from entity
     *
     * Resets meta_data to empty JSON object.
     *
     * @param object $entity The entity object (passed by reference)
     *
     * @return object Returns the modified entity for chaining
     */
    public static function clear(&$entity) {
        self::validate_entity($entity);
        $entity->meta_data = '{}';
        return $entity;
    }

    /**
     * Get all meta data as array
     *
     * Returns the complete decoded meta_data as an associative array.
     *
     * @param object $entity The entity object
     *
     * @return array All meta data as key-value pairs
     *
     * @throws InvalidArgumentException If entity is invalid
     */
    public static function all($entity) {
        self::validate_entity($entity);

        try {
            return self::decode_meta_data($entity->meta_data ?? '{}');
        } catch (\Throwable $e) {
            do_action('bso_survival_metadata_error', 'all', $entity, null, $e);
            return [];
        }
    }

    /**
     * Check if meta key exists
     *
     * @param object $entity The entity object
     * @param string $key The meta key to check
     *
     * @return bool True if key exists, false otherwise
     */
    public static function has($entity, $key) {
        self::validate_entity($entity);
        self::validate_key($key);

        try {
            $data = self::decode_meta_data($entity->meta_data ?? '{}');
            return array_key_exists($key, $data);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get meta keys
     *
     * Returns array of all meta keys in entity.
     *
     * @param object $entity The entity object
     *
     * @return array Array of keys
     */
    public static function keys($entity) {
        self::validate_entity($entity);

        try {
            $data = self::decode_meta_data($entity->meta_data ?? '{}');
            return array_keys($data);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get number of meta entries
     *
     * @param object $entity The entity object
     *
     * @return int Number of meta keys
     */
    public static function count($entity) {
        self::validate_entity($entity);

        try {
            $data = self::decode_meta_data($entity->meta_data ?? '{}');
            return count($data);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Increment numeric meta value
     *
     * Safely increments a numeric meta value by the given amount.
     * If key doesn't exist, initializes to 0 before incrementing.
     *
     * @param object $entity The entity object (passed by reference)
     * @param string $key The meta key to increment
     * @param int|float $increment Amount to add (default 1)
     *
     * @return object Returns the modified entity for chaining
     *
     * @throws InvalidArgumentException If key is invalid or value is not numeric
     */
    public static function increment(&$entity, $key, $increment = 1) {
        self::validate_entity($entity);
        self::validate_key($key);

        if (!is_numeric($increment)) {
            throw new InvalidArgumentException("Increment must be numeric, got " . gettype($increment));
        }

        try {
            $data = self::decode_meta_data($entity->meta_data ?? '{}');
            $current = $data[$key] ?? 0;
            
            if (!is_numeric($current)) {
                throw new InvalidArgumentException("Current value is not numeric");
            }
            
            $data[$key] = $current + $increment;
            $entity->meta_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $entity;
        } catch (\Throwable $e) {
            do_action('bso_survival_metadata_error', 'increment', $entity, $key, $e);
            throw new InvalidArgumentException("Failed to increment meta key '{$key}': " . $e->getMessage());
        }
    }

    /**
     * Decrement numeric meta value
     *
     * Safely decrements a numeric meta value by the given amount.
     *
     * @param object $entity The entity object (passed by reference)
     * @param string $key The meta key to decrement
     * @param int|float $decrement Amount to subtract (default 1)
     *
     * @return object Returns the modified entity for chaining
     */
    public static function decrement(&$entity, $key, $decrement = 1) {
        return self::increment($entity, $key, -$decrement);
    }

    // ============================================================================
    // Private Helper Methods
    // ============================================================================

    /**
     * Decode meta_data string safely
     *
     * @param string $json JSON string to decode
     *
     * @return array Decoded array
     *
     * @throws InvalidArgumentException If JSON is invalid
     */
    private static function decode_meta_data($json) {
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                "Invalid JSON in meta_data: " . json_last_error_msg()
            );
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException(
                "meta_data must decode to an array, got " . gettype($decoded)
            );
        }

        return $decoded;
    }

    /**
     * Validate entity object
     *
     * @param object $entity Entity to validate
     *
     * @throws InvalidArgumentException If entity is invalid
     */
    private static function validate_entity($entity) {
        if (!is_object($entity)) {
            throw new InvalidArgumentException(
                "Entity must be an object, got " . gettype($entity)
            );
        }

        if (!property_exists($entity, 'meta_data')) {
            throw new InvalidArgumentException(
                "Entity must have a meta_data property"
            );
        }
    }

    /**
     * Validate meta key
     *
     * @param string $key Key to validate
     *
     * @throws InvalidArgumentException If key is invalid
     */
    private static function validate_key($key) {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                "Meta key must be a string, got " . gettype($key)
            );
        }

        if (empty($key)) {
            throw new InvalidArgumentException("Meta key cannot be empty");
        }

        if (strlen($key) > 255) {
            throw new InvalidArgumentException("Meta key cannot exceed 255 characters");
        }
    }

    /**
     * Validate meta value
     *
     * @param mixed $value Value to validate
     *
     * @throws InvalidArgumentException If value is not JSON-serializable
     */
    private static function validate_value($value) {
        // Check if value is JSON-serializable
        if (is_resource($value) || ($value instanceof \Closure)) {
            throw new InvalidArgumentException(
                "Meta value must be JSON-serializable, got " . gettype($value)
            );
        }

        // Test JSON encoding
        $encoded = @json_encode($value);
        if ($encoded === false) {
            throw new InvalidArgumentException(
                "Meta value is not JSON-serializable"
            );
        }
    }
}
