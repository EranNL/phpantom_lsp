<?php

namespace Bug4587;

use function PHPStan\Testing\assertType;

class HelloWorld
{
	public function a(): void
	{
		/** @var list<array{a: int}> $results */
		$results = [];

		$type = array_map(static function (array $result): array {
			assertType('array{a: int}', $result);
			return $result;
		}, $results);

		assertType('list<array{a: int}>', $type);
	}

	public function b(): void
	{
		/** @var list<array{a: int}> $results */
		$results = [];

		$type = array_map(static function (array $result): array {
			assertType('array{a: int}', $result);
			$result['a'] = (string) $result['a'];
			// PHPantom treats PHPStan's string refinements (lowercase-string, numeric-string, ...) as string.
			assertType('array{a: string}', $result);

			return $result;
		}, $results);

		assertType('list<array{a: lowercase-string&numeric-string&uppercase-string}>', $type); // SKIP: array_map() with a closure that rewrites its parameter keeps the parameter's original shape
	}
}
