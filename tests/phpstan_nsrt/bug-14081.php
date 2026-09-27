<?php

declare(strict_types = 1);

namespace Bug14081;

use function PHPStan\Testing\assertType;

/** @param list<string> $array */
function first(array $array): mixed
{
	if (($key = array_key_first($array))) {
		assertType('string', $array[$key]);
		return $array[$key];
	}
	return null;
}

/** @param list<string> $array */
function last(array $array): mixed
{
	if (($key = array_key_last($array))) {
		assertType('string', $array[$key]);
		return $array[$key];
	}
	return null;
}

function maybeNonEmpty(): void
{
	if (rand(0,1)) {
		$array = ['one', 'two'];
	} else {
		$array = [];
	}
	assertType("array{}|array{'one', 'two'}", $array);
	$key = array_key_last($array);
	assertType('0|1|null', $key); // SKIP: array_key_last() of an array literal widens its literal keys
	// PHPantom is more precise than PHPStan here: when the array is the empty one, the key is null and the read finds nothing, which is null at runtime.
	assertType("'one'|'two'|null", $array[$key]);
}

/** @param list<string> $array */
function firstNotNull(array $array): mixed
{
	if (($key = array_key_first($array)) !== null) {
		assertType('int<0, max>', $key);
		assertType('string', $array[$key]);
		return $array[$key];
	}
	return null;
}

/** @param list<string> $array */
function lastNotNull(array $array): mixed
{
	if (($key = array_key_last($array)) !== null) {
		assertType('int<0, max>', $key);
		assertType('string', $array[$key]);
		return $array[$key];
	}
	return null;
}

/** @param list<string> $array */
function noIf(array $array): void
{
	$key = array_key_first($array);
	assertType('int<0, max>|null', $key);
	assertType('list<string>', $array);
	assertType('string', $array[$key]);

	if ($array === []) {
		return;
	}
	$key = array_key_first($array);
	assertType('int<0, max>', $key);
	assertType('non-empty-list<string>', $array);
	assertType('string', $array[$key]);
}
