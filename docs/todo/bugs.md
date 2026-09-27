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

### B518. `===` against a literal-typed variable does not narrow the other side

**Impact: Low-Medium · Complexity: Medium**

```php
/** @return positive-int */
function getPositiveInt(): int { return 2; }

$a = 2;
$b = getPositiveInt();
assert($a === $b);
$b; // positive-int, should be 2
```

An identity check narrows both operands to what they share. Against a
literal written inline (`$b === 2`) that already happens; against a
variable that holds the literal it does not.

Found porting Psalm's `TypeReconciliation/ConditionalTest.php`
(`assertionsWorksBothWays`).

## Arithmetic

No outstanding items.

## Symbol resolution

No outstanding items.

## Array types

### B519. Array spread builds a list instead of the shape it spreads, and drops string keys

**Impact: Medium · Complexity: Medium**

```php
$arrayA = [1, 2, 3];
$arrayB = [4, 5];
$result = [0, ...$arrayA, ...$arrayB, 6, 7];
// list<0|1|2|3|4|5|6|7>, should be array{0, 1, 2, 3, 4, 5, 6, 7}

$arr1 = [3 => 1, 1 => 2, 3];
$arr3 = [1 => 0, ...$arr1];
// array{1: 0}, should be array{1: 0, 2: 1, 3: 2, 4: 3}

$x = ['a' => 0, ...['a' => 1], ...['b' => 2]];
// array{a: 0}, should be array{a: 1, b: 2}

/** @var array<string, int> $s */
$y = [...$s];
// list<int>, should be array<string, int>
```

Since PHP 8.1 a spread keeps string keys (a later one overwrites an
earlier one) and renumbers integer keys onto the end, so spreading known
shapes gives a known shape and spreading a string-keyed array keeps its
keys. PHPantom types every spread as a list of the value types, which is
wrong rather than merely imprecise for string keys, and in the keyed
examples above it also drops the entries the spread adds. `[...[], ...[]]`
should be `array{}`.

The SKIPs are in `tests/psalm_assertions/array_assignment.php`, under
"array spread builds a list instead of the shape it spreads".
Found porting Psalm's `ArrayAssignmentTest.php`.

### B520. Writing a literal int key into an empty array builds a generic array

**Impact: Medium · Complexity: Low (a decision, then a small change)**

```php
$f = [];
$f[0] = 'hello';
// non-empty-array<int, 'hello'>, should be array{'hello'}

$a = [];
$a[0]['a'] = 5;
// non-empty-array<int, array{a: 5}>, should be array{array{a: 5}}
```

A string key (`$f['k'] = …`) builds a shape, and so does an int key
written into a shape that already has entries, but an int key written
into `[]` does not. That is deliberate: the comment in
`merge_nested_array_write_inner` (`type_engine/variable/array_shape_writes.rs`)
says a run of `$data[0] = …; $data[1] = …;` onto `[]` is rarely a promise
about how many entries there are. The array PHP builds is that exact
shape, though, and PHPStan and Psalm both report it as one. Keeping the
exception needs a reason stronger than intent (a performance or loop-growth
case the tests pin); otherwise the empty shape should take the same path
as a non-empty one.

The SKIPs are in `tests/psalm_assertions/array_assignment.php`, under
"writing a literal int key into an empty array".
Found porting Psalm's `ArrayAssignmentTest.php`.

### B521. A key held in a variable with a literal value does not build a shape

**Impact: Medium · Complexity: Medium**

```php
$string = 'c';
$b = ['z' => 1];
$b[$string] = 5;
// non-empty-array<string, 1|5>, should be array{z: 1, c: 5}
```

`$b['c'] = 5` adds the entry; `$b[$string] = 5` with `$string` known to
be `'c'` does not, because the write path only reads a key from a literal
written at the write site (`extract_array_key_for_shape`). A key
expression whose resolved type is a single literal should take the same
path. `$leading_zero_map[$leading_zero_key]` in
`collection_key_boundaries_normalize_literal_and_coercible_keys`
(`type_engine/variable/resolution_tests.rs`) pins the current behaviour.

The SKIPs are in `tests/psalm_assertions/array_assignment.php`, under
"a key held in a variable with a literal value".
Found porting Psalm's `ArrayAssignmentTest.php`.

### B522. A key taken from iterating a literal list does not build a shape

**Impact: Low · Complexity: Medium-High**

```php
$result = [];
foreach (['a', 'b'] as $k) {
    $result[$k] = true;
}
// non-empty-array<string, true>, should be array{a: true, b: true}
```

The loop body runs once per element of a list whose values are all known,
so every key is written. Short of that, the key type should at least stay
the literal union `'a'|'b'` rather than widening to `string`.

Found porting Psalm's `ArrayAssignmentTest.php` (`assignUnionOfLiterals`,
`implicitIndexedIntArrayCreation`).

### B523. Destructuring a nullable shape drops the null

**Impact: Low-Medium · Complexity: Low**

```php
/** @return array{'foo', 'bar'}|null */
function foobar(): ?array { return null; }

[$foo, $bar] = foobar();
// $foo: 'foo', should be 'foo'|null
```

Destructuring `null` assigns `null` to every target, so each target's type
should keep the null member.

Found porting Psalm's `ArrayAssignmentTest.php` (`nullableDestructuring`).

### B524. Destructuring an untyped call's result keeps each target's earlier value

**Impact: Low-Medium · Complexity: Low**

```php
$a = 'a';
function getMixed() {}
list($a, list($b, $c)) = getMixed();
// $a: 'a', should be mixed
```

A destructuring assignment writes every target whatever the right-hand
side is. When it resolves to nothing, the targets keep what they held
before instead of becoming `mixed`.

Found porting Psalm's `ListTest.php` (`mixedNestedAssignment`).

## Laravel

No outstanding items.

## Blade

No outstanding items.

## Templates

### B525. A constructor's default argument does not bind a class template

**Impact: Low-Medium · Complexity: Medium**

```php
/** @template T of object */
class E {
    /** @param class-string<T> $t */
    function __construct(string $t = D::class) { … }
}
$e = new E();
// E<object>, should be E<D>
```

An omitted argument takes the parameter's default, so the default binds
the template the same way a passed argument would.

Found porting Psalm's `Template/ClassTemplateTest.php`
(`templateDefaultClassConstant`).

### B526. `@var T` on a promoted constructor property does not bind the class template

**Impact: Medium · Complexity: Low-Medium**

```php
/** @template T */
class A {
    public function __construct(
        /** @var T */
        public mixed $t
    ) {}
}
$a = new A(5);
// A<mixed>, should be A<int>; $a->t is mixed, should be int
```

A promoted parameter's `@var` is its parameter type as well as its
property type, so it binds `T` from the argument just as `@param T $t`
does.

Found porting Psalm's `Template/ClassTemplateTest.php` (`promoted property
with template`).

### B527. A method returning `T|E` reads an offset from only one of the two bound shapes

**Impact: Low · Complexity: Medium**

```php
/** @template T */
class Option {
    /** @param T $v */
    public function __construct(private $v) {}
    /**
     * @template E
     * @param E $else
     * @return T|E
     */
    public function getOrElse($else) { … }
}
$b = (new Option([1, 3]))->getOrElse([2, 4])[0];
// 2, should be 1|2
```

Found porting Psalm's `Template/ClassTemplateTest.php`
(`combineTwoTemplatedArrays`).

### B528. A conditional return type nested in a generic argument is not resolved

**Impact: Low-Medium · Complexity: Medium**

```php
/**
 * @template TMappedValue
 * @param (Closure(TValue): TMappedValue)|true $callback
 * @return list<$callback is true ? array : TMappedValue>
 */
public function toArray1(Closure|true $callback): array { … }

$a = (new a)->toArray1(static fn ($obj) => $obj->key);
// list<array|inner>, should be list<inner>
```

A conditional written as the whole return type is decided against the
call's arguments; one written inside `list<…>` keeps both branches.

Found porting Psalm's `ClosureTest.php` (`templateShenanigans`).

### B529. A template bounded by a `Closure(…)` signature does not bind the closure's return type

**Impact: Low-Medium · Complexity: Medium**

```php
/**
 * @template TMappedValue
 * @template T as (Closure(TValue): TMappedValue)
 * @param T $callback
 * @return list<TMappedValue>
 */
public function toArray2(Closure $callback): array { … }

$b = (new a)->toArray2(static fn ($obj) => $obj->key);
// list<mixed>, should be list<inner>
```

Written as `@param (Closure(TValue): TMappedValue) $callback` the same
call binds `TMappedValue`; routed through another template's bound it
does not.

Found porting Psalm's `ClosureTest.php` (`templateShenanigans`).

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

### B536. Calling the closure a closure returns ignores the inner closure's return type

**Impact: Low · Complexity: Medium**

```php
$a = function (): Closure { return function (): string { return 'hello'; }; };
$b = $a()();
// mixed, should be string
```

The outer closure declares a bare `Closure`, so its call resolves to that.
Its body returns a closure whose signature is known, and a closure's real
return type is the narrower of what it declares and what its body produces
(the rule `array_map` callbacks already follow).

Found porting Psalm's `ClosureTest.php` (`singleLineClosures`).
