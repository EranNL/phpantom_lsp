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

No outstanding items.

## Miscellaneous

### B537. A spread of an array filled through a dynamic key makes the call `never`

**Impact: Medium · Complexity: Medium**

```php
$floats = ['a' => [], 'b' => []];
foreach (['a' => $x, 'b' => $y] as $key => $types) {
    foreach ($types as $type) {
        $floats[$key][] = $type;
    }
}
if (count($floats['a']) === 0) { … } elseif (count($floats['b']) === 0) { … }
$aTypes = TypeCombinator::union(...$floats['a']);
$aTypes->equals($b);
// Cannot access method 'equals' on type 'never'
```

Two things seem to go wrong here (not yet confirmed in isolation).
`$floats[$key][] = …` with a non-literal key probably leaves
`$floats['a']` as `array{}`. And spreading an empty array passes no
arguments, which is a perfectly reachable call. It should not count as an
argument that can never be reached, so it should not make the call `never`.

Found in phpstan-src's `MutatingScope.php` (the only diagnostic
`analyze` reports on it). It is already there at HEAD, before the
template fixes.

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
