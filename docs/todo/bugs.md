# PHPantom — Bug Fixes

Every bug below must be fixed at its root cause. "Detect the
symptom and suppress the diagnostic" is not an acceptable fix.
If the type resolution pipeline produces wrong data, fix the
pipeline so it produces correct data. Downstream consumers
(diagnostics, hover, completion, definition) should never need
to second-guess upstream output.

Each entry below carries an **Impact · Complexity** rating using the same
scale defined in [`docs/todo.md`](../todo.md), but a bug's row lives
**here only** — do not add or link a bug entry to `docs/todo.md`'s sprint
or backlog tables. This file is its own list, not a domain document
sprint items draw from: whenever it holds anything, that is actively
addressed, independently of sprint planning.

Bugs land here from wherever they surface: found while working on another
task, or sweeps of the sample projects under `projects/`. Entries are
grouped by the mechanism that has to change, not by the symptom that
surfaced: one entry is one root cause, however many shapes it shows up in.

## Crashes

No outstanding items.

## Type comparison

No outstanding items.

## Standard-library return types

No outstanding items.

## Reachability

No outstanding items.

## Narrowing

No outstanding items.

## Arithmetic

No outstanding items.

## Symbol resolution

### B540. A closure's declared parameter/return classes resolve against the wrong namespace when short names repeat across the file

**Impact: Medium · Complexity: Medium**

```php
namespace PsalmTest_closure_5 {
    class A {}
    class B {}
    class C {}
}

namespace PsalmTest_closure_6 {
    class A {}
    class B {}
    class C {}
    class C2 extends C {}

    /**
     * @param Closure(B):A $f
     * @param Closure(C):B $g
     * @return Closure(C2):A
     */
    function foo(Closure $f, Closure $g): Closure {
        return function (C $x) use ($f, $g): A {
            return $f($g($x));
        };
        // Return type Closure(PsalmTest_closure_5\C): PsalmTest_closure_5\A
        // is incompatible with declared return type
        // PsalmTest_closure_6\Closure(PsalmTest_closure_6\C2): PsalmTest_closure_6\A
        //
        // Should resolve C/A against PsalmTest_closure_6, the namespace the
        // closure literal is actually written in.
    }
}
```

The inner closure's native `C`/`A` hints get qualified against
`PsalmTest_closure_5`, an unrelated namespace earlier in the same file that
happens to declare classes with the same short names, instead of
`PsalmTest_closure_6`, the namespace the closure literal is lexically in.
The same file shows it corrupting `type_mismatch_argument` diagnostics too
(lines 107-108, 134-136 of the file below), always pulling from
`PsalmTest_closure_5` regardless of which later namespace the mismatched
call actually lives in.

Found while fixing B536, in `tests/psalm_assertions/closure.php`
(`returnsTypedClosureWithSubclassParam` and later namespaces in the same
file). Not yet isolated to a root cause.

## Array types

No outstanding items.

## Laravel

No outstanding items.

## Blade

No outstanding items.

## Templates

No outstanding items.

## Miscellaneous

### B530. A constant whose initializer uses another constant has no type

**Impact: Medium · Complexity: Medium**

```php
const ONE = 1;
const TWO = ONE * 2;           // no type, should be 2

class C {
    const ONE = 1;
    const THREE = 3;
    const ONE_THIRD = self::ONE / self::THREE;               // no type, should be float
    const SENTENCE = 'The value of THREE is ' . self::THREE; // no type
}

class Child extends Base {
    const A = [...parent::KEYS, 'c' => 'c'];                 // array, should be the shape
}
```

`self::ONE * 2` and `self::ONE >> 2` in a class constant fold, but
division, concatenation and a spread do not, and a global constant's
initializer that names another constant does not fold at all.

The SKIPs are in `tests/psalm_assertions/php56.php` and
`array_assignment.php`. Found porting Psalm's `Php56Test.php` and
`ArrayAssignmentTest.php`.

### B531. An out type read from the callee's body ignores the paths that return early

**Impact: Medium · Complexity: Medium**

```php
/** @param-out int $s */
function addFoo(?string &$s): void {
    if ($s === null) {
        $s = 5;
        return;
    }
    $s = 4;
}
addFoo($a);
// 4, should be 4|5
```

`read_out_type` (`type_engine/call_resolution/out_param.rs`) resolves the
parameter at the body's closing brace, which only the fall-through paths
reach. A `return` leaves the parameter holding whatever it held there, so
each `return` has to contribute to the join too. The reading narrows the
declared `@param-out int`, so the wrong literal comes out looking precise.

Found porting Psalm's `ReferenceConstraintTest.php`.

### B532. Unpacking an array into a by-reference variadic parameter replaces the array

**Impact: Low · Complexity: Low**

```php
function example(int &...$x): void {}
$z = [0];
example(...$z);
// int, should be array<int, int>
```

The write-back treats the spread argument as if it were the parameter
itself. Each element of the unpacked array is what the callee writes to,
so the array keeps its keys and its values take the parameter's type.

Found porting Psalm's `ArgTest.php` (`unpackByRefArg`).

### B533. A reference assigned inside `??`'s left operand takes the fallback's type

**Impact: Low · Complexity: Low**

```php
$var = 0;
($a =& $var) ?? 'hello';
// $a: string, should be 0
```

Found porting Psalm's `Php70Test.php` (`nullCoalesceWithReference`).

### B534. An inline `@var` above an array-element assignment is ignored

**Impact: Low · Complexity: Low-Medium**

```php
/** @var string */
$GLOBALS['sql_query'] = rand(0, 1) ? 'asd' : null;
// 'asd'|null, should be string
```

A nameless `@var` above an assignment types its right-hand side. It is
honoured when the target is a variable, not when it is an array element.

Found porting Psalm's `TypeReconciliation/EmptyTest.php` (`issue-9341-1`).

### B535. First-class callables built from a dynamic name or an invokable object have no return type

**Impact: Low-Medium · Complexity: Low-Medium**

```php
$name = 'length';
$f = $test->$name(...);    // calling $f gives mixed, should be int
$g = Test::$name(...);     // same
$h = $test(...);           // same, for an object with __invoke(): int
```

`$test->length(...)` resolves; a method name held in a variable with a
literal value, and an invokable object, do not.

Found porting Psalm's `ClosureTest.php`.
