<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2009, Union of Rad, Inc. (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\util;

use \stdClass;
use \lithium\util\Collection;

class DispatchTest {

	public $marker = false;

	public $data = 'foo';

	public function mark() {
		$this->marker = true;
		return true;
	}

	function mapArray() {
		return array('foo');
	}
}

class CoreDispatchTest extends \lithium\core\Object {

	public $data = array(1 => 2);

	public function invokeMethod($method, $params = array()) {
		return $method;
	}

	public function to($format, $options = array()) {
		switch ($format) {
			case 'array':
				return $this->data + array(2 => 3);
		}
	}
}

class CollectionTest extends \lithium\test\Unit {

	public function testArrayLike() {
		$collection = new Collection();
		$collection[] = 'foo';
		$this->assertEqual($collection[0], 'foo');
		$this->assertEqual(count($collection), 1);

		$collection = new Collection(array('items' => array('foo')));
		$this->assertEqual($collection[0], 'foo');
		$this->assertEqual(count($collection), 1);
	}

	public function testObjectMethodDispatch() {
		$collection = new Collection();

		for ($i = 0; $i < 10; $i++) {
			$collection[] = new DispatchTest();
		}
		$result = $collection->mark();
		$this->assertEqual($result, array_fill(0, 10, true));

		$result = $collection->mapArray();
		$this->assertEqual($result, array_fill(0, 10, array('foo')));

		$result = $collection->invoke('mapArray', array(), array('merge' => true));
		$this->assertEqual($result, array_fill(0, 10, 'foo'));

		$collection = new Collection(array('items' => array_fill(0, 10, new CoreDispatchTest())));
		$result = $collection->testFoo();
		$this->assertEqual($result, array_fill(0, 10, 'testFoo'));

		$result = $collection->invoke('testFoo', array(), array('collect' => true));
		$this->assertTrue($result instanceof Collection);
		$this->assertEqual($result->to('array'), array_fill(0, 10, 'testFoo'));
	}

	public function testObjectCasting() {
		$collection = new Collection(array('items' => array_fill(0, 10, new CoreDispatchTest())));
		$result = $collection->to('array');
		$expected = array_fill(0, 10, array(1 => 2, 2 => 3));
		$this->assertEqual($expected, $result);

		$collection = new Collection(array('items' => array_fill(0, 10, new DispatchTest())));
		$result = $collection->to('array');
		$expected = array_fill(0, 10, array('marker' => false, 'data' => 'foo'));
		$this->assertEqual($expected, $result);
	}

	/**
	 * Tests that the `find()` method properly filters items out of the resulting collection.
	 *
	 * @return void
	 */
	public function testCollectionFindFilter() {
		$collection = new Collection(array('items' => array_merge(
			array_fill(0, 10, 1),
			array_fill(0, 10, 2)
		)));
		$this->assertEqual(20, count($collection->to('array')));

		$filter = function($item) { return $item == 1; };
		$result = $collection->find($filter);
		$this->assertTrue($result instanceof Collection);
		$this->assertEqual(array_fill(0, 10, 1), $result->to('array'));

		$result = $collection->find($filter, array('collect' => false));
		$this->assertEqual(array_fill(0, 10, 1), $result);
	}

	/**
	 * Tests that the `first()` method properly returns the first non-empty value.
	 *
	 * @return void
	 */
	public function testCollectionFirstFilter() {
		$collection = new Collection(array('items' => array(0, 1, 2)));
		$result = $collection->first(function($value) { return $value; });
		$this->assertEqual(1, $result);

		$collection = new Collection(array('items' => array('Hello', '', 'Goodbye')));
		$result = $collection->first(function($value) { return $value; });
		$this->assertEqual('Hello', $result);

		$collection = new Collection(array('items' => array('', 'Hello', 'Goodbye')));
		$result = $collection->first(function($value) { return $value; });
		$this->assertEqual('Hello', $result);

		$collection = new Collection(array('items' => array('', 'Hello', 'Goodbye')));
		$result = $collection->first();
		$this->assertEqual('', $result);
	}

	/**
	 * Tests that the `each()` filter applies the callback to each item in the current collection,
	 * returning an instance of itself.
	 *
	 * @return void
	 */
	public function testCollectionEachFilter() {
		$collection = new Collection(array('items' => array(1, 2, 3, 4, 5)));
		$filter = function($item) { return ++$item; };
		$result = $collection->each($filter);

		$this->assertIdentical($collection, $result);
		$this->assertEqual(array(2, 3, 4, 5, 6), $collection->to('array'));
	}

	public function testCollectionMapFilter() {
		$collection = new Collection(array('items' => array(1, 2, 3, 4, 5)));
		$filter = function($item) { return ++$item; };
		$result = $collection->map($filter);

		$this->assertNotEqual($collection, $result);
		$this->assertEqual(array(1, 2, 3, 4, 5), $collection->to('array'));
		$this->assertEqual(array(2, 3, 4, 5, 6), $result->to('array'));

		$result = $collection->map($filter, array('collect' => false));
		$this->assertEqual(array(2, 3, 4, 5, 6), $result);
	}

	/**
	 * Tests the `ArrayAccess` interface implementation for manipulating values by direct offsets.
	 *
	 * @return void
	 */
	public function testArrayAccessOffsetMethods() {
		$collection = new Collection(array('items' => array('foo', 'bar', 'baz' => 'dib')));
		$this->assertTrue($collection->offsetExists(0));
		$this->assertTrue($collection->offsetExists(1));
		$this->assertTrue($collection->offsetExists('0'));
		$this->assertTrue($collection->offsetExists('baz'));

		$this->assertFalse($collection->offsetExists('2'));
		$this->assertFalse($collection->offsetExists('bar'));
		$this->assertFalse($collection->offsetExists(2));

		$this->assertEqual('foo', $collection->offsetSet('bar', 'foo'));
		$this->assertTrue($collection->offsetExists('bar'));

		$this->assertNull($collection->offsetUnset('bar'));
		$this->assertFalse($collection->offsetExists('bar'));
	}

	/**
	 * Tests the `ArrayAccess` interface implementation for traversing values.
	 *
	 * @return void
	 */
	public function testArrayAccessTraversalMethods() {
		$collection = new Collection(array('items' => array('foo', 'bar', 'baz' => 'dib')));
		$this->assertEqual('foo', $collection->current());
		$this->assertEqual('bar', $collection->next());
		$this->assertEqual('foo', $collection->prev());
		$this->assertEqual('bar', $collection->next());
		$this->assertEqual('dib', $collection->next());
		$this->assertEqual('baz', $collection->key());
		$this->assertTrue($collection->valid());
		$this->assertFalse($collection->next());
		$this->assertFalse($collection->valid());
		$this->assertEqual('foo', $collection->rewind());
		$this->assertTrue($collection->valid());
		$this->assertEqual('dib', $collection->prev());
		$this->assertTrue($collection->valid());
		$this->assertEqual('bar', $collection->prev());
		$this->assertTrue($collection->valid());
		$this->assertEqual('dib', $collection->end());
		$this->assertTrue($collection->valid());
	}

	/**
	 * Tests objects and scalar values being appended to the collection.
	 *
	 * @return void
	 */
	public function testValueAppend() {
		$collection = new Collection();
		$this->assertFalse($collection->valid());
		$this->assertEqual(0, count($collection));

		$collection->append(1);
		$this->assertEqual(1, count($collection));
		$collection->append(new stdClass());
		$this->assertEqual(2, count($collection));

		$this->assertEqual(1, $collection->current());
		$this->assertEqual(new stdClass(), $collection->next());
	}

	/**
	 * Tests getting the index of the internal array.
	 *
	 * @return void
	 */
	public function testInternalKeys() {
		$collection = new Collection(array('items' => array('foo', 'bar', 'baz' => 'dib')));
		$this->assertEqual(array(0, 1, 'baz'), $collection->keys());
	}

	public function testCollectionFormatConversion() {
		$items = array('hello', 'goodbye', 'foo' => array('bar', 'baz' => 'dib'));
		$collection = new Collection(compact('items'));

		$expected = json_encode($items);
		$result = $collection->to('json');
		$this->assertEqual($result, $expected);

		$this->assertNull($collection->to('badness'));
	}
}

?>