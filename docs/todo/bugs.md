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

No outstanding items.

## Array types

### B537. An array of unknown length spread after known entries loses them

**Impact: Low-Medium · Complexity: Medium-High**

```php
/** @var list<User> $users */
$config = ['admin' => new AdminUser(), ...$users];
// array<int|string, AdminUser|User>, should be array{admin: AdminUser, ...<int, User>}
```

A spread whose source has no fixed set of keys (`list<T>`, `array<K, V>`)
turns the whole literal into `array<K, V>`, so the entries written beside
it are no longer known one by one: `$config['` offers no key completion,
and `$config['admin']` reads `AdminUser|User`. PHPStan and Psalm describe
this as an unsealed shape, the known entries plus a `...<K, V>` tail for
the rest. PHPantom has no such type, so this needs one in `php_type/`
(parsing, display, and the shape operations that would have to respect
the tail) before the array literal builder in
`type_engine/variable/raw_type_inference.rs` can produce it.

The SKIPs are in `tests/psalm_assertions/array_assignment.php`, under
"PHPantom has no unsealed shape type".
Found porting Psalm's `ArrayAssignmentTest.php`.

### B538. `Foo::class` is typed as `class-string<Foo>` rather than the name it evaluates to

**Impact: Low · Complexity: Medium**

```php
$result = [];
foreach ([a::class, b::class] as $k) {
    $result[$k] = true;
}
// non-empty-array<class-string<a>|class-string<b>, true>, should be array{a::class: true, b::class: true}
```

`a::class` is exactly the string `'…\a'`, but it is typed as
`class-string<a>`, which also admits every subclass's name. A write
through it cannot name the entry it lands on, so a loop over a list of
class constants builds `array<K, V>` where a list of string literals
builds a shape. Typing `Foo::class` as the literal name (while still
treating it as a `class-string<Foo>` wherever one is expected) would let
it take the same path, and shapes would need a way to carry a
class-constant key they can compare, which they currently only store as
its spelling.

The SKIP is in `tests/psalm_assertions/array_assignment.php`, under
"`a::class` is typed as `class-string<a>`".
Found porting Psalm's `ArrayAssignmentTest.php` (`assignUnionOfLiteralsClassKeys`).

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
