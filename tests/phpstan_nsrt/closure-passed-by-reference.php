<?php

namespace ClosurePassedByReference;

use function PHPStan\Testing\assertType;

function () {

	$progressStarted = false;
	$anotherVariable = false;
	$incrementedInside = 1;
	$fooOrNull = null;
	assertType('false', $progressStarted);
	assertType('false', $anotherVariable);
	assertType('1', $incrementedInside);
	assertType('null', $fooOrNull);
	$callback = function () use (&$progressStarted, $anotherVariable, &$untouchedPassedByRef, &$incrementedInside, &$fooOrNull): void {
		assertType('1|bool', $progressStarted);
		assertType('false', $anotherVariable);
		assertType('null', $untouchedPassedByRef); // SKIP: a variable first introduced by a by-reference closure use is not typed null
		assertType('ClosurePassedByReference\Foo|null', $fooOrNull);
		if (doFoo()) {
			$progressStarted = 1;
			return;
		}
		if (!$progressStarted) {
			$progressStarted = true;
		}
		if (!$anotherVariable) {
			$anotherVariable = true;
		}
		if ($fooOrNull === null) {
			$fooOrNull = new Foo();
		}

		$incrementedInside++;

		assertType('1|true', $progressStarted);

		assertType('true', $anotherVariable);


		assertType('ClosurePassedByReference\Foo', $fooOrNull);
	};

	assertType('1|bool', $progressStarted);

	assertType('false', $anotherVariable);

	assertType('null', $untouchedPassedByRef); // SKIP: a variable first introduced by a by-reference closure use is not typed null


	assertType('ClosurePassedByReference\Foo|null', $fooOrNull);
};
