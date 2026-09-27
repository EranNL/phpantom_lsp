<?php

namespace Bug5129;

use function PHPStan\Testing\assertType;

class HelloWorld
{

	private $foo;

	public function sayHello(string $s): void
	{
		$this->foo = '';
		assertType('0', strlen($this->foo)); // SKIP: strlen() of a known literal string is not folded
		if (strlen($this->foo) > 0) {
			return;
		}

		assertType('0', strlen($this->foo)); // SKIP: strlen() of a known literal string is not folded

		$this->foo = 'x';
		assertType('1', strlen($this->foo)); // SKIP: strlen() of a known literal string is not folded
		if (strlen($this->foo) > 0) {
			return;
		}

		assertType('0', strlen($this->foo)); // SKIP: strlen() of a known literal string is not folded

		$this->foo = $s;
		assertType('int<0, max>', strlen($this->foo));
	}

	public function sayHello2(string $s): void
	{
		$this->foo = '';
		if (!$this->isFoo($this->foo)) {
			return;
		}

		assertType('true', $this->isFoo($this->foo));

		$this->foo = 'x';
		assertType('bool', $this->isFoo($this->foo));
		if (!$this->isFoo($this->foo)) {
			return;
		}
		assertType('true', $this->isFoo($this->foo));

		$this->foo = $s;
		assertType('bool', $this->isFoo($this->foo));
		if (!$this->isFoo($this->foo)) {
			return;
		}
		assertType('true', $this->isFoo($this->foo));
	}

	public function isFoo(string $s): bool
	{
		return strlen($s) % 3;
	}

}
