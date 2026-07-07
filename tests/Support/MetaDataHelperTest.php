<?php
/**
 * Unit Tests for MetaDataHelper
 *
 * Tests cover all public methods of MetaDataHelper with valid and edge-case inputs.
 *
 * @package BSO\Survival\Tests
 * @since 2.0.0
 */

namespace BSO\Survival\Tests\Support;

use BSO\Survival\Support\MetaDataHelper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MetaDataHelperTest extends TestCase {
    
    private $entity;

    protected function setUp(): void {
        $this->entity = (object) [
            'id' => 1,
            'name' => 'Test Entity',
            'meta_data' => '{}'
        ];
    }

    // ============================================================================
    // GET Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_returns_value_when_key_exists() {
        $this->entity->meta_data = json_encode(['key1' => 'value1']);
        $result = MetaDataHelper::get($this->entity, 'key1');
        $this->assertEquals('value1', $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_returns_default_when_key_missing() {
        $this->entity->meta_data = json_encode(['key1' => 'value1']);
        $result = MetaDataHelper::get($this->entity, 'missing_key', 'default_value');
        $this->assertEquals('default_value', $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_returns_null_when_no_default_and_key_missing() {
        $this->entity->meta_data = '{}';
        $result = MetaDataHelper::get($this->entity, 'missing_key');
        $this->assertNull($result);
    }

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_returns_default_when_meta_data_empty() {
        $this->entity->meta_data = null;
        $result = MetaDataHelper::get($this->entity, 'any_key', 'fallback');
        $this->assertEquals('fallback', $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_handles_numeric_values() {
        $this->entity->meta_data = json_encode(['count' => 42]);
        $result = MetaDataHelper::get($this->entity, 'count');
        $this->assertEquals(42, $result);
        $this->assertIsInt($result);
    }

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_handles_boolean_values() {
        $this->entity->meta_data = json_encode(['active' => true, 'deleted' => false]);
        $this->assertTrue(MetaDataHelper::get($this->entity, 'active'));
        $this->assertFalse(MetaDataHelper::get($this->entity, 'deleted'));
    }

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_handles_array_values() {
        $this->entity->meta_data = json_encode(['tags' => ['tag1', 'tag2', 'tag3']]);
        $result = MetaDataHelper::get($this->entity, 'tags');
        $this->assertEquals(['tag1', 'tag2', 'tag3'], $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_throws_exception_when_entity_invalid() {
        $this->expectException(InvalidArgumentException::class);
        MetaDataHelper::get('not_an_object', 'key');
    }

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_throws_exception_when_entity_missing_meta_data_property() {
        $invalid_entity = (object) ['id' => 1];
        $this->expectException(InvalidArgumentException::class);
        MetaDataHelper::get($invalid_entity, 'key');
    }

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_throws_exception_when_key_empty() {
        $this->expectException(InvalidArgumentException::class);
        MetaDataHelper::get($this->entity, '');
    }

    /**
     * @test
     * @covers MetaDataHelper::get
     */
    public function test_get_throws_exception_when_key_not_string() {
        $this->expectException(InvalidArgumentException::class);
        MetaDataHelper::get($this->entity, 123);
    }

    // ============================================================================
    // SET Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::set
     */
    public function test_set_adds_new_key() {
        MetaDataHelper::set($this->entity, 'new_key', 'new_value');
        $result = MetaDataHelper::get($this->entity, 'new_key');
        $this->assertEquals('new_value', $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::set
     */
    public function test_set_overwrites_existing_key() {
        $this->entity->meta_data = json_encode(['key' => 'old_value']);
        MetaDataHelper::set($this->entity, 'key', 'new_value');
        $result = MetaDataHelper::get($this->entity, 'key');
        $this->assertEquals('new_value', $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::set
     */
    public function test_set_preserves_other_keys() {
        $this->entity->meta_data = json_encode(['key1' => 'value1', 'key2' => 'value2']);
        MetaDataHelper::set($this->entity, 'key1', 'modified');
        $this->assertEquals('modified', MetaDataHelper::get($this->entity, 'key1'));
        $this->assertEquals('value2', MetaDataHelper::get($this->entity, 'key2'));
    }

    /**
     * @test
     * @covers MetaDataHelper::set
     */
    public function test_set_returns_entity_for_chaining() {
        $result = MetaDataHelper::set($this->entity, 'key', 'value');
        $this->assertSame($this->entity, $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::set
     */
    public function test_set_handles_array_values() {
        MetaDataHelper::set($this->entity, 'tags', ['tag1', 'tag2']);
        $result = MetaDataHelper::get($this->entity, 'tags');
        $this->assertEquals(['tag1', 'tag2'], $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::set
     */
    public function test_set_handles_nested_arrays() {
        $nested = ['level1' => ['level2' => ['level3' => 'value']]];
        MetaDataHelper::set($this->entity, 'nested', $nested);
        $result = MetaDataHelper::get($this->entity, 'nested');
        $this->assertEquals($nested, $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::set
     */
    public function test_set_throws_exception_when_key_too_long() {
        $long_key = str_repeat('a', 256);
        $this->expectException(InvalidArgumentException::class);
        MetaDataHelper::set($this->entity, $long_key, 'value');
    }

    /**
     * @test
     * @covers MetaDataHelper::set
     */
    public function test_set_throws_exception_when_value_is_resource() {
        $resource = fopen('php://memory', 'r');
        $this->expectException(InvalidArgumentException::class);
        MetaDataHelper::set($this->entity, 'key', $resource);
        fclose($resource);
    }

    // ============================================================================
    // MERGE Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::merge
     */
    public function test_merge_adds_multiple_keys() {
        MetaDataHelper::merge($this->entity, ['key1' => 'value1', 'key2' => 'value2']);
        $this->assertEquals('value1', MetaDataHelper::get($this->entity, 'key1'));
        $this->assertEquals('value2', MetaDataHelper::get($this->entity, 'key2'));
    }

    /**
     * @test
     * @covers MetaDataHelper::merge
     */
    public function test_merge_overwrites_existing_keys() {
        $this->entity->meta_data = json_encode(['key1' => 'old', 'key2' => 'keep']);
        MetaDataHelper::merge($this->entity, ['key1' => 'new']);
        $this->assertEquals('new', MetaDataHelper::get($this->entity, 'key1'));
        $this->assertEquals('keep', MetaDataHelper::get($this->entity, 'key2'));
    }

    /**
     * @test
     * @covers MetaDataHelper::merge
     */
    public function test_merge_with_empty_array_does_nothing() {
        $original = json_encode(['key' => 'value']);
        $this->entity->meta_data = $original;
        MetaDataHelper::merge($this->entity, []);
        $this->assertEquals($original, $this->entity->meta_data);
    }

    /**
     * @test
     * @covers MetaDataHelper::merge
     */
    public function test_merge_returns_entity_for_chaining() {
        $result = MetaDataHelper::merge($this->entity, ['key' => 'value']);
        $this->assertSame($this->entity, $result);
    }

    // ============================================================================
    // DELETE Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::delete
     */
    public function test_delete_removes_key() {
        $this->entity->meta_data = json_encode(['key1' => 'value1', 'key2' => 'value2']);
        MetaDataHelper::delete($this->entity, 'key1');
        $this->assertNull(MetaDataHelper::get($this->entity, 'key1'));
        $this->assertEquals('value2', MetaDataHelper::get($this->entity, 'key2'));
    }

    /**
     * @test
     * @covers MetaDataHelper::delete
     */
    public function test_delete_non_existent_key_does_nothing() {
        $this->entity->meta_data = json_encode(['key' => 'value']);
        MetaDataHelper::delete($this->entity, 'missing');
        $this->assertEquals('value', MetaDataHelper::get($this->entity, 'key'));
    }

    /**
     * @test
     * @covers MetaDataHelper::delete
     */
    public function test_delete_returns_entity_for_chaining() {
        $this->entity->meta_data = json_encode(['key' => 'value']);
        $result = MetaDataHelper::delete($this->entity, 'key');
        $this->assertSame($this->entity, $result);
    }

    // ============================================================================
    // CLEAR Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::clear
     */
    public function test_clear_removes_all_meta_data() {
        $this->entity->meta_data = json_encode(['key1' => 'value1', 'key2' => 'value2']);
        MetaDataHelper::clear($this->entity);
        $this->assertEquals('{}', $this->entity->meta_data);
        $this->assertNull(MetaDataHelper::get($this->entity, 'key1'));
    }

    /**
     * @test
     * @covers MetaDataHelper::clear
     */
    public function test_clear_returns_entity_for_chaining() {
        $result = MetaDataHelper::clear($this->entity);
        $this->assertSame($this->entity, $result);
    }

    // ============================================================================
    // ALL Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::all
     */
    public function test_all_returns_all_data() {
        $data = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];
        $this->entity->meta_data = json_encode($data);
        $result = MetaDataHelper::all($this->entity);
        $this->assertEquals($data, $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::all
     */
    public function test_all_returns_empty_array_when_empty() {
        $this->entity->meta_data = '{}';
        $result = MetaDataHelper::all($this->entity);
        $this->assertEquals([], $result);
    }

    // ============================================================================
    // HAS Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::has
     */
    public function test_has_returns_true_when_key_exists() {
        $this->entity->meta_data = json_encode(['key' => 'value']);
        $this->assertTrue(MetaDataHelper::has($this->entity, 'key'));
    }

    /**
     * @test
     * @covers MetaDataHelper::has
     */
    public function test_has_returns_false_when_key_missing() {
        $this->entity->meta_data = json_encode(['other' => 'value']);
        $this->assertFalse(MetaDataHelper::has($this->entity, 'key'));
    }

    /**
     * @test
     * @covers MetaDataHelper::has
     */
    public function test_has_returns_false_for_null_value() {
        $this->entity->meta_data = json_encode(['key' => null]);
        $this->assertTrue(MetaDataHelper::has($this->entity, 'key'));
    }

    // ============================================================================
    // KEYS Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::keys
     */
    public function test_keys_returns_all_keys() {
        $this->entity->meta_data = json_encode(['key1' => 'v1', 'key2' => 'v2', 'key3' => 'v3']);
        $result = MetaDataHelper::keys($this->entity);
        $this->assertEquals(['key1', 'key2', 'key3'], $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::keys
     */
    public function test_keys_returns_empty_array_when_empty() {
        $this->entity->meta_data = '{}';
        $result = MetaDataHelper::keys($this->entity);
        $this->assertEquals([], $result);
    }

    // ============================================================================
    // COUNT Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::count
     */
    public function test_count_returns_number_of_keys() {
        $this->entity->meta_data = json_encode(['key1' => 'v1', 'key2' => 'v2', 'key3' => 'v3']);
        $result = MetaDataHelper::count($this->entity);
        $this->assertEquals(3, $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::count
     */
    public function test_count_returns_zero_when_empty() {
        $this->entity->meta_data = '{}';
        $result = MetaDataHelper::count($this->entity);
        $this->assertEquals(0, $result);
    }

    // ============================================================================
    // INCREMENT Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::increment
     */
    public function test_increment_adds_one_by_default() {
        $this->entity->meta_data = json_encode(['counter' => 5]);
        MetaDataHelper::increment($this->entity, 'counter');
        $result = MetaDataHelper::get($this->entity, 'counter');
        $this->assertEquals(6, $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::increment
     */
    public function test_increment_adds_custom_amount() {
        $this->entity->meta_data = json_encode(['counter' => 10]);
        MetaDataHelper::increment($this->entity, 'counter', 5);
        $result = MetaDataHelper::get($this->entity, 'counter');
        $this->assertEquals(15, $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::increment
     */
    public function test_increment_initializes_missing_key_to_zero() {
        $this->entity->meta_data = '{}';
        MetaDataHelper::increment($this->entity, 'new_counter');
        $result = MetaDataHelper::get($this->entity, 'new_counter');
        $this->assertEquals(1, $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::increment
     */
    public function test_increment_handles_floats() {
        $this->entity->meta_data = json_encode(['value' => 10.5]);
        MetaDataHelper::increment($this->entity, 'value', 2.5);
        $result = MetaDataHelper::get($this->entity, 'value');
        $this->assertEquals(13.0, $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::increment
     */
    public function test_increment_returns_entity_for_chaining() {
        $this->entity->meta_data = json_encode(['counter' => 1]);
        $result = MetaDataHelper::increment($this->entity, 'counter');
        $this->assertSame($this->entity, $result);
    }

    // ============================================================================
    // DECREMENT Tests
    // ============================================================================

    /**
     * @test
     * @covers MetaDataHelper::decrement
     */
    public function test_decrement_subtracts_one_by_default() {
        $this->entity->meta_data = json_encode(['counter' => 5]);
        MetaDataHelper::decrement($this->entity, 'counter');
        $result = MetaDataHelper::get($this->entity, 'counter');
        $this->assertEquals(4, $result);
    }

    /**
     * @test
     * @covers MetaDataHelper::decrement
     */
    public function test_decrement_subtracts_custom_amount() {
        $this->entity->meta_data = json_encode(['counter' => 10]);
        MetaDataHelper::decrement($this->entity, 'counter', 3);
        $result = MetaDataHelper::get($this->entity, 'counter');
        $this->assertEquals(7, $result);
    }

    // ============================================================================
    // Integration & Chaining Tests
    // ============================================================================

    /**
     * @test
     */
    public function test_chaining_multiple_operations() {
        MetaDataHelper::set($this->entity, 'key1', 'value1');
        MetaDataHelper::set($this->entity, 'key2', 'value2');

        // Since set returns the entity, this validates consecutive operations on the same object
        $this->assertEquals('value1', MetaDataHelper::get($this->entity, 'key1'));
        $this->assertEquals('value2', MetaDataHelper::get($this->entity, 'key2'));
    }

    /**
     * @test
     */
    public function test_merge_then_delete_workflow() {
        MetaDataHelper::merge($this->entity, ['k1' => 'v1', 'k2' => 'v2', 'k3' => 'v3']);
        $this->assertEquals(3, MetaDataHelper::count($this->entity));
        
        MetaDataHelper::delete($this->entity, 'k2');
        $this->assertEquals(2, MetaDataHelper::count($this->entity));
        $this->assertNull(MetaDataHelper::get($this->entity, 'k2'));
    }

    /**
     * @test
     */
    public function test_complex_data_structure() {
        $complex = [
            'event_id' => 1,
            'sponsor' => [
                'name' => 'Acme Corp',
                'logo_url' => 'https://example.com/logo.png',
                'contact' => [
                    'email' => 'sponsor@acme.com',
                    'phone' => '555-1234'
                ]
            ],
            'tags' => ['survival', 'outdoor', 'teams'],
            'flags' => ['sponsored' => true, 'premium' => false],
            'metadata' => null
        ];
        
        MetaDataHelper::set($this->entity, 'sponsor_info', $complex);
        $retrieved = MetaDataHelper::get($this->entity, 'sponsor_info');
        $this->assertEquals($complex, $retrieved);
    }
}
