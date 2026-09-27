# PHPantom — Bug Fixes

Every bug below must be fixed at its root cause. "Detect the
symptom and suppress the diagnostic" is not an acceptable fix.
If the type resolution pipeline produces wrong data, fix the
pipeline so it produces correct data. Downstream consumers
(diagnostics, hover, completion, definition) should never need
to second-guess upstream output.

Each entry below carries an **Impact · Complexity** rating using the same
scale defined in [`docs/todo.md`](../todo.md); that table is also where
each bug's row lives in the current sprint/backlog.

Bugs land here from wherever they surface: found while working on another
task, or sweeps of the sample projects under `projects/`. Entries are
grouped by the mechanism that has to change, not by the symptom that
surfaced: one entry is one root cause, however many shapes it shows up in.

## Crashes

No outstanding items.

## Type comparison

No outstanding items.

## Standard-library return types

### B482. Key functions widen the literal keys of an array literal
**Impact: Low · Complexity: Low-Medium**

```php
$a = [2 => 1, 3 => 2, 4 => 1];
array_keys($a);     // should be array{2, 3, 4}, is list<int>
array_keys($a, 1);  // should be list<2|3|4>, is list<int>
array_key_last(rand(0, 1) ? [] : ['one', 'two']); // should be 0|1|null, is int|null
```

The key template is bound from the shape's widened key type. A declared `array<1|2|3, V>` keeps its literal keys, so only the array literal's own keys are lost.

Found porting PHPStan's `nsrt/bug-11928.php` and `nsrt/bug-14081.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_nsrt/`.

### B483. `preg_replace()` over an array shape loses its keys
**Impact: Low · Complexity: Medium**

```php
/** @param array{a: string, b: string} $arr */
function f(array $arr) {
    preg_replace('/^a/', 'x', $arr); // should be array{a?: string, b?: string}, is array<array-key, string>
}
```

Given an array subject, `preg_replace()` returns the subject's keys (each optional, since an entry whose replacement fails is dropped) with string values.

Found porting PHPStan's `nsrt/bug-11547.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B484. `array_merge()` of array literals does not produce the merged shape
**Impact: Low-Medium · Complexity: Medium**

```php
array_merge(['foo' => 1, 'bar' => 2], [2, 3]); // should be array{foo: 1, bar: 2, 0: 2, 1: 3}, is array
```

Merging known shapes has a known result: string keys from later arrays overwrite earlier ones and integer keys are renumbered. Array key completion on the result is where this shows.

Found porting PHPStan's `nsrt/array-merge2.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B485. `array_shift()` does not drop the first entry of a list shape
**Impact: Low · Complexity: Medium**

```php
/** @param list<int> $items */
function f(array $items) {
    if (count($items) === 3) {
        array_shift($items);
        $items; // should be array{int, int}, is array{int, int, int}
    }
}
```

The by-reference write leaves the argument as it was. A list shape loses its first entry and the rest move down one key.

Found porting PHPStan's `nsrt/list-count.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B486. `strlen()` of a known literal string is not folded
**Impact: Low · Complexity: Low**

```php
$this->foo = '';
strlen($this->foo); // should be 0, is int<0, max>
```

A literal argument has a known length, the same way the other scalar folds read one.

Found porting PHPStan's `nsrt/bug-5129.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B506. A pointer function passed an empty array widens it to `array|object`
**Impact: Low · Complexity: Low-Medium**

```php
$empty = [];
reset($empty); // false (correct)
$empty;        // should be array{}, is object|array
end($empty);   // should be false, is mixed|false
```

`reset()`, `end()`, `next()` and `prev()` take their array by reference only to move its internal pointer, so the value is the same after the call. The by-reference seeding in `seed_pass_by_ref_primitives` drops an empty shape on purpose (a `preg_match_all()` out-parameter really is overwritten) and falls back to the stub's `array|object`. A non-empty shape survives only because it is a subtype of the hint. The pointer functions need to be marked as leaving their argument's value alone.

Found porting PHPStan's `nsrt/array-pointer-functions.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

## Reachability

### B493. A `catch` the try body cannot reach is still merged into `finally`
**Impact: Low · Complexity: Medium-High**

```php
try {
    $s = 1;
    $this->throwsLogicException(); // @throws LogicException
} catch (\RuntimeException $e) {
    $s = 'bar';
} finally {
    $s; // should be 1, is 1|'bar'
}
```

Telling that a catch is dead needs the exceptions the try body can throw, read from the `@throws` of what it calls.

Found porting PHPStan's `nsrt/finally-scope.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

## Narrowing

### B487. An impure call does not forget what was proved about a static property
**Impact: Low · Complexity: Medium**

```php
private static int|float $i;
public function __construct() {
    self::$i = getInt();
    $this->impureCall(); // @phpstan-impure
    self::$i; // should be float|int, is int
}
```

`process_receiver_mutation` invalidates what is known through the receiver (`$this->…`), but any impure call can write a static property, so `self::$x`, `static::$x`, `parent::$x` and `Foo::$x` keys have to go too.

Found porting PHPStan's `nsrt/bug-12902.php` and `nsrt/bug-12902-non-strict.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_nsrt/`.

### B488. A call proved through `?->` stays narrowed under its `->` spelling when the method is impure
**Impact: Low · Complexity: Low-Medium**

```php
if ($bar?->getImpure() !== null) { // @phpstan-impure, returns ?int
    $bar->getImpure();  // should be int|null, is int
    $bar?->getImpure(); // int|null (correct)
}
```

The nullsafe spelling of the call is forgotten as an impure result, but the `->` spelling the condition also proves is kept.

Found porting PHPStan's `nsrt/bug-4757.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B489. A comparison does not narrow a union of float literals
**Impact: Low · Complexity: Medium**

```php
$x = 0.0;
if ($y > 0) { $x += 1; }
if ($x > 0) {
    $x; // should be 1.0, is 0.0|1.0
}
```

Integer literal unions are narrowed by `<`, `>`, `<=` and `>=`; float literals are left as they were.

Found porting PHPStan's `nsrt/bug-5309.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B490. `count()` does not size a shape with explicit keys and optional entries
**Impact: Low · Complexity: Medium**

```php
/** @param array{0: mixed, 1?: string|null} $row */
function f(array $row) {
    if (count($row) === 1) {
        $row; // should be array{mixed}, is array{0: mixed, 1?: string|null}
    } else {
        $row; // should be array{mixed, string|null}
    }
}
```

`list_of_size` gives up on a shape whose entries spell out their keys, even when the keys are the sequential ones a list has. The branch where the sizes differ learns nothing either, although it rules out the size the check named.

Found porting PHPStan's `nsrt/list-count.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B491. The receiver of a nullsafe call is not narrowed inside its arguments
**Impact: Low · Complexity: Low-Medium**

```php
function f(?\Exception $e) {
    $e?->getMessage(g($e)); // $e should be Exception inside the arguments, is ?Exception
}
```

The arguments are only evaluated when the receiver is not null.

Found porting PHPStan's `nsrt/nullsafe.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B492. What the constructor proves about a readonly property of a readonly property is not remembered
**Impact: Low · Complexity: Medium**

```php
public readonly ?Foo $prop; // Foo has `public readonly int $readonly`
public function __construct() {
    $this->prop = new Foo();
    if ($this->prop->readonly != 5) { throw new LogicException(); }
}
public function doFoo() {
    $this->prop->readonly; // should be 5, is int
}
```

`seed_constructor_readonly_properties` carries the class's own readonly properties into the other methods. A path through two readonly properties cannot change either, so it can be carried too.

Found porting PHPStan's `nsrt/remember-readonly-constructor-narrowed.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B507. An assertion on a value typed as a union of classes and `null` leaves the `null`
**Impact: Low-Medium · Complexity: Low-Medium**

```php
class A { /** @return A|B|null */ public function abn() {} }
/** @psalm-assert A $v */
function assertA($v): void {}

$x = $a->abn();
assertA($x);
$x; // should be A, is A|null
```

The value resolves to one entry per class plus a separate `null` entry, and the assertion narrows the class entries without dropping the `null` one. A single class (`?A`) carries the `null` inside its own entry and narrows correctly. PHPUnit's `assertInstanceOf()` goes through the same path.

## Arithmetic

No outstanding items.

## Symbol resolution

No outstanding items.

## Array types

No outstanding items.

## Laravel

No outstanding items.

## Blade

No outstanding items.

## Templates

### B500. A key template bound from an array that names only its value type binds the whole array
**Impact: Low · Complexity: Medium**

```php
/** @template T of array-key @param array<T, mixed> $one @return T */
function uksort(array $one) {}
/** @var Foo[] $items */
uksort($items); // should be int|string, is array<Foo>
```

`Foo[]` has the implicit key type `array-key`, which is what `T` binds to.

Found porting PHPStan's `nsrt/uksort-bug.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B501. An assertion on `$this` that names a generic class loses the template arguments
**Impact: Low · Complexity: Medium**

```php
/** @template TOk @template TErr */
interface Result {
    /** @phpstan-assert-if-true Ok<TOk> $this */
    public function isOk(): bool;
}
/** @var Result<int, string> $result */
if ($result->isOk()) {
    $result; // should be Ok<int>, is Ok
}
```

The narrowing picks the right class and its members resolve (`$result->unwrap()` is `int`), but the asserted type's own template arguments are not substituted with the receiver's.

Found porting PHPStan's `nsrt/assert-this.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B502. A global constant as a conditional type's target is not resolved to its value
**Impact: Low · Complexity: Low-Medium**

```php
/** @return ($flag is PREG_SPLIT_NO_EMPTY ? true : false) */
function f(int $flag): bool {}
f(PREG_SPLIT_NO_EMPTY); // should be true, is false
```

The target names a constant, which has to be read as its value (`1`) before the argument can be compared with it.

Found porting PHPStan's `nsrt/conditional-types-constant.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B503. A conditional on a name that is not a parameter picks the else branch
**Impact: Low · Complexity: Low**

```php
/** @return ($parameter is true ? int : string) */
function f() {}
f(); // should be int|string, is string
```

Nothing is known about a name the signature does not declare, so both branches remain.

Found porting PHPStan's `nsrt/conditional-types.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B504. A conditional type whose subject is a literal is not evaluated
**Impact: Low · Complexity: Low-Medium**

```php
/** @return (5 is int ? true : false) */
function f() {}
f(); // should be true, is false

/** @param (true is true ? string : bool) $foo */
function g($foo) {
    $foo; // should be string, is shown as the raw conditional
}
```

A literal subject decides the condition without any argument.

Found porting PHPStan's `nsrt/conditional-types.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B505. A template bound through another template's bound from an empty array does not bind `never`
**Impact: Low · Complexity: Medium**

```php
/**
 * @template TKey of array-key
 * @template TArray of array<TKey, mixed>
 * @param TArray $array
 * @return (TArray is non-empty-array ? non-empty-list<TKey> : list<TKey>)
 */
function arrayKeys(array $array) {}
arrayKeys([]); // should be list<never>, is list<array-key>
```

`TArray` binds to `array{}`, but `TKey` is only reached through `TArray`'s bound, and an empty array leaves it on its own bound instead of `never`. A template bound directly from an empty array (`@param iterable<T>` handed `[]`) already binds `never`.

Found porting PHPStan's `nsrt/conditional-types.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

## Miscellaneous

No outstanding items.
